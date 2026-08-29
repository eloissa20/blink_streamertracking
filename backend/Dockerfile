FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 10000
CMD php artisan migrate --force && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}