# syntax=docker/dockerfile:1
FROM php:8.4-cli AS base

# pdo_sqlite and mbstring ship with the official image; git and unzip let Composer fetch dist archives.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    DATABASE_PATH=/app/var/bookmarks.sqlite

WORKDIR /app

FROM base AS app

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --no-interaction

EXPOSE 8080

CMD ["sh", "-c", "php bin/migrate.php && php -d expose_php=0 -S 0.0.0.0:8080 -t public public/index.php"]
