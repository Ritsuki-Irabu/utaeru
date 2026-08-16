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
                'lyrics' => $this->song?->lyrics,
                'opening_line' => $this->song?->opening_line,
                'album' => $this->song?->album,
                'artwork_url' => $this->song?->artwork_url,
                'bpm' => $this->song?->bpm,
                'duration_ms' => $this->song?->duration_ms,
                'spotify_id' => $this->song?->spotify_id,
                'playback_provider' => $this->song?->playback_provider,
                'playback_key' => $this->song?->playback_key,
                'playback_url' => $this->song?->playback_url,
            ],
            'memo' => $this->memo,
            // 曲マスタのBPMとは別に、ユーザーがタップ計測した値を返す。
            'bpm' => $this->bpm,
            // tagsはCollectionなので、必要なid/nameだけに整形して返す
            'tags' => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d'),
        ];
    }
}
