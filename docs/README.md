# ウタエル ドキュメント一覧

ウタエルの現行実装、設計資料、開発振り返りを確認するための入口です。コードと設計書の差分がある場合は、`docs/10_current_implementation.md`と`docs/03_basic_design.md`、および実際のソースコードを優先します。

## すぐ確認したい資料

| 資料 | 内容 |
| --- | --- |
| [現行実装ガイド](10_current_implementation.md) | 現在の画面、API、データフロー、外部サービス、起動・テスト方法 |
| [開発振り返りレポート](development-review.md) | 開発全体の振り返り、Issue #1〜#34 の流れ、学び、苦戦したこと、今後の改善 |
| [プロダクト仕様書](02_specification.md) | アプリのコンセプト、提供価値、機能構成、MVP定義 |
| [基本設計書](03_basic_design.md) | システム構成、DB設計、認証・権限設計、外部API連携 |
| [Issue #16追加設計書](05_playlist_share_audio_design.md) | プレイリストCRUD、共有、権限、テスト設計 |
| [YouTube公式プレイヤー連携設計書](09_youtube_playback_design.md) | 曲詳細のMVボタンと公式埋め込み再生 |

## 設計資料

| 資料 | 内容 |
| --- | --- |
| [要件定義書](01_requirements.md) | 何を作るか、背景、目的、機能要件 |
| [プロダクト仕様書](02_specification.md) | どう動くか、機能仕様、技術スタック |
| [基本設計書](03_basic_design.md) | 全体構成、DB設計、認証・権限、外部API |
| [詳細設計書](04_detail_design.md) | 実装コード、クラス構成、API詳細 |
| [プレイリスト・共有・音声再生設計書](05_playlist_share_audio_design.md) | Issue #16の要件・基本設計・詳細設計 |
| [ユーザー向け曲検索・カタログ取り込み設計書](06_music_catalog_design.md) | Issue #17の要件・Provider・API・データ設計 |
| [お気に入り・プレイリストUX設計書](07_favorites_playlist_ux_design.md) | Issue #18の名称、再生導線、ダークテーマ設計 |
| [プレイリスト導線・責務整理設計書](08_playlist_navigation_ux_design.md) | Issue #19の一覧・詳細・管理・再生導線 |
| [YouTube公式プレイヤー連携設計書](09_youtube_playback_design.md) | Issue #20〜#34のMVボタン・公式埋め込み再生・BPM連動 |
| [現行実装ガイド](10_current_implementation.md) | Issue #34までの実装を横断した使い方・構成・運用情報 |

## 補助資料

| 資料 | 内容 |
| --- | --- |
| [画像資料](images/) | Issueごとの概要図、手順図、構造理解用の画像 |
