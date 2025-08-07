# Stage 1: Build Frontend Assets
FROM node:20 as node-builder
WORKDIR /app
COPY package*.json vite.config.js ./
RUN npm install
COPY resources ./resources
COPY public ./public
RUN npm run build

# Stage 2: Laravel App
FROM richarvey/nginx-php-fpm:3.1.6
WORKDIR /var/www/html

# Copy app files
COPY . .
COPY --from=node-builder /app/public/build ./public/build

# Environment variables
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV APP_URL=https://monikudi.onrender.com
ENV ASSET_URL=https://monikudi.onrender.com

