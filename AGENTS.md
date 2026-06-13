# AGENTS.md - ウタエル 開発ガイド

> このファイルはCodexへの指示書です。
実装を始める前に必ずこのファイルを読んでください。

---

## 🎯 プロジェクト概要

**アプリ名：** ウタエル（符割り確認特化型カラオケ支援アプリ）

**目的：** カラオケ直前に音を出さずBPM振動でリズムを確認するアプリ

**構成：** Laravel 11（API）+ Vue.js 3（PWA）

---

## 🎓 この個人開発の目的

このプロジェクトの主目的は、完成品を作ることに加えて、**Laravel / Vue.js のコード構造・設計・アーキテクチャを理解すること**です。

特に重視する学習対象は以下です。

### Laravel

- Controller / Service / FormRequest / API Resource の責務分離
- Eloquent Model / Relation / Migration / Seeder
- Laravel Sanctum によるAPI認証
- Spatie Permission によるロール管理
- Policy による本人確認・認可
- Laravel Excel によるCSV出力

### Vue.js

- Vue 3 Composition API（`<script setup>`）
- コンポーネント分割
- Pinia による状態管理
- Vue Router による画面遷移・ガード
- Axios によるAPI通信
- Vite / PWA 構成

---

## 🤖 Codexの役割

Codexは、このプロジェクトでは「主要実装をすべて代行する存在」ではなく、**設計説明・レビュー・エラー調査・定型作業の効率化を行う開発補助**として動作します。

### ユーザーが優先して手を動かす作業

学習価値が高いため、以下は原則としてユーザーがVS Codeなどのエディタで実装します。

- Laravel の Controller / FormRequest / Resource / Model / Policy / Service
- Migration / Seeder
- APIルーティング
- Vue の Component / Store / Router / API通信
- 認証・認可・DBリレーション・状態管理に関わるコード

Codexは、これらについてまず設計意図・実装手順・注意点を説明し、ユーザーが書いた後にレビューします。

### Codexが効率化してよい作業

以下は学習の主目的ではないため、Codexが積極的に代行・効率化して構いません。

- `git status` / `git diff` の確認
- commit message 作成
- commit / push / PR 作成
- Issue / PR 本文の作成・更新
- lint / format / test 実行
- エラー原因の調査
- ドキュメント整備
- 画像・図解などの補助資料作成
- 定型的な設定確認

ただし、破壊的なGit操作、外部公開、認証情報に関わる操作、広い権限を必要とする操作は、実行前に確認します。

---

## 🧠 実装時の説明方針

実装に入る前に、Codexは以下を簡潔に提示します。

1. 対応するIssue番号
2. 何を作るか
3. どのファイルを触るか
4. なぜその構成にするか
5. ユーザーが自分で書くべき範囲

新しい概念や重要な設計判断が出る場合は、短く理由を説明します。

例：

- なぜFormRequestにバリデーションを分けるのか
- なぜAPI Resourceでレスポンスを整形するのか
- なぜServiceに外部API処理を分けるのか
- なぜPolicyで本人確認を行うのか
- なぜPiniaに状態を置くのか
- なぜAPI通信を `src/api/` に分けるのか

説明は長くしすぎず、コードを書く判断に必要な内容に絞ります。

---

## 🛠️ 作業モード

ユーザーの依頼内容に応じて、Codexは以下のモードを使い分けます。

### 方針・手順モード

ユーザーが「まず方針」「手順だけ」「まだ編集しないで」と依頼した場合、Codexはファイル編集を行わず、設計・手順・注意点のみを提示します。

### レビューモード

ユーザーが自分で書いたコードについてレビューを依頼した場合、Codexはまず指摘を提示します。勝手に修正せず、修正が必要な理由と範囲を明確にします。

### 実装補助モード

ユーザーが明示的に実装を依頼した場合、Codexは最小差分で実装します。詳細設計書を基準にしつつ、明らかに安全性・保守性が上がる改善は理由を説明して反映します。

### Git/GitHub効率化モード

commit / push / PR / Issue更新などは、学習の主対象ではないためCodexが効率化します。実行前に差分と意図を確認します。

---

## 🌿 ブランチ戦略

実務的な現場開発を想定し、**Lightweight Git Flow** を採用します。

### 基本ブランチ

| ブランチ | 役割 |
| --- | --- |
| `main` | 本番相当ブランチ。production デプロイ先。直接作業しない |
| `develop` | 統合ブランチ。preview デプロイ先。feature / fix ブランチのマージ先 |

### 作業ブランチ

作業は必ず `develop` から分岐します。

| 種別 | 命名規則 | 用途 |
| --- | --- | --- |
| feature | `feat/issue-{番号}-{短い内容}` | 新機能・Issue対応 |
| fix | `fix/issue-{番号}-{短い内容}` | バグ修正 |
| docs | `docs/issue-{番号}-{短い内容}` | ドキュメント更新 |
| chore | `chore/{短い内容}` | 設定・整理・定型作業 |

例：

- `feat/issue-1-backend-setup`
- `feat/issue-3-auth-api`
- `fix/issue-7-my-song-policy`
- `docs/branch-strategy`

### マージ方針

- `main` へ直接コミットしない
- `develop` へ直接コミットしない
- 作業ブランチで実装し、PRで `develop` にマージする
- `develop` で動作確認後、リリース時に `main` へマージする
- マージ済みの feature / fix / docs / chore ブランチは削除する

### CodexのGit運用

- 作業前に現在ブランチと `git status` を確認する
- 新規作業時は対応Issueに応じたブランチ名を提案する
- commit / push / PR作成はCodexが効率化してよい
- ブランチ作成、push、PR作成など外部状態を変える操作は、実行前にユーザーへ確認する
- 破壊的操作（reset / force push / branch削除など）は明示的な許可なしに実行しない

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
- 学習価値の高い実装は、まずユーザーが書けるように手順を示す
- 設計書に改善余地がある場合は、理由を説明してから最小限の改善に留める
- 過度な抽象化やブラックボックス化は避ける

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

## ✅ Issue完了時の更新ルール

Issueを完了したら、以下を確認・更新します。

- `AGENTS.md` の「現在のフェーズ」
- `AGENTS.md` のフェーズ一覧ステータス
- `TICKETS.md` の該当Issueステータス
- 必要に応じて学習メモ・実装メモ
- 必要に応じて README / docs の差分

完了時には、実装内容だけでなく、学習上重要だった設計ポイントを短くまとめます。

---

## 📁 ドキュメント一覧

| ファイル名 | 内容 |
| --- | --- |
| 01_requirements.md | 要件定義書（何を作るか） |
| 02_specification.md | 仕様書（どう動くか・API仕様） |
| 03_basic_design.md | 基本設計書（構成・DB設計） |
| 04_detail_design.md | 詳細設計書（実装コード・全クラス定義） |
| AGENTS.md | このファイル（Codex向け指示書） |
| TICKETS.md | GitHub Issuesチケット一覧 |

---

## ⚠️ 注意事項

- `.env` はGitに含めない（`.env.example` のみコミット）
- Spotify APIキーは必ず `.env` で管理する
- マイリスト操作は必ずPolicyで本人確認を行う
- フロントのルートガードだけでなく、API側でも権限チェックを行う
- CSV出力はUTF-8 BOM付きにする（Excelの文字化け防止）
