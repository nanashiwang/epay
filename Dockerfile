FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    openssl

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        bcmath \
        mbstring \
        zip \
        opcache

# Create directories
RUN mkdir -p /var/www/epay \
    /etc/nginx/ssl \
    /run/nginx \
    /var/log/supervisor

# Copy configs
COPY docker/nginx.conf /etc/nginx/epay.conf.template
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/collection-runner.sh /usr/local/bin/epay-collection-runner
RUN chmod +x /entrypoint.sh
RUN chmod +x /usr/local/bin/epay-collection-runner

# Copy application code
COPY . /var/www/epay/

# Set permissions
RUN chown -R www-data:www-data /var/www/epay \
    && chmod -R 755 /var/www/epay

WORKDIR /var/www/epay

EXPOSE 80 443

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
