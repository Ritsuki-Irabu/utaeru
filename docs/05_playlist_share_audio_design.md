# Issue #16 プレイリスト・共有・音声再生設計書

**対応Issue：** #16  
**バージョン：** 1.0  
**作成日：** 2026年7月  
**ステータス：** 実装済み（Issue #16 MVP）

## 1. 設計方針

Issue #16では、既存の「お気に入り（追加した全曲）」に加えて、ユーザーが曲を複数グループに分けるプレイリストを作成し、共有できるようにする。画面上の用語と再生導線は、Issue #18の[UX設計書](07_favorites_playlist_ux_design.md)を正とする。

音声再生は、アプリが音源ファイルを所有・配信する方式ではなく、YouTube公式IFrameを曲詳細・プレイリスト詳細へ埋め込む方式とする。アプリがYouTube等から音声を抽出したり、権利のない音源を保存・再配信したりすることは行わない。

### 1.1 採用する仕様

- `my_songs`は内部名称として残し、画面上はお気に入り（追加した全曲）として扱う。
- `playlists`はユーザーが作成する複数グループとして別管理する。
- プレイリストの曲は公開曲マスタ（`songs`）を参照する。
- プレイリスト所有者は、名前・説明・曲の追加・曲順・メモ・削除を管理できる。
- 共有リンクの受け手は、MVPでは閲覧と自分のプレイリストへのコピーだけできる。
- 共有リンクの受け手に、元プレイリストの編集権限は与えない。
- 音声再生は、YouTube公式IFrameプレイヤーを利用する。Spotifyの再生UIや公式リンクは提供しない。
- 音声再生とBPM視覚リズムは別操作とし、MVPでは同期再生を行わない。
- 再生元が利用できない場合でも、BPM視覚リズムとプレイリスト閲覧は利用できる。

### 1.2 採用しない方式

- YouTube動画から音声だけを抽出する。
- Spotify等の音源ファイルを取得して自サーバーへ保存する。
- 権利条件を確認していない任意の音声URLを再生する。
- 共有先ユーザーが元プレイリストを直接編集する共同編集機能（将来拡張）。

## 2. 要件

### 2.1 プレイリスト要件

| ID | 要件 | 優先度 |
| --- | --- | --- |
| PL-01 | userがプレイリストを作成できる | 必須 |
| PL-02 | userが自分のプレイリスト一覧を取得できる | 必須 |
| PL-03 | userがプレイリスト名・説明を編集できる | 必須 |
| PL-04 | userが公開曲マスタから曲を追加できる | 必須 |
| PL-05 | userが追加した曲の曲順とプレイリスト内メモを編集できる | 必須 |
| PL-06 | userがプレイリストから曲を削除できる | 必須 |
| PL-07 | userが自分のプレイリストを削除できる | 必須 |
| PL-08 | userが共有リンクを発行・再発行・無効化できる | 必須 |
| PL-09 | 共有リンクからプレイリストを閲覧できる | 必須 |
| PL-10 | 共有先userが自分のプレイリストとしてコピーできる | 必須 |
| PL-11 | 他人のプレイリストをIDだけで推測・取得できない | 必須 |
| PL-12 | 同じ曲を同じプレイリストへ重複登録できない | 必須 |

### 2.2 権限要件

| ID | 要件 | 優先度 |
| --- | --- | --- |
| AUTH-PL-01 | プレイリスト所有者だけがプレイリストを編集・削除できる | 必須 |
| AUTH-PL-02 | プレイリスト所有者だけが共有リンクを管理できる | 必須 |
| AUTH-PL-03 | 共有閲覧者は元プレイリストを編集・削除できない | 必須 |
| AUTH-PL-04 | ユーザーは公開曲マスタの曲名・アーティスト・BPMを編集できない | 必須 |
| AUTH-PL-05 | `added_by_user_id`を保存し、将来の共同編集権限に利用できる | 推奨 |

### 2.3 音声再生要件

| ID | 要件 | 優先度 |
| --- | --- | --- |
| AUDIO-01 | 曲詳細でYouTube MV再生操作を表示できる | 必須 |
| AUDIO-02 | 動画ID登録済みの曲はその動画を再生する | 必須 |
| AUDIO-03 | 動画ID未登録の曲は曲名・アーティスト名から候補を取得する | 必須 |
| AUDIO-04 | 再生・一時停止・停止・シーク・音量変更を行える | 必須 |
| AUDIO-05 | 自動再生を行わず、ユーザー操作を起点に再生する | 必須 |
| AUDIO-06 | 再生元がない曲でもBPM視覚リズムを利用できる | 必須 |
| AUDIO-07 | 音声再生とBPM視覚リズムの同期はMVPの対象外とする | 必須 |
| AUDIO-08 | 音源ファイルをアプリへアップロード・再配信しない | 必須 |

