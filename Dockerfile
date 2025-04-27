# Build stage
FROM composer:2 as composer
FROM node:18 as node

FROM php:8.2-apache as builder
COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Set working directory
WORKDIR /app

# Copy entire application first to ensure all files are available
COPY . /app

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev --no-interaction

# Install JS dependencies and build assets
RUN npm ci && npm run build

# Production stage
FROM php:8.2-apache as production

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && a2enmod rewrite

# Configure Apache
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application from builder
COPY --from=builder --chown=www-data:www-data /app /var/www/html

# Create storage directory structure
RUN mkdir -p /var/www/html/storage/framework/{sessions,views,cache} \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage \
    && chown -R www-data:www-data /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/bootstrap/cache

# PHP configuration
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Set environment variables
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV ASSET_URL=""

# Expose port
EXPOSE 80

# Set up entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Run entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Start Apache server
CMD ["apache2-foreground"] 