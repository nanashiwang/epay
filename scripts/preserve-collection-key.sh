#!/bin/sh
# Run before recreating an existing Docker app. Never print credential bytes.
set -eu
app_container=${EPAY_APP_CONTAINER:-epay-app}
if ! docker container inspect "$app_container" >/dev/null 2>&1; then exit 0; fi
if [ "$(docker inspect -f '{{.State.Running}}' "$app_container")" != true ]; then
    echo '请先启动旧应用容器，再保全收款主密钥。' >&2; exit 1
fi
key_volume=${EPAY_COLLECTION_KEY_VOLUME:-}
if [ -z "$key_volume" ]; then
    # Resolve .env through Compose, without evaluating shell text or printing credentials.
    key_volume=$(docker compose config --format json | docker exec -i "$app_container" php -r '$c=json_decode(stream_get_contents(STDIN),true); $v=$c["volumes"]["collection_keys"]["name"]??""; if (!preg_match("/^[a-zA-Z0-9][a-zA-Z0-9_.-]+$/D",$v)) exit(1); echo $v;') || { echo '无法确认主密钥卷名称，请检查 Compose 配置。' >&2; exit 1; }
fi
old_key=$(docker exec "$app_container" php -r 'echo getenv("EPAY_COLLECTION_KEY_FILE") ?: "/var/www/epay-collection.key";')
case "$old_key" in /*) ;; *) echo '旧密钥路径无效，停止更新。' >&2; exit 1;; esac
if ! docker exec "$app_container" test -f "$old_key"; then
    # A previously initialized installation must not silently generate a replacement.
    initialized=$(docker exec "$app_container" php -r 'chdir("/var/www/epay"); $nosession=true; require "includes/common.php"; echo empty($conf["collection_parent"])?"NEW":"INITIALIZED";')
    if [ "$initialized" = NEW ]; then exit 0; fi
    echo '已初始化的收款主密钥缺失或状态无法确认，停止更新；请先恢复原密钥。' >&2; exit 1
fi
staging=$(mktemp -d)
trap 'chmod -R u+rwX "$staging"; rm -r "$staging"' EXIT HUP INT TERM
docker cp "$app_container:$old_key" "$staging/collection.key" >/dev/null
chmod 600 "$staging/collection.key"
if [ "$(wc -c < "$staging/collection.key" | tr -d ' ')" != 32 ]; then echo '旧密钥长度错误，停止更新。' >&2; exit 1; fi
docker volume create "$key_volume" >/dev/null
app_image=$(docker inspect -f '{{.Config.Image}}' "$app_container")
docker run --rm --user 0 --entrypoint sh -v "$key_volume:/keys" -v "$staging/collection.key:/incoming/key:ro" "$app_image" -c '
set -eu
if [ -e /keys/collection.key ]; then
    cmp -s /incoming/key /keys/collection.key || { echo "持久卷与旧主密钥不一致，停止更新。" >&2; exit 1; }
else
    umask 077
    cp /incoming/key /keys/collection.key
fi
chown www-data:www-data /keys /keys/collection.key
chmod 700 /keys
chmod 600 /keys/collection.key
'
echo '收款主密钥已保全到持久卷；请另行离线备份该卷。'
