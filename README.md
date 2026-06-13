# ウタエル

符割り確認特化型カラオケ支援アプリ

カラオケ直前に**音を出さず**、BPMの視覚フィードバックでリズムを確認できるPWAアプリ。

---

## 企画概要

![ウタエル プロジェクト概要](docs/images/utaeru-project-overview-imagegen-v2.png)

---

## 概要

| 一般的な音楽アプリ | ウタエル |
| --- | --- |
| 音再生前提 | 無音前提 |
| 練習用 | 本番直前用 |
| 採点志向 | 不安解消志向 |

---

## 技術スタック

| 領域 | 技術 |
| --- | --- |
| バックエンド | PHP 8.2 / Laravel 11 |
| 開発環境 | WSL2 / Docker Desktop / Laravel Sail |
| 認証 | Laravel Sanctum |
| 権限管理 | Spatie Laravel Permission |
| CSV出力 | Maatwebsite Laravel Excel |
| 外部API | Spotify Web API（BPM取得） |
| DB | MySQL 8.0 |
| フロントエンド | Vue.js 3 + Vite |
| 状態管理 | Pinia |
| HTTP | Axios |
| ルーティング | Vue Router 4 |
| PWA | vite-plugin-pwa |

---

## セットアップ

### 必要環境

- WSL2
- Docker Desktop
- Docker Desktop の WSL2 backend
- VS Code + Remote WSL（推奨）

PHP / Composer / MySQL / Node.js は、原則として Laravel Sail のコンテナ内で利用する。

### バックエンド

Laravel / Sail の初回導入は Issue #1 で実施する。導入後の通常操作は以下。

```bash
./vendor/bin/sail up -d
cp .env.example .env
./vendor/bin/sail artisan key:generate
```

`.env` を編集してDB接続情報・Spotify APIキーを設定する。

```bash
./vendor/bin/sail artisan migrate --seed
```

### フロントエンド

```bash
./vendor/bin/sail npm install --prefix frontend
cp frontend/.env.example frontend/.env
./vendor/bin/sail npm run dev --prefix frontend
```

---

## 環境変数

### バックエンド（`.env`）

```
DB_DATABASE=utaeru
DB_HOST=mysql
DB_USERNAME=sail
DB_PASSWORD=password

SPOTIFY_CLIENT_ID=your_client_id
SPOTIFY_CLIENT_SECRET=your_client_secret
```

### フロントエンド（`.env`）

```
VITE_API_URL=http://localhost:8000/api
```

---

## 画面構成

| 画面 | パス | 権限 |
| --- | --- | --- |
| ログイン | `/login` | 全員 |
| マイリスト | `/` | user |
| 曲検索・追加 | `/songs` | user |
| 曲マスタ管理 | `/admin/songs` | admin |

---

## テストアカウント（Seeder）

| ロール | メール | パスワード |
| --- | --- | --- |
| admin | admin@example.com | password |
| user | user@example.com | password |

---

## iPhoneへのインストール（PWA）

1. Safari でアプリのURLを開く
2. 共有ボタン →「ホーム画面に追加」
3. アプリとして起動できる

---

## ライセンス

MIT
