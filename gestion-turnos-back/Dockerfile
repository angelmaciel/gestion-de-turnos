# PHP 8.4: requisito de pest-plugin-laravel v5, el único compatible con Laravel 13.
FROM php:8.4-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        zip \
        unzip \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        mariadb-client \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . /var/www/html

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# El proceso no debe correr como root: si alguien logra ejecutar código a través
# de la aplicación, queda limitado a los permisos de www-data.
# php-fpm normalmente arranca su master como root para poder hacer setuid; acá
# se arranca directamente como www-data, así que la directiva user del pool
# sobra y hay que quitarla para que no falle al iniciar.
RUN sed -i 's/^user = www-data/;user = www-data/; s/^group = www-data/;group = www-data/' \
        /usr/local/etc/php-fpm.d/www.conf \
    && chown -R www-data:www-data /var/www/html

USER www-data

EXPOSE 9000
CMD ["php-fpm"]
