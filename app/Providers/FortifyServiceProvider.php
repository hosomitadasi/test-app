<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Responses\LoginResponse;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

/**
 * Fortify にはログインとログアウトだけを任せる。
 * 会員登録・カギの作り直しは、確認番号を使う独自の画面（Auth\RegisterController など）で行う。
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ログインの入力チェックを、日本語の文言を持つ FormRequest に差し替える
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);

        // ログインできたら、お帰りなさいのダイアログを出してトップ画面へ進む
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => Inertia::render('Auth/Login'));

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }
}
