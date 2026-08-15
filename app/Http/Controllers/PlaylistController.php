<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlaylistRequest;
use App\Http\Requests\UpdatePlaylistRequest;
use App\Http\Resources\PlaylistResource;
use App\Models\Playlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PlaylistController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Playlist::class);

        $playlists = Playlist::query()
            ->where('user_id', auth()->id())
            ->withCount('playlistSongs')
            ->latest()
            ->get();

        return PlaylistResource::collection($playlists);
    }

    public function store(StorePlaylistRequest $request): JsonResponse
    {
        $this->authorize('create', Playlist::class);

        $playlist = Playlist::create([
            ...$request->validated(),
            'user_id' => auth()->id(),
            'visibility' => 'private',
        ]);

        return response()->json(
            new PlaylistResource($playlist->load(['user', 'playlistSongs.song'])),
            201,
        );
    }

    public function show(Playlist $playlist): JsonResponse
    {
        $this->authorize('view', $playlist);

        return response()->json(new PlaylistResource($playlist->load(['user', 'playlistSongs.song'])));
    }

    public function update(UpdatePlaylistRequest $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('update', $playlist);

        $attributes = $request->validated();

        if (($attributes['visibility'] ?? null) === 'private') {
            $attributes['share_token_hash'] = null;
            $attributes['shared_at'] = null;
            $attributes['revoked_at'] = now();
        } else {
            unset($attributes['visibility']);
        }

        $playlist->update($attributes);

        return response()->json(new PlaylistResource($playlist->refresh()->load(['user', 'playlistSongs.song'])));
    }

    public function destroy(Playlist $playlist): JsonResponse
    {
        $this->authorize('delete', $playlist);
        $playlist->delete();

        return response()->json(['message' => 'プレイリストを削除しました。']);
    }
}
