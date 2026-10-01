FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev && docker-php-ext-install intl pdo_mysql zip sockets && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts
COPY . .
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache
EXPOSE 8000
CMD ["php","artisan","serve","--host=0.0.0.0","--port=8000"]
