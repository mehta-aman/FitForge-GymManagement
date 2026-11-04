# FitForge Gym Management System — Production Docker Container
FROM php:8.4-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql gd zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure Apache
RUN a2enmod rewrite headers
ENV APACHE_DOCUMENT_ROOT=/var/www/html/gym
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Configure production PHP settings
RUN echo "upload_max_filesize = 32M\npost_max_size = 32M\nmemory_limit = 256M\ndisplay_errors = Off\nlog_errors = On" > /usr/local/etc/php/conf.d/fitforge-custom.ini

# Copy project source
WORKDIR /var/www/html
COPY . /var/www/html/

# Set safe permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
