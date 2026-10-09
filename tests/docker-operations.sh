#!/bin/sh
# Disposable containers and synthetic keys only. Never target an installed app.
set -eu
image=${EPAY_DOCKER_TEST_IMAGE:-epay-operations-test:local}
repository=$(pwd)
compose_fixture=$(mktemp -d)
prefix="epay-key-test-$$"
legacy="$prefix-old"; replacement="$prefix-new"; missing="$prefix-missing"; volume="$prefix-keys"
cleanup() {
    for name in "$legacy" "$replacement" "$missing"; do docker stop -t 1 "$name" >/dev/null 2>&1 || true; docker rm "$name" >/dev/null 2>&1 || true; done
    docker volume rm "$volume" >/dev/null 2>&1 || true
    rm -r "$compose_fixture"
}
trap cleanup EXIT HUP INT TERM
docker run -d --name "$legacy" --entrypoint sh "$image" -c 'php -r '\''file_put_contents("/var/www/epay-collection.key",random_bytes(32));'\''; sleep 600' >/dev/null
docker exec "$legacy" sh -c 'i=0; until test -f /var/www/epay-collection.key; do i=$((i+1)); test "$i" -lt 10; sleep 1; done'
docker exec "$legacy" php -r 'define("ROOT","/var/www/epay/"); require ROOT."includes/lib/GatewaySecrets.php"; file_put_contents("/tmp/cipher",lib\GatewaySecrets::encrypt(["token"=>"synthetic-test-token"],1000));'
EPAY_APP_CONTAINER="$legacy" EPAY_COLLECTION_KEY_VOLUME="$volume" sh scripts/preserve-collection-key.sh
cp docker-compose.yml "$compose_fixture/docker-compose.yml"
printf 'DB_ROOT_PASS=synthetic-test-password\nDB_PASS=synthetic-test-password\nEPAY_COLLECTION_KEY_VOLUME=%s\n' "$volume" > "$compose_fixture/.env"
(cd "$compose_fixture"; EPAY_APP_CONTAINER="$legacy" sh "$repository/scripts/preserve-collection-key.sh")
docker run -d --name "$replacement" -v "$volume:/var/lib/epay-keys" -e EPAY_COLLECTION_KEY_FILE=/var/lib/epay-keys/collection.key --entrypoint sh "$image" -c 'touch /var/www/epay/install/install.lock; exec /entrypoint.sh /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf' >/dev/null
docker exec "$legacy" cat /tmp/cipher | docker exec -i --user www-data "$replacement" php -r 'define("ROOT","/var/www/epay/"); require ROOT."includes/lib/GatewaySecrets.php"; if(lib\GatewaySecrets::decrypt(stream_get_contents(STDIN),1000)!==["token"=>"synthetic-test-token"])exit(1);'
docker exec "$replacement" sh -c '[ "$(stat -c %a /var/lib/epay-keys/collection.key)" = 600 ] && [ "$(stat -c %U /var/lib/epay-keys/collection.key)" = www-data ]'
docker exec "$replacement" sh -c 'i=0; until test "$(ps | grep -c "[e]pay-collection-runner")" -ge 2; do i=$((i+1)); test "$i" -lt 10; sleep 1; done'
docker exec "$replacement" nginx -t
# Enabling TLS later must restore the original server block on restart.
docker exec "$replacement" openssl req -x509 -newkey rsa:2048 -nodes -keyout /etc/nginx/ssl/epay.key -out /etc/nginx/ssl/epay.pem -days 1 -subj /CN=localhost >/dev/null 2>&1
docker restart "$replacement" >/dev/null
docker exec "$replacement" nginx -t
docker exec "$replacement" sh -c 'grep -q "listen 443 ssl" /etc/nginx/http.d/default.conf'
docker exec "$legacy" cat /tmp/cipher | docker exec -i --user www-data "$replacement" php -r 'define("ROOT","/var/www/epay/"); require ROOT."includes/lib/GatewaySecrets.php"; if(lib\GatewaySecrets::decrypt(stream_get_contents(STDIN),1000)!==["token"=>"synthetic-test-token"])exit(1);'
# A conflicting destination must remain untouched.
docker exec "$legacy" php -r 'file_put_contents("/var/www/epay-collection.key",random_bytes(32));'
if EPAY_APP_CONTAINER="$legacy" EPAY_COLLECTION_KEY_VOLUME="$volume" sh scripts/preserve-collection-key.sh; then echo 'Conflict unexpectedly accepted' >&2; exit 1; fi
docker exec "$legacy" cat /tmp/cipher | docker exec -i --user www-data "$replacement" php -r 'define("ROOT","/var/www/epay/"); require ROOT."includes/lib/GatewaySecrets.php"; if(lib\GatewaySecrets::decrypt(stream_get_contents(STDIN),1000)!==["token"=>"synthetic-test-token"])exit(1);'
# Model an initialized installation with a lost key. Setup must not replace it.
docker run -d --name "$missing" -e EPAY_COLLECTION_KEY_FILE=/var/www/missing.key --entrypoint sh "$image" -c 'sleep 600' >/dev/null
docker exec -i "$missing" sh -c 'cat > /var/www/epay/includes/common.php' <<'PHP'
<?php
define('ROOT','/var/www/epay/');define('SYSTEM_ROOT',ROOT.'includes/');require SYSTEM_ROOT.'autoloader.php';Autoloader::register();$conf=['collection_parent'=>1];
PHP
if EPAY_APP_CONTAINER="$missing" EPAY_COLLECTION_KEY_VOLUME="$volume" sh scripts/preserve-collection-key.sh; then echo 'Missing key unexpectedly accepted' >&2; exit 1; fi
if docker exec "$missing" php /var/www/epay/scripts/collection-setup.php --apply >/dev/null 2>&1; then echo 'Missing key replaced' >&2; exit 1; fi
docker exec "$missing" test ! -e /var/www/missing.key
echo 'Docker operations: persistence, decrypt, permissions, supervision, restart and failure checks passed'
