<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMySongRequest;
use App\Http\Resources\MySongResource;
use App\Models\MySong;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MySongController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // マイリスト取得（本人のみ）
        $mySongs = MySong::with(['song', 'tags'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return MySongResource::collection($mySongs);
    }

    public function store(StoreMySongRequest $request): JsonResponse
    {
        // マイリストに曲を追加
        $mySong = MySong::create([
            'user_id' => auth()->id(),
            'song_id' => $request->song_id,
            'memo' => $request->memo,
        ]);

        // タグの付け外し（多対多）
        if ($request->has('tag_ids')) {
            $mySong->tags()->sync($request->tag_ids);
        }

        $mySong->load(['song', 'tags']);

        return response()->json(new MySongResource($mySong), 201);
    }

    public function update(StoreMySongRequest $request, MySong $mySong): JsonResponse
    {
        // マイリストのメモ・タグ編集（本人のみ）
        $this->authorize('update', $mySong); // Policy で本人確認

        if ($request->has('memo')) {
            $mySong->update(['memo' => $request->memo]);
        }

        if ($request->has('tag_ids')) {
            $mySong->tags()->sync($request->tag_ids);
        }

        $mySong->load(['song', 'tags']);

        return response()->json(new MySongResource($mySong));
    }

    public function destroy(MySong $mySong): JsonResponse
    {
        // マイリストから曲を削除（本人のみ）
        $this->authorize('delete', $mySong); // Policy で本人確認

        $mySong->tags()->detach(); // 中間テーブルも削除
        $mySong->delete();

        return response()->json(['message' => '削除しました。']);
    }
}