### 2.4 非機能要件

| ID | 要件 | 内容 |
| --- | --- | --- |
| N-PL-01 | 認可 | API側で所有者・共有閲覧者を判定する。フロントの非表示だけに依存しない |
| N-PL-02 | 共有リンク安全性 | 連番IDを使わず、推測困難なランダムトークンを使用する |
| N-PL-03 | 共有リンク管理 | 所有者が再発行または無効化できる |
| N-PL-04 | データ整合性 | 曲順はプレイリスト単位で一意に管理し、削除後に再採番する |
| N-PL-05 | 外部依存 | 外部サービス障害時も、曲情報・BPM・プレイリスト編集を継続できる |

## 3. 権限モデル

### 3.1 MVPの権限

| 操作 | 所有者 | 共有閲覧者 | 未ログイン |
| --- | --- | --- | --- |
| 自分のプレイリスト一覧 | 可能 | 不可 | 不可 |
| プレイリスト閲覧 | 可能 | 共有トークン経由で可能 | 不可 |
| 名前・説明の編集 | 可能 | 不可 | 不可 |
| 曲の追加・曲順変更・削除 | 可能 | 不可 | 不可 |
| 共有リンクの発行・無効化 | 可能 | 不可 | 不可 |
| 自分のプレイリストへコピー | 可能 | 可能 | 不可 |
| 公開曲マスタの編集 | 不可（adminを除く） | 不可 | 不可 |

### 3.2 将来の共同編集

共同編集を追加する場合は、`playlist_members`テーブルを追加する。

```text
playlist_members
- playlist_id
- user_id
- role: viewer / editor
```

MVPでは導入せず、共有先は読み取り専用とする。これにより、共有リンクからの意図しない編集や削除を防ぐ。

## 4. データ設計

### 4.1 playlists

| カラム | 型 | 制約 | 説明 |
| --- | --- | --- | --- |
| id | BIGINT | PK | プレイリストID |
| user_id | BIGINT | FK(users.id), NOT NULL | 所有者 |
| name | VARCHAR(100) | NOT NULL | プレイリスト名 |
| description | TEXT | NULL | 説明 |
| visibility | VARCHAR(20) | NOT NULL, default private | `private` / `shared` |
| share_token_hash | CHAR(64) | NULL, UNIQUE | 共有トークンのハッシュ |
| shared_at | TIMESTAMP | NULL | 共有リンク発行日時 |
| revoked_at | TIMESTAMP | NULL | 共有リンク無効化日時 |
| created_at | TIMESTAMP | | 作成日時 |
| updated_at | TIMESTAMP | | 更新日時 |

共有URLに平文トークンを保存せず、サーバーにはSHA-256などのハッシュだけを保存する。再発行時は旧トークンを無効化する。

### 4.2 playlist_songs

| カラム | 型 | 制約 | 説明 |
| --- | --- | --- | --- |
| id | BIGINT | PK | プレイリスト曲ID |
| playlist_id | BIGINT | FK(playlists.id), CASCADE | プレイリスト |
| song_id | BIGINT | FK(songs.id), CASCADE | 公開曲マスタ |
| added_by_user_id | BIGINT | FK(users.id) | 追加したユーザー |
| position | UNSIGNED INT | NOT NULL | 曲順。1始まり |
| memo | TEXT | NULL | プレイリスト内のメモ |
| created_at | TIMESTAMP | | 作成日時 |
| updated_at | TIMESTAMP | | 更新日時 |

`UNIQUE(playlist_id, song_id)`を設定し、同じ曲の二重登録をDBでも防止する。

### 4.3 音声再生元

音源ファイルではなく、YouTube動画IDを内部の再生キャッシュとして保持する。通常の曲登録・編集画面での手入力は行わない。

| カラム | 型 | 制約 | 説明 |
| --- | --- | --- | --- |
| playback_provider | VARCHAR(30) | NULL | `youtube` / NULL |
| playback_key | VARCHAR(255) | NULL | YouTube動画ID |
| playback_url | VARCHAR(500) | NULL | 旧データ互換。再生には使用しない |

任意URLをそのまま再生するのではなく、プロバイダごとに形式を検証する。複数の再生元を持たせる必要が出た場合は、将来`song_playback_sources`テーブルへ分離する。

## 5. API設計

全APIは`auth:sanctum`を前提とし、共有閲覧だけはトークンをURLパラメータとして受け取る。

### 5.1 プレイリストCRUD

| Method | URI | 権限 | 用途 |
| --- | --- | --- | --- |
| GET | `/api/playlists` | user | 自分のプレイリスト一覧 |
| POST | `/api/playlists` | user | プレイリスト作成 |
| GET | `/api/playlists/{playlist}` | owner | 詳細取得 |
| PUT | `/api/playlists/{playlist}` | owner | 名前・説明編集 |
| DELETE | `/api/playlists/{playlist}` | owner | プレイリスト削除 |

