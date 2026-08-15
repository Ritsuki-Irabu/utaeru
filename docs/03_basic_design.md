# 基本設計書

**バージョン：** 2.0
**作成・更新日：** 2026年8月15日
**ステータス：** Issue #34 までの現行実装を反映

本書は、ウタエルのシステム構成、データ構造、APIの責務分担、認証・認可、外部サービス連携、Vue/PWAの画面構成を定義する。

機能の詳しい画面仕様は以下の設計書を正とする。

- [要件定義書](01_requirements.md)：目的・機能要件・非機能要件
- [仕様書](02_specification.md)：ユーザー操作と機能仕様
- [詳細設計書](04_detail_design.md)：クラス・API・実装手順の詳細
- [プレイリスト・共有・音声再生設計](05_playlist_share_audio_design.md)
- [ユーザー向け曲検索・カタログ取り込み設計](06_music_catalog_design.md)
- [お気に入り・プレイリストUX設計](07_favorites_playlist_ux_design.md)
- [プレイリスト導線・責務整理設計](08_playlist_navigation_ux_design.md)
- [YouTube公式プレイヤー連携設計](09_youtube_playback_design.md)

---

## 1. システム概要

### 1.1 目的

ウタエルは、カラオケ直前に音を出さず、曲のBPMに合わせた視覚的な4拍子の表示で歌い出しを確認するPWAである。音声を確認したい場合だけ、YouTubeの公式埋め込みプレイヤーを利用する。

中心となる設計方針は次のとおり。

- BPMによるリズム確認は音声再生から独立させる
- 曲情報は共通曲カタログに集約し、お気に入りやプレイリストから参照する
- 外部サービスの通信はLaravel側に閉じ込め、Vueとドメインロジックを直接結合しない
- 音源・動画ファイルは保存または再配信せず、公式プレイヤーだけを利用する
- フロントエンドの認証ガードはUXのために使い、実際の認可は必ずAPI側でも行う

### 1.2 システム境界

```text
┌─────────────────────────────────────────────────────────────┐
│                    Vue.js 3 / Vite PWA                      │
│                                                             │
│  Views       Components       Pinia Stores       Axios API   │
│  ・ログイン  ・リズム表示     ・認証             ・REST通信   │
│  ・お気に入り・MVプレイヤー    ・曲/お気に入り                │
│  ・検索      ・プレイリスト    ・プレイリスト                   │
└───────────────────────────┬─────────────────────────────────┘
                            │ HTTPS / JSON
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                     Laravel 13 API                          │
│                                                             │
│  Route → FormRequest → Controller → Service/Provider        │
│                         │             │                     │
│                         ▼             ▼                     │
│                   API Resource    外部サービスAdapter        │
│                         │                                   │
│                         ▼                                   │
│                    Eloquent Model                           │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            ▼
                    MySQL 8.4 / Sanctum

  外部サービス：iTunes Search / Deezer / GetSongBPM / Spotify
                YouTube Data API / 利用許諾済み歌詞Provider
```

### 1.3 主要なデータフロー

```text
ログイン
  → Sanctumトークンを取得
  → AxiosがBearerトークンを付けてAPIへ送信

曲を探す
  → songsをローカル検索
  → 不足分をMusicSearchProviderで外部検索
  → 検索結果の追加操作時だけsongsへ正規化して保存
  → BPMをBpmResolverで補完（取得できない場合はnullを許容）

お気に入り / プレイリスト
  → 共通曲カタログのsong_idを参照
  → API側で所有者Policyを確認
  → MySongまたはPlaylistSongを保存

曲詳細
  → SongResourceを取得
  → BPMがあれば4拍子の視覚リズムを開始
  → MVボタン押下時だけYouTube候補を検索して公式iframeを表示
  → 歌詞が未登録の場合だけ歌詞Providerを参照
```

---

## 2. 技術構成

| 領域 | 採用技術 | 役割 |
| --- | --- | --- |
| API | PHP 8.3+ / Laravel 13 | REST API、認証、認可、業務処理 |
| 認証 | Laravel Sanctum | Bearerトークンの発行・検証 |
| 権限 | Spatie Laravel Permission | `admin` / `user` ロール管理 |
| DB | MySQL 8.4 | ユーザー、曲、個人データの永続化 |
| 開発環境 | WSL2 / Docker Desktop / Laravel Sail | PHP・DB・Nodeの実行環境 |
| フロントエンド | Vue.js 3 / Vite | SPA形式の画面表示 |
| 状態管理 | Pinia | 認証、曲一覧、再生状態などの共有状態管理 |
| API通信 | Axios | Laravel APIとの通信とBearerヘッダー付与 |
| ルーティング | Vue Router 4 | 画面遷移と認証・管理者ガード |
| PWA | `vite-plugin-pwa` | ホーム画面追加、PWAビルド |
| 外部曲検索 | iTunes Search API | 曲メタデータの検索・正規化 |
| BPM取得 | Deezer / GetSongBPM / Spotify / YouTubeメタデータ | BPMの補完 |
| MV再生 | YouTube Data API + 公式IFrame | MV候補検索と公式プレイヤー表示 |

