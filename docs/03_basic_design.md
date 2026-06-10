# 基本設計書

**バージョン：** 1.0
**作成日：** 2026年6月
**ステータス：** MVP確定

---

## 1. システム構成

```
┌─────────────────────────────────────┐
│   フロントエンド（Vue.js PWA）         │
│   ・iPhoneのホーム画面に追加          │
│   ・視覚フィードバックでリズム表現     │
│   ・Axios で Laravel API を呼び出し   │
└─────────────────────────────────────┘
              ↓ HTTP（JSON）
┌─────────────────────────────────────┐
│        Laravel 11 API               │
│  ┌────────────────────────────────┐ │
│  │  Sanctum（認証）               │ │
│  │  Spatie Permission（権限管理） │ │
│  └────────────────────────────────┘ │
└─────────────────────────────────────┘
        ↓                    ↓
      MySQL              Spotify API
   （データ永続化）       （BPM取得）
```

---

## 2. ディレクトリ構成

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php
│   │   ├── SongController.php
│   │   ├── MySongController.php
│   │   └── TagController.php
│   ├── Requests/
│   │   ├── Auth/
│   │   │   ├── RegisterRequest.php
│   │   │   └── LoginRequest.php
│   │   ├── StoreSongRequest.php
│   │   └── StoreMySongRequest.php
│   └── Resources/
│       ├── SongResource.php
│       ├── MySongResource.php
│       └── TagResource.php
├── Models/
│   ├── User.php
│   ├── Song.php
│   ├── MySong.php
│   └── Tag.php
├── Services/
│   └── SpotifyService.php
└── Exports/
    └── MySongsExport.php
```

---

## 3. DB設計

### 3.1 ER図

```
users ──< my_songs >── songs
              │
         my_song_tag
              │
            tags
```

### 3.2 テーブル定義

#### users

| カラム名 | 型 | 制約 | 説明 |
| --- | --- | --- | --- |
| id | BIGINT | PK, AUTO_INCREMENT | ユーザーID |
| name | VARCHAR(255) | NOT NULL | ユーザー名 |
| email | VARCHAR(255) | NOT NULL, UNIQUE | メールアドレス |
| password | VARCHAR(255) | NOT NULL | ハッシュ化パスワード |
| created_at | TIMESTAMP | | 作成日時 |
| updated_at | TIMESTAMP | | 更新日時 |

#### songs（公開曲マスタ）

| カラム名 | 型 | 制約 | 説明 |
| --- | --- | --- | --- |
| id | BIGINT | PK, AUTO_INCREMENT | 曲ID |
| title | VARCHAR(255) | NOT NULL | 曲名 |
| artist | VARCHAR(255) | NOT NULL | アーティスト名 |
| bpm | INT | NOT NULL | BPM（1〜300） |
| spotify_id | VARCHAR(100) | NULL | Spotify曲ID |
| created_at | TIMESTAMP | | 作成日時 |
| updated_at | TIMESTAMP | | 更新日時 |

#### tags

| カラム名 | 型 | 制約 | 説明 |
| --- | --- | --- | --- |
| id | BIGINT | PK, AUTO_INCREMENT | タグID |
| name | VARCHAR(100) | NOT NULL, UNIQUE | タグ名 |
| created_at | TIMESTAMP | | 作成日時 |
| updated_at | TIMESTAMP | | 更新日時 |

#### my_songs（マイリスト）

| カラム名 | 型 | 制約 | 説明 |
| --- | --- | --- | --- |
| id | BIGINT | PK, AUTO_INCREMENT | マイリストID |
| user_id | BIGINT | FK(users.id) | ユーザーID |
| song_id | BIGINT | FK(songs.id) | 曲ID |
| memo | TEXT | NULL | 自由メモ |
| created_at | TIMESTAMP | | 作成日時 |
| updated_at | TIMESTAMP | | 更新日時 |

#### my_song_tag（中間テーブル）

| カラム名 | 型 | 制約 | 説明 |
| --- | --- | --- | --- |
| my_song_id | BIGINT | FK(my_songs.id) | マイリストID |
| tag_id | BIGINT | FK(tags.id) | タグID |

---

## 4. 認証・権限設計

### 4.1 認証フロー（Sanctum）

```
1. POST /api/auth/login にメール・パスワードを送信
2. 認証成功 → Sanctumトークンを発行・返却
3. 以降のリクエストは Authorization: Bearer {token} で送信
4. POST /api/auth/logout でトークンを削除
```

### 4.2 ロール設計（Spatie Permission）

| ロール | 権限 |
| --- | --- |
| admin | 公開曲マスタのCRUD、タグ登録、全機能アクセス |
| user | マイリストのCRUD、CSV出力、公開曲・タグの閲覧 |

### 4.3 権限チェックの実装方針

```php
// ミドルウェアによるロールチェック
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('/songs', [SongController::class, 'store']);
});

