<?php

namespace App\Http\Requests\Auth;

use App\Support\AuthMessages;
use Illuminate\Foundation\Http\FormRequest;

/**
 * 確認番号（6桁）の入力。会員登録・カギの作り直しで共通。
 *
 * ここでは形だけを確かめ、番号が合っているかはコントローラで確かめる
 * （まちがえた回数を数える必要があるため）。
 */
class VerificationCodeRequest extends FormRequest
{
    use NormalizesInput;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // 全角の数字や、途中に入った空白でもそのまま受け付ける
        $this->merge(['code' => $this->toHalfWidth($this->input('code'))]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => AuthMessages::CODE_REQUIRED,
            'code.string' => AuthMessages::CODE_MISMATCH,
            'code.digits' => AuthMessages::CODE_MISMATCH,
        ];
    }
}