Laravel・Composer・npm・artisanの操作は、開発環境では原則としてLaravel Sail経由で実行する。

---

## 3. アプリケーション構成

### 3.1 バックエンド

```text
app/
├── Contracts/
│   ├── LyricsProvider.php
│   ├── MusicSearchProvider.php
│   └── VideoSearchProvider.php
├── Exports/
│   └── MySongsExport.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/AuthController.php
│   │   ├── LyricsController.php
│   │   ├── MySongController.php
│   │   ├── MySongExportController.php
│   │   ├── PlaylistController.php
│   │   ├── PlaylistShareController.php
│   │   ├── PlaylistSongController.php
│   │   ├── SongCatalogController.php
│   │   ├── SongController.php
│   │   ├── TagController.php
│   │   └── YoutubeVideoController.php
│   ├── Requests/
│   │   └── 入力用途ごとのFormRequest
│   └── Resources/
│       ├── MySongResource.php
│       ├── PlaylistResource.php
│       ├── PlaylistSongResource.php
│       ├── SongResource.php
│       └── TagResource.php
├── Models/
│   ├── User.php
│   ├── Song.php
│   ├── MySong.php
│   ├── Tag.php
│   ├── Playlist.php
│   └── PlaylistSong.php
├── Policies/
│   ├── MySongPolicy.php
│   ├── PlaylistPolicy.php
│   └── PlaylistSongPolicy.php
└── Services/
    ├── AuthorizedLyricsProvider.php
    ├── BpmResolver.php
    ├── DeezerService.php
    ├── GetSongBpmService.php
    ├── ItunesMusicProvider.php
    ├── MusicCatalogService.php
    ├── PlaylistService.php
    ├── ShareTokenService.php
    ├── SpotifyService.php
    └── YoutubeVideoProvider.php
```

### 3.2 バックエンドの責務

| 層 | 責務 | 置く処理 |
| --- | --- | --- |
| Route | URLと認証・ロールの入口を定義 | `auth:sanctum`、`role:admin` |
| FormRequest | 入力値を検証する | 必須、文字数、ID存在、BPM範囲 |
| Controller | HTTP入出力を調整する | Request受取、Policy呼出、Resource返却 |
| Service | 複数モデル・外部APIを含む処理をまとめる | BPM解決、検索・取り込み、曲順変更、コピー |
| Contract / Provider | 外部サービスの差分を隠蔽する | 曲検索、歌詞検索、MV候補検索 |
| Model | DB属性とリレーションを表現する | `belongsTo`、`hasMany`、`belongsToMany` |
| Policy | 対象リソースの所有者を確認する | お気に入り・プレイリストの本人確認 |
| Resource | APIレスポンスの形を統一する | 曲、マイリスト、プレイリストのJSON |
| Export | CSVの行生成と出力を担当する | UTF-8 BOM付きお気に入りCSV |

Controllerに外部API通信、曲順計算、CSVの行生成を直接書かず、責務ごとにService・Provider・Exportへ分離する。

### 3.3 フロントエンド

```text
frontend/src/
├── api/
│   ├── auth.js
│   ├── client.js
│   ├── mySongs.js
│   ├── playlists.js
│   ├── songs.js
│   └── tags.js
├── components/
│   ├── PlaylistQueuePlayer.vue
│   ├── RhythmPlayer.vue
│   ├── RhythmPlaybackDock.vue
│   ├── SettingsMenu.vue
│   ├── YoutubeMvPlaybackDock.vue
│   └── YoutubeMvPlayer.vue
├── stores/
│   ├── app.js
│   ├── auth.js
│   ├── mySongs.js
│   ├── playlists.js
│   ├── rhythmPlayback.js
│   ├── songs.js
│   └── youtubePlayback.js
├── views/
│   ├── AdminSongsView.vue
│   ├── LoginView.vue
│   ├── MyListView.vue
│   ├── PlaylistDetailView.vue
│   ├── PlaylistsView.vue
│   ├── SharedPlaylistView.vue
│   ├── SongDetailView.vue
│   └── SongSearchView.vue
├── App.vue
├── main.js
└── router/index.js
```

