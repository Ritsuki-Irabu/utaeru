<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class YoutubeSearchRequest extends FormRequest
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
        return [
            // song_id指定時は、サーバー側で曲マスタのタイトル・アーティストから検索語を生成する。
            'song_id' => ['nullable', 'integer', 'exists:songs,id', 'required_without:q'],
            // 既存の汎用検索APIとの互換性のため、q単体での検索も引き続き許可する。
            'q' => ['nullable', 'string', 'min:2', 'max:100', 'required_without:song_id'],
        ];
    }
}
