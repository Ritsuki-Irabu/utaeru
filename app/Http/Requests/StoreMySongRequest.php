<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMySongRequest extends FormRequest
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
            // 登録時はsong_id必須、更新時は送られてきた場合だけ検証する
            'song_id' => $this->isMethod('post')
                ? [
                    'required',
                    'exists:songs,id',
                    Rule::unique('my_songs', 'song_id')
                        ->where(fn ($query) => $query->where('user_id', auth()->id())),
                ]
                : ['sometimes', 'required', 'exists:songs,id'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'bpm' => ['nullable', 'integer', 'between:40,300'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
        ];
    }
}
