<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongResource extends JsonResource
    // SongResource は、Song モデルをAPIレスポンス用のJSON形式に整えるクラス
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {// 返すJSONの形を揃える
        return [
            'id' => $this->id,
            'title' => $this->title,
            'artist' => $this->artist,
            'bpm' => $this->bpm,
            'spotify_id' => $this->spotify_id,
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
