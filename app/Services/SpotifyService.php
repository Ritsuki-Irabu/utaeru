<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SpotifyService
{
    private ?string $clientId;

    private ?string $clientSecret;

    private string $baseUrl = 'https://api.spotify.com/v1';

    public function __construct()
    {
        // Spotifyの認証情報はコードに直接書かず、.env → config/services.php 経由で読み取る
        $this->clientId = config('services.spotify.client_id');
        $this->clientSecret = config('services.spotify.client_secret');
    }

    public function getAccessToken(): string
    {
        if (blank($this->clientId) || blank($this->clientSecret)) {
            throw new \RuntimeException('Spotify APIの認証情報が設定されていません。');
        }

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
        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Spotify APIからアクセストークンを取得できませんでした。');
        }

        return $token;
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
                'bpm' => $this->normalizeBpm($features['tempo'] ?? null),
            ];
        })->toArray();
    }

    /**
     * 曲名とアーティスト名からSpotifyの音声特徴量を検索し、BPMを返す。
     *
     * 検索結果の曲IDを先に確定してからAudio Features APIを呼ぶことで、
     * タイトルの表記ゆれがあっても同一曲のBPMを取得しやすくする。
     */
    public function findBpm(string $title, string $artist, ?int $durationMs = null, ?string $album = null): ?int
    {
        $token = $this->getAccessToken();
        $query = trim(sprintf('track:"%s" artist:"%s"', $title, $artist));

        $searchResponse = Http::withToken($token)
            ->get("{$this->baseUrl}/search", [
                'q' => $query,
                'type' => 'track',
                'limit' => 5,
            ]);

        $searchResponse->throw();
        $normalizedTitle = $this->normalizeText($title);
        $normalizedArtist = $this->normalizeText($artist);
        $normalizedAlbum = $this->normalizeText((string) $album);
        $matched = collect($searchResponse->json('tracks.items', []))
            ->map(function (array $track) use ($normalizedTitle, $normalizedArtist, $normalizedAlbum, $durationMs): array {
                $trackTitle = $this->normalizeText((string) ($track['name'] ?? ''));
                $trackArtist = $this->normalizeText((string) data_get($track, 'artists.0.name', ''));
                $trackAlbum = $this->normalizeText((string) data_get($track, 'album.name', ''));
                $score = 0;

                $score += $trackTitle === $normalizedTitle ? 100 : 0;
                $score += $trackArtist === $normalizedArtist ? 100 : 0;

                if ($normalizedAlbum !== '' && $trackAlbum === $normalizedAlbum) {
                    $score += 30;
                }

                if ($durationMs !== null && is_numeric($track['duration_ms'] ?? null)) {
                    $durationDifference = abs((int) $track['duration_ms'] - $durationMs);
                    $score += match (true) {
                        $durationDifference <= 2000 => 40,
                        $durationDifference <= 5000 => 25,
                        $durationDifference <= 15000 => 5,
                        default => -80,
                    };
                }

                $versionText = mb_strtolower("{$trackTitle} {$trackAlbum}");
                foreach (['live', 'ライブ', 'remix', 'リミックス', 'cover', 'カバー', 'acoustic', 'アコースティック', 'karaoke', 'カラオケ'] as $keyword) {
                    if (str_contains($versionText, $keyword)) {
                        $score -= 1000;
                    }
                }

                return ['track' => $track, 'score' => $score];
            })
            ->sort(function (array $left, array $right): int {
                $scoreDifference = ($right['score'] ?? 0) <=> ($left['score'] ?? 0);

                if ($scoreDifference !== 0) {
                    return $scoreDifference;
                }

                return strcmp(
                    (string) data_get($left, 'track.id', ''),
                    (string) data_get($right, 'track.id', ''),
                );
            })
            ->first();

        if (! is_array($matched) || ($matched['score'] ?? 0) < 150) {
            return null;
        }

        $trackId = data_get($matched, 'track.id');

        if (blank($trackId)) {
            return null;
        }

        $features = $this->getAudioFeatures((string) $trackId, $token);

        return $this->normalizeBpm($features['tempo'] ?? null);
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

    private function normalizeBpm(mixed $tempo): ?int
    {
        if (! is_numeric($tempo)) {
            return null;
        }

        $bpm = (int) round((float) $tempo);

        return $bpm >= 1 && $bpm <= 300 ? $bpm : null;
    }

    private function normalizeText(string $value): string
    {
        $normalized = mb_strtolower($value);
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }
}
