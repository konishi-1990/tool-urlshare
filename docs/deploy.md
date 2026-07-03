# デプロイフロー設計書（本番 / VPS）

main → product への PR マージをトリガーに、GitHub Actions から VPS へ SSH 接続し、
サーバ上の `deploy.sh` を実行して自動デプロイするフローの設計をまとめる。

> ステータス: **実装済み**（2026-07-03）。以下のファイルを作成・修正済み。
> - `.github/workflows/deploy-product.yml`（新規）
> - `docker-compose.prod.yml`（新規）
> - `.dockerignore`（新規／production ビルドの context 軽量化）
> - `script/deploy.sh`（本プロジェクト用に修正）
>
> 残作業は「9. 未決事項 / TODO」の Secrets 登録・サーバ側準備・`product` ブランチ push。

---

## 1. 全体像

```
[開発者]
   │  main で開発・コミット
   ▼
[main → product への Pull Request]
   │  レビュー後マージ
   ▼
[GitHub Actions: deploy-product.yml]
   │  PR が「マージされた」場合のみ発動
   │  Secrets の接続情報で VPS に SSH
   ▼
[VPS: deploy.sh 実行]
   │  git reset --hard origin/product
   │  docker compose -f docker-compose.prod.yml up -d --build
   │  artisan migrate / cache 再生成
   ▼
[上位 nginx-proxy(jwilder) 経由で HTTPS 公開]
   https://tool-urlshare.cde.jp
```

### 運用ルール

- `main` … 開発ブランチ
- `product` … 本番デプロイ対象ブランチ（VPS にチェックアウトされる）
- デプロイは「`main` → `product` の PR を**マージ**したとき」のみ発生する
  （PR を閉じただけ・直接 push では発動しない設計）

---

## 2. トリガー設計

発動条件「main→product の PR マージ時」を正確に表現するため、`push` ではなく
`pull_request: closed` イベント + マージ判定を用いる。

| 項目 | 値 |
|---|---|
| イベント | `pull_request` |
| types | `closed` |
| branches（PR の base＝マージ先） | `product` |
| 追加ガード | `if: github.event.pull_request.merged == true` |

- `branches: [product]` は PR の**マージ先（base）**で絞り込む。
- `merged == true` により「マージされず閉じられた PR」では発動しない。
- 再デプロイ用に `workflow_dispatch`（手動実行）も併設する。

> 補足: `pull_request` イベントで使用されるワークフロー定義は **base ブランチ（product）側**の
> ものが参照される。よって `deploy-product.yml` は `product` ブランチにも存在している必要がある
> （`product` は `main` から作成するため、両ブランチに同一ファイルが入る）。

---

## 3. 成果物一覧

| ファイル / 作業 | 区分 | 概要 |
|---|---|---|
| `.github/workflows/deploy-product.yml` | 新規 | PR マージで発動し VPS へ SSH、`deploy.sh` を実行 |
| `docker-compose.prod.yml` | 新規 | 本番用 compose（php=production / nginx-proxy 連携 / db 隔離） |
| `script/deploy.sh` | 修正 | 既存は LMS 用テンプレ。本プロジェクト用に修正 |
| `product` ブランチ | 新規 | `main` から作成（リモート push は実行前に確認） |
| `README.md` / 本書 | 追記 | サーバ側前提・Secrets 設定手順を記載 |

---

## 4. GitHub Actions ワークフロー

`.github/workflows/deploy-product.yml`（案）

```yaml
name: Deploy to Product (VPS)

on:
  pull_request:
    types: [closed]
    branches: [product]      # PR のマージ先が product
  workflow_dispatch:          # 手動再デプロイ用

jobs:
  deploy:
    # PR がマージされた場合のみ（手動実行時は常に通す）
    if: >-
      github.event_name == 'workflow_dispatch' ||
      github.event.pull_request.merged == true
    runs-on: ubuntu-latest
    steps:
      - name: Deploy over SSH
        uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.VPS_HOST }}
          port: ${{ secrets.VPS_PORT }}
          username: ${{ secrets.VPS_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          script: bash ${{ secrets.DEPLOY_SCRIPT_PATH }} ${{ secrets.DEPLOY_ARGS }}
```

