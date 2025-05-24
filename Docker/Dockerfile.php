# Use the official PHP CLI image as a base
FROM php:8.2-cli

# Install system dependencies and PHP extensions for WordPress and CLI usage
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev libcurl4-openssl-dev pkg-config libssl-dev zlib1g-dev default-mysql-client \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configure Xdebug for profiling
COPY ../nginx/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
