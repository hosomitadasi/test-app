<?php

namespace App\Actions\Fortify;

use App\Support\AuthMessages;

trait PasswordValidationRules
{
    /**
     * カギ（パスワード）のルール。8文字以上で、アルファベットと数字を両方含む。
     *
     * @return array<int, string>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', 'min:8', 'max:255', 'regex:/[A-Za-z]/', 'regex:/[0-9]/', 'confirmed'];
    }

    /**
     * カギのルールに合わないときの文言。
     *
     * @return array<string, string>
     */
    protected function passwordMessages(): array
    {
        return [
            'password.required' => AuthMessages::PASSWORD_REQUIRED,
            'password.string' => AuthMessages::PASSWORD_RULE,
            'password.min' => AuthMessages::PASSWORD_RULE,
            'password.max' => AuthMessages::PASSWORD_RULE,
            'password.regex' => AuthMessages::PASSWORD_RULE,
            'password.confirmed' => AuthMessages::PASSWORD_CONFIRMATION,
        ];
    }
}
