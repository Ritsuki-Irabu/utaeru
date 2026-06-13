#!/bin/bash

# GitHub Issues 一括登録スクリプト
# 実行前に `gh auth login` で認証済みであること

REPO="Ritsuki-Irabu/utaeru"

echo "ラベルを作成中..."
gh label create "backend"  --color "#e74c3c" --description "Laravel関連"  --repo "$REPO" 2>/dev/null || echo "  backend: 既存スキップ"
gh label create "frontend" --color "#3498db" --description "Vue.js関連"   --repo "$REPO" 2>/dev/null || echo "  frontend: 既存スキップ"
gh label create "learning" --color "#f1c40f" --description "学習メモあり" --repo "$REPO" 2>/dev/null || echo "  learning: 既存スキップ"
gh label create "bug"      --color "#e67e22" --description "バグ修正"      --repo "$REPO" 2>/dev/null || echo "  bug: 既存スキップ"
gh label create "done"     --color "#2ecc71" --description "完了"          --repo "$REPO" 2>/dev/null || echo "  done: 既存スキップ"
echo "ラベル作成完了"
echo ""

echo "Issueを登録中..."

gh issue create --repo "$REPO" \
  --title "#1 【backend】環境構築" \
  --label "backend,learning" \
  --body "## やること
- [ ] WSL2 + Docker Desktop の準備
- [ ] Laravel Sail で Laravel 11 環境構築
- [ ] Sail の MySQL 接続設定（.env）
- [ ] Laravel Sanctum インストール・設定
- [ ] Spatie Permission インストール・設定
- [ ] Laravel Excel インストール
- [ ] 動作確認（\`./vendor/bin/sail up -d\`）
- [ ] 動作確認（\`./vendor/bin/sail artisan migrate\`）

## 実務ワンポイント
\`\`\`
本番環境では .env をGitに含めない。
.env.example に項目だけ残しておくのが慣例。
このプロジェクトの開発環境は WSL2 + Docker + Laravel Sail を前提にする。
Artisan / Composer / npm は原則として Sail 経由で実行する。
例：./vendor/bin/sail artisan migrate
Sanctum のインストール後は config/sanctum.php の
stateful ドメイン設定を忘れずに確認すること。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#2 【backend】DB設計・マイグレーション作成" \
  --label "backend,learning" \
  --body "## やること
- [ ] users テーブル（デフォルト流用）
- [ ] songs テーブル作成
- [ ] tags テーブル作成
- [ ] my_songs テーブル作成
- [ ] my_song_tag 中間テーブル作成
- [ ] 各モデルのリレーション定義
- [ ] Seeder でテストデータ投入

## 実務ワンポイント
\`\`\`
中間テーブルのマイグレーションは
アルファベット順（my_song_tag）で命名するのが慣例。
外部キー制約（foreign()）は忘れがちなので注意。
Seeder は開発中に何度もリセットするので
factory() を使うと効率的。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#3 【backend】認証API（Sanctum）" \
  --label "backend,learning" \
  --body "## やること
- [ ] AuthController 作成
- [ ] RegisterRequest バリデーション作成
- [ ] LoginRequest バリデーション作成
- [ ] POST /api/auth/register 実装
- [ ] POST /api/auth/login 実装
- [ ] POST /api/auth/logout 実装
- [ ] Postman で動作確認

## 実務ワンポイント
\`\`\`
Sanctum のトークンは発行時に一度しか平文で取得できない。
レスポンスで返した後は再取得不可なので注意。
本番では createToken() の第2引数で有効期限を設定する。
例：createToken('auth', ['*'], now()->addDays(30))
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#4 【backend】ロール設定（Spatie Permission）" \
  --label "backend,learning" \
  --body "## やること
- [ ] admin / user ロール作成（Seeder）
- [ ] ミドルウェア設定（role:admin）
- [ ] ルートにロールチェック追加
- [ ] admin ユーザーのテストデータ作成
- [ ] 権限エラー時のレスポンス確認（403）

## 実務ワンポイント
\`\`\`
Spatie の Role と Permission はキャッシュされる。
ロールを変更しても反映されない場合は
./vendor/bin/sail artisan permission:cache-reset を実行する。
本番デプロイ後も同様。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#5 【backend】公開曲マスタCRUD" \
  --label "backend,learning" \
  --body "## やること
- [ ] SongController 作成
- [ ] StoreSongRequest バリデーション作成
- [ ] GET /api/songs（一覧）実装
- [ ] POST /api/songs（登録）実装
- [ ] PUT /api/songs/{id}（編集）実装
- [ ] DELETE /api/songs/{id}（削除）実装
- [ ] SongResource（レスポンス整形）作成
- [ ] Postman で動作確認

## 実務ワンポイント
\`\`\`
API Resource を使うとレスポンスの形を統一できる。
DBのカラム名をそのまま返すのではなく
Resource で整形する習慣をつけると
フロントとの連携がスムーズになる。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#6 【backend】Spotify API連携（BPM取得）" \
  --label "backend,learning" \
  --body "## やること
- [ ] Spotify Developer でアプリ登録・キー取得
- [ ] SpotifyService クラス作成
- [ ] アクセストークン取得処理
- [ ] 曲検索処理（Search API）
- [ ] BPM取得処理（Audio Features API）
- [ ] GET /api/songs/spotify?q={keyword} 実装
- [ ] .env に CLIENT_ID / CLIENT_SECRET 追加

## 実務ワンポイント
\`\`\`
外部APIのキーは必ず .env で管理する。
SpotifyService のような外部連携クラスは
app/Services/ に切り出すのが実務の慣例。
APIが落ちた場合の例外処理（try-catch）も忘れずに。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#7 【backend】マイリストCRUD + タグ付け" \
  --label "backend,learning" \
  --body "## やること
- [ ] MySongController 作成
- [ ] StoreMySongRequest バリデーション作成
- [ ] GET /api/my-songs（一覧）実装
- [ ] POST /api/my-songs（追加）実装
- [ ] PUT /api/my-songs/{id}（編集）実装
- [ ] DELETE /api/my-songs/{id}（削除）実装
- [ ] タグの付け外し（sync）実装
- [ ] Policy で本人確認実装
- [ ] MySongResource 作成

## 実務ワンポイント
\`\`\`
多対多の付け外しは sync() メソッドが便利。
\$mySong->tags()->sync(\$request->tag_ids);
これで渡したID以外は自動削除・追加される。
Policy の作成は ./vendor/bin/sail artisan make:policy で生成できる。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#8 【backend】CSV出力（Laravel Excel）" \
  --label "backend,learning" \
  --body "## やること
- [ ] MySongsExport クラス作成
- [ ] GET /api/my-songs/export 実装
- [ ] ヘッダー行の設定
- [ ] タグのカンマ結合処理
- [ ] ファイル名に日付付与
- [ ] 実際にCSVをダウンロードして確認

## 実務ワンポイント
\`\`\`
Laravel Excel の WithHeadings を使うとヘッダー行を定義できる。
日本語ヘッダーを使う場合は UTF-8 BOM付きにしないと
Excelで文字化けするので WithCustomCsvSettings で設定する。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#9 【frontend】Vue.js + PWA 環境構築" \
  --label "frontend,learning" \
  --body "## やること
- [ ] Vite + Vue.js 3 プロジェクト作成
- [ ] Axios インストール・設定
- [ ] Pinia インストール・設定
- [ ] Vue Router インストール・設定
- [ ] vite-plugin-pwa インストール・設定
- [ ] Laravel API との疎通確認

## 実務ワンポイント
\`\`\`
Axios のベースURLは .env で管理する。
VITE_API_URL=http://localhost:8000/api のように設定し
import.meta.env.VITE_API_URL で参照する。
Vue.js の環境変数は VITE_ プレフィックスが必要。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#10 【frontend】ログイン画面・Axios設定" \
  --label "frontend,learning" \
  --body "## やること
- [ ] ログイン画面（Login.vue）作成
- [ ] Sanctumトークンの保存・取得処理
- [ ] Axios インターセプター設定（トークン自動付与）
- [ ] 認証ストア（stores/auth.js）作成
- [ ] ルートガード設定（未ログインはログインへ）

## 実務ワンポイント
\`\`\`
Vue Router のナビゲーションガードで
未認証ユーザーをログイン画面にリダイレクトできる。
router.beforeEach() を使うのが一般的。
トークンは localStorage に保存するのが簡単だが
セキュリティ要件が高い場合は httpOnly Cookie を検討する。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#11 【frontend】マイリスト画面" \
  --label "frontend,learning" \
  --body "## やること
- [ ] マイリスト画面（MySongs.vue）作成
- [ ] マイリストストア（stores/mySongs.js）作成
- [ ] 曲一覧の表示
- [ ] タグ表示
- [ ] メモ表示
- [ ] 曲削除ボタン

## 実務ワンポイント
\`\`\`
Pinia のストアは defineStore() で定義する。
APIのデータ取得は onMounted() 内で行うのが基本。
ローディング状態（isLoading）をストアで管理すると
画面のちらつきを防げる。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#12 【frontend】リズム再生コンポーネント" \
  --label "frontend,learning" \
  --body "## やること
- [ ] RhythmPlayer.vue 作成
- [ ] BPMから振動間隔計算ロジック実装
- [ ] 4拍子の視覚フィードバック実装
- [ ] 開始・停止ボタン実装
- [ ] 現在の拍をハイライト表示
- [ ] iPhoneでの動作確認

## 実務ワンポイント
\`\`\`
setInterval() は画面遷移時に必ず clearInterval() で止める。
Vue.js では onUnmounted() フックで後処理を行う。
止め忘れるとメモリリークの原因になる。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#13 【frontend】曲検索・追加画面" \
  --label "frontend,learning" \
  --body "## やること
- [ ] 曲検索画面（SongSearch.vue）作成
- [ ] 公開曲マスタ一覧の表示
- [ ] マイリストへの追加ボタン
- [ ] タグ選択UI
- [ ] メモ入力フォーム

## 実務ワンポイント
\`\`\`
検索フォームはデバウンス処理を入れると
入力のたびにAPIを叩かなくて済む。
lodash の debounce() か
Vue.js の watchEffect() + setTimeout() で実装できる。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

gh issue create --repo "$REPO" \
  --title "#14 【frontend】管理者画面（曲マスタ管理）" \
  --label "frontend,learning" \
  --body "## やること
- [ ] 管理者画面（Admin/Songs.vue）作成
- [ ] 曲一覧表示・削除
- [ ] Spotify検索フォーム
- [ ] BPM取得・確認・登録フロー
- [ ] adminロール以外はアクセス不可のガード設定

## 実務ワンポイント
\`\`\`
adminのみアクセス可能なページは
ルートガードでロールチェックを行う。
フロントだけでなくバックエンドのAPI側でも
必ず権限チェックをすること（フロントのガードだけでは不十分）。
\`\`\`

## 学習メモ（実装後に記入）
\`\`\`
- ハマったこと：
- 解決方法：
- 参考URL：
- 所要時間：
\`\`\`"

echo ""
echo "✅ 全Issue登録完了！"
