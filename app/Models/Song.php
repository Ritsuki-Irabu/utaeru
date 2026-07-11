<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Song extends Model
{
    protected $fillable = [//DBへまとめて保存してよいカラム
        'title',
        'artist',
        'bpm',
        'spotify_id',
    ];
    
    //リレーション
    public function mySongs(): HasMany
    {
        // 1つの公開曲は、複数ユーザーのマイリストから参照される
        return $this->hasMany(MySong::class);
    }
}
