<?php

namespace App\Services;

use App\Contracts\VideoSearchProvider;
use Illuminate\Support\Facades\Http;

class YoutubeVideoProvider implements VideoSearchProvider
{
    private string $baseUrl = 'https://www.googleapis.com/youtube/v3';

    /**
     * 曲名から、ウタエル内に埋め込める動画だけを候補にする。
     * 動画本体は保存せず、MVボタン押下時に最初の候補だけを返す。
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $query): array
    {
        $key = config('services.youtube.key');

        if (! is_string($key) || $key === '') {
            throw new \RuntimeException('YOUTUBE_API_KEY is not configured.');
        }

        $searchQuery = trim($query);

        if (! preg_match('/official\s*(?:music\s*)?(?:video|mv)|公式\s*(?:ミュージックビデオ|MV)/iu', $searchQuery)) {
            $searchQuery .= ' official music video';
        }

        $searchParameters = [
                'part' => 'snippet',
                'q' => $searchQuery,
                'type' => 'video',
                'maxResults' => config('services.youtube.max_results', 10),
                'regionCode' => config('services.youtube.region', 'JP'),
                'videoCategoryId' => '10',
                'videoEmbeddable' => 'true',
                // モバイル・アプリ内プレイヤーでも再生可能な候補に限定する。
                'videoSyndicated' => 'true',
                'key' => $key,
        ];

        $response = Http::acceptJson()
            ->timeout(8)
            ->get("{$this->baseUrl}/search", $searchParameters);

        $response->throw();

        $items = collect($response->json('items', []))
            ->filter(fn (array $item): bool => filled(data_get($item, 'id.videoId')))
            ->values();

        // 音楽カテゴリが未設定の公式MVもあるため、カテゴリ指定で空になった場合だけ再検索する。
        if ($items->isEmpty()) {
            unset($searchParameters['videoCategoryId']);

            $fallbackResponse = Http::acceptJson()
                ->timeout(8)
                ->get("{$this->baseUrl}/search", $searchParameters);

            $fallbackResponse->throw();
            $items = collect($fallbackResponse->json('items', []))
                ->filter(fn (array $item): bool => filled(data_get($item, 'id.videoId')))
                ->values();
        }

        if ($items->isEmpty()) {
            return [];
        }

        // 曲IDから生成した検索語には曲名を引用符で含める。
        // YouTubeの順位だけに任せると同じアーティストの別曲が混ざるため、
        // 曲名をタイトルに含む候補がある場合は、それ以外を候補から除外する。
        $requestedTitle = $this->requestedTitle($searchQuery);

        if ($requestedTitle !== null) {
            $normalizedTitle = $this->normalizeCandidateText($requestedTitle);
            $titleMatches = $items->filter(function (array $item) use ($normalizedTitle): bool {
                $candidateTitle = $this->normalizeCandidateText((string) data_get($item, 'snippet.title', ''));

                return $normalizedTitle !== '' && str_contains($candidateTitle, $normalizedTitle);
            })->values();

            // 候補が1件も曲名に一致しない場合は、別曲を返さず空結果にする。
            if ($titleMatches->isEmpty()) {
                return [];
            }

            $items = $titleMatches;
        }

        // search APIの埋め込み可否は動画削除・設定変更後に古くなることがあるため、
        // 採用前にvideos APIで現在の公開状態と埋め込み可否を再確認する。
        $videoIds = $items->pluck('id.videoId')->implode(',');
        $statusResponse = Http::acceptJson()
            ->timeout(8)
            ->get("{$this->baseUrl}/videos", [
                'part' => 'status',
                'id' => $videoIds,
                'key' => $key,
            ]);

        $statusResponse->throw();

        $availableVideoIds = collect($statusResponse->json('items', []))
            ->filter(function (array $item): bool {
                return data_get($item, 'status.embeddable') === true
                    && data_get($item, 'status.uploadStatus') === 'processed'
                    && data_get($item, 'status.privacyStatus') === 'public';
            })
            ->pluck('id')
            ->flip();

        return $items
            ->filter(fn (array $item): bool => isset($availableVideoIds[data_get($item, 'id.videoId')]))
            // YouTubeの検索順位だけに依存せず、公式MV・レコーディング音源を常に優先する。
            ->sort(function (array $left, array $right) use ($searchQuery): int {
                $scoreDifference = $this->candidateScore($right, $searchQuery) <=> $this->candidateScore($left, $searchQuery);

                if ($scoreDifference !== 0) {
                    return $scoreDifference;
                }

                // APIの返却順が変わっても同じ候補を選ぶため、最後は公開情報を固定キーで比較する。
                return strcmp(
                    mb_strtolower((string) data_get($left, 'snippet.title', '').'|'.data_get($left, 'id.videoId', '')),
                    mb_strtolower((string) data_get($right, 'snippet.title', '').'|'.data_get($right, 'id.videoId', '')),
                );
            })
            ->map(function (array $item): array {
                $videoId = data_get($item, 'id.videoId');

                return [
                    'provider' => 'youtube',
                    'video_id' => $videoId,
                    'title' => data_get($item, 'snippet.title', ''),
                    'channel_title' => data_get($item, 'snippet.channelTitle', ''),
                    'thumbnail_url' => data_get($item, 'snippet.thumbnails.medium.url')
                        ?? data_get($item, 'snippet.thumbnails.default.url'),
                    'watch_url' => $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null,
                    'embed_url' => $videoId ? "https://www.youtube.com/embed/{$videoId}" : null,
                    'is_embeddable' => true,
                    'bpm' => $this->extractBpm(
                        (string) data_get($item, 'snippet.title', '').' '.
                        (string) data_get($item, 'snippet.description', ''),
                    ),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * YouTubeには音声波形を取得するAPIがないため、動画のメタデータに明記された値だけを補完に使う。
     * 実音源を解析したBPMではないので、曲マスタへの自動登録は他プロバイダより後順位にする。
     */
    public function findBpmFromMetadata(string $title, string $artist): ?int
    {
        $candidates = $this->search(sprintf('"%s" "%s" BPM', trim($title), trim($artist)));

        foreach ($candidates as $candidate) {
            $bpm = $candidate['bpm'] ?? null;

            if (is_int($bpm) && $bpm >= 40 && $bpm <= 300) {
                return $bpm;
            }
        }

        return null;
    }

