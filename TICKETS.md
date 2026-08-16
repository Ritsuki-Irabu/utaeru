# TICKETS.md - GitHub Issues チケット一覧

> GitHub Issues の静的コピー。最新状況は GitHub を参照。

---

## ラベル

| ラベル | 色 | 用途 |
| --- | --- | --- |
| backend | #e74c3c | Laravel関連 |
| frontend | #3498db | Vue.js関連 |
| learning | #f1c40f | 学習メモあり |
| bug | #e67e22 | バグ修正 |
| done | #2ecc71 | 完了 |

---

## チケット一覧

| # | タイトル | ラベル | ステータス |
| --- | --- | --- | --- |
| #1 | 【backend】環境構築 | backend, learning | ✅ |
| #2 | 【backend】DB設計・マイグレーション作成 | backend, learning | ✅ |
| #3 | 【backend】認証API（Sanctum） | backend, learning | ✅ |
| #4 | 【backend】ロール設定（Spatie Permission） | backend, learning | ✅ |
| #5 | 【backend】公開曲マスタCRUD | backend, learning | ✅ |
| #6 | 【backend】Spotify API連携（BPM取得） | backend, learning | ✅ |
| #7 | 【backend】マイリストCRUD + タグ付け | backend, learning | ✅ |
| #8 | 【backend】CSV出力（Laravel Excel） | backend, learning | ✅ |
| #9 | 【frontend】Vue.js + PWA 環境構築 | frontend, learning | ✅ |
| #10 | 【frontend】ログイン画面・Axios設定 | frontend, learning | ✅ |
| #11 | 【frontend】マイリスト画面 | frontend, learning | ✅ |
| #12 | 【frontend】リズム再生コンポーネント | frontend, learning | ✅ |
| #13 | 【frontend】曲検索・追加画面 | frontend, learning | ✅ |
| #14 | 【frontend】管理者画面（曲マスタ管理） | frontend, learning | ✅ |
| #15 | 【frontend】Playwright画面テスト・UX課題発見 | frontend, learning | ✅ |
| #16 | 【backend/frontend】プレイリスト作成・共有・音声再生 | backend, frontend, learning | ✅ |
| #17 | 【backend/frontend】ユーザー向け曲検索・カタログ取り込み | backend, frontend, learning | ✅ |
| #18 | 【frontend/docs】お気に入り・プレイリストUX整理、再生導線・ダークモード | frontend, learning, ux | ✅ |
| #19 | 【frontend/docs】プレイリスト導線・CRUD責務整理 | frontend, learning, ux | ✅ |
| #20 | 【frontend/docs】曲詳細のYouTube MV再生 | frontend, learning, ux | ✅ |
| #21 | 【backend/frontend/docs】BPM自動取得・プレイリスト連続再生・曲検索UX | backend, frontend, learning, ux | ✅ |
| #22 | 【backend/frontend】音楽再生・歌詞表示・BPM取得の安定化 | backend, frontend, bug | ✅ |
| #23 | 【backend/frontend】曲詳細カテゴリのドラッグ並び替え・YouTube API安定化 | backend, frontend, bug, ux | ✅ |
| #24 | 【backend/frontend】曲タイトル紐づけ検索・管理者MV再生導線 | backend, frontend, bug, ux | ✅ |
| #25 | 【frontend】ドラッグ挿入位置表示・プレイリスト登録UX短縮 | frontend, bug, ux | ✅ |
| #26 | 【backend/frontend】曲一覧の歌いだし表示・詳細開閉・BPM/MVフォールバック | backend, frontend, bug, ux | ✅ |
| #27 | 【frontend/docs】曲詳細ラベル整理・MV自動再生停止・スマホPWA確認導線 | frontend, docs, ux | ✅ |
| #28 | 【frontend/docs】曲詳細アイコン配置・不要情報整理・歌詞プレビューUX | frontend, docs, ux | ✅ |
| #29 | 【backend/frontend】お気に入り画面整理・検索復帰導線・詳細開閉アニメーション | backend, frontend, ux, bug | ✅ |
| #30 | 【backend/frontend】お気に入り状態同期・トグル操作・歌いだし補完 | backend, frontend, ux, bug | ✅ |
| #31 | 【frontend】詳細画面カテゴリ統一・お気に入り/プレイリスト操作 | frontend, ux | ✅ |
| #32 | 【frontend】リズム再生の詳細内管理・設定メニュー・詳細操作整理 | frontend, ux | ✅ |
| #33 | 【backend/frontend】MVバックグラウンド再生・個人BPM登録・タグ作成 | backend, frontend, bug, ux | ✅ |
| #34 | 【frontend】スライド遷移・MV/BPM連動・詳細表示整理 | frontend, bug, ux | ✅ |

> ステータス凡例：⬜ 未着手 / 🔄 対応中 / ✅ 完了

---

## 環境構築メモ

Issue #1 は WSL2 + Docker Desktop + Laravel Sail を前提に、PHP 8.3+ / Laravel 13 で進める。

- Laravel / Composer / npm / artisan は原則として Sail 経由で実行する
- DB接続は Sail の `mysql` サービスを使用する
- `.env.example` のDB接続値は `DB_HOST=mysql`, `DB_USERNAME=sail`, `DB_PASSWORD=password` を基準にする
