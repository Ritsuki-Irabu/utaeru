<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'visibility',
        'share_token_hash',
        'shared_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'shared_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function playlistSongs(): HasMany
    {
        return $this->hasMany(PlaylistSong::class)->orderBy('position');
    }

    public function isShared(): bool
    {
        return $this->visibility === 'shared'
            && filled($this->share_token_hash)
            && is_null($this->revoked_at);
    }
}