- 接続情報・スクリプトパス・引数はすべて Secrets 化 →
  **コードを変更せず GitHub 上の設定だけで変更可能**。

### GitHub Secrets（リポジトリ Settings → Secrets and variables → Actions）

| Secret 名 | 用途 | 例 |
|---|---|---|
| `VPS_HOST` | 接続先ホスト / IP | `203.0.113.10` |
| `VPS_PORT` | SSH ポート | `22` |
| `VPS_USER` | SSH ユーザ | `urlshare` |
| `VPS_SSH_KEY` | SSH 秘密鍵（PEM 全文） | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `DEPLOY_SCRIPT_PATH` | サーバ上の deploy.sh フルパス | `/home/urlshare/tool-urlshare/script/deploy.sh` |
| `DEPLOY_ARGS` | deploy.sh への引数 | `/home/urlshare/tool-urlshare product docker-compose.prod.yml` |

---

## 5. 本番用 Docker 構成

### 前提：上位 nginx-proxy 構成

VPS には別途 `jwilder/nginx-proxy` + `letsencrypt-nginx-proxy-companion` が稼働しており、
80/443 の終端と Let's Encrypt 証明書の自動取得を担う。アプリ側のコンテナは
**ポートを公開せず**、proxy と同じ Docker ネットワークに参加し、環境変数で自動ルーティングされる。

| 項目 | 値 |
|---|---|
| 共有ネットワーク名 | `nginx-proxy_default`（`external: true` で参照） |
| 公開ドメイン | `tool-urlshare.cde.jp` |
| VIRTUAL_HOST / LETSENCRYPT_HOST | `tool-urlshare.cde.jp` |
| LETSENCRYPT_EMAIL | `cony@cde.jp` |

### `docker-compose.prod.yml`（案）

```yaml
services:
  nginx:
    image: nginx:alpine
    # ports は公開しない（80/443 は上位 nginx-proxy が担当）
    expose:
      - "80"
    environment:
      VIRTUAL_HOST: tool-urlshare.cde.jp
      VIRTUAL_PORT: "80"
      LETSENCRYPT_HOST: tool-urlshare.cde.jp
      LETSENCRYPT_EMAIL: cony@cde.jp
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
      - ./frontend:/var/www/frontend
      - ./admin:/var/www/admin
    networks:
      - proxy        # 上位 nginx-proxy と同じ外部ネットワーク
      - internal
    depends_on:
      - php

  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
      target: production            # backend を焼き込み・config:cache 済み
    volumes:
      - ./backend/.env:/var/www/backend/.env:ro   # 実行時に本番 .env を供給
    networks:
      - internal
    depends_on:
      db:
        condition: service_healthy

  db:
    image: postgres:16-alpine
    env_file:
      - .env.db
    volumes:
      - db_data:/var/lib/postgresql/data
    networks:
      - internal
    # 開発用と異なり ports は公開しない（外部非公開）
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U urlshare"]
      interval: 5s
      timeout: 5s
      retries: 5

networks:
  proxy:
    external: true
    name: nginx-proxy_default
  internal:

volumes:
  db_data:
```

### 開発用 compose との差分

| 観点 | 開発（docker-compose.yml） | 本番（docker-compose.prod.yml） |
|---|---|---|
| php ビルドターゲット | `development`（ソースはマウント） | `production`（ソース焼き込み + cache） |
| php ビルドコンテキスト | `./docker/php` | `.`（リポジトリroot。Dockerfile が `backend/` を COPY するため） |
| nginx ポート | `80:80` を公開 | 公開せず `expose: 80` + VIRTUAL_HOST |
| db ポート | `5432:5432` 公開 | 公開しない |
| ネットワーク | デフォルト | `proxy`(external) + `internal` |
| backend ソース | ボリュームマウント | イメージに焼き込み（.env のみマウント） |

