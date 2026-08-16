<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GetSongBpmService
{
    private string $baseUrl = 'https://api.getsong.co';

    public function findBpm(string $title, string $artist): ?int
    {
        $key = config('services.getsongbpm.key');

        if (! is_string($key) || $key === '') {
            return null;
        }

        $response = Http::acceptJson()
            ->timeout(8)
            ->get("{$this->baseUrl}/search/", [
                'api_key' => $key,
                'type' => 'both',
                'lookup' => sprintf('song:%s artist:%s', $title, $artist),
                'limit' => 10,
            ]);

        $response->throw();

        $normalizedTitle = $this->normalizeText($title);
        $normalizedArtist = $this->normalizeText($artist);
        $matched = collect($response->json('search', []))
            ->map(function (array $candidate) use ($normalizedTitle, $normalizedArtist): array {
                $candidateTitle = $this->normalizeText((string) ($candidate['title'] ?? $candidate['song_title'] ?? ''));
                $candidateArtist = $this->normalizeText((string) data_get($candidate, 'artist.name', ''));
                $score = 0;
                $score += $candidateTitle === $normalizedTitle ? 100 : 0;
                $score += $candidateArtist === $normalizedArtist ? 100 : 0;

                $versionText = mb_strtolower((string) ($candidate['title'] ?? ''));
                foreach (['live', 'ライブ', 'remix', 'リミックス', 'cover', 'カバー', 'acoustic', 'アコースティック', 'karaoke', 'カラオケ'] as $keyword) {
                    if (str_contains($versionText, $keyword)) {
                        $score -= 1000;
                    }
                }

                return ['candidate' => $candidate, 'score' => $score];
            })
            ->sort(function (array $left, array $right): int {
                $scoreDifference = ($right['score'] ?? 0) <=> ($left['score'] ?? 0);

                if ($scoreDifference !== 0) {
                    return $scoreDifference;
                }

                return strcmp(
                    (string) data_get($left, 'candidate.id', ''),
                    (string) data_get($right, 'candidate.id', ''),
                );
            })
            ->first();

        if (! is_array($matched) || ($matched['score'] ?? 0) < 150) {
            return null;
        }

        $tempo = data_get($matched, 'candidate.tempo');

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