| フロント層 | 責務 |
| --- | --- |
| `views/` | 画面単位の表示、画面固有の操作 |
| `components/` | リズム表示、MV、プレイリスト連続再生などの再利用部品 |
| `stores/` | 画面をまたいで共有する認証・データ・再生状態 |
| `api/` | Axios呼出し、HTTPメソッドとURLの集約 |
| `router/` | URLとViewの対応、ログイン・adminガード |
| `App.vue` | 共通ヘッダー、設定メニュー、ルート遷移、MVドック |

---

## 4. データ設計

### 4.1 ER図

```text
users ────────────────< my_songs >────────────── songs
  │                       │                       │
  │                       └────< my_song_tag >────┘
  │                                      │
  │                                      └──── tags
  │
  ├────────────────< playlists ────────< playlist_songs >──── songs
  │                         │                    │
  │                         └─ share token       └─ added_by_user_id → users
  │
  └────────────────< personal_access_tokens

  Spatie Permission:
  users ── model_has_roles ── roles ── role_has_permissions ── permissions
```

### 4.2 users

Laravel標準のユーザーを利用する。パスワードはLaravelの`hashed` castでハッシュ化し、APIトークンは`personal_access_tokens`へ保存する。

| カラム | 型・制約 | 説明 |
| --- | --- | --- |
| `id` | BIGINT, PK | ユーザーID |
| `name` | VARCHAR(255), NOT NULL | 表示名 |
| `email` | VARCHAR(255), UNIQUE | ログインメールアドレス |
| `password` | VARCHAR(255), NOT NULL | ハッシュ化パスワード |
| `email_verified_at` | TIMESTAMP, NULL | Laravel標準項目 |
| `remember_token` | VARCHAR, NULL | Laravel標準項目 |
| `created_at` / `updated_at` | TIMESTAMP | 作成・更新日時 |

ロールはusersテーブルに列を追加せず、Spatie Permissionの関連テーブルで管理する。

### 4.3 songs（共通曲カタログ）

お気に入りとプレイリストから参照される共通の曲マスタである。検索結果は、ユーザーが追加操作をした時点でこのテーブルへ取り込む。

| カラム | 型・制約 | 説明 |
| --- | --- | --- |
| `id` | BIGINT, PK | 曲ID |
| `title` | VARCHAR(255), NOT NULL | 曲名 |
| `artist` | VARCHAR(255), NOT NULL | アーティスト名 |
| `lyrics` | TEXT, NULL | 管理者登録または許諾済みProviderから取得した歌詞 |
| `album` | VARCHAR(255), NULL | アルバム名 |
| `artwork_url` | VARCHAR(500), NULL | ジャケット画像URL |
| `bpm` | UNSIGNED INT, NULL | 曲マスタのBPM。未取得を許容 |
| `duration_ms` | UNSIGNED INT, NULL | 曲の長さ（ミリ秒） |
| `spotify_id` | VARCHAR(100), NULL | 既存データ互換・補助識別子 |
| `playback_provider` | VARCHAR(30), NULL | 現行は`youtube`。旧値は互換保持 |
| `playback_key` | VARCHAR(255), NULL | YouTube動画IDなどの再生識別子 |
| `playback_url` | VARCHAR(500), NULL | 旧データ互換用のURL。現行MV再生の正規値ではない |
| `created_at` / `updated_at` | TIMESTAMP | 作成・更新日時 |

`playback_provider`と`playback_key`には複合ユニーク制約を設定し、同一Providerの同一曲の重複取り込みを防ぐ。NULLを持つ管理者登録曲は外部Provider曲と異なる扱いになる。

### 4.4 my_songs（お気に入り）

DB・API上の名称は既存互換のため`my_songs`を維持し、画面上では「お気に入り」と表示する。ユーザーごとの曲に対するメモ、タグ、個人BPMを保持する。

| カラム | 型・制約 | 説明 |
| --- | --- | --- |
| `id` | BIGINT, PK | お気に入りレコードID |
| `user_id` | BIGINT, FK, CASCADE | 所有ユーザー |
| `song_id` | BIGINT, FK, CASCADE | 参照する曲 |
| `memo` | TEXT, NULL | ユーザー個人のメモ |
| `bpm` | UNSIGNED SMALLINT, NULL | ユーザーがタップ計測して登録した個人BPM |
| `created_at` / `updated_at` | TIMESTAMP | 作成・更新日時 |

`user_id`と`song_id`の複合ユニーク制約により、同じ曲を同じユーザーが二重登録することを防ぐ。個人BPMは曲マスタのBPMを上書きせず、表示・リズム再生時に曲マスタ値より優先する。ただし、曲マスタBPMが登録済みの場合は現在のUIでは個人BPMの計測操作を表示しない。

