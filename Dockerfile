FROM richarvey/nginx-php-fpm:3.1.6

WORKDIR /var/www/html

COPY . .
COPY --from=node-builder /app/public/build ./public/build

ENV SKIP_COMPOSER=0 \
    WEBROOT=/var/www/html/public \
    PHP_ERRORS_STDERR=1 \
    RUN_SCRIPTS=1 \
    REAL_IP_HEADER=1 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

RUN composer install --no-dev --optimize-autoloader \
 && chown -R www-data:www-data storage bootstrap/cache

 # Laravel setup
RUN php artisan config:clear && \
    php artisan migrate --seed && \
    php artisan storage:link

EXPOSE 80

CMD ["/start.sh"]