### 5.2 プレイリスト曲管理

| Method | URI | 権限 | 用途 |
| --- | --- | --- | --- |
| POST | `/api/playlists/{playlist}/songs` | owner | 曲追加 |
| PUT | `/api/playlists/{playlist}/songs/{playlistSong}` | owner | メモ・曲順編集 |
| DELETE | `/api/playlists/{playlist}/songs/{playlistSong}` | owner | 曲削除 |

`{playlistSong}`は必ず`{playlist}`に属しているか確認し、別プレイリストのIDを指定した場合は404または403を返す。

### 5.3 共有・コピー

| Method | URI | 権限 | 用途 |
| --- | --- | --- | --- |
| POST | `/api/playlists/{playlist}/share` | owner | 共有トークン発行・再発行 |
| DELETE | `/api/playlists/{playlist}/share` | owner | 共有無効化 |
| GET | `/api/shared/playlists/{token}` | authenticated | 共有プレイリスト閲覧 |
| POST | `/api/shared/playlists/{token}/copy` | authenticated | 自分のプレイリストへコピー |

共有トークンが無効・期限切れ・再発行済みの場合は404を返し、トークンの存在を推測できないようにする。

### 5.4 リクエスト例

```json
POST /api/playlists
{
  "name": "飲み会で歌う曲",
  "description": "最初に歌いやすい曲",
  "visibility": "private"
}
```

```json
POST /api/playlists/1/songs
{
  "song_id": 12,
  "position": 1,
  "memo": "イントロ後すぐ歌い出し"
}
```

## 6. バックエンド設計

### 6.1 追加クラス

```text
app/
├── Http/Controllers/
│   ├── PlaylistController.php
│   ├── PlaylistSongController.php
│   └── PlaylistShareController.php
├── Http/Requests/
│   ├── StorePlaylistRequest.php
│   ├── UpdatePlaylistRequest.php
│   ├── StorePlaylistSongRequest.php
│   └── UpdatePlaylistSongRequest.php
├── Http/Resources/
│   ├── PlaylistResource.php
│   └── PlaylistSongResource.php
├── Models/
│   ├── Playlist.php
│   └── PlaylistSong.php
├── Policies/
│   ├── PlaylistPolicy.php
│   └── PlaylistSongPolicy.php
└── Services/
    ├── PlaylistService.php
    └── ShareTokenService.php
```

Controllerは入力受付・認可・Resource返却に限定し、曲順更新・コピー・共有トークン発行はServiceへ分離する。

### 6.2 認可方針

```php
$this->authorize('update', $playlist);
$this->authorize('delete', $playlist);
$this->authorize('manageSongs', $playlist);
```

`PlaylistPolicy`では`$user->id === $playlist->user_id`を基準に判定する。共有閲覧は、トークン検証済みの専用処理でのみ許可し、通常の`show`へ所有者以外を通さない。

### 6.3 曲順更新

曲の追加・削除・並び替えはトランザクションで処理する。`position`を詰め直し、同時更新時の重複を防ぐため必要に応じて`lockForUpdate()`を利用する。

## 7. フロントエンド設計

### 7.1 画面

| 画面 | パス | 内容 |
| --- | --- | --- |
| プレイリスト一覧 | `/playlists` | 自分のプレイリスト、作成ボタン |
| プレイリスト編集 | `/playlists/:id` | 曲追加・削除・並び替え・共有 |
| 共有プレイリスト | `/shared/playlists/:token` | 閲覧・コピー |

### 7.2 追加コンポーネント

```text
frontend/src/
├── api/playlists.js
├── stores/playlists.js
├── components/
│   ├── PlaylistForm.vue
│   ├── PlaylistSongList.vue
│   ├── SharePlaylistDialog.vue
│   └── YoutubeMvPlayer.vue
└── views/
    ├── PlaylistsView.vue
    ├── PlaylistDetailView.vue
    └── SharedPlaylistView.vue
```

共有閲覧画面では編集ボタンを描画しない。ただし、API側の認可が本体であり、表示制御だけで権限を実装しない。

### 7.3 音声プレイヤー

`YoutubeMvPlayer.vue`は、曲詳細の「再生する」操作を起点に動画IDを解決し、YouTube公式IFrameを表示する。埋め込みには`autoplay=0`を指定し、表示直後に再生を開始しない。プレイリスト詳細の`PlaylistQueuePlayer.vue`は明示的な「連続再生を開始」操作を起点に同じ解決処理を使い、曲を上から順に再生する。

曲詳細・プレイリスト詳細では、公式YouTube IFrame内の標準コントロールを利用する。アプリ独自の音楽プレーヤーや再生・一時停止・停止・シーク・音量ボタンは表示しない。プレイリストの連続再生は開始操作後に曲終了時の次曲切替だけを担当し、動画を音声だけに変換したり、YouTube IFrameを不可視にしたりはしない。

