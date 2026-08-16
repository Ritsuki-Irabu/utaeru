<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    protected function casts(): array
    {
        return [
            'bpm' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    protected $fillable = [//DBへまとめて保存してよいカラム
        'title',
        'artist',
        'lyrics',
        'album',
        'artwork_url',
        'bpm',
        'duration_ms',
        'spotify_id',
        'playback_provider',
        'playback_key',
        'playback_url',
    ];

    /**
     * 歌詞カードと一覧で共通利用する歌いだし。
     * LRC形式の時刻タグが保存されている場合も、表示時に除去する。
     */
    public function getOpeningLineAttribute(): ?string
    {
        if (! is_string($this->lyrics) || trim($this->lyrics) === '') {
            return null;
        }

        foreach (preg_split('/\r?\n/u', $this->lyrics) ?: [] as $line) {
            $line = trim((string) preg_replace('/^\s*(?:\[\d{2}:\d{2}(?:\.\d{1,3})?\]\s*)+/u', '', $line));

            if ($line !== '') {
                return mb_strlen($line) > 90 ? mb_substr($line, 0, 90).'…' : $line;
            }
        }

        return null;
    }
    
    //リレーション
    public function mySongs(): HasMany
    {
        // 1つの公開曲は、複数ユーザーのマイリストから参照される
        return $this->hasMany(MySong::class);
    }

    public function playlistSongs(): HasMany
    {
        return $this->hasMany(PlaylistSong::class);
    }
}
