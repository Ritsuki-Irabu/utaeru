<?php

namespace App\Policies;

use App\Models\PlaylistSong;
use App\Models\User;

class PlaylistSongPolicy
{
    public function update(User $user, PlaylistSong $playlistSong): bool
    {
        return $user->id === $playlistSong->playlist?->user_id;
    }

    public function delete(User $user, PlaylistSong $playlistSong): bool
    {
        return $user->id === $playlistSong->playlist?->user_id;
    }
}
