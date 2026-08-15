<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlaylistResource;
use App\Models\Playlist;
use App\Services\PlaylistService;
use App\Services\ShareTokenService;
use Illuminate\Http\JsonResponse;

class PlaylistShareController extends Controller
{
    public function __construct(
        private readonly ShareTokenService $shareTokenService,
        private readonly PlaylistService $playlistService,
    ) {}

    public function store(Playlist $playlist): JsonResponse
    {
        $this->authorize('share', $playlist);

        $token = $this->shareTokenService->issue();
        $playlist->update([
            'visibility' => 'shared',
            'share_token_hash' => $token['hash'],
            'shared_at' => now(),
            'revoked_at' => null,
        ]);

        return response()->json([
            'share_url' => rtrim(config('app.frontend_url'), '/') . '/shared/playlists/' . $token['plain'],
            'playlist' => new PlaylistResource($playlist->refresh()->load(['user', 'playlistSongs.song'])),
        ]);
    }

    public function destroy(Playlist $playlist): JsonResponse
    {
        $this->authorize('share', $playlist);

        $playlist->update([
            'visibility' => 'private',
            'share_token_hash' => null,
            'revoked_at' => now(),
        ]);

        return response()->json(['message' => '共有リンクを無効化しました。']);
    }

    public function show(string $token): JsonResponse
    {
        $playlist = $this->shareTokenService->findActivePlaylist($token);
        abort_if($playlist === null, 404);

        return response()->json(new PlaylistResource($playlist->load(['user', 'playlistSongs.song'])));
    }

    public function copy(string $token): JsonResponse
    {
        $source = $this->shareTokenService->findActivePlaylist($token);
        abort_if($source === null, 404);

        $playlist = $this->playlistService->copyForUser($source, request()->user());

        return response()->json(new PlaylistResource($playlist), 201);
    }
}
