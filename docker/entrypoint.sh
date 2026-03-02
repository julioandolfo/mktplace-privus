#!/bin/sh
set -e

cd /var/www/html

# Generate app key if not set
php artisan key:generate --no-interaction --force 2>/dev/null || true

# Cache configs for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force --no-interaction

# Fix storage permissions
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

exec "$@"