### 4.5 tags / my_song_tag

| テーブル | カラム・制約 | 説明 |
| --- | --- | --- |
| `tags` | `id`、`name` UNIQUE、timestamps | タグ名称の共有マスタ |
| `my_song_tag` | `my_song_id` + `tag_id` PRIMARY KEY | お気に入りとタグの多対多中間テーブル |

タグ作成時は同名タグを`firstOrCreate`で再利用する。お気に入り削除時と曲・タグ削除時は中間レコードをカスケード削除する。

### 4.6 playlists / playlist_songs

`playlists`はユーザーが作成する再生順のまとまり、`playlist_songs`はその中の曲と曲順を表す。

#### playlists

| カラム | 型・制約 | 説明 |
| --- | --- | --- |
| `id` | BIGINT, PK | プレイリストID |
| `user_id` | BIGINT, FK, CASCADE | 所有者 |
| `name` | VARCHAR(100), NOT NULL | プレイリスト名 |
| `description` | TEXT, NULL | 説明 |
| `visibility` | VARCHAR(20), NOT NULL | `private` または`shared` |
| `share_token_hash` | CHAR(64), UNIQUE, NULL | 共有トークンのSHA-256ハッシュ |
| `shared_at` | TIMESTAMP, NULL | 共有リンク発行日時 |
| `revoked_at` | TIMESTAMP, NULL | 共有リンク無効化日時 |
| `created_at` / `updated_at` | TIMESTAMP | 作成・更新日時 |

共有URLに含める平文トークンはDBへ保存しない。閲覧時に受け取ったトークンをSHA-256化し、`visibility = shared`かつ`revoked_at IS NULL`のレコードだけを有効とする。

#### playlist_songs

| カラム | 型・制約 | 説明 |
| --- | --- | --- |
| `id` | BIGINT, PK | プレイリスト曲ID |
| `playlist_id` | BIGINT, FK, CASCADE | 所属プレイリスト |
| `song_id` | BIGINT, FK, CASCADE | 共通曲カタログの曲 |
| `added_by_user_id` | BIGINT, FK, CASCADE | 追加したユーザー |
| `position` | UNSIGNED INT, NOT NULL | 1始まりの表示順 |
| `memo` | TEXT, NULL | プレイリスト内だけのメモ |
| `created_at` / `updated_at` | TIMESTAMP | 作成・更新日時 |

`playlist_id`と`song_id`の複合ユニーク制約により、同一プレイリスト内の曲重複を防ぐ。曲の追加・並び替え・削除は`PlaylistService`のトランザクションで行い、前後のpositionを詰める。

### 4.7 削除と参照の方針

- ユーザー削除を導入する場合は、そのユーザーのお気に入り・プレイリスト・トークンも削除対象とする
- プレイリスト削除時は、プレイリスト曲を削除するが、共通曲カタログは削除しない
- 共通曲削除時は、外部キーのCASCADEにより参照しているお気に入り・プレイリスト曲も削除されるため、管理者操作では影響範囲に注意する
- 共有リンクの無効化はプレイリストを削除せず、トークンを破棄して非公開へ戻す

---

## 5. 認証・認可設計

### 5.1 Sanctum認証フロー

```text
1. POST /api/auth/register または /api/auth/login
2. Laravelがユーザーを確認し、SanctumのplainTextTokenを発行
3. VueのauthストアがtokenとuserをlocalStorageへ保存
4. AxiosクライアントがAuthorization: Bearer {token}を付けてAPIを呼び出す
5. POST /api/auth/logoutでcurrentAccessTokenを削除
6. ログアウト時にVue側の認証・MV・リズム状態も破棄
```

パスワードはレスポンスへ返さない。フロントエンドの`localStorage`は画面復元のために使うが、API側のトークン検証を省略する根拠にはしない。

### 5.2 ロール

| ロール | 主な操作 |
| --- | --- |
| `user` | 曲検索・取り込み、お気に入り、タグ、プレイリスト、共有、BPM確認、CSV出力 |
| `admin` | `user`の全操作に加え、曲カタログの登録・編集・削除、BPM再取得、管理者向け検索 |

新規登録ユーザーには`user`ロールを自動付与する。管理者専用APIには`auth:sanctum`と`role:admin`を重ねて設定する。

### 5.3 Policy

| 対象 | Policy | 判定 |
| --- | --- | --- |
| お気に入り | `MySongPolicy` | `my_songs.user_id === auth()->id()` |
| プレイリスト | `PlaylistPolicy` | `playlists.user_id === auth()->id()` |
| プレイリスト曲 | `PlaylistSongPolicy` | 曲が属するプレイリストの所有者が本人 |
| 共有閲覧・コピー | `ShareTokenService` | 有効なハッシュ、公開状態、未失効を確認 |
| 曲カタログ管理 | Route middleware | `role:admin` |

