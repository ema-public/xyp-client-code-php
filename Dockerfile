# Base image
FROM php:8.0-apache

# Set working directory
WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libzip-dev \
    libonig-dev \
    libxml2-dev

# Enable required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath opcache
RUN docker-php-ext-install soap

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Copy application files
COPY . .

# Copy custom Apache configuration
COPY /apache/laravel.conf /etc/apache2/sites-available/000-default.conf

# Set file permissions
RUN chown -R www-data:www-data /var/www/html

# Install Laravel dependencies
RUN composer install --optimize-autoloader --no-dev

# Set up Apache
RUN a2enmod rewrite

# Expose port
EXPOSE 80

# # Start Apache
# CMD ["apache2-foreground"]
