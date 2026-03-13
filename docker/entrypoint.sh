#!/bin/sh

cd /var/www/html

# Garante que os diretórios de storage existam antes de qualquer comando
mkdir -p storage/logs storage/framework/sessions storage/framework/views storage/framework/cache/data bootstrap/cache

# Create .env from environment variables if not exists
if [ ! -f .env ]; then
    cp .env.example .env 2>/dev/null || touch .env
fi

# Generate app key if APP_KEY not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --no-interaction --force
else
    php artisan key:generate --no-interaction --force --show > /dev/null 2>&1 || true
fi

# Cache configs (non-fatal)
php artisan config:cache 2>/dev/null || echo "[warn] config:cache failed, continuing..."
php artisan route:cache 2>/dev/null || echo "[warn] route:cache failed, continuing..."
php artisan view:cache 2>/dev/null || echo "[warn] view:cache failed, continuing..."

# Run migrations (non-fatal)
php artisan migrate --force --no-interaction 2>/dev/null || echo "[warn] migrate failed, continuing..."

# Run fix-pipeline to ensure pipeline_status and company_id are set
php artisan orders:fix-pipeline 2>/dev/null || echo "[warn] orders:fix-pipeline failed, continuing..."

# Run seeder only on first deploy (when no users exist)
USER_COUNT=$(php artisan tinker --execute='echo App\Models\User::count();' 2>/dev/null | tail -1 | tr -d '[:space:]')
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    echo "[info] Primeiro deploy detectado, executando seeder..."
    php artisan db:seed --force --no-interaction 2>/dev/null || echo "[warn] seeder falhou, continuando..."
fi

# Create storage symlink for public file access
php artisan storage:link --force 2>/dev/null || echo "[warn] storage:link failed, continuing..."

# Fix storage permissions DEPOIS de todos os comandos artisan (que rodam como root)
# Garante que www-data possa escrever em storage e bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

exec "$@"
