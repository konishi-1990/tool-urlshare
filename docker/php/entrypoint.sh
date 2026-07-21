#!/bin/sh
set -e

# 本番 .env は実行時にマウントされる（docker-compose.prod.yml）。
# そのため env に依存する config/route/view キャッシュとマイグレーションは
# ビルド時ではなく「コンテナ起動時」に実行する必要がある。
# （ビルド時に config:cache すると .env 不在で DB_CONNECTION が既定の
#   sqlite に焼き込まれ、実行時の pgsql 設定が無視されてしまう）

cd /var/www/backend

# 前回のキャッシュを破棄してから、マウント済み .env を元に再キャッシュする
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# DB スキーマを最新化（本番では対話プロンプトを出さない）
php artisan migrate --force

exec "$@"
