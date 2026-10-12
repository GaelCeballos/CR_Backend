FROM php:8.4-fpm-alpine

# Dependencias del sistema necesarias para compilar las extensiones de PHP
RUN apk add --no-cache \
        bash \
        git \
        unzip \
        libpq-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        $PHPIZE_DEPS

# Extensiones de PHP requeridas por la aplicacion (Postgres, Intl para catalogos, bcmath, zip, opcache)
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        intl \
        bcmath \
        zip \
        opcache

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-progress

COPY . .

RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
