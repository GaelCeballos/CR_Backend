FROM php:8.4-apache

# Instalamos dependencias del sistema (usando apt-get porque la imagen de Apache usa Debian)
RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        libpng-dev \
        libfreetype6-dev \
        libjpeg-dev \
    && rm -rf /var/lib/apt/lists/*

# Configuramos e instalamos las extensiones requeridas de PHP
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql intl bcmath zip gd opcache

# Habilitamos el mod_rewrite de Apache (crucial para que funcionen las rutas de Laravel)
RUN a2enmod rewrite

# Apuntamos el servidor de Apache a la carpeta 'public' de Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Hacemos que Apache escuche en el puerto dinámico que Render inyecta ($PORT)
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf
RUN sed -i 's/:80/:${PORT}/g' /etc/apache2/sites-available/000-default.conf

# Copiamos la configuración de opcache
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Instalamos Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Instalamos dependencias de Laravel
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-progress

# Copiamos el resto del código
COPY . .

# Optimizamos dependencias y ajustamos permisos
RUN composer dump-autoload --optimize \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

