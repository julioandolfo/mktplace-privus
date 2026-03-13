FROM php:8.2-fpm-alpine

# Install install-php-extensions helper via GitHub (avoids Docker Hub rate limits)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/install-php-extensions
RUN chmod +x /usr/local/bin/install-php-extensions

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    git \
    curl \
    zip \
    unzip

# Install all required PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache \
    intl \
    xml \
    dom \
    xmlreader \
    xmlwriter \
    simplexml \
    fileinfo \
    redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer files first (for cache)
COPY composer.json composer.lock ./

# Install PHP dependencies (includes chillerlan/php-qrcode via composer.lock)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --ignore-platform-reqs

# Copy application files
COPY . .

# Install and build frontend assets
RUN npm ci && npm run build && rm -rf node_modules

# Garante que diretórios de runtime existam
RUN mkdir -p storage/logs storage/framework/sessions storage/framework/views \
        storage/framework/cache/data bootstrap/cache

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Copy Nginx config and fix temp directory permissions
COPY docker/nginx.conf /etc/nginx/nginx.conf
RUN mkdir -p /var/lib/nginx/tmp/client_body /var/lib/nginx/tmp/proxy /var/lib/nginx/tmp/fastcgi \
    && chown -R www-data:www-data /var/lib/nginx/tmp

# Copy Supervisor config
COPY docker/supervisord.conf /etc/supervisord.conf

# PHP opcache config
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# PHP upload & post limits
RUN echo "upload_max_filesize=10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=12M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time=60" >> /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 80

# Copy and set entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
