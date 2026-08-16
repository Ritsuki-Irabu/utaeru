# ウタエル フロントエンド

Vue.js 3 + Vite + PWA のフロントエンドです。

## 画面と状態管理

主な画面は、ログイン、お気に入り、曲検索、曲詳細、プレイリスト一覧・詳細、共有プレイリスト、管理者向け曲マスタ管理です。

- `src/views/`: 画面単位の表示と操作
- `src/components/`: リズム表示、YouTube MV、プレイリスト連続再生などの再利用部品
- `src/api/`: AxiosによるLaravel API通信
- `src/stores/`: 認証、曲、お気に入り、プレイリスト、リズム、MVの共有状態
- `src/router/`: 認証ガードと管理者画面ガード

YouTubeのiframeは`youtubePlayback`ストアと`YoutubeMvPlaybackDock`でアプリ全体に1つだけ保持します。曲詳細からお気に入りやプレイリストへ移動しても、MVの再生状態を失わない構成です。

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

## テスト

PlaywrightはデスクトップChromeと390×844のモバイル相当Chromiumで実行します。

```bash
./vendor/bin/sail npm --prefix frontend run test:e2e
./vendor/bin/sail npm --prefix frontend run test:live-api
```

APIの接続先やスモークテスト用ユーザーを変更する場合は、`E2E_API_URL`、`E2E_SMOKE_EMAIL`、`E2E_SMOKE_PASSWORD`を指定します。
