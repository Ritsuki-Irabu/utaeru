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
            'lyrics' => $this->lyrics,
            'opening_line' => $this->opening_line,
            'album' => $this->album,
            'artwork_url' => $this->artwork_url,
            'bpm' => $this->bpm,
            'duration_ms' => $this->duration_ms,
            'spotify_id' => $this->spotify_id,
            'playback_provider' => $this->playback_provider,
            'playback_key' => $this->playback_key,
            'playback_url' => $this->playback_url,
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