フロントエンドの`router.beforeEach`は未ログインユーザーをログイン画面へ送り、管理画面リンクを隠すための補助機能である。データの取得・更新・削除の最終判断はAPI側で行う。

---

## 6. API基本設計

### 6.1 共通方針

- APIのベースパスは`/api`
- JSONの一覧・単体レスポンスはLaravel API Resourceを利用する
- Resourceコレクションは原則`{ "data": [...] }`、単体は`{ "data": {...} }`で返す
- バリデーションエラーはLaravel標準の422、未認証は401、権限不足は403、存在しないリソースは404を利用する
- 外部Provider障害は、可能な範囲でローカル結果を返し、外部処理だけが失敗した場合は502/503で画面に再試行導線を表示する

### 6.2 認証API

| Method | URI | 認証 | 用途 |
| --- | --- | --- | --- |
| POST | `/auth/register` | 不要 | ユーザー登録とトークン発行 |
| POST | `/auth/login` | 不要 | ログインとトークン発行 |
| GET | `/user` | 必須 | 現在のユーザー確認 |
| POST | `/auth/logout` | 必須 | 現在のトークンを削除 |

### 6.3 曲・お気に入り・タグAPI

| Method | URI | 認証・権限 | 用途 |
| --- | --- | --- | --- |
| GET | `/songs` | 必須 | 曲カタログ一覧（20件ページング） |
| GET | `/songs/{song}` | 必須 | 曲詳細情報 |
| GET | `/songs/search` | 必須 | ローカル曲と外部Providerの検索 |
| POST | `/songs/import` | 必須 | 外部検索結果を正規化してカタログへ取り込み |
| GET | `/songs/{song}/lyrics` | 必須 | 登録歌詞または歌詞Providerの歌詞取得 |
| GET | `/songs/youtube/search` | 必須 | 曲に紐づくYouTube MV候補検索 |
| GET | `/my-songs` | 必須 | 自分のお気に入り一覧 |
| POST | `/my-songs` | 必須 | 曲をお気に入りへ追加 |
| PUT | `/my-songs/{mySong}` | 必須・Policy | メモ、個人BPM、タグを更新 |
| DELETE | `/my-songs/{mySong}` | 必須・Policy | お気に入りから削除 |
| GET | `/my-songs/export` | 必須 | UTF-8 BOM付きCSVをダウンロード |
| GET | `/tags` | 必須 | タグ一覧 |
| POST | `/tags` | 必須 | タグを作成または同名タグを再利用 |

`/songs/youtube/search`や`/songs/{song}`のように動的パラメータと衝突するルートは、固定パスを先に定義し、曲IDは数値に制限する。

### 6.4 プレイリスト・共有API

| Method | URI | 認証・権限 | 用途 |
| --- | --- | --- | --- |
| GET | `/playlists` | 必須・本人 | 自分のプレイリスト一覧 |
| POST | `/playlists` | 必須 | プレイリスト作成 |
| GET | `/playlists/{playlist}` | 必須・本人 | プレイリスト詳細 |
| PUT | `/playlists/{playlist}` | 必須・Policy | 名前、説明、公開状態を更新 |
| DELETE | `/playlists/{playlist}` | 必須・Policy | プレイリスト削除 |
| POST | `/playlists/{playlist}/songs` | 必須・Policy | 曲を指定位置へ追加 |
| PUT | `/playlists/{playlist}/songs/{playlistSong}` | 必須・Policy | 曲順・メモを更新 |
| DELETE | `/playlists/{playlist}/songs/{playlistSong}` | 必須・Policy | プレイリストから曲を削除 |
| POST | `/playlists/{playlist}/share` | 必須・Policy | 共有リンク発行・再発行 |
| DELETE | `/playlists/{playlist}/share` | 必須・Policy | 共有リンク無効化 |
| GET | `/shared/playlists/{token}` | 必須 | 有効な共有プレイリストを閲覧 |
| POST | `/shared/playlists/{token}/copy` | 必須 | 自分の非公開プレイリストへコピー |

共有プレイリストは読み取り専用であり、共有元の編集・削除APIは所有者以外から実行できない。共有閲覧・コピーも現在はログイン済みユーザーに限定する。

### 6.5 管理者API

| Method | URI | 権限 | 用途 |
| --- | --- | --- | --- |
| GET | `/songs/spotify` | admin | 既存管理画面向けSpotify検索 |
| POST | `/songs` | admin | 曲マスタ登録 |
| PUT | `/songs/{song}` | admin | 曲マスタ編集 |
| POST | `/songs/{song}/bpm/refresh` | admin | BPM再取得 |
| DELETE | `/songs/{song}` | admin | 曲マスタ削除 |
| GET | `/admin/check` | admin | 管理者権限確認 |

