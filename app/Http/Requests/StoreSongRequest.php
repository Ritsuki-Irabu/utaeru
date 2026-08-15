<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSongRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // 曲マスタはuser画面でも使うため、保存前に必須項目とBPM範囲をここで守る
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['required', 'string', 'max:255'],
            // 歌詞は権利確認済みのテキストだけを管理者が登録する。外部サイトから自動転載しない。
            'lyrics' => ['nullable', 'string', 'max:20000'],
            'bpm' => ['nullable', 'integer', 'min:1', 'max:300'],
            'spotify_id' => ['nullable', 'string', 'max:100'],
            // 再生UIはYouTube公式プレイヤーに統一し、旧Spotify等の再生元は新規登録させない
            'playback_provider' => ['nullable', 'string', 'in:youtube'],
            'playback_key' => ['nullable', 'string', 'max:255'],
            'playback_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
