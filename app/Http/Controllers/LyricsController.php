<?php

namespace App\Http\Controllers;

use App\Contracts\LyricsProvider;
use App\Models\Song;
use Illuminate\Http\JsonResponse;

class LyricsController extends Controller
{
    public function __construct(private readonly LyricsProvider $provider)
    {
    }

    public function show(Song $song): JsonResponse
    {
        // 管理者が登録した権利確認済み歌詞を最優先し、外部APIは補助として扱う。
        if (is_string($song->lyrics) && trim($song->lyrics) !== '') {
            return response()->json([
                'data' => [
                    'lyrics' => trim($song->lyrics),
                    'opening_line' => $song->opening_line,
                    'source_url' => null,
                    'source' => 'registered',
                ],
            ]);
        }

        try {
            $lyrics = $this->provider->find($song->title, $song->artist);
        } catch (\Throwable) {
            return response()->json([
                'message' => '歌詞サービスに接続できませんでした。',
            ], 502);
        }

        if ($lyrics === null) {
            return response()->json([
                'data' => null,
                'message' => '利用許諾済みの歌詞データが見つかりませんでした。',
            ], 404);
        }

        $lyricsText = trim((string) ($lyrics['lyrics'] ?? ''));

        if ($lyricsText !== '') {
            // 詳細画面で取得した歌詞を曲マスタへ反映し、検索・お気に入り一覧でも
            // 同じ歌いだしを再利用できるようにする。
            $song->forceFill(['lyrics' => $lyricsText])->save();
        }

        return response()->json([
            'data' => [
                ...$lyrics,
                'lyrics' => $lyricsText,
                'opening_line' => $song->opening_line,
                'source' => 'licensed_provider',
            ],
        ]);
    }
}