### 6.6 CSV

CSVは`MySongsExport`が本人のレコードだけを取得し、次の順で出力する。

```text
曲名, アーティスト, BPM, メモ, タグ, 追加日
```

Excelでの文字化けを防ぐため、ストリームの先頭にUTF-8 BOMを付ける。CSV出力のために楽曲データを他ユーザーの範囲まで取得しない。

---

## 7. 外部サービス連携

### 7.1 Providerの差し替え

外部サービスのレスポンス形式をControllerやVueに直接漏らさない。`AppServiceProvider`でContractと実装をbindする。

| Contract | 現行実装 | 責務 |
| --- | --- | --- |
| `MusicSearchProvider` | `ItunesMusicProvider` | 曲候補の検索、Providerキーから正規曲の解決 |
| `LyricsProvider` | `AuthorizedLyricsProvider` | 許諾済み歌詞APIからの取得 |
| `VideoSearchProvider` | `YoutubeVideoProvider` | 埋め込み可能なMV候補の検索 |

Providerが返す曲情報は、`provider`、`provider_key`、`title`、`artist`、`album`、`artwork_url`、`duration_ms`、`playback_url`、`bpm`などの共通形式へ正規化する。

### 7.2 曲検索・カタログ取り込み

`MusicCatalogService`は次の順で処理する。

1. `songs`をタイトル・アーティスト・アルバムから検索する
2. ひらがな入力時はカタカナ表記も検索する
3. 外部Providerを検索し、Providerキーで重複を除く
4. ローカル曲と同じ`provider:provider_key`の候補を除外する
5. ユーザーが追加したときだけProviderの`resolve`で正規データを取得する
6. `playback_provider`と`playback_key`を使って既存曲を再利用し、なければ新規作成する

外部Providerが停止しても、ローカル曲の検索と既存曲の利用は継続できる。曲の取り込みはBPM取得に失敗しても中止せず、`bpm = null`で保存する。

### 7.3 BPM解決

`BpmResolver`は次の順で候補を確認する。

```text
Deezer
  ↓ 未取得・障害
GetSongBPM
  ↓ 未取得・障害
Spotify
  ↓ 未取得・障害
YouTubeのタイトル・説明に明記されたBPM/テンポ
  ↓ 未取得
null（未登録のまま保存）
```

曲名・アーティストに加え、取得できる場合はアルバム名と演奏時間も照合し、Live・Remix・Coverなど別バージョンの混入を抑える。BPMの自動取得は曲登録を止める必須処理ではない。

### 7.4 歌詞

- 管理者が登録した`songs.lyrics`を最優先する
- 未登録の場合だけ`LyricsProvider`へ曲名・アーティストを渡す
- LRC形式を受け取った場合は時刻タグを除去して表示用テキストにする
- 取得した歌詞は曲マスタへ保存し、次回以降は再利用する
- 利用許諾のない歌詞サイトのスクレイピングや無許可転載は行わない

### 7.5 YouTube MV

MV再生は、音声ファイルを取得するのではなく、YouTube公式IFrameを画面へ埋め込む。

1. 曲詳細でユーザーが「再生する」を押す
2. 既存の`playback_provider = youtube`と`playback_key`があれば利用する
3. 未登録なら曲IDをAPIへ渡し、API側がDBの曲名・アーティストから検索語を生成する
4. YouTube Data APIで音楽カテゴリ、埋め込み可、配信可の候補を検索する
5. `videos` APIで公開・処理済み・埋め込み可否を再検証する
6. 公式MV・Official Videoを優先し、ライブ、カバー、カラオケ、解説などを減点する
7. 候補の動画IDだけをフロントへ返し、動画データは保存しない

候補のBPMメタデータがあればMV表示時のリズムに利用し、なければ曲マスタBPMを利用する。MV候補が見つからない場合や再生不能の場合も、BPM確認機能は継続して利用できる。

### 7.6 外部サービス障害時

- 各HTTP通信にはタイムアウトを設定する
- 曲検索では外部結果がなくてもローカル結果を返す
- BPM未取得は登録失敗ではなく`null`で扱う
- YouTube APIキー未設定・通信失敗は、画面に再試行可能なエラーを表示する
- APIキーなどの認証情報は`.env`で管理し、フロントへ公開しない

---

## 8. フロントエンド基本設計

### 8.1 画面とURL

