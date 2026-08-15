<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TagController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TagResource::collection(Tag::orderBy('name')->get());
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        // 同名タグは共有し、連打や別画面からの追加でも重複を作らない。
        $tag = Tag::firstOrCreate(['name' => trim($request->string('name')->toString())]);

        return response()->json(new TagResource($tag), $tag->wasRecentlyCreated ? 201 : 200);
    }
}
