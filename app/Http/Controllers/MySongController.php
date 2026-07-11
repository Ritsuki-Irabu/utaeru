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
        // ログインユーザー本人のマイリストだけを取得する
        $mySongs = MySong::with(['song', 'tags'])
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return MySongResource::collection($mySongs);
    }

    public function store(StoreMySongRequest $request): JsonResponse
    {
        // ログインユーザーのIDを使い、自分のマイリストとして曲を追加する
        $mySong = MySong::create([
            'user_id' => auth()->id(),
            'song_id' => $request->song_id,
            'memo' => $request->memo,
        ]);

        // tag_ids が送られてきた場合だけ、中間テーブルのタグ紐付けを更新する
        if ($request->has('tag_ids')) {
            $mySong->tags()->sync($request->tag_ids);
        }

        $mySong->load(['song', 'tags']);

        return response()->json(new MySongResource($mySong), 201);
    }

    public function update(StoreMySongRequest $request, MySong $mySong): JsonResponse
    {
        // Policyで「このmy_songが本人のものか」を確認してから編集する
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
        // Policyで本人確認してから、タグ紐付けとマイリスト本体を削除する
        $this->authorize('delete', $mySong); // Policy で本人確認

        $mySong->tags()->detach(); // 中間テーブルも削除
        $mySong->delete();

        return response()->json(['message' => '削除しました。']);
    }
}
