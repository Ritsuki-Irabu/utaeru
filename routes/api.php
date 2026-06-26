<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\MySongController;
use App\Http\Controllers\MySongExportController;
use App\Http\Controllers\SongController;
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
    Route::get('/songs', [SongController::class, 'index']);

    // マイリストCRUD
    Route::get('/my-songs', [MySongController::class, 'index']);
    Route::get('/my-songs/export', MySongExportController::class);
    Route::post('/my-songs', [MySongController::class, 'store']);
    Route::put('/my-songs/{mySong}', [MySongController::class, 'update']);
    Route::delete('/my-songs/{mySong}', [MySongController::class, 'destroy']);

    // admin(管理者)の権限がついている
    // songのルートを追加する
    Route::middleware('role:admin')->group(function () {
        // Spotify APIで曲候補とBPMを取得する
        Route::get('/songs/spotify', [SongController::class, 'searchSpotify']);
        Route::post('/songs', [SongController::class, 'store']);
        Route::put('/songs/{song}', [SongController::class, 'update']);
        Route::delete('/songs/{song}', [SongController::class, 'destroy']);
    });
});

// admin ルートを追加
Route::middleware(['auth:sanctum', 'role:admin'])->get('/admin/check', function () {
    return response()->json(['message' => 'admin OK']);
});
// Route::get('/admin/check', ...)→URLの入り口
// middleware()→ sanctumでは、ログイン済みか？Tokenは正しいか？roleは、 admin ロールを持っているか？　
