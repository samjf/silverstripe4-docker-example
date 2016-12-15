# The image on which to base our Dockerfile
# https://hub.docker.com/_/php/
FROM php:7.0-apache

# A directive to run various linux commands.
# We stack up various requirements we need for our environment
RUN apt-get update && apt-get install -y libpng12-dev libjpeg-dev \
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
