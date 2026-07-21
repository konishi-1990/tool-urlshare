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

# 依存だけをビルド時に固める。
# config/route/view の cache と migrate は .env がマウントされる起動時に
# entrypoint で実行する（ビルド時は .env 不在で誤った値が焼き込まれるため）。
RUN composer install --no-dev --optimize-autoloader \
    && chown -R www-data:www-data /var/www/backend

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
