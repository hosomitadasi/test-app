<?php

namespace App\Http\Requests\Auth;

use App\Actions\Fortify\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * カギ（パスワード）の登録。会員登録・カギの作り直しで共通。
 */
class PasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => $this->passwordRules(),
        ];
    }

    public function messages(): array
    {
        return $this->passwordMessages();
    }
}
