<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\AuthMessages;
use Illuminate\Validation\Rule;

/**
 * カギの作り直し：メールアドレスの入力。登録済みのアドレスだけを受け付ける。
 */
class ForgotPasswordEmailRequest extends EmailRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['email'][] = Rule::exists(User::class, 'email');

        return $rules;
    }

    public function messages(): array
    {
        return parent::messages() + [
            'email.exists' => AuthMessages::EMAIL_NOT_FOUND,
        ];
    }
}
