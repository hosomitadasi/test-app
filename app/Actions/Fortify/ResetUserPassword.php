<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * カギ（パスワード）を作り直す。
 *
 * 入力内容の確認は PasswordRequest で済ませてから呼ぶ。
 */
class ResetUserPassword implements ResetsUserPasswords
{
    /**
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
