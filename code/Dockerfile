FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli

RUN a2enmod rewrite

RUN sed -i 's|<Directory /var/www/>|<Directory /var/www/html>|g' /etc/apache2/apache2.conf \
    && sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

EXPOSE 80