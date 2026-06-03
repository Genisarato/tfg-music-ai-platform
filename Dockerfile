# ==============================================================================
# Dockerfile - Laravel Application Container
# ==============================================================================
#
# Builds the PHP-FPM runtime for the Laravel application with:
#   - PHP 8.4 with required extensions (MySQL, GD, ZIP, etc.)
#   - Node.js 20.x for Vite frontend build tooling
#   - Composer for PHP dependency management
#
# This container serves the PHP-FPM process, which Nginx proxies to.
# The Vite dev server also runs inside this container on port 5173.
# ==============================================================================

FROM php:8.4-fpm

# Install system dependencies and PHP extensions required by Laravel
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Node.js 20.x for the Vite frontend build tooling
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install Composer from the official Composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# Ensure Laravel's storage and cache directories are writable
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache