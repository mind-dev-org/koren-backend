FROM php:8.2-cli-alpine

RUN apk add --no-cache postgresql-dev
RUN docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 8000

CMD php -S 0.0.0.0:${PORT:-8000} -t public/
