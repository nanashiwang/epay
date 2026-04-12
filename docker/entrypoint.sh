#!/bin/sh
set -e

APP_DIR="/var/www/epay"
CONFIG_FILE="${APP_DIR}/config.php"

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

# Remove SSL server block if no certificate exists
if [ ! -f /etc/nginx/ssl/epay.pem ]; then
    echo "[epay] No SSL certificate found, disabling HTTPS server block..."
    # Create a minimal nginx config without SSL
    sed -i '/^server {$/,/^}$/{ /listen 443/,/^}$/d; }' /etc/nginx/http.d/default.conf 2>/dev/null || true
fi

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
