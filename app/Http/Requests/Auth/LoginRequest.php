<?php

namespace App\Http\Requests\Auth;

use App\Support\AuthMessages;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

/**
 * ログイン。Fortify のログイン処理が使う FormRequest を、この内容に差し替える。
 * （FortifyServiceProvider で差し替えている）
 */
class LoginRequest extends FortifyLoginRequest
{
    use NormalizesInput;

    protected function prepareForValidation(): void
    {
        $this->merge([Fortify::username() => $this->toHalfWidth($this->input(Fortify::username()))]);
    }

    public function rules()
    {
        return [
            Fortify::username() => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            Fortify::username().'.required' => AuthMessages::LOGIN_REQUIRED,
            Fortify::username().'.string' => AuthMessages::LOGIN_REQUIRED,
            'password.required' => AuthMessages::LOGIN_REQUIRED,
            'password.string' => AuthMessages::LOGIN_REQUIRED,
        ];
    }
}
