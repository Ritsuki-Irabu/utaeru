<?php

namespace App\Services;

use App\Models\Playlist;

class ShareTokenService
{
    /**
     * @return array{plain: string, hash: string}
     */
    public function issue(): array
    {
        $plain = bin2hex(random_bytes(32));

        return [
            'plain' => $plain,
            'hash' => hash('sha256', $plain),
        ];
    }

    public function findActivePlaylist(string $plainToken): ?Playlist
    {
        return Playlist::query()
            ->where('share_token_hash', hash('sha256', $plainToken))
            ->where('visibility', 'shared')
            ->whereNull('revoked_at')
            ->first();
    }
}
