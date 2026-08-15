<?php

namespace App\Services;

use App\Contracts\LyricsProvider;
use Illuminate\Support\Facades\Http;

/**
 * 利用許諾済みの歌詞APIへ接続するアダプター。
 *
 * 任意の歌詞サイトをHTMLスクレイピングしたり、歌詞を無断転載したりせず、
 * 契約済みサービスのAPIレスポンスだけを表示するための境界を設ける。
 */
class AuthorizedLyricsProvider implements LyricsProvider
{
    public function find(string $title, string $artist): ?array
    {
        // 個人利用の初期設定では、曲名・アーティスト検索に対応するLRCLIBを使う。
        // 独自または許諾済みサービスを使う場合はLYRICS_API_URLで差し替えられる。
        $url = config('services.lyrics.url') ?: 'https://lrclib.net/api/get';

        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $request = Http::acceptJson()->timeout(8);
        $token = config('services.lyrics.token');

        if (is_string($token) && trim($token) !== '') {
            $request = $request->withToken($token);
        }

        $isLrcLib = str_contains($url, 'lrclib.net');
        $response = $request->get($url, $isLrcLib ? [
            'track_name' => $title,
            'artist_name' => $artist,
        ] : [
            'title' => $title,
            'artist' => $artist,
        ]);

        // 該当なしは障害ではなく「歌詞未登録」として扱う。
        if ($response->status() === 404) {
            return $isLrcLib ? $this->searchLrcLib($title, $artist, $token) : null;
        }

        $payload = $response->throw()->json();

        if ($isLrcLib) {
            $result = $this->normalizeLrcLibPayload($payload, $title, $artist);

            if ($result !== null) {
                return $result;
            }

            // 完全一致で本文が空のレコードでも、検索結果に別表記の本文がある場合がある。
            return $this->searchLrcLib($title, $artist, $token);
        } else {
            $lyrics = data_get($payload, 'lyrics') ?? data_get($payload, 'data.lyrics');
        }

        if (! is_string($lyrics) || trim($lyrics) === '') {
            return null;
        }

        $sourceUrl = $isLrcLib
            ? 'https://lrclib.net/'
            : data_get($payload, 'source_url') ?? data_get($payload, 'data.source_url');

        return [
            'lyrics' => trim($lyrics),
            'source_url' => is_string($sourceUrl) && filter_var($sourceUrl, FILTER_VALIDATE_URL)
                ? $sourceUrl
                : null,
        ];
    }

    private function searchLrcLib(string $title, string $artist, mixed $token): ?array
    {
        $request = Http::acceptJson()->timeout(8);

        if (is_string($token) && trim($token) !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->get('https://lrclib.net/api/search', [
            'q' => trim("{$title} {$artist}"),
        ]);

        if ($response->status() === 404) {
            return null;
        }

        return $this->normalizeLrcLibPayload($response->throw()->json(), $title, $artist);
    }

    private function normalizeLrcLibPayload(mixed $payload, string $title, string $artist): ?array
    {
        $items = is_array($payload) && array_is_list($payload) ? $payload : [$payload];
        $normalizedTitle = $this->normalizeText($title);
        $normalizedArtist = $this->normalizeText($artist);

        usort($items, function (mixed $left, mixed $right) use ($normalizedTitle, $normalizedArtist): int {
            return $this->lyricsCandidateScore($right, $normalizedTitle, $normalizedArtist)
                <=> $this->lyricsCandidateScore($left, $normalizedTitle, $normalizedArtist);
        });

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $lyrics = data_get($item, 'plainLyrics');

            if (! is_string($lyrics) || trim($lyrics) === '') {
                $lyrics = $this->plainLyrics(data_get($item, 'syncedLyrics'));
            }

            if (! is_string($lyrics) || trim($lyrics) === '') {
                continue;
            }

            return [
                'lyrics' => trim($lyrics),
                'source_url' => 'https://lrclib.net/',
            ];
        }

        return null;
    }

    private function lyricsCandidateScore(mixed $item, string $title, string $artist): int
    {
        if (! is_array($item)) {
            return 0;
        }

        $candidateTitle = $this->normalizeText((string) data_get($item, 'trackName', ''));
        $candidateArtist = $this->normalizeText((string) data_get($item, 'artistName', ''));

        return ($candidateTitle === $title ? 100 : (str_contains($candidateTitle, $title) || str_contains($title, $candidateTitle) ? 40 : 0))
            + ($candidateArtist === $artist ? 100 : (str_contains($candidateArtist, $artist) || str_contains($artist, $candidateArtist) ? 40 : 0));
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower((string) preg_replace('/[\s　\-‐‑‒–—―:：・,，.．!?！？「」『』()（）［］【】]/u', '', trim($value)));
    }

    private function plainLyrics(mixed $syncedLyrics): ?string
    {
        if (! is_string($syncedLyrics) || trim($syncedLyrics) === '') {
            return null;
        }

        // LRCの時刻タグとメタデータタグを外し、歌詞カードで読める行だけにする。
        $plain = preg_replace('/^\s*\[(?:\d{2}:\d{2}(?:\.\d{1,3})?|(?:ar|al|ti|by|re|ve):[^\]]*)\]\s*/mi', '', $syncedLyrics);

        return is_string($plain) && trim($plain) !== '' ? trim($plain) : null;
    }
}
