<?php

namespace App\Http\Requests\Auth;

use App\Support\AuthMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * メールアドレスの入力（会員登録・カギの作り直しで共通の部分）。
 */
abstract class EmailRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => $this->toHalfWidth($this->input('email'))]);
    }

    public function rules(): array
    {
        return [
            // 改行を含むアドレスはメールの宛先に使わせない（email ルールの既知の不具合への対策）
            'email' => ['required', 'string', 'max:255', 'not_regex:/[\r\n]/', 'email:rfc'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => AuthMessages::EMAIL_REQUIRED,
            'email.string' => AuthMessages::EMAIL_INVALID,
            'email.max' => AuthMessages::EMAIL_INVALID,
            'email.not_regex' => AuthMessages::EMAIL_INVALID,
            'email.email' => AuthMessages::EMAIL_INVALID,
        ];
    }
}
