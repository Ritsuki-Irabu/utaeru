<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\PlaylistSong;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlaylistService
{
    public function addSong(Playlist $playlist, array $attributes, User $user): PlaylistSong
    {
        return DB::transaction(function () use ($playlist, $attributes, $user): PlaylistSong {
            $songs = $this->lockedSongs($playlist);
            $maxPosition = $songs->count();
            $position = $this->normalizePosition($attributes['position'] ?? null, $maxPosition + 1);

            if ($position <= $maxPosition) {
                PlaylistSong::query()
                    ->where('playlist_id', $playlist->id)
                    ->where('position', '>=', $position)
                    ->increment('position');
            }

            return PlaylistSong::create([
                'playlist_id' => $playlist->id,
                'song_id' => $attributes['song_id'],
                'added_by_user_id' => $user->id,
                'position' => $position,
                'memo' => $attributes['memo'] ?? null,
            ]);
        });
    }

    public function updateSong(PlaylistSong $playlistSong, array $attributes): PlaylistSong
    {
        return DB::transaction(function () use ($playlistSong, $attributes): PlaylistSong {
            $songs = $this->lockedSongs($playlistSong->playlist);
            $oldPosition = $playlistSong->position;
            $newPosition = array_key_exists('position', $attributes)
                ? $this->normalizePosition($attributes['position'], $songs->count())
                : $oldPosition;

            if ($newPosition !== $oldPosition) {
                if ($newPosition < $oldPosition) {
                    PlaylistSong::query()
                        ->where('playlist_id', $playlistSong->playlist_id)
                        ->whereBetween('position', [$newPosition, $oldPosition - 1])
                        ->increment('position');
                } else {
                    PlaylistSong::query()
                        ->where('playlist_id', $playlistSong->playlist_id)
                        ->whereBetween('position', [$oldPosition + 1, $newPosition])
                        ->decrement('position');
                }
            }

            $playlistSong->update([
                'position' => $newPosition,
                'memo' => array_key_exists('memo', $attributes)
                    ? $attributes['memo']
                    : $playlistSong->memo,
            ]);

            return $playlistSong->refresh();
        });
    }

    public function deleteSong(PlaylistSong $playlistSong): void
    {
        DB::transaction(function () use ($playlistSong): void {
            $position = $playlistSong->position;
            $playlistId = $playlistSong->playlist_id;

            $playlistSong->delete();

            PlaylistSong::query()
                ->where('playlist_id', $playlistId)
                ->where('position', '>', $position)
                ->decrement('position');
        });
    }

    public function copyForUser(Playlist $source, User $user): Playlist
    {
        return DB::transaction(function () use ($source, $user): Playlist {
            $playlist = Playlist::create([
                'user_id' => $user->id,
                'name' => mb_substr($source->name . '（コピー）', 0, 100),
                'description' => $source->description,
                'visibility' => 'private',
            ]);

            $source->loadMissing('playlistSongs');

            foreach ($source->playlistSongs as $index => $sourceSong) {
                $playlist->playlistSongs()->create([
                    'song_id' => $sourceSong->song_id,
                    'added_by_user_id' => $user->id,
                    'position' => $index + 1,
                    'memo' => $sourceSong->memo,
                ]);
            }

            return $playlist->load(['user', 'playlistSongs.song']);
        });
    }

    private function lockedSongs(Playlist $playlist)
    {
        return PlaylistSong::query()
            ->where('playlist_id', $playlist->id)
            ->orderBy('position')
            ->lockForUpdate()
            ->get();
    }

    private function normalizePosition(?int $position, int $max): int
    {
        if ($position === null) {
            return $max;
        }

        return max(1, min($position, $max));
    }
}
