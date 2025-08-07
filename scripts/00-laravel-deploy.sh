#!/usr/bin/env bash
set -e

echo "🔧 Running composer..."

cd /var/www/html

echo "Running composer"
composer install --no-dev --optimize-autoloader --no-dev

if [ ! -f "vendor/autoload.php" ]; then
    echo "Installing Composer dependencies..."
    composer install --optimize-autoloader --no-dev --prefer-dist --no-interaction || {
        echo "Composer install failed, trying with cache clear..."
        composer clear-cache
        composer install --optimize-autoloader --no-dev --prefer-dist --no-interaction
    }
fi

chown -R www-data:www-data storage bootstrap/cache

echo "🔐 Caching config..."
php artisan config:cache

echo "🚦 Caching routes..."
php artisan route:cache

echo "📦 Linking storage..."
php artisan storage:link || true

echo "🧬 Running migrations..."
php artisan migrate --force

echo "🌱 Seeding database..."
php artisan db:seed --force || true

echo "🚀 Starting Laravel server..."




