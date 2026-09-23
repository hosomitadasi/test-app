<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * 会員登録の最後に、利用者を作る。
 *
 * 入力内容の確認は、各段階の FormRequest（名前・メールアドレス・確認番号・カギ）で済ませてから呼ぶ。
 */
class CreateNewUser implements CreatesNewUsers
{
    /**
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        // 確認番号でメールアドレスを確かめ終えているため、確認済みとして記録する
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
