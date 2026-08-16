<?php

namespace App\Http\Controllers;

use App\Contracts\VideoSearchProvider;
use App\Http\Requests\YoutubeSearchRequest;
use App\Models\Song;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\JsonResponse;

class YoutubeVideoController extends Controller
{
    public function __construct(private readonly VideoSearchProvider $provider)
    {
    }

    public function search(YoutubeSearchRequest $request): JsonResponse
    {
        $query = $request->string('q')->toString();

        // 曲IDが指定された場合は、クライアントの検索文字列を信用せず、
        // DBの曲タイトル・アーティストからYouTube検索語を組み立てる。
        if ($request->filled('song_id')) {
            $song = Song::query()->findOrFail($request->integer('song_id'));
            $query = $this->songQuery($song);
        }

        try {
            $results = $this->provider->search($query);
        } catch (\RuntimeException) {
            return response()->json([
                'message' => 'YouTube APIキーが未設定です。.envのYOUTUBE_API_KEYを設定してください。',
            ], 503);
        } catch (HttpClientException) {
            return response()->json([
                'message' => 'YouTube APIへの接続に失敗しました。',
            ], 502);
        }

        return response()->json(['data' => $results]);
    }

    private function songQuery(Song $song): string
    {
        $title = str_replace('"', '', trim($song->title));
        $artist = str_replace('"', '', trim($song->artist));

        return sprintf('"%s" "%s" official music video', $title, $artist);
    }
}
