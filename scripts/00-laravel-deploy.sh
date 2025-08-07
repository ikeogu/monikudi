#!/usr/bin/env bash
set -e

echo "🔧 Running composer..."

cd /var/www/html

echo "Running composer"
composer install --no-dev --optimize-autoloader

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

