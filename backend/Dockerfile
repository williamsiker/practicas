FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git unzip curl zip libzip-dev libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . /var/www
RUN composer install --no-interaction --prefer-dist --optimize-autoloader
