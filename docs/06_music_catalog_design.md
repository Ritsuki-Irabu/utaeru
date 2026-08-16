# Issue #17 ユーザー向け曲検索・カタログ取り込み設計書

**対応Issue：** #17  
**ステータス：** MVP実装済み（現行ProviderはiTunes Search API）

## 1. 目的

ウタエルを「管理者が登録した曲を選ぶアプリ」から、一般的な音楽アプリと同じようにユーザーが曲を検索・お気に入り登録・プレイリスト追加できるアプリへ再構成する。

ウタエル固有の価値は、曲を探した後にBPM表示・無音リズム確認・YouTube公式MVを利用できる点とする。

## 2. ユーザー体験

```text
曲名・アーティストを検索
  ├─ ローカルカタログから検索
  └─ 見つからなければ外部Providerから検索
       ↓
検索結果を確認（曲名・アーティスト・アルバム・ジャケット・再生元）
       ↓
お気に入りへ追加 / プレイリストへ追加
       ↓
BPMリズム確認 / YouTube MV再生
```

外部結果はユーザーが追加操作を行った時点でカタログへ取り込む。検索しただけではDBへ保存しない。

## 3. 権限の再構成

| 操作 | user | admin |
| --- | --- | --- |
| 曲を検索 | 可能 | 可能 |
| 検索結果をカタログへ取り込む | 可能 | 可能 |
| お気に入りへ追加 | 可能 | 可能 |
| プレイリストへ追加 | 可能 | 可能 |
| 曲情報の修正・非公開化 | 不可 | 可能 |
| 重複・不正データの整理 | 不可 | 可能 |

管理者は登録の門番ではなく、品質管理とモデレーションを担当する。

## 4. Provider設計

外部サービスごとの差異をControllerやVueへ漏らさないため、Providerインターフェースを設ける。

```text
MusicSearchProvider
├── search(query): SearchResult[]
└── resolve(providerKey): CanonicalSong
```

現行ProviderはiTunes Search APIのメタデータ検索と公式ページURLを利用する。Providerは差し替え可能にし、Spotifyのように認証・利用条件が必要なサービスをアプリ本体へ直接結合しない。

Providerが返す共通項目：

- provider
- provider_key
- title
- artist
- album
- artwork_url
- duration_ms
- playback_url
- bpm（取得できない場合はnull）

プレビュー音源を自サーバーへ保存・中継せず、再生は公式ページまたは公式埋め込みに限定する。

## 5. DB方針

既存の`my_songs`と`playlists`は維持し、`songs`を共通カタログとして拡張する。

追加項目：

- `album` nullable
- `artwork_url` nullable
- `duration_ms` nullable
- `bpm` nullable化
- `playback_provider`にProvider名を保存
- `playback_key`に外部曲IDを保存

`playback_provider + playback_key`を外部曲の識別子として使い、同一曲の重複取り込みを防ぐ。将来的に複数Providerを同一曲へ紐付ける必要が生じた場合は`song_sources`へ分離する。

## 6. API

| Method | URI | 権限 | 用途 |
| --- | --- | --- | --- |
| GET | `/api/songs/search?q=...` | authenticated | ローカル・外部Provider検索 |
| POST | `/api/songs/import` | authenticated | 検索結果をカタログへ取り込み、曲を返す |
| POST | `/api/my-songs` | authenticated | 取り込み済み曲をお気に入りへ追加 |
| POST | `/api/playlists/{playlist}/songs` | owner | 取り込み済み曲をプレイリストへ追加 |

`/api/songs`の管理者CRUDは互換性のため残すが、通常ユーザーの追加導線には使用しない。

## 7. BPM未取得時の扱い

- 検索・お気に入り・プレイリスト追加はBPM未取得でも可能
- リズム画面では「BPM未登録」と表示
- 管理者が後からBPMを補正可能
- 管理者は曲マスタ画面の「BPMを再取得」から、改善された候補照合で既存曲を再計算できる
- 取込時はDeezerの曲検索とトラック詳細からBPMを自動補完する。候補は曲名・アーティスト名に加えてアルバム名・演奏時間を照合し、Live・Remix・Coverなどの別バージョンを避ける。外部API障害時や一致度が不足する場合だけ未登録として保存する

## 8. セキュリティ・権利

- 外部ProviderのキーとURLはサーバー側で正規化する
- 任意URLを音源として取得・保存しない
- YouTube公式埋め込みだけを利用する
- Provider検索はレート制限と短期キャッシュを設ける
- 外部サービスの利用規約・帰属表示・再生条件に従う

## 9. 実装順序

1. `songs`の共通カタログ項目追加とBPM nullable化
2. `MusicSearchProvider`と最初のProvider実装
3. 検索・取り込みAPI
4. 一般ユーザー向け検索画面の再構成
5. お気に入り・プレイリスト追加導線
6. Provider未設定・BPM未取得・検索失敗のUX
7. Feature Test、Playwright、Providerモックテスト

## 10. 完了条件

- 一般ユーザーが管理者操作なしで曲を検索できる
- 検索結果からお気に入り・プレイリストへ追加できる
- 外部曲が重複なく共通カタログへ取り込まれる
- BPM未取得でも曲の保存・再生元表示ができる
- 管理者は品質管理を行える
- 外部Providerを将来差し替えられる