| URL | View | 認証 | 役割 |
| --- | --- | --- | --- |
| `/login` | `LoginView` | 不要 | ログイン |
| `/` | `MyListView` | 必須 | お気に入り一覧、メモ・タグ・個人BPM |
| `/songs` | `SongSearchView` | 必須 | 曲検索、カタログ取り込み、お気に入り・プレイリスト追加 |
| `/songs/:id` | `SongDetailView` | 必須 | リズム確認、MV、歌詞、曲情報 |
| `/playlists` | `PlaylistsView` | 必須 | プレイリスト一覧・作成 |
| `/playlists/:id` | `PlaylistDetailView` | 必須・所有者 | 曲一覧、管理パネル、共有、連続再生 |
| `/shared/playlists/:token` | `SharedPlaylistView` | 必須 | 共有プレイリスト閲覧・コピー |
| `/admin/songs` | `AdminSongsView` | 必須・admin | 曲マスタCRUD、BPM再取得、曲詳細への導線 |

### 8.2 画面責務

```text
お気に入り / 曲検索
  └─ 曲カードを選択
      └─ 曲詳細
          ├─ リズムを確認する
          ├─ MVを再生する
          ├─ 歌詞を表示する
          └─ 曲の情報

プレイリスト
  ├─ プレイリスト一覧
  └─ プレイリスト詳細
      ├─ 通常表示：曲タイトル一覧
      └─ 管理パネル：曲追加、並び替え、共有、削除
```

一覧画面には音声プレイヤーやリズムプレイヤーを置かず、再生責務を曲詳細へ集約する。プレイリスト詳細では曲を選択して曲詳細へ移動でき、必要な場合だけ同画面の「連続再生を開始」を利用する。

### 8.3 Piniaストア

| Store | 保持する状態 |
| --- | --- |
| `auth` | token、ユーザー、ログイン状態 |
| `songs` | 公開曲カタログ一覧、取得状態、エラー |
| `mySongs` | 自分のお気に入り一覧、取得状態、エラー |
| `playlists` | プレイリスト一覧、取得状態、エラー |
| `rhythmPlayback` | BPM、現在の拍、再生中の曲、タイマー |
| `youtubePlayback` | MVの曲、動画ID、候補、表示状態、再生エラー |
| `app` | アプリ共通の表示設定 |

API通信のURLやAxios設定をViewへ直接書かず、`src/api/`へ分離する。ストアはAPI呼出し後の状態更新と、複数コンポーネントで共有する状態の管理を担当する。

### 8.4 リズム確認

曲のBPMを`bpm`として、1拍の間隔を次式で計算する。

```text
拍間隔（ms） = 60000 / BPM
```

4拍目の次は1拍目へ戻り、1拍目を強拍として視覚的に強調する。iOSでは一般的なバイブレーションAPIが利用できないため、円の点滅・拡大で表現する。

曲マスタBPMがない場合は、曲詳細でタップテンポを使える。未保存の値は曲ID単位でlocalStorageへ保存し、お気に入り登録済みなら`my_songs.bpm`へ登録できる。個人BPMは曲マスタを変更せず、ユーザー固有の補助値として扱う。

`setInterval`は`rhythmPlayback`ストアで一元管理し、停止時・ログアウト時・曲詳細を離れた時に必ず解除する。MV起点のリズムだけはMVドックの状態と連動して保持する。

### 8.5 MV再生と画面遷移

- `YoutubeMvPlaybackDock`を`App.vue`からマウントし、個別曲MV用のYouTube iframeをアプリ全体で1つだけ管理する
- 曲詳細では`#song-detail-mv-host`へTeleportし、他画面では`#youtube-mv-mini-player-host`へTeleportする
- ルート切り替え時も同じiframeを移動するため、再生位置をリセットしない
- MV再生中に曲詳細へ戻ると、曲詳細内で標準YouTubeコントロールを再表示する
- アプリ独自の再生・一時停止・シーク・音量UIは作らず、動画内の標準コントロールを利用する
- ログアウト時はYouTubeストアをリセットし、MVを破棄する

ルートにはスライドトランジションを適用し、`KeepAlive`でログイン以外のViewを保持する。リズム再生は詳細画面内の練習機能として扱い、MVのような全画面共通のリズムドックは表示しない。

### 8.6 プレイリスト連続再生

`PlaylistQueuePlayer`は、プレイリスト曲をposition順にYouTube公式プレイヤーへ渡す。曲終了時は次の曲を検索・読み込みし、最後の曲まで進める。再生操作はYouTube標準コントロールを利用し、キュー機能とMVの曲順管理をプレイリスト詳細に限定する。

### 8.7 PWA・テーマ

