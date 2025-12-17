
FROM composer:2 AS vendor

WORKDIR /var/www/backend

COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

COPY backend/ .
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

WORKDIR /var/www/backend

COPY --from=vendor /var/www/backend /var/www/backend

RUN mkdir -p /opt/app/storage-skeleton /var/www/backend/storage /var/www/backend/bootstrap/cache && \
    rsync -a /var/www/backend/storage/ /opt/app/storage-skeleton/ && \
    chown -R www-data:www-data /var/www/backend/storage /var/www/backend/bootstrap/cache && \
    chmod -R ug+rwX /var/www/backend/storage /var/www/backend/bootstrap/cache

COPY backend/docker/entrypoint.sh /usr/local/bin/backend-entrypoint

RUN chmod +x /usr/local/bin/backend-entrypoint

ENTRYPOINT ["backend-entrypoint"]
CMD ["php-fpm"]
