FROM composer:2 AS vendor

WORKDIR /var/www

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.2-fpm

ENV PHP_MEMORY_LIMIT=512M \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libonig-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libfreetype6-dev \
    rsync \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip gd \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www

COPY --from=vendor /var/www /var/www

RUN mkdir -p /opt/app/storage-skeleton /var/www/storage /var/www/bootstrap/cache && \
    rsync -a /var/www/storage/ /opt/app/storage-skeleton/ && \
    chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache && \
    chmod -R ug+rwX /var/www/storage /var/www/bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/backend-entrypoint

RUN chmod +x /usr/local/bin/backend-entrypoint

ENTRYPOINT ["backend-entrypoint"]
CMD ["php-fpm"]