IFrame APIの読み込みに失敗した場合も、標準YouTubeプレイヤーを表示し、動画内の再生・停止操作を利用できる状態を残す。登録済みの動画IDを使う場合も、曲詳細を開いた時点でIFrame API操作を接続する。

曲詳細のMVは自動再生しない。埋め込み表示後は動画内のYouTube標準コントロールから再生を開始する。プレイリストの連続再生だけは、ユーザーが「連続再生を開始」を押した後に限り次曲へ進める。`RhythmPlayer.vue`はBPM表示と視覚リズムを担当し、MVプレイヤーと相互にタイマーを操作しない。

画面表示は`652ms / 拍`ではなく、曲マスタの値を使って`BPM 92`のように表示する。ms値は内部のタイマー計算だけで使用する。

## 8. 共有フロー

```text
所有者
  │ 共有ボタン
  ▼
POST /playlists/{id}/share
  │ 平文トークンはレスポンスで一度だけ返す
  ▼
共有URLをコピー
  │
  ▼
受け手がログイン後にURLを開く
  │
  ├─ GET /shared/playlists/{token} → 閲覧
  └─ POST .../copy → 自分のプレイリストへ複製
```

共有コピーでは、元プレイリストを参照し続けず、曲・曲順・メモを新しいプレイリストへ複製する。以後の編集はコピー側へ影響しない。

## 9. セキュリティ・権利上の注意

- 共有トークンは`random_bytes`等で生成し、DBにはハッシュを保存する。
- 共有URLの再発行時は旧URLを即時無効化する。
- エラーメッセージに所有者のメールアドレスや内部IDを含めない。
- `playback_key`はYouTube動画IDの形式だけを保存する。
- 外部URLのサーバー側取得は行わず、SSRFの入口を作らない。
- アプリが商用楽曲の音源ファイルを保存・配信しない。
- YouTube公式プレイヤーの表示・再生条件とYouTube Developer Policiesに従う。
- 利用許諾のない音源をアップロードさせる機能は実装しない。

### 9.1 参照資料

- [文化庁：著作権の基本と海賊版](https://www.bunka.go.jp/seisaku/chosakuken/kaizoku/kihon.html)
- [文化庁：著作権に関する契約マニュアル](https://www.bunka.go.jp/chosakuken/keiyaku_manual/1_1_2.html)
- [Spotify Web Playback SDK](https://developer.spotify.com/documentation/web-playback-sdk)
- [Spotify Web Playback SDK Reference](https://developer.spotify.com/documentation/web-playback-sdk/reference)
- [YouTube Developer Policies](https://developers.google.com/youtube/terms/developer-policies-guide)

## 10. テスト設計

### 10.1 Laravel Feature Test

- 所有者がプレイリストを作成・編集・削除できる
- 他ユーザーが所有者用APIを実行すると403になる
- 曲を追加・並び替え・削除できる
- 同じ曲を二重登録すると422になる
- 共有トークンを発行・再発行・無効化できる
- 無効なトークンで404になる
- 共有閲覧者が閲覧・コピーできる
- 共有閲覧者が編集・削除すると403になる
- コピー後に元プレイリストを変更してもコピー側は変わらない

### 10.2 Playwright E2E

- プレイリスト作成から曲追加まで
- プレイリスト名・曲メモの編集
- 曲削除と曲順変更
- 共有リンク発行と共有画面表示
- 共有画面で編集UIが表示されない
- コピー後に自分の一覧へ追加される
- 再生元なし、未認証、再生不可の各状態表示

外部音楽サービスへの実接続はE2Eの必須条件にせず、Provider AdapterをモックしてUI状態をテストする。実サービス確認は手動のスモークテストで行う。

## 11. 実装順序

1. `playlists`・`playlist_songs`のマイグレーションとモデル
2. Playlist Policy、FormRequest、Resource
3. プレイリストCRUD API
4. 曲追加・編集・削除・並び替えAPI
5. 共有トークン発行・閲覧・コピーAPI
6. Vueの一覧・詳細・共有画面
7. 公式埋め込みプレイヤー・再生元リンクUI
8. BPM表示を`BPM {value}`へ統一
9. Feature Test・Playwright E2E・ビルド確認

## 12. 完了条件

- userが自分のプレイリストを作成・編集・削除できる。
- userがプレイリストへ曲を追加・編集・削除できる。
- 所有者以外は元プレイリストを編集・削除できない。
- 共有リンクから閲覧・コピーができる。
- 音源ファイルを無断で保存・配信しない。
- 再生元がなくても、曲情報とBPM視覚リズムが利用できる。
- Laravel Feature TestとPlaywright E2Eが成功する。
