<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSongRequest;
use App\Http\Resources\SongResource;
use App\Models\Song;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SongController extends Controller
{
    public function index(): AnonymousResourceCollection
    {// 曲一覧取得
        $songs = Song::orderBy('title')->paginate(20);

        return SongResource::collection($songs);
    }

    public function store(StoreSongRequest $request): JsonResponse
    {// 曲登録
        $song = Song::create($request->validated());

        return response()->json(new SongResource($song), 201);
    }

    public function update(StoreSongRequest $request, Song $song): JsonResponse
    {// 曲編集
        $song->update($request->validated());

        return response()->json(new SongResource($song));
    }

    public function destroy(Song $song): JsonResponse
    {// 曲削除
        $song->delete();

        return response()->json(['message' => '削除しました。']);
    }
}
