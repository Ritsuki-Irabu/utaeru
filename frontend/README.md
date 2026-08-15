# ウタエル フロントエンド

Vue.js 3 + Vite + PWA のフロントエンドです。

## 開発サーバー

プロジェクト直下から実行します。

```bash
./vendor/bin/sail npm run dev --prefix frontend -- --host 0.0.0.0
```

スマホ実機から確認する場合は、PCと同じWi-Fiへ接続し、次のコマンドでLAN公開します。

```bash
./vendor/bin/sail npm run dev:lan --prefix frontend
```

WindowsのIPv4アドレスを使って、スマホで`http://<PCのIPv4>:5173`を開いてください。このアプリはVue PWAのため、Expo Goではなくスマホのブラウザ（必要ならホーム画面追加）で確認します。

## ビルド

```bash
./vendor/bin/sail npm run build --prefix frontend
```

## 環境変数

```env
VITE_API_URL=http://localhost/api
```