- Viteでビルドし、`vite-plugin-pwa`でmanifestとService Workerを生成する
- iPhoneのホーム画面への追加を想定する
- 基本テーマは暗色とし、タッチ操作可能なボタンサイズと十分なコントラストを確保する
- アニメーションは`prefers-reduced-motion`を尊重する
- APIのベースURLは`VITE_API_URL`で環境ごとに切り替える

---

## 9. 横断的な設計方針

### 9.1 入力検証

Controller内で個別に検証せず、FormRequestへ分離する。主な制約は次のとおり。

- 曲名・アーティストは必須、最大255文字
- 曲マスタBPMは1〜300、個人BPMは40〜300
- 検索語は1〜100文字、検索対象は`all`・`title`・`artist`・`album`
- お気に入り・プレイリスト内メモは最大1000文字
- 曲ID・タグID・Providerキーは存在性・許可値を検証する
- 外部Providerは現行実装の`itunes`だけを取り込み対象とする

### 9.2 トランザクションと整合性

- 外部曲の取り込みは、重複確認と保存を1トランザクションで行う
- プレイリストのposition変更は対象プレイリストの曲をロックして計算する
- 共有リンク発行時は、既存トークンを新しいハッシュへ置き換える
- DBのユニーク制約を最終的な重複防止策とし、画面側の二重クリックだけに依存しない

### 9.3 パフォーマンス

- 曲カタログ一覧は20件単位でページングする
- 外部曲検索は最大20件程度に制限する
- プレイリスト一覧では曲数を`withCount`で取得する
- 詳細表示では必要な関連を`with`で先読みし、N+1クエリを避ける
- 外部通信にはタイムアウトを設定し、外部障害で主要なローカル機能を停止させない

### 9.4 セキュリティ・権利

- `.env`のAPIキーをGitへ含めない
- APIはBearerトークンを検証し、対象データごとにPolicyを適用する
- 共有URLは平文トークンをDBに保存しない
- YouTube動画・音源ファイルをダウンロード、抽出、保存、再配信しない
- 歌詞は管理者登録または利用許諾済みProviderの結果だけを扱う
- YouTube検索語は、`song_id`が指定された場合にサーバー側の曲情報から再構成する

### 9.5 エラー処理

| 状況 | APIの扱い | UIの扱い |
| --- | --- | --- |
| 入力不正 | 422 | 入力欄のエラー表示 |
| 未ログイン | 401 | ログイン画面へ遷移 |
| 権限不足 | 403 | 操作不可・エラーメッセージ |
| 対象なし | 404 | 存在しない状態として表示 |
| 外部API障害 | 502/503または代替結果 | 再試行導線、ローカル結果継続 |
| BPM未取得 | 正常保存、`bpm: null` | 「BPM未登録」、タップ計測を案内 |
| MV再生不可 | 候補切替・再検索 | 別のMVを探す導線 |

---

## 10. テストと運用確認

### 10.1 バックエンド

Feature Testで以下を確認する。

- 登録・ログイン・ログアウトとBearer認証
- admin専用の曲カタログ操作
- お気に入りの本人確認、重複防止、個人BPM、タグ
- プレイリストの所有者確認、曲順変更、共有・無効化・コピー
- Provider障害時のフォールバックとBPM未取得保存
- 歌詞の登録値優先と外部Provider取得
- YouTube候補の曲名照合、埋め込み可否、候補切替
- UTF-8 BOM付きCSVの内容とユーザー分離

### 10.2 フロントエンド

Playwrightで以下の主要導線を確認する。

- ログイン後のお気に入り表示
- 曲検索、外部候補の取り込み、お気に入り・プレイリスト追加
- 曲詳細のBPM表示、タップ計測、MV再生・候補切替
- プレイリストの作成、管理パネル、並び替え、連続再生
- 共有プレイリストの閲覧・コピー
- 管理者画面のアクセス制御
- 画面遷移後もMV iframeと再生状態が保持されること

### 10.3 完了時の設計書更新

機能追加やIssue完了時は、実装だけでなく次の資料も確認する。

- `AGENTS.md`の現在フェーズとフェーズ一覧
- `TICKETS.md`のIssueステータス
- 本書のシステム構成・データ・API・画面責務
- 必要に応じたIssue別設計書と学習メモ

---

## 11. スコープ外・将来拡張

現行MVPでは次を対象外とする。

- メロディや音域の解析
- サビ区間だけのリズム表示
- 3拍子、複雑な拍子、テンポ変化への対応
- 音源ファイルのアップロード・保存・配信
- 共有プレイリストの共同編集
- AIによる曲推薦

将来、複数Providerを同一曲へ紐付ける必要が生じた場合は、`songs`へProvider列を追加し続けるのではなく、`song_sources`のような専用テーブルへ分離する。
