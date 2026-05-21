FROM php:8.2-apache

# Install the mysqli extension
RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

# Ensure Apache rewrite module is enabled (optional but good practice)
RUN a2enmod rewrite
