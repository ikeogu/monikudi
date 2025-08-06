# Build assets
FROM node:20 as build-stage
WORKDIR /app
COPY package*.json vite.config.js ./
COPY resources resources
COPY public public
RUN npm install
RUN npm run build

# Laravel + Nginx/PHP
FROM richarvey/nginx-php-fpm:3.1.6
WORKDIR /var/www/html

# Copy Laravel code
COPY . .

# Copy Vite build
COPY --from=build-stage /app/public/build public/build

# Your existing configs
ENV SKIP_COMPOSER 1
ENV WEBROOT /var/www/html/public
ENV PHP_ERRORS_STDERR 1
ENV RUN_SCRIPTS 1
ENV REAL_IP_HEADER 1
ENV APP_ENV production
ENV APP_DEBUG false
ENV LOG_CHANNEL stderr
ENV COMPOSER_ALLOW_SUPERUSER 1

COPY scripts/00-laravel-deploy.sh /start.sh
RUN chmod +x /start.sh
CMD ["/start.sh"]
