# Stage 1: Build frontend assets
FROM node:20-alpine as node

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY . .
RUN npm run build

# Stage 2: PHP / Laravel
FROM php:8.2-fpm-alpine

# Install PHP extensions and system dependencies
RUN apk add --no-cache \
    bash \
    git \
    curl \
    libpng \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    php82-pdo \
    php82-pdo_mysql \
    php82-mbstring \
    php82-xml \
    php82-curl

RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip intl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app files
COPY . .

# Copy frontend build from previous stage
COPY --from=node /app/public/js public/js
COPY --from=node /app/public/css public/css
COPY --from=node /app/mix-manifest.json public/mix-manifest.json

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Laravel commands
RUN composer install --no-dev --optimize-autoloader
RUN php artisan config:cache && php artisan route:cache && php artisan view:cache

EXPOSE 8000

CMD php artisan serve --host=0.0.0.0 --port=8000
