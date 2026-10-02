FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        bash git curl libzip-dev zip unzip oniguruma-dev mysql-client \
        libpng-dev libjpeg-turbo-dev freetype-dev poppler-utils sqlite-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring zip bcmath gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

WORKDIR /var/www/html

COPY backend/ ./
RUN composer install --no-dev --no-interaction --prefer-dist && composer dump-autoload --no-dev --optimize

EXPOSE 9000
CMD ["php-fpm"]
