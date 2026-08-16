<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DeezerService
{
    private string $baseUrl = 'https://api.deezer.com';

    /**
     * Deezerの公開トラック情報からBPMを取得する。
     * 検索レスポンスにはBPMが含まれないため、候補の詳細を追加取得する。
     */
    public function findBpm(string $title, string $artist, ?int $durationMs = null, ?string $album = null): ?int
    {
        $response = Http::acceptJson()
            ->timeout(8)
            ->get("{$this->baseUrl}/search/track", [
                'q' => trim("{$title} {$artist}"),
                'limit' => 10,
            ]);

        $response->throw();

        $tracks = collect($response->json('data', []));
        $normalizedTitle = $this->normalizeText($title);
        $normalizedArtist = $this->normalizeText($artist);
        $normalizedAlbum = $this->normalizeText((string) $album);

        $matched = $tracks->map(function (array $candidate) use ($normalizedTitle, $normalizedArtist, $normalizedAlbum, $durationMs): array {
            $candidateTitle = $this->normalizeText((string) ($candidate['title'] ?? ''));
            $candidateArtist = $this->normalizeText((string) data_get($candidate, 'artist.name', ''));
            $candidateAlbum = $this->normalizeText((string) data_get($candidate, 'album.title', ''));
            $titleExact = $candidateTitle === $normalizedTitle;
            $artistExact = $candidateArtist === $normalizedArtist;
            $score = 0;

            $score += $titleExact
                ? 100
                : (str_starts_with($candidateTitle, $normalizedTitle.' ') ? 70 : 0);
            $score += $artistExact ? 100 : 0;

            if ($normalizedAlbum !== '' && $candidateAlbum === $normalizedAlbum) {
                $score += 30;
            }

            if ($durationMs !== null && is_numeric($candidate['duration'] ?? null)) {
                $durationDifference = abs(((int) $candidate['duration'] * 1000) - $durationMs);
                $score += match (true) {
                    $durationDifference <= 2000 => 40,
                    $durationDifference <= 5000 => 25,
                    $durationDifference <= 15000 => 5,
                    default => -80,
                };
            }

            $versionText = mb_strtolower("{$candidateTitle} {$candidateAlbum}");

            foreach (['live', 'ライブ', 'remix', 'リミックス', 'cover', 'カバー', 'acoustic', 'アコースティック', 'karaoke', 'カラオケ', 'instrumental', 'インスト', 'sped up', 'slowed', 'nightcore'] as $keyword) {
                if (str_contains($versionText, $keyword)) {
                    $score -= 1000;
                }
            }

            return [
                'track' => $candidate,
                'score' => $score,
                'title_exact' => $titleExact,
                'artist_exact' => $artistExact,
            ];
        })->sort(function (array $left, array $right): int {
            $scoreDifference = ($right['score'] ?? 0) <=> ($left['score'] ?? 0);

            if ($scoreDifference !== 0) {
                return $scoreDifference;
            }

            return strcmp(
                (string) data_get($left, 'track.id', ''),
                (string) data_get($right, 'track.id', ''),
            );
        })->first();

        // タイトル・アーティストが完全一致しない候補のBPMを誤採用しない。
        // 「Live」「Remix」などの候補はスコアを下げて除外する。
        if (! is_array($matched)
            || ($matched['title_exact'] ?? false) !== true
            || ($matched['artist_exact'] ?? false) !== true
            || ($matched['score'] ?? 0) < 150) {
            return null;
        }

        $track = $matched['track'];

        $trackId = $track['id'] ?? null;

        if (! is_numeric($trackId)) {
            return null;
        }

        $detailResponse = Http::acceptJson()
            ->timeout(8)
            ->get("{$this->baseUrl}/track/{$trackId}");

        $detailResponse->throw();

        return $this->normalizeBpm($detailResponse->json('bpm'));
    }

    private function normalizeText(string $value): string
    {
        $normalized = mb_strtolower($value);
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    private function normalizeBpm(mixed $bpm): ?int
    {
        if (! is_numeric($bpm)) {
            return null;
        }

        $normalized = (int) round((float) $bpm);

        return $normalized >= 1 && $normalized <= 300 ? $normalized : null;
    }
}
