# The image on which to base our Dockerfile
# https://hub.docker.com/_/php/
FROM php:7.0-apache

# A directive to run various linux commands.
# We stack up various requirements we need for our environment
RUN apt-get update && apt-get install -y libpng12-dev libjpeg-dev zip unzip \
    && docker-php-ext-install gd opcache mysqli

RUN a2enmod rewrite expires

# Copy files from outside our container to a path inside the container
# We copy in our own config files and our project
COPY ./config/php.ini /usr/local/etc/php/
COPY ./ /var/www/html/

# We change the working directory to our projects base
# The WORKDIR instruction sets the working directory for any
# RUN, CMD, ENTRYPOINT, COPY and ADD instructions that follow it in the Dockerfile.
# We just want commands like 'docker-compose run web bash' to drop us here
# ... though it is defined in the original image
WORKDIR /var/www/html/

# Do a usual php composer install and make it global
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php -r "if (hash_file('SHA384', 'composer-setup.php') === '55d6ead61b29c7bdee5cccfb50076874187bd9f21f65d8991d46ec5cc90518f447387fb9f76ebae1fbbacf329e583e30') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
    && php composer-setup.php \
    && php -r "unlink('composer-setup.php');" \
    && mv composer.phar /usr/local/bin/composer \
    && composer install