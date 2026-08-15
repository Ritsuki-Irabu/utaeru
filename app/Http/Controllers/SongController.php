<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSongRequest;
use App\Http\Resources\SongResource;
use App\Models\Song;
use App\Services\BpmResolver;
use App\Services\SpotifyService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SongController extends Controller
{
    public function __construct(
        private SpotifyService $spotify,
        private BpmResolver $bpmResolver,
    )
    {
        // 外部API通信はControllerに直接書かず、SpotifyServiceへ任せる
    }

    public function index(): AnonymousResourceCollection
    {
        // 公開曲マスタをタイトル順で取得し、ResourceでJSON形式を揃える
        $songs = Song::orderBy('title')->paginate(20);

        return SongResource::collection($songs);
    }

    public function show(Song $song): JsonResponse
    {
        // 再生は曲詳細画面で行うため、詳細画面用の曲情報をResourceで返す
        return response()->json(new SongResource($song));
    }

    public function store(StoreSongRequest $request): JsonResponse
    {
        // FormRequestで検証済みの値だけを使って曲を登録する
        $song = Song::create($request->validated());

        return response()->json(new SongResource($song), 201);
    }

    public function update(StoreSongRequest $request, Song $song): JsonResponse
    {
        // ルートモデルバインディングで受け取った曲を、検証済みデータで更新する
        $song->update($request->validated());

        return response()->json(new SongResource($song));
    }

    public function refreshBpm(Song $song): JsonResponse
    {
        $bpm = $this->bpmResolver->resolve(
            $song->title,
            $song->artist,
            $song->duration_ms,
            $song->album,
        );

        if ($bpm === null) {
            return response()->json([
                'message' => '一致するBPMを取得できませんでした。管理画面から手動で補正してください。',
            ], 422);
        }

        $song->update(['bpm' => $bpm]);

        return response()->json(new SongResource($song->refresh()));
    }

    public function destroy(Song $song): JsonResponse
    {
        // admin専用ルートから呼ばれるため、ここでは曲マスタの削除処理に集中する
        $song->delete();

        return response()->json(['message' => '削除しました。']);
    }

    public function searchSpotify(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'max:100'],
        ]);

        try {
            // 検索キーワードをSpotifyServiceへ渡し、曲候補とBPMを取得する
            $results = $this->spotify->searchSong($request->q);
        } catch (RequestException|\RuntimeException) {
            return response()->json([
                'message' => 'Spotify APIとの通信に失敗しました。',
            ], 502);
        }

        return response()->json($results);
    }
}
