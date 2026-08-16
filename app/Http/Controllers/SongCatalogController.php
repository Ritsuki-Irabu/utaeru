<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportSongRequest;
use App\Http\Requests\SearchSongsRequest;
use App\Http\Resources\SongResource;
use App\Services\MusicCatalogService;
use Illuminate\Http\JsonResponse;

class SongCatalogController extends Controller
{
    public function __construct(private readonly MusicCatalogService $catalog) {}

    public function search(SearchSongsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->catalog->search(
                $request->string('q')->toString(),
                $request->string('field')->toString() ?: 'all',
            ),
        ]);
    }

    public function import(ImportSongRequest $request): JsonResponse
    {
        $song = $this->catalog->import(
            $request->string('provider')->toString(),
            $request->string('provider_key')->toString(),
        );

        return response()->json(new SongResource($song), 201);
    }
}
