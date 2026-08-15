<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaylistSongResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'song' => new SongResource($this->whenLoaded('song')),
            'added_by_user_id' => $this->added_by_user_id,
            'position' => $this->position,
            'memo' => $this->memo,
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
