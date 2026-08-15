<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Playlist;

class StorePlaylistSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $playlist = $this->route('playlist');
        $playlistId = $playlist instanceof Playlist ? $playlist->id : $playlist;

        return [
            'song_id' => [
                'required',
                'exists:songs,id',
                Rule::unique('playlist_songs', 'song_id')
                    ->where(fn ($query) => $query->where('playlist_id', $playlistId)),
            ],
            'position' => ['sometimes', 'integer', 'min:1'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
