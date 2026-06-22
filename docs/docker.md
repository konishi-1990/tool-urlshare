# URL共有ツール Docker 構成・デプロイ設計書

## 1. コンテナ構成

| コンテナ名 | イメージ | 役割 |
|---|---|---|
| nginx | nginx:alpine | リバースプロキシ・静的ファイル配信 |
| php | カスタム (php:8.3-fpm) | Laravel API サーバ |
| db | postgres:16-alpine | データベース |

---

## 2. 開発環境

### docker-compose.yml

```yaml
services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
      - ./frontend:/var/www/frontend
      - ./admin:/var/www/admin
      - ./backend:/var/www/backend
    depends_on:
      - php

  php:
    build:
      context: ./docker/php
      target: development
    volumes:
      - ./backend:/var/www/backend   # ホットリロードのためマウント
    depends_on:
      - db

  db:
    image: postgres:16-alpine
    env_file:
      - .env.db
    volumes:
      - db_data:/var/lib/postgresql/data
    ports:
      - "5432:5432"   # ローカル DB クライアント接続用（開発のみ）

volumes:
  db_data:
```

> **注意**: PHP サービスは `env_file` を使わず、Laravel が起動時に `backend/.env` を直接読み込む。OS 環境変数に DB 設定が注入されるとテスト時の DB 分離が機能しなくなるため。

### .env.db（開発用）

```dotenv
POSTGRES_DB=urlshare
POSTGRES_USER=urlshare
POSTGRES_PASSWORD=secret
```

### backend/.env（開発用）

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=urlshare
DB_USERNAME=urlshare
DB_PASSWORD=secret
```

---

## 3. 本番環境

開発環境との主な差分：コードはイメージに焼き込む・DBポートを外部非公開・シークレットは環境変数で注入。

### docker-compose.prod.yml

```yaml
services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/prod.conf:/etc/nginx/conf.d/default.conf
      - ./frontend:/var/www/frontend
      - ./admin:/var/www/admin
      - ./backend/public:/var/www/backend/public   # publicのみマウント
      - ssl_certs:/etc/letsencrypt:ro
    depends_on:
      - php

  php:
    build:
      context: .
      dockerfile: ./docker/php/Dockerfile
      target: production              # マルチステージ: コードをイメージに焼き込む
    env_file:
      - .env.prod                     # 本番環境変数
    depends_on:
      - db
    restart: always

  db:
    image: postgres:16-alpine
    env_file:
      - .env.db.prod
    volumes:
      - db_data:/var/lib/postgresql/data
    # ports は公開しない（コンテナ内部通信のみ）
    restart: always

volumes:
  db_data:
  ssl_certs:
```

### .env.prod（本番用・リポジトリに含めない）

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example.com

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=urlshare
DB_USERNAME=urlshare
DB_PASSWORD=<strong-password>   # 本番は強力なパスワードを設定

APP_KEY=<base64:...>            # php artisan key:generate で生成
```

---

## 4. 設定ファイル詳細

### docker/php/Dockerfile（マルチステージ）

```dockerfile
# ─── 共通ベース ───────────────────────────────────────────
FROM php:8.3-fpm AS base

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# ─── 開発 ─────────────────────────────────────────────────
FROM base AS development
# ソースはボリュームマウントで提供するためコピー不要

# ─── 本番 ─────────────────────────────────────────────────
FROM base AS production

COPY backend/ /var/www/backend/

RUN composer install --no-dev --optimize-autoloader \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data /var/www/backend
```

### docker/nginx/default.conf（開発）

```nginx
server {
    listen 80;

    # フロントエンド（ユーザ向け）
    location / {
        root /var/www/frontend;
        index index.html;
        try_files $uri $uri/ /index.html;
    }

    # 管理画面
    location /admin {
        alias /var/www/admin;
        index index.html;
        try_files $uri $uri/ /admin/index.html;
    }

    # Laravel API
    location /api {
        root /var/www/backend/public;
        fastcgi_pass php:9000;
        fastcgi_param SCRIPT_FILENAME /var/www/backend/public/index.php;
        include fastcgi_params;
    }
}
```

### docker/nginx/prod.conf（本番）

```nginx
# HTTP → HTTPS リダイレクト
server {
    listen 80;
    server_name your-domain.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name your-domain.example.com;

    ssl_certificate     /etc/letsencrypt/live/your-domain.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.example.com/privkey.pem;

    # フロントエンド
    location / {
        root /var/www/frontend;
        index index.html;
        try_files $uri $uri/ /index.html;
        gzip_static on;
    }

    # 管理画面
    location /admin {
        alias /var/www/admin;
        index index.html;
        try_files $uri $uri/ /admin/index.html;
    }

    # Laravel API
    location /api {
        root /var/www/backend/public;
        fastcgi_pass php:9000;
        fastcgi_param SCRIPT_FILENAME /var/www/backend/public/index.php;
        include fastcgi_params;
        fastcgi_read_timeout 60;
    }
}
```

---

## 5. デプロイ手順（VPS）

```
1. VPS に Docker / Docker Compose をインストール

2. リポジトリをクローン
   git clone https://github.com/your-repo/UrlShare.git
   cd UrlShare

3. 本番用 .env ファイルを設置
   cp backend/.env.example backend/.env
   # APP_KEY / DB_PASSWORD / APP_URL を編集
   php artisan key:generate --show  → APP_KEY に設定

4. SSL 証明書を取得（Let's Encrypt）
   docker run --rm -p 80:80 \
     -v ssl_certs:/etc/letsencrypt \
     certbot/certbot certonly --standalone \
     -d your-domain.example.com

5. 本番コンテナをビルド・起動
   docker compose -f docker-compose.prod.yml up -d --build

6. マイグレーション実行
   docker compose -f docker-compose.prod.yml exec php \
     php artisan migrate --force

7. 動作確認
   curl https://your-domain.example.com/api/v1/health
```

### 更新デプロイ手順

```bash
git pull origin main
docker compose -f docker-compose.prod.yml build php
docker compose -f docker-compose.prod.yml up -d --no-deps php
docker compose -f docker-compose.prod.yml exec php php artisan migrate --force
```

---

## 6. よく使うコマンド

### 開発

```bash
# 起動
docker compose up -d

# コンテナ状態確認
docker compose ps

# マイグレーション
docker compose exec php php artisan migrate

# テスト実行
docker compose exec php php artisan test

# Laravel ログ確認
docker compose exec php tail -f storage/logs/laravel.log

# コンテナ停止
docker compose down

# DB データも含めて完全削除（開発リセット）
docker compose down -v
```

### 本番

```bash
# ログ確認
docker compose -f docker-compose.prod.yml logs -f php

# コンテナ再起動（設定変更後）
docker compose -f docker-compose.prod.yml restart nginx

# DB バックアップ
docker compose -f docker-compose.prod.yml exec db \
  pg_dump -U urlshare urlshare > backup_$(date +%Y%m%d).sql
```

---

## 7. 未決事項

| 項目 | 内容 |
|---|---|
| VPS サービス | さくらVPS / Vultr / DigitalOcean 等から選定 |
| SSL 自動更新 | certbot の cron 設定（90日更新） |
| バックアップ自動化 | pg_dump の定期実行（cron or GitHub Actions） |
| CI/CD | GitHub Actions で push 時に自動テスト → VPS へ SSH デプロイ |
