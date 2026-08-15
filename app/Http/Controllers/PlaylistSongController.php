<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlaylistSongRequest;
use App\Http\Requests\UpdatePlaylistSongRequest;
use App\Http\Resources\PlaylistSongResource;
use App\Models\Playlist;
use App\Models\PlaylistSong;
use App\Services\PlaylistService;
use Illuminate\Http\JsonResponse;

class PlaylistSongController extends Controller
{
    public function __construct(private readonly PlaylistService $playlistService) {}

    public function store(StorePlaylistSongRequest $request, Playlist $playlist): JsonResponse
    {
        $this->authorize('manageSongs', $playlist);

        $playlistSong = $this->playlistService->addSong(
            $playlist,
            $request->validated(),
            $request->user(),
        );

        return response()->json(
            new PlaylistSongResource($playlistSong->load('song')),
            201,
        );
    }

    public function update(
        UpdatePlaylistSongRequest $request,
        Playlist $playlist,
        PlaylistSong $playlistSong,
    ): JsonResponse {
        $this->assertBelongsToPlaylist($playlist, $playlistSong);
        $this->authorize('update', $playlistSong);

        $playlistSong = $this->playlistService->updateSong(
            $playlistSong,
            $request->validated(),
        );

        return response()->json(new PlaylistSongResource($playlistSong->load('song')));
    }

    public function destroy(
        Playlist $playlist,
        PlaylistSong $playlistSong,
    ): JsonResponse {
        $this->assertBelongsToPlaylist($playlist, $playlistSong);
        $this->authorize('delete', $playlistSong);
        $this->playlistService->deleteSong($playlistSong);

        return response()->json(['message' => 'プレイリストから曲を削除しました。']);
    }

    private function assertBelongsToPlaylist(Playlist $playlist, PlaylistSong $playlistSong): void
    {
        abort_unless($playlistSong->playlist_id === $playlist->id, 404);
    }
}
