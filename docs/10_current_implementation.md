# 現行実装ガイド

**確認基準：** Issue #34 完了時点（2026年8月）

この資料は、設計書を読む前に現在のコードの全体像を確認するための入口です。詳細な設計判断は[基本設計書](03_basic_design.md)と各Issue別設計書を参照し、最終的な挙動は実際のソースコードを正とします。

## 1. 現在提供している機能

- Sanctumによるユーザー登録・ログイン・ログアウト
- adminによる曲マスタ・タグの管理
- 曲名・アーティスト・アルバムを対象にしたローカル／外部曲検索
- 検索結果の共通曲カタログへの取り込み
- お気に入り、メモ、個人BPM、タグ、CSV出力
- プレイリストの作成、曲追加、曲順・メモ編集、削除
- プレイリストの共有リンク発行・無効化・閲覧・コピー
- 曲詳細での4拍子BPMリズム確認、タップテンポ、歌詞表示
- YouTube公式MVの候補検索・埋め込み再生・候補切り替え
- MV再生中のミニプレーヤー表示と、MVのBPMに連動したリズム確認
- ダークテーマ、PWA、曲詳細カテゴリの並び替え、画面スライド遷移

音源ファイルや歌詞サイトのHTMLをアプリへ保存・再配信する構成ではありません。MVはYouTube公式iframe、歌詞は設定済みの歌詞APIから取得した内容だけを扱います。

## 2. 起動と停止

プロジェクト直下で実行します。

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install --prefix frontend
./vendor/bin/sail npm run dev --prefix frontend -- --host 0.0.0.0
```

ブラウザでは`http://localhost`、フロントエンド開発サーバーでは`http://localhost:5173`を開きます。Sailを停止する場合は次を実行します。

```bash
./vendor/bin/sail down
```

## 3. 画面とURL

| 画面 | URL | 認証 | 主な責務 |
| --- | --- | --- | --- |
| ログイン | `/login` | 不要 | 登録・ログイン |
| お気に入り | `/` | 必須 | 曲・メモ・タグ・個人BPM・CSV |
| 曲検索 | `/songs` | 必須 | ローカル／外部検索、追加 |
| 曲詳細 | `/songs/:id` | 必須 | BPM、MV、歌詞、曲操作 |
| プレイリスト一覧 | `/playlists` | 必須 | 一覧、作成 |
| プレイリスト詳細 | `/playlists/:id` | 必須 | 曲一覧、再生、管理、共有 |
| 共有プレイリスト | `/shared/playlists/:token` | 必須 | 閲覧、コピー |
| 管理画面 | `/admin/songs` | admin | 曲マスタ、BPM、タグ管理 |

共有URLも現在は認証済みユーザーだけが閲覧できます。共有先ユーザーは元プレイリストを編集せず、自分のプレイリストへコピーします。

## 4. APIの構成

APIのベースパスは`/api`です。認証不要なのは登録・ログイン、その他は`auth:sanctum`が必要です。

| APIグループ | 主なエンドポイント | 内容 |
| --- | --- | --- |
| 認証 | `/auth/register`、`/auth/login`、`/auth/logout`、`/user` | トークン認証 |
| 曲 | `/songs`、`/songs/{song}` | 曲カタログ、詳細 |
| 曲検索・取り込み | `/songs/search`、`/songs/import` | iTunes検索とカタログ取り込み |
| MV・歌詞 | `/songs/youtube/search`、`/songs/{song}/lyrics` | YouTube候補、歌詞取得 |
| お気に入り | `/my-songs`、`/my-songs/export` | 個人ライブラリ、CSV |
| タグ | `/tags` | タグ一覧、作成 |
| プレイリスト | `/playlists`、`/playlists/{playlist}/songs` | CRUD、曲管理 |
| 共有 | `/playlists/{playlist}/share`、`/shared/playlists/{token}` | 共有、無効化、閲覧、コピー |
| 管理者 | `/songs/spotify`、曲のPOST／PUT／DELETE、BPM再取得 | admin専用操作 |

