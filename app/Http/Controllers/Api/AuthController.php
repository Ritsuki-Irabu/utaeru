<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

//APIとして呼ばれる処理をメソッドごとに分ける
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {// 登録処理
        $user = User::create($request->validated());//ユーザー情報を取り出し、usersテーブルへ保存する

        $token = $user->createToken('auth')->plainTextToken;//取り出したユーザーのトークンを発行する

        //成功したらJSONで返す
        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201);//201は「新しく作成しました」というHTTPステータスである
    }

    
    public function login(LoginRequest $request): JsonResponse
    {// ログイン処理
        $user = User::where('email', $request->email)->first();//入力されたメールアドレスでusersテーブルからユーザーを特定する

        if (!$user || !Hash::check($request->password, $user->password)) {//! $user→メールアドレスのユーザーが存在しない
            throw ValidationException::withMessages([
                'email' => ['メールアドレスまたはパスワードが正しくありません。'],//! Hash::check(...)→パスワードが一致しない
            ]);
        }

        $token = $user->createToken('auth')->plainTextToken;//成功したら、トークンを発行する

        //tokenとユーザー情報をJSONで返す
        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(): JsonResponse
    {// ログアウト処理
        auth()->user()->currentAccessToken()->delete();//上で特定/作成したユーザー/トークンを取得し、削除する

        //ログアウトメッセージをJSONで返す
        return response()->json([
            'message' => 'ログアウトしました。',
        ]);
    }
}