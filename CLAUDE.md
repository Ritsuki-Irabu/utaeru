# CLAUDE.md - ウタエル 開発ガイド

> このファイルはCodexへの指示書です。
実装を始める前に必ずこのファイルを読んでください。

---

## 🎯 プロジェクト概要

**アプリ名：** ウタエル（符割り確認特化型カラオケ支援アプリ）

**目的：** カラオケ直前に音を出さずBPM振動でリズムを確認するアプリ

**構成：** Laravel 11（API）+ Vue.js 3（PWA）

---

## 🏗️ 技術スタック

### バックエンド

- PHP 8.2 / Laravel 11
- Laravel Sanctum（トークン認証）
- Spatie Laravel Permission（ロール管理）
- Laravel Excel / Maatwebsite（CSV出力）
- Spotify Web API（BPM取得）
- MySQL 8.0

### フロントエンド

- Vue.js 3 + Vite
- Pinia（状態管理）
- Axios（API通信）
- Vue Router 4
- vite-plugin-pwa（PWA対応）

---

## 📌 現在のフェーズ

> ⚠️ Issueを完了したら必ずここを更新してください

```
フェーズ：1 - 環境構築
対応中Issue：#1
ステータス：未着手
```

---

## 🗺️ フェーズ一覧

| フェーズ | 内容 | Issue番号 | ステータス |
| --- | --- | --- | --- |
| 1 | 環境構築 | #1 | ⬜ 未着手 |
| 2 | DB設計・マイグレーション | #2 | ⬜ 未着手 |
| 3 | 認証API（Sanctum） | #3 | ⬜ 未着手 |
| 4 | ロール設定（Spatie） | #4 | ⬜ 未着手 |
| 5 | 公開曲マスタCRUD | #5 | ⬜ 未着手 |
| 6 | Spotify API連携 | #6 | ⬜ 未着手 |
| 7 | マイリストCRUD + タグ付け | #7 | ⬜ 未着手 |
| 8 | CSV出力（Laravel Excel） | #8 | ⬜ 未着手 |
| 9 | Vue.js + PWA環境構築 | #9 | ⬜ 未着手 |
| 10 | ログイン画面・Axios設定 | #10 | ⬜ 未着手 |
| 11 | マイリスト画面 | #11 | ⬜ 未着手 |
| 12 | リズム再生コンポーネント | #12 | ⬜ 未着手 |
| 13 | 曲検索・追加画面 | #13 | ⬜ 未着手 |
| 14 | 管理者画面 | #14 | ⬜ 未着手 |

> ステータス凡例：⬜ 未着手 / 🔄 対応中 / ✅ 完了

---

## 📐 コーディングルール

### 共通

- 1 Issue = 1機能（小さく区切って実装する）
- 実装前に必ず対応するIssue番号を確認する
- 設計書（04_detail_design.md）のコードを基準にする

### バックエンド（Laravel）

- コントローラーはスリムに保つ（ロジックはServiceに切り出す）
- バリデーションは必ずFormRequestを使う
- レスポンスは必ずAPI Resourceで整形する
- 認証が必要なルートは `auth:sanctum` ミドルウェアをつける
- admin専用ルートは `role:admin` ミドルウェアをつける
- 他ユーザーのデータ操作はPolicyで必ず本人確認する

### フロントエンド（Vue.js）

- Composition API（`<script setup>`）を使う
- API通信は `src/api/` に切り出す
- 状態管理はPiniaストアを使う
- `setInterval` は `onUnmounted` で必ず `clearInterval` する
- 環境変数は `.env` で管理し、`VITE_` プレフィックスをつける

---

## 📁 ドキュメント一覧

| ファイル名 | 内容 |
| --- | --- |
| 01_requirements.md | 要件定義書（何を作るか） |
| 02_specification.md | 仕様書（どう動くか・API仕様） |
| 03_basic_design.md | 基本設計書（構成・DB設計） |
| 04_detail_design.md | 詳細設計書（実装コード・全クラス定義） |
| CLAUDE.md | このファイル（Codex向け指示書） |
| TICKETS.md | GitHub Issuesチケット一覧 |

---

## ⚠️ 注意事項

- `.env` はGitに含めない（`.env.example` のみコミット）
- Spotify APIキーは必ず `.env` で管理する
- マイリスト操作は必ずPolicyで本人確認を行う
- フロントのルートガードだけでなく、API側でも権限チェックを行う
- CSV出力はUTF-8 BOM付きにする（Excelの文字化け防止）