    /**
     * 公式MV・レコーディング音源を優先するための候補スコア。
     * 候補が少ない場合に備えてライブ映像も返すが、同一検索では必ず後順位にする。
     */
    private function candidateScore(array $item, string $query = ''): int
    {
        $title = (string) data_get($item, 'snippet.title', '');
        $channel = (string) data_get($item, 'snippet.channelTitle', '');
        $text = mb_strtolower("{$title} {$channel}");
        $score = 0;

        foreach (['official music video', 'official video', 'official mv', 'music video', 'ミュージックビデオ', '公式mv', '公式'] as $keyword) {
            if (str_contains($text, $keyword)) {
                $score += match ($keyword) {
                    'official music video', 'official video', 'official mv', 'ミュージックビデオ', '公式mv' => 100,
                    'music video' => 75,
                    default => 35,
                };
            }
        }

        // フロントエンドは曲名・アーティスト名を引用符で渡すため、先頭の引用句を曲名として最優先する。
        // 同じアーティストの別曲が多い検索では、チャンネル一致より曲名一致を強くする必要がある。
        $titlePhrase = $this->requestedTitle($query);

        if (is_string($titlePhrase) && trim($titlePhrase) !== '') {
            $normalizedTitlePhrase = $this->normalizeCandidateText($titlePhrase);
            $normalizedCandidateTitle = $this->normalizeCandidateText($title);

            if ($normalizedTitlePhrase !== '' && str_contains($normalizedCandidateTitle, $normalizedTitlePhrase)) {
                $score += $normalizedCandidateTitle === $normalizedTitlePhrase ? 360 : 240;
            }
        }

        // 曲名・アーティスト名を引用符付きで検索し、公式チャンネル名との一致を優先する。
        $terms = preg_split(
            '/\s+/u',
            trim(preg_replace('/\b(?:official|music|video|mv)\b/iu', '', $query) ?? $query),
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        foreach ($terms as $term) {
            $term = trim($term, "\"'「」『』");

            if (mb_strlen($term) < 2) {
                continue;
            }

            $normalizedTerm = mb_strtolower($term);
            $score += str_contains(mb_strtolower($channel), $normalizedTerm) ? 80 : 0;
            $score += str_contains(mb_strtolower($title), $normalizedTerm) ? 35 : 0;
        }

        foreach (['vevo', 'official channel', '公式チャンネル', 'records', 'record label', 'レコード'] as $keyword) {
            if (str_contains($text, $keyword)) {
                $score += 80;
            }
        }

        foreach (['live', 'ライブ', 'concert', 'コンサート', 'tour', 'ツアー', 'performance', 'session', 'acoustic', 'アコースティック', 'cover', 'カバー', 'karaoke', 'カラオケ', 'reaction', 'リアクション', 'instrumental', 'インスト', 'shorts', '#shorts', 'ショート', '縦型', '弾いてみた', '歌ってみた', '演奏', 'ボイストレーナー', '歌い方', '解説', 'レッスン', '練習', '歌唱', 'flute', 'piano', 'guitar', 'duet', 'デュエット', '一人で', 'ボカロ', '替え歌', 'ランキング', 'how to play', '歌詞あり', 'color-coded'] as $keyword) {
            if (str_contains($text, $keyword)) {
                $score -= 1500;
            }
        }

        if (str_contains($text, 'official audio') || str_contains($text, 'オフィシャルオーディオ')) {
            $score += 55;
        }

        if (str_contains($text, 'lyric') || str_contains($text, '歌詞')) {
            $score -= 25;
        }

        return $score;
    }

    private function normalizeCandidateText(string $value): string
    {
        $normalized = mb_convert_kana(trim($value), 'asKV', 'UTF-8');

        return mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized));
    }

    private function requestedTitle(string $query): ?string
    {
        preg_match_all('/"([^"]+)"|「([^」]+)」|『([^』]+)』/u', $query, $phraseMatches, PREG_SET_ORDER);

        return $phraseMatches[0][1] ?? $phraseMatches[0][2] ?? $phraseMatches[0][3] ?? null;
    }

    private function extractBpm(string $text): ?int
    {
        $patterns = [
            '/\b(?:bpm|tempo|テンポ)\s*[:：]?\s*(\d{2,3})(?!\d)/iu',
            '/\b(\d{2,3})\s*(?:bpm|拍\s*\/\s*分)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match($pattern, $text, $matches)) {
                continue;
            }

            $bpm = (int) ($matches[1] ?? 0);

            if ($bpm >= 40 && $bpm <= 300) {
                return $bpm;
            }
        }

        return null;
    }
}
