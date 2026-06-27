# ウタエル フロントエンド

Vue.js 3 + Vite + PWA のフロントエンドです。

## 開発サーバー

プロジェクト直下から実行します。

```bash
./vendor/bin/sail npm run dev --prefix frontend -- --host 0.0.0.0
```

## ビルド

```bash
./vendor/bin/sail npm run build --prefix frontend
```

## 環境変数

```env
VITE_API_URL=http://localhost/api
```
