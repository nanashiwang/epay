#!/bin/sh
set -eu
image=${EPAY_DOCKER_TEST_IMAGE:-epay-operations-test:local}
prefix="epay-health-test-$$"; app="$prefix-app"; db="$prefix-db"; network="$prefix-net"
cleanup(){
 for name in "$app" "$db"; do docker stop -t 1 "$name" >/dev/null 2>&1 || true; docker rm "$name" >/dev/null 2>&1 || true; done
 docker network rm "$network" >/dev/null 2>&1 || true
}
trap cleanup EXIT HUP INT TERM
docker network create "$network" >/dev/null
docker run -d --name "$db" --network "$network" -e MYSQL_ROOT_PASSWORD=synthetic-test-password -e MYSQL_DATABASE=epay_bepusdt_health_test mysql:8.0 >/dev/null
i=0
until docker exec "$db" mysqladmin ping -h127.0.0.1 -psynthetic-test-password --silent >/dev/null 2>&1; do
 i=$((i+1)); test "$i" -lt 40; sleep 2
done
docker run -d --name "$app" --network "$network" \
 -e DB_HOST="$db" -e DB_USER=root -e DB_PASS=synthetic-test-password -e DB_NAME=epay_bepusdt_health_test -e DB_PREFIX=bt \
 -e EPAY_TEST_HOST="$db" -e EPAY_TEST_DB=epay_bepusdt_health_test -e EPAY_TEST_PASSWORD=synthetic-test-password \
 -e EPAY_COLLECTION_KEY_FILE=/var/lib/epay-keys/collection.key --entrypoint sh "$image" \
 -c 'touch install/install.lock; exec /entrypoint.sh sh -c '\''su -s /bin/sh www-data -c "php tests/runtime-health-fixture.php" && exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf'\''' >/dev/null
i=0
until docker exec --user www-data "$app" sh -c 'test -f /tmp/epay-health-fixture-ready && php scripts/health-check.php --json' 2>/dev/null | grep -q '"ok":true,"exit_code":0'; do
 i=$((i+1)); if [ "$i" -ge 45 ]; then docker logs "$app";exit 1;fi;sleep 2
done
docker exec --user www-data "$app" php scripts/health-check.php
i=0
until docker exec "$app" curl --fail --silent --max-time 10 -o /dev/null http://127.0.0.1/user/login.php; do
 i=$((i+1)); test "$i" -lt 10; sleep 1
done
docker exec "$app" sh -c 'cp /run/epay-worker-instance /tmp/health-instance; printf "replacement-test-instance" > /run/epay-worker-instance'
if docker exec --user www-data "$app" php scripts/health-check.php --json >/dev/null; then echo 'Previous worker generation passed';exit 1;fi
docker exec "$app" sh -c 'mv /tmp/health-instance /run/epay-worker-instance'
docker exec "$app" sh -c 'mv install/install.lock /tmp/health-install.lock'
if docker exec --user www-data "$app" php scripts/health-check.php --json >/dev/null; then echo 'Missing install lock passed';exit 1;fi
docker exec "$app" sh -c 'mv /tmp/health-install.lock install/install.lock'
docker stop -t 1 "$db" >/dev/null
if docker exec --user www-data "$app" php scripts/health-check.php --json; then echo 'Unavailable database passed';exit 1;fi
echo 'Docker health: real PHP, MySQL, supervised workers, readable ciphertext and failure exit codes passed'
