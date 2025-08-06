FROM richarvey/nginx-php-fpm:3.1.6

# Set the working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set ENV vars
ENV SKIP_COMPOSER=0 \
    WEBROOT=/var/www/html/public \
    PHP_ERRORS_STDERR=1 \
    RUN_SCRIPTS=1 \
    REAL_IP_HEADER=1 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

# Install Laravel dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node & build Vue frontend
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && \
    apt-get install -y nodejs && \
    npm install && \
    npm run build

# Ensure correct permissions for Laravel
RUN chown -R www-data:www-data storage bootstrap/cache

# Generate Laravel key (optional: can also be done from env or deploy step)
# RUN php artisan key:generate

# Expose port 80 for web
EXPOSE 80

# Final CMD (already handled by image)
CMD ["/start.sh"]
