<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\AuthMessages;
use Illuminate\Validation\Rule;

/**
 * 会員登録：メールアドレスの入力。まだ登録されていないアドレスだけを受け付ける。
 */
class RegisterEmailRequest extends EmailRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['email'][] = Rule::unique(User::class, 'email');

        return $rules;
    }

    public function messages(): array
    {
        return parent::messages() + [
            'email.unique' => AuthMessages::EMAIL_TAKEN,
        ];
    }
}
