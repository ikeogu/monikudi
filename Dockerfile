# Stage 1: Build Frontend Assets with Vite
FROM node:20 as node-builder
WORKDIR /app

# Copy package files first for better caching
COPY package*.json ./
COPY vite.config.js ./

# Install dependencies
RUN npm install

# Copy source files
COPY resources ./resources
COPY public ./public

# Build assets
RUN npm run build

# Stage 2: Laravel PHP Image
FROM richarvey/nginx-php-fpm:3.1.6
WORKDIR /var/www/html

# Copy Laravel app (excluding node_modules and build artifacts)
COPY --exclude=node_modules --exclude=public/build . .

# Copy Vite built assets from node-builder stage
COPY --from=node-builder /app/public/build ./public/build

# Laravel env configs
ENV SKIP_COMPOSER=1
ENV WEBROOT=/var/www/html/public
ENV PHP_ERRORS_STDERR=1
ENV RUN_SCRIPTS=1
ENV REAL_IP_HEADER=1
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV LOG_CHANNEL=stderr
ENV COMPOSER_ALLOW_SUPERUSER=1

# Run Laravel script
COPY scripts/00-laravel-deploy.sh /start.sh
RUN chmod +x /start.sh

CMD ["/start.sh"]
