<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SpotifyService
{
    private string $clientId;

    private string $clientSecret;

    private string $baseUrl = 'https://api.spotify.com/v1';

    public function __construct()
    {
        // Spotifyの認証情報はコードに直接書かず、.env → config/services.php 経由で読み取る
        $this->clientId = config('services.spotify.client_id');
        $this->clientSecret = config('services.spotify.client_secret');
    }

    public function getAccessToken(): string
    {
        // Search APIなどを呼ぶ前に、Client ID/Secretを使って一時的なアクセストークンを取得する
        // asForm() は Spotify の token API が application/x-www-form-urlencoded 形式を期待するために付ける
        $response = Http::asForm()
            // withBasicAuth() は Authorization: Basic ... を作り、Client ID/SecretをSpotifyに渡す
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->post('https://accounts.spotify.com/api/token', [
                // client_credentials は「ユーザー個人ではなく、このアプリとしてAPIを使う」認証方式
                'grant_type' => 'client_credentials',
            ]);

        $response->throw();

        // SpotifyのレスポンスJSONから access_token だけを取り出して返す
        return $response->json('access_token');
    }

    public function searchSong(string $query): array
    {
        // Spotifyの各APIを呼ぶため、まずアクセストークンを取得する
        $token = $this->getAccessToken();

        // Search APIで曲を検索する。type=track にすることで曲だけを検索対象にする
        $searchResponse = Http::withToken($token)
            ->get("{$this->baseUrl}/search", [
                // q は検索キーワード。曲名だけでも、曲名 + アーティスト名でもよい
                'q' => $query,
                'type' => 'track',
                // 候補を出しすぎないよう、最初は5件だけ返す
                'limit' => 5,
            ]);

        $searchResponse->throw();

        // Spotifyの検索結果は tracks.items の中に曲候補の配列として入っている
        $tracks = $searchResponse->json('tracks.items', []);

        // 各曲候補を、ウタエルのAPIで使いやすい形に変換する
        return collect($tracks)->map(function (array $track) use ($token) {
            // 検索結果の曲IDを使って、tempo(BPM)を含む音声特徴量を別APIから取得する
            $features = $this->getAudioFeatures($track['id'], $token);

            return [
                // 後でsongsテーブルの spotify_id に保存する値
                'spotify_id' => $track['id'],
                // Spotify上の曲名
                'title' => $track['name'],
                // artists は配列なので、MVPでは先頭のアーティスト名を使う
                'artist' => $track['artists'][0]['name'],
                // tempo は小数で返ることがあるため、四捨五入して整数のBPMにする
                'bpm' => (int) round($features['tempo'] ?? 0),
            ];
        })->toArray();
    }

    private function getAudioFeatures(string $spotifyId, string $token): array
    {
        // Audio Features APIはDeprecatedだが、現時点ではtempo(BPM)取得用として使う
        // $spotifyId は Search APIで取得した曲ごとのID
        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/audio-features/{$spotifyId}");

        $response->throw();

        // tempo などの音声特徴量が入ったJSON全体を配列として返す
        return $response->json();
    }
}
