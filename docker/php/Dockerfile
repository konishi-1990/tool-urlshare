FROM php:8.3-fpm AS base

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# ─── 開発 ────────────────────────────────────────────────
FROM base AS development
# ソースはボリュームマウントで提供するためコピー不要

# ─── 本番 ────────────────────────────────────────────────
FROM base AS production

COPY backend/ /var/www/backend/

RUN composer install --no-dev --optimize-autoloader \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data /var/www/backend