実際のルート定義は[`routes/api.php`](../routes/api.php)、入力検証は`app/Http/Requests/`、レスポンス形式は`app/Http/Resources/`を確認します。

## 5. 外部サービスとフォールバック

### 曲検索・取り込み

`MusicCatalogService`がローカル曲を検索した後、`ItunesMusicProvider`で外部候補を取得します。外部曲をお気に入りやプレイリストへ追加する時点で、`provider + provider_key`を使って`songs`へ一度だけ取り込みます。

### BPM

取り込み時や管理者の再取得では、`BpmResolver`が次の順に問い合わせます。

1. Deezer
2. GetSongBPM（APIキー設定時）
3. Spotify（認証情報設定時）
4. YouTubeメタデータ
5. 取得できない場合は`null`

BPM未取得でも曲の保存・お気に入り・プレイリスト追加は継続できます。曲詳細ではタップテンポで端末内の暫定値を記録し、お気に入り登録済みの場合は個人BPMとして保存できます。

### MV

曲マスタにYouTubeの再生元がある場合はその動画を使い、未登録の場合だけ`YOUTUBE_API_KEY`を使って候補を検索します。候補は埋め込み可能性を確認し、再生エラー時は次候補へ切り替えます。動画内の標準コントロールを利用し、アプリ独自の音声抽出や再生UIは実装していません。

### 歌詞

曲マスタに歌詞がなければ、`LYRICS_API_URL`で設定したAPIを参照します。未設定時の既定値はLRCLIBです。取得した歌詞は曲へ保存し、歌いだしの表示にも再利用します。取得失敗や未登録は、曲詳細・MV・BPMの利用を止めない扱いです。

## 6. コードの責務分担

### Laravel

- Controller：HTTP入出力、Policy呼び出し、Resource返却
- FormRequest：入力値の検証
- Service：BPM解決、外部検索・取り込み、プレイリスト操作
- Provider／Contract：iTunes、歌詞、YouTubeなど外部サービスの差し替え境界
- Model／Policy：DBリレーションと本人確認
- Resource／Export：JSONレスポンスとCSV出力

### Vue

- `views/`：画面単位の表示と操作
- `components/`：リズム、MV、プレイリスト連続再生などの部品
- `api/`：Axios通信
- `stores/`：認証、曲、お気に入り、プレイリスト、リズム、MVの共有状態
- `router/`：ログイン・管理者ガード

MV iframeは`youtubePlayback`ストアと`YoutubeMvPlaybackDock`で保持します。曲詳細と画面遷移後のミニプレーヤーで同じ再生状態を共有し、MV再生時は`rhythmPlayback`へBPMを連携します。

## 7. テスト

```bash
# Laravel Feature / Unit Test
./vendor/bin/sail artisan test

# Vueのビルド
./vendor/bin/sail npm run build --prefix frontend

# Playwright（Desktop Chrome + mobile相当Chromium）
./vendor/bin/sail npm --prefix frontend run test:e2e

# 起動中APIへのCRUDスモークテスト
./vendor/bin/sail npm --prefix frontend run test:live-api
```

Playwrightのケース一覧は[`e2e-test-cases.md`](e2e-test-cases.md)、テストコードは`frontend/e2e/`を参照します。

## 8. ドキュメントの使い分け

- 要件や対象範囲：[`01_requirements.md`](01_requirements.md)、[`02_specification.md`](02_specification.md)
- 全体構成・API・DB・画面責務：[`03_basic_design.md`](03_basic_design.md)
- 学習用のクラス別詳細：[`04_detail_design.md`](04_detail_design.md)
- Issueごとの追加仕様：[`05_playlist_share_audio_design.md`](05_playlist_share_audio_design.md)〜[`09_youtube_playback_design.md`](09_youtube_playback_design.md)
- コードを横断した現在の挙動：この資料

機能を追加・変更したときは、実装だけでなくREADME、関連Issue別設計書、`TICKETS.md`、`AGENTS.md`のフェーズ情報も確認します。
