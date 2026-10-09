#!/bin/sh
set -e

APP_DIR="/var/www/epay"
CONFIG_FILE="${APP_DIR}/config.php"

# A fresh container start must complete its own worker rounds before acceptance.
php -r 'file_put_contents("/run/epay-worker-instance",bin2hex(random_bytes(12)));'
chmod 644 /run/epay-worker-instance

# Prepare only the directory. Existing encrypted installations never get a replacement key.
mkdir -p /var/lib/epay-keys
chown www-data:www-data /var/lib/epay-keys
chmod 700 /var/lib/epay-keys
if [ -f /var/lib/epay-keys/collection.key ]; then
    [ "$(wc -c < /var/lib/epay-keys/collection.key | tr -d ' ')" = 32 ] || { echo '收款主密钥长度错误' >&2; exit 1; }
    chown www-data:www-data /var/lib/epay-keys/collection.key
    chmod 600 /var/lib/epay-keys/collection.key
fi

# Generate config.php from environment variables if it doesn't have DB credentials
if [ -n "$DB_HOST" ] && [ -n "$DB_PASS" ]; then
    if ! grep -q "'user' => '${DB_USER}'" "$CONFIG_FILE" 2>/dev/null; then
        echo "[epay] Generating config.php from environment..."
        cat > "$CONFIG_FILE" <<PHPEOF
<?php
/*数据库配置*/
\$dbconfig=array(
    'host' => '${DB_HOST}',
    'port' => ${DB_PORT:-3306},
    'user' => '${DB_USER:-epay}',
    'pwd' => '${DB_PASS}',
    'dbname' => '${DB_NAME:-epay}',
    'dbqz' => '${DB_PREFIX:-pay}'
);
PHPEOF
        chown www-data:www-data "$CONFIG_FILE"
        echo "[epay] config.php generated."
    fi
fi

# Render a writable runtime config from the read-only template on every start.
if [ ! -f /etc/nginx/ssl/epay.pem ]; then
    echo "[epay] No SSL certificate found, disabling HTTPS server block..."
    awk '/^# BEGIN HTTPS/{exit} {print}' /etc/nginx/epay.conf.template > /etc/nginx/http.d/default.conf
else
    cp /etc/nginx/epay.conf.template /etc/nginx/http.d/default.conf
fi
nginx -t

# Wait for MySQL and run initial setup if needed
if [ ! -f "${APP_DIR}/install/install.lock" ] && [ -f "${APP_DIR}/install/install.sql" ]; then
    echo "[epay] Waiting for MySQL..."
    max_tries=30
    count=0
    while ! mysqladmin ping -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --silent 2>/dev/null; do
        count=$((count + 1))
        if [ $count -ge $max_tries ]; then
            echo "[epay] MySQL connection timeout, skipping auto-install."
            break
        fi
        sleep 2
    done

    if [ $count -lt $max_tries ]; then
        echo "[epay] Importing initial database..."
        # Replace table prefix in SQL
        sed "s/pre_/${DB_PREFIX:-pay}_/g" "${APP_DIR}/install/install.sql" | \
            mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>/dev/null && \
            touch "${APP_DIR}/install/install.lock" && \
            echo "[epay] Database initialized successfully." || \
            echo "[epay] Database import skipped (may already exist)."
    fi
fi

# Fix permissions
chown -R www-data:www-data "${APP_DIR}/install" 2>/dev/null || true

echo "[epay] Starting services..."
exec "$@"
