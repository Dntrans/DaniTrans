FROM php:8.2-apache

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y libpq-dev libpng-dev libzip-dev unzip curl \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local \
    && docker-php-ext-install pdo_pgsql pgsql gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html \
    && a2enmod rewrite

EXPOSE 80

CMD ["apache2-foreground"]
