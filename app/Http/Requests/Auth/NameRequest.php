<?php

namespace App\Http\Requests\Auth;

use App\Support\AuthMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 会員登録：名前の入力。
 */
class NameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            // 前後の全角空白も取り除く（半角空白は TrimStrings で取り除かれる）
            $this->merge(['name' => preg_replace('/\A[\s　]+|[\s　]+\z/u', '', $this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => AuthMessages::NAME_REQUIRED,
            'name.string' => AuthMessages::NAME_REQUIRED,
            'name.max' => AuthMessages::NAME_TOO_LONG,
        ];
    }
}
