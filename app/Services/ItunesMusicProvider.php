<?php

namespace App\Services;

use App\Contracts\MusicSearchProvider;
use Illuminate\Support\Facades\Http;

class ItunesMusicProvider implements MusicSearchProvider
{
    private string $baseUrl = 'https://itunes.apple.com';

    public function search(string $query, ?string $field = null): array
    {
        $parameters = [
            'term' => $query,
            'country' => config('services.itunes.country', 'jp'),
            'media' => 'music',
            'entity' => 'song',
            'limit' => 20,
        ];

        if ($field === 'title') {
            $parameters['attribute'] = 'songTerm';
        } elseif ($field === 'artist') {
            $parameters['attribute'] = 'artistTerm';
        } elseif ($field === 'album') {
            $parameters['attribute'] = 'albumTerm';
        }

        $response = Http::acceptJson()
            ->timeout(8)
            ->get("{$this->baseUrl}/search", $parameters);

        $response->throw();

        return collect($response->json('results', []))
            ->map(fn (array $track): array => $this->normalize($track))
            ->filter(fn (array $track): bool => filled($track['provider_key']))
            ->values()
            ->all();
    }

    public function resolve(string $providerKey): array
    {
        $response = Http::acceptJson()
            ->timeout(8)
            ->get("{$this->baseUrl}/lookup", [
                'id' => $providerKey,
                'entity' => 'song',
                'country' => config('services.itunes.country', 'jp'),
            ]);

        $response->throw();

        $track = collect($response->json('results', []))
            ->first(fn (array $result): bool => (string) ($result['trackId'] ?? '') === $providerKey);

        abort_if($track === null, 422, '検索結果の曲を確認できませんでした。');

        return $this->normalize($track);
    }

    /**
     * @param array<string, mixed> $track
     * @return array<string, mixed>
     */
    private function normalize(array $track): array
    {
        return [
            'provider' => 'itunes',
            'provider_key' => isset($track['trackId']) ? (string) $track['trackId'] : null,
            'title' => $track['trackName'] ?? '',
            'artist' => $track['artistName'] ?? '',
            'lyrics' => null,
            'opening_line' => null,
            'album' => $track['collectionName'] ?? null,
            'artwork_url' => $track['artworkUrl100'] ?? null,
            'duration_ms' => isset($track['trackTimeMillis']) ? (int) $track['trackTimeMillis'] : null,
            'playback_url' => $track['trackViewUrl'] ?? null,
            'bpm' => null,
        ];
    }
}
