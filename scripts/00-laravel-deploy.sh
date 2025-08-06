#!/usr/bin/env bash
set -e

echo "🔧 Running composer..."
composer install --no-dev --optimize-autoloader --working-dir=/var/www/html

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
php artisan serve --host=0.0.0.0 --port=8000
