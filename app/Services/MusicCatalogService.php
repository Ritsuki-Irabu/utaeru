<?php

namespace App\Services;

use App\Contracts\MusicSearchProvider;
use App\Models\Song;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

class MusicCatalogService
{
    public function __construct(
        private readonly MusicSearchProvider $provider,
        private readonly BpmResolver $bpmResolver,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, string $field = 'all'): array
    {
        $searchTerms = $this->searchTerms($query);

        $localSongs = Song::query()
            ->where(function ($builder) use ($searchTerms, $field): void {
                foreach ($searchTerms as $index => $term) {
                    $escapedTerm = addcslashes($term, '%_\\');
                    $like = "%{$escapedTerm}%";
                    $method = $index === 0 ? 'where' : 'orWhere';

                    $builder->{$method}(function ($termBuilder) use ($like, $field): void {
                        if ($field === 'title') {
                            $termBuilder->where('title', 'like', $like);
                        } elseif ($field === 'artist') {
                            $termBuilder->where('artist', 'like', $like);
                        } elseif ($field === 'album') {
                            $termBuilder->where('album', 'like', $like);
                        } else {
                            $termBuilder
                                ->where('title', 'like', $like)
                                ->orWhere('artist', 'like', $like)
                                ->orWhere('album', 'like', $like);
                        }
                    });
                }
            })
            ->orderBy('title')
            ->limit(20)
            ->get();

        $localKeys = $localSongs
            ->map(fn (Song $song): string => $this->catalogKey($song->playback_provider, $song->playback_key))
            ->filter()
            ->all();

        $results = $localSongs
            ->map(fn (Song $song): array => $this->localResult($song))
            ->values()
            ->all();

        $externalResults = collect();

        foreach ($searchTerms as $term) {
            try {
                $externalResults = $externalResults->merge(
                    $this->provider->search($term, $field === 'all' ? null : $field),
                );
            } catch (RequestException) {
                // 外部Provider障害時も、ローカルカタログの検索結果は返す
            }
        }

        foreach ($externalResults->unique(fn (array $result): string => $this->catalogKey($result['provider'], $result['provider_key'])) as $externalResult) {
            $key = $this->catalogKey($externalResult['provider'], $externalResult['provider_key']);

            if ($key !== '' && in_array($key, $localKeys, true)) {
                continue;
            }

            $results[] = [
                'id' => null,
                ...$externalResult,
                'source' => 'external',
                'is_imported' => false,
            ];
        }

        return $results;
    }

    /**
     * ひらがな入力でも、カタカナ表記のローカル曲・外部候補を検索できるようにする。
     *
     * @return list<string>
     */
    private function searchTerms(string $query): array
    {
        $terms = [trim($query)];
        $katakana = mb_convert_kana(trim($query), 'CV', 'UTF-8');

        if ($katakana !== $terms[0]) {
            $terms[] = $katakana;
        }

        return array_values(array_filter(array_unique($terms), fn (string $term): bool => $term !== ''));
    }

    public function import(string $provider, string $providerKey): Song
    {
        $canonical = $this->provider->resolve($providerKey);

        abort_unless($canonical['provider'] === $provider, 422, 'Providerが一致しません。');

        $resolvedBpm = $canonical['bpm'] ?? null;

        if ($resolvedBpm === null) {
            $resolvedBpm = $this->bpmResolver->resolve(
                (string) $canonical['title'],
                (string) $canonical['artist'],
                isset($canonical['duration_ms']) ? (int) $canonical['duration_ms'] : null,
                isset($canonical['album']) ? (string) $canonical['album'] : null,
            );
        }

        return DB::transaction(function () use ($canonical, $resolvedBpm): Song {
            $song = Song::query()
                ->where('playback_provider', $canonical['provider'])
                ->where('playback_key', $canonical['provider_key'])
                ->lockForUpdate()
                ->first();

            if ($song !== null) {
                $song->update([
                    'lyrics' => $song->lyrics ?: ($canonical['lyrics'] ?? null),
                    'album' => $song->album ?: $canonical['album'],
                    'artwork_url' => $song->artwork_url ?: $canonical['artwork_url'],
                    'duration_ms' => $song->duration_ms ?: $canonical['duration_ms'],
                    'playback_url' => $song->playback_url ?: $canonical['playback_url'],
                    'bpm' => $song->bpm ?: $resolvedBpm,
                ]);

                return $song->refresh();
            }

            return Song::create([
                'title' => $canonical['title'],
                'artist' => $canonical['artist'],
                'lyrics' => $canonical['lyrics'] ?? null,
                'album' => $canonical['album'],
                'artwork_url' => $canonical['artwork_url'],
                'duration_ms' => $canonical['duration_ms'],
                'bpm' => $resolvedBpm,
                'playback_provider' => $canonical['provider'],
                'playback_key' => $canonical['provider_key'],
                'playback_url' => $canonical['playback_url'],
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function localResult(Song $song): array
    {
        return [
            'id' => $song->id,
            'title' => $song->title,
            'artist' => $song->artist,
            'lyrics' => $song->lyrics,
            'opening_line' => $song->opening_line,
            'album' => $song->album,
            'artwork_url' => $song->artwork_url,
            'duration_ms' => $song->duration_ms,
            'bpm' => $song->bpm,
            'playback_provider' => $song->playback_provider,
            'playback_key' => $song->playback_key,
            'playback_url' => $song->playback_url,
            'provider' => $song->playback_provider,
            'provider_key' => $song->playback_key,
            'source' => 'catalog',
            'is_imported' => true,
        ];
    }

    private function catalogKey(?string $provider, ?string $providerKey): string
    {
        if (blank($provider) || blank($providerKey)) {
            return '';
        }

        return "{$provider}:{$providerKey}";
    }
}