> **注意（config:cache のタイミング）**
> 本番 Dockerfile はビルド時に `php artisan config:cache` を実行するが、ビルド時点では
> `.env` が存在しない。よって実行時に `.env` をマウントしたうえで、`deploy.sh` 内で
> `config:clear` → `config:cache` を再実行し、本番値でキャッシュし直す。

---

## 6. デプロイスクリプト

`script/deploy.sh` は現状 LMS プロジェクトの設定（サービス名 `lms_phpfpm`、
`docker-compose.stg.yml`、`/svr/app` など）が残っており**そのままでは動作しない**。
本プロジェクト用に以下へ修正する。

| 箇所 | 現在（LMS） | 修正後（UrlShare） |
|---|---|---|
| 既定ブランチ | `staging` | `product` |
| compose ファイル | `docker-compose.stg.yml` | `docker-compose.prod.yml` |
| サービス名 `APP_SVC` | `lms_phpfpm` | `php` |
| アプリパス | `/svr/app` | `/var/www/backend` |
| composer / npm | 実行時に実行 | 本番イメージビルド時に実施済み（実行時はスキップ） |

### 修正後の処理フロー

1. `git fetch --prune origin`
2. `git checkout product && git reset --hard origin/product`
   （`.env` / `.env.db` / `db_data` は gitignore・named volume のため保持）
3. `docker compose -f docker-compose.prod.yml up -d --build`（新ソースで再ビルド）
4. `docker compose exec -T php php artisan migrate --force`
5. `docker compose exec -T php php artisan config:clear && config:cache && route:cache && view:cache`
   （本番 `.env` の値で再キャッシュ）

> フロントエンド / 管理画面（`frontend/` `admin/`）は静的ファイルを nginx が直接配信するため
> ビルド工程は不要。バックエンドは API 専用のため `npm run build` は行わない
> （将来 Vite アセットが必要になった場合は追加検討）。

---

## 7. サーバ（VPS）側の前提

デプロイ前に VPS 側で以下が整っていること。

1. **Docker / Docker Compose 導入済み**
2. **上位 nginx-proxy + letsencrypt-companion が稼働中**（`nginx-proxy_default` ネットワークが存在）
3. **リポジトリを clone 済み**
   ```bash
   git clone github_cdetool:konishi-1990/tool-urlshare.git /home/urlshare/tool-urlshare
   cd /home/urlshare/tool-urlshare
   git checkout product
   ```
4. **環境変数ファイルを配置**（gitignore 済みのためサーバ上に手動作成）
   - `backend/.env`（`APP_ENV=production` / `APP_DEBUG=false` / `APP_KEY` / DB 接続 / `APP_URL=https://tool-urlshare.cde.jp` 等）
   - `.env.db`（POSTGRES_DB / USER / PASSWORD）
5. **サーバが GitHub から pull できる**こと（deploy key 等）
6. **GitHub に上記 6 つの Secrets を登録**

### DNS

- `tool-urlshare.cde.jp` の A レコードを VPS の IP に向ける
  （letsencrypt-companion の証明書取得に必要）

---

## 8. 初回セットアップ手順（サーバ上、手動・一度きり）

```bash
cd /home/urlshare/tool-urlshare
git checkout product

# .env / .env.db を配置後
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec -T php php artisan key:generate --force
docker compose -f docker-compose.prod.yml exec -T php php artisan migrate --force
# 管理者ユーザー作成（必要に応じて）
```

以降は `main → product` の PR マージで自動デプロイされる。

---

## 9. 未決事項 / TODO

- [ ] `backend/.env`（本番）の具体値の確定（APP_KEY 採番、DB 認証情報）
- [ ] VPS 上のデプロイ先ディレクトリパス確定（`DEPLOY_ARGS` に反映）
- [ ] SSH 接続ユーザ・鍵の準備と Secrets 登録
- [ ] `nginx-proxy_default` ネットワークの実在確認（`docker network ls`）
- [ ] DNS（A レコード）設定
- [x] 各ファイルの作成・修正（workflow / prod compose / .dockerignore / deploy.sh）
- [ ] `product` ブランチを `main` から作成し push（PR base として必要）
```