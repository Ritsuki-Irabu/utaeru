<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\MySongController;
use App\Http\Controllers\MySongExportController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\PlaylistShareController;
use App\Http\Controllers\PlaylistSongController;
use App\Http\Controllers\LyricsController;
use App\Http\Controllers\SongCatalogController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\YoutubeVideoController;
use App\Http\Controllers\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// auth というURLのまとまりを作る
// この中に書いたルートは /api/auth/... になる
Route::prefix('auth')->group(function () {
    // POST /api/auth/register
    // 新規登録用。まだログインしていない人でも使える
    Route::post('/register', [AuthController::class, 'register']);

    // POST /api/auth/login
    // ログイン用。メールアドレスとパスワードを確認してTokenを返す
    Route::post('/login', [AuthController::class, 'login']);
});

// auth:sanctum は「Tokenを持っているログイン済みユーザーだけ通す」設定
Route::middleware('auth:sanctum')->group(function () {
    // GET /api/user
    // 今ログインしているユーザー情報を返す確認用API
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // POST /api/auth/logout
    // 今使っているTokenを削除してログアウトする
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // GET /api/songs
    // user/adminどちらも使う公開曲マスタ一覧API
    Route::get('/songs', [SongController::class, 'index']);
    // 一般ユーザー向けの検索・外部カタログ取り込み
    Route::get('/songs/search', [SongCatalogController::class, 'search']);
    Route::post('/songs/import', [SongCatalogController::class, 'import']);
    // 曲詳細のMVボタンから内部的に呼ぶYouTube候補検索
    Route::get('/songs/youtube/search', [YoutubeVideoController::class, 'search']);
    // DBに歌詞がない場合だけ、設定済みの利用許諾済み歌詞APIを補助的に参照する。
    Route::get('/songs/{song}/lyrics', [LyricsController::class, 'show'])->whereNumber('song');
    // 一覧から遷移する再生専用の曲詳細。spotify等の文字列と衝突しないようIDを数値に限定する。
    Route::get('/songs/{song}', [SongController::class, 'show'])->whereNumber('song');

    // GET /api/tags
    // マイリスト編集時に選択できるタグ一覧
    Route::get('/tags', [TagController::class, 'index']);
    Route::post('/tags', [TagController::class, 'store']);

    // マイリストCRUD
    Route::get('/my-songs', [MySongController::class, 'index']);
    Route::get('/my-songs/export', MySongExportController::class);
    Route::post('/my-songs', [MySongController::class, 'store']);
    Route::put('/my-songs/{mySong}', [MySongController::class, 'update']);
    Route::delete('/my-songs/{mySong}', [MySongController::class, 'destroy']);

    // ユーザープレイリストCRUD
    Route::get('/playlists', [PlaylistController::class, 'index']);
    Route::post('/playlists', [PlaylistController::class, 'store']);
    Route::get('/playlists/{playlist}', [PlaylistController::class, 'show']);
    Route::put('/playlists/{playlist}', [PlaylistController::class, 'update']);
    Route::delete('/playlists/{playlist}', [PlaylistController::class, 'destroy']);

    // プレイリスト内の曲管理
    Route::post('/playlists/{playlist}/songs', [PlaylistSongController::class, 'store']);
    Route::put('/playlists/{playlist}/songs/{playlistSong}', [PlaylistSongController::class, 'update']);
    Route::delete('/playlists/{playlist}/songs/{playlistSong}', [PlaylistSongController::class, 'destroy']);

    // 共有リンクの発行・無効化。共有閲覧もログイン済みユーザーに限定する。
    Route::post('/playlists/{playlist}/share', [PlaylistShareController::class, 'store']);
    Route::delete('/playlists/{playlist}/share', [PlaylistShareController::class, 'destroy']);
    Route::get('/shared/playlists/{token}', [PlaylistShareController::class, 'show']);
    Route::post('/shared/playlists/{token}/copy', [PlaylistShareController::class, 'copy']);

    // admin(管理者)の権限がついている
    // 曲マスタの登録・編集・削除はadminだけ操作できる
    Route::middleware('role:admin')->group(function () {
        // Spotify APIで曲候補とBPMを取得する
        Route::get('/songs/spotify', [SongController::class, 'searchSpotify']);
        Route::post('/songs', [SongController::class, 'store']);
        Route::put('/songs/{song}', [SongController::class, 'update']);
        Route::post('/songs/{song}/bpm/refresh', [SongController::class, 'refreshBpm']);
        Route::delete('/songs/{song}', [SongController::class, 'destroy']);
    });
});

// admin ルートを追加
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/check', function () {
    return response()->json(['message' => 'admin OK']);
});
// Route::get('/admin/check', ...)→URLの入り口
// middleware()→ sanctumでは、ログイン済みか？Tokenは正しいか？roleは、 admin ロールを持っているか？　
