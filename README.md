# URL共有ツール

あとで読みたい記事の URL を保存・管理し、複数端末から参照できる Web アプリ + ブラウザ拡張機能のセット。

## 機能

- **URL 保存** — OGP / meta タグからタイトル・概要・サムネイルを自動取得
- **ステータス管理** — 仮保存 → ブックマーク → 削除 のワークフロー
- **マルチデバイス対応** — どの端末からでも同じリストを参照
- **ブラウザ拡張機能** — 現在のタブを Chrome からワンクリックで保存
- **管理画面** — URL 一覧管理・ブックマーク HTML エクスポート
- **ユーザー管理** — 管理者によるユーザー作成・削除

## 技術スタック

| レイヤー | 技術 |
|---|---|
| インフラ | Docker / Docker Compose |
| Web サーバ | nginx |
| バックエンド | PHP 8.3 + Laravel 11 |
| フロントエンド | HTML / CSS / JS（スマホファースト） |
| 管理画面 | HTML / CSS / JS（PC ベース） |
| データベース | PostgreSQL 16 |
| 認証 | Laravel Sanctum |
| ブラウザ拡張 | Chrome Extension Manifest V3 |

## ディレクトリ構成

```
UrlShare/
├── docker-compose.yml
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
├── backend/          # Laravel プロジェクト
├── frontend/         # ユーザ向け画面（スマホファースト）
├── admin/            # 管理画面（PC ベース）
├── extension/        # Chrome 拡張機能
└── docs/             # 設計ドキュメント
```

## 開発環境のセットアップ

### 前提条件

- Docker Desktop がインストール済みであること

### 手順

```bash
# 1. リポジトリをクローン
git clone https://github.com/your-repo/UrlShare.git
cd UrlShare

# 2. 環境変数ファイルを作成
cp backend/.env.example backend/.env
cp .env.db.example .env.db

# 3. コンテナをビルド・起動
docker compose up -d --build

# 4. Laravel の初期設定
docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate

# 5. 管理者ユーザーを作成
docker compose exec php php artisan tinker
> \App\Models\User::create(['email' => 'admin@example.com', 'password' => 'password123', 'is_admin' => true]);
```

### アクセス先

| URL | 説明 |
|---|---|
| http://localhost | フロントエンド（ユーザ向け） |
| http://localhost/admin | 管理画面（管理者アカウントでログイン） |
| http://localhost/api/v1 | REST API |

## テスト

```bash
# 全テスト実行
docker compose exec php php artisan test

# フィルタ指定
docker compose exec php php artisan test --filter AuthTest
docker compose exec php php artisan test --filter UrlEntry
```

> テストは `backend/.env.testing`（SQLite インメモリ）を使用し、開発用 PostgreSQL には影響しません。

## よく使うコマンド

```bash
# ログ確認
docker compose exec php tail -f storage/logs/laravel.log

# マイグレーション（再実行）
docker compose exec php php artisan migrate:fresh --seed

# コンテナ停止
docker compose down

# DB データごと初期化
docker compose down -v

# Tinker でユーザー管理
docker compose exec php php artisan tinker
```

## ドキュメント

| ファイル | 内容 |
|---|---|
| [docs/require.md](docs/require.md) | 要件定義 |
| [docs/architecture.md](docs/architecture.md) | アーキテクチャ設計（システム構成・API 設計・フロー図） |
| [docs/model.md](docs/model.md) | データモデル設計・ER 図 |
| [docs/docker.md](docs/docker.md) | Docker 構成・デプロイ手順 |
| [docs/implementation-plan.md](docs/implementation-plan.md) | 実装プラン（TDD） |
