<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = [
        'name',
    ];

    //リレーション
    public function mySongs():BelongsToMany//多対多
    {
        return $this->belongsToMany(MySong::class,'my_song_tag');
    }
}
