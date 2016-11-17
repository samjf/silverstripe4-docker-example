FROM php:7.0-apache
RUN apt-get update && apt-get install -y libpng12-dev libjpeg-dev \
    && docker-php-ext-install gd opcache mysqli

RUN a2enmod rewrite expires

COPY ./config/php.ini /usr/local/etc/php/
COPY ./ /var/www/html/
WORKDIR /var/www/html/
