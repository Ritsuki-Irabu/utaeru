<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MySongResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // フロントで扱いやすいよう、関連する曲情報をsongの中にまとめて返す
            'song' => [
                'id' => $this->song?->id,
                'title' => $this->song?->title,
                'artist' => $this->song?->artist,
                'bpm' => $this->song?->bpm,
                'spotify_id' => $this->song?->spotify_id,
            ],
            'memo' => $this->memo,
            // tagsはCollectionなので、必要なid/nameだけに整形して返す
            'tags' => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