// Policyによる本人確認（マイリスト操作）
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/my-songs/{mySong}', [MySongController::class, 'update']);
});
```

---

## 5. 外部API連携設計（Spotify）

### 5.1 利用するAPI

- Spotify Web API：Search API + Audio Features API
- 認証方式：Client Credentials Flow

### 5.2 BPM取得フロー

```
1. admin が曲名・アーティスト名を入力
2. GET /api/songs/spotify?q={keyword} を呼び出し
3. SpotifyService が Search API で曲を検索
4. 取得した spotify_id で Audio Features API を呼び出し
5. tempo（BPM）を取得してレスポンス
6. admin が確認後、曲登録APIで保存
```

### 5.3 SpotifyService の責務

```php
class SpotifyService
{
    public function getAccessToken(): string {}
    public function searchSong(string $query): array {}
    public function getAudioFeatures(string $spotifyId): array {}
}
```

---

## 6. CSV出力設計（Laravel Excel）

### 6.1 出力クラス

```php
class MySongsExport implements FromCollection, WithHeadings
{
    public function collection(): Collection {}
    public function headings(): array {}
}
```

### 6.2 出力カラム順

```
曲名 / アーティスト / BPM / タグ / メモ / 登録日
```

---

## 7. Eloquentリレーション設計

```php
// User.php
public function mySongs(): HasMany {}      // user → my_songs

// MySong.php
public function user(): BelongsTo {}       // my_songs → users
public function song(): BelongsTo {}       // my_songs → songs
public function tags(): BelongsToMany {}   // my_songs ↔ tags（中間テーブル経由）

// Song.php
public function mySongs(): HasMany {}      // songs → my_songs

// Tag.php
public function mySongs(): BelongsToMany {} // tags ↔ my_songs（中間テーブル経由）
```

---

## 8. フロントエンド設計（Vue.js PWA）

### 8.1 ディレクトリ構成

```
src/
├── api/
│   ├── auth.js
│   ├── songs.js
│   └── mySongs.js
├── components/
│   ├── RhythmPlayer.vue
│   ├── SongCard.vue
│   └── TagBadge.vue
├── pages/
│   ├── Login.vue
│   ├── MySongs.vue
│   ├── SongSearch.vue
│   └── Admin/
│       └── Songs.vue
├── stores/
│   ├── auth.js
│   └── mySongs.js
└── router/
    └── index.js
```

### 8.2 画面構成

| 画面 | パス | 権限 | 説明 |
| --- | --- | --- | --- |
| ログイン | /login | 全員 | メール・パスワードでログイン |
| マイリスト | / | user | 登録曲一覧・リズム再生 |
| 曲検索・追加 | /songs | user | 公開曲を検索してマイリストに追加 |
| 曲マスタ管理 | /admin/songs | admin | 公開曲のCRUD・BPM取得 |

### 8.3 リズム再生（核心機能）

iOSではバイブレーションAPIが非対応のため、視覚フィードバックでリズムを表現する。

```
【4拍子の視覚パターン】
1拍目：大きな円が光る（強拍）
2〜4拍目：小さな円が光る（弱拍）

振動間隔(ms) = 60000 / BPM
例：120BPM → 500ms間隔
```

---

## 9. 実装順序

| フェーズ | ステップ | 内容 |
| --- | --- | --- |
| バックエンド | 1 | 環境構築（Laravel + MySQL + Sanctum + Spatie） |
| | 2 | DB設計・マイグレーション |
| | 3 | 認証API |
| | 4 | ロール設定 |
| | 5 | 公開曲マスタCRUD |
| | 6 | Spotify API連携 |
| | 7 | マイリストCRUD + タグ付け |
| | 8 | CSV出力 |
| フロントエンド | 9 | Vue.js + PWA環境構築 |
| | 10 | ログイン画面・Axios設定 |
| | 11 | マイリスト画面 |
| | 12 | リズム再生コンポーネント |
| | 13 | 曲検索・追加画面 |
| | 14 | 管理者画面 |
