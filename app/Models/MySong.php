<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MySong extends Model
{
    protected $fillable = [//DBへまとめて保存してよいカラム
        'user_id',
        'song_id',
        'memo',
        'bpm',
    ];
    
    //リレーション
    public function user(): BelongsTo
    {
        // このマイリスト曲を所有しているユーザー
        return $this->belongsTo(User::class);
    }

    public function song(): BelongsTo
    {
        // マイリストが参照している公開曲マスタ
        return $this->belongsTo(Song::class);
    }

    public function tags(): BelongsToMany
    {
        // マイリスト曲とタグは中間テーブル経由で多対多につながる
        return $this->belongsToMany(Tag::class, 'my_song_tag');
    }
}
