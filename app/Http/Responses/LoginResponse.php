<?php

namespace App\Http\Responses;

use App\Support\AuthMessages;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * ログインできたときは、トップ画面（ログイン後）へ進み、お帰りなさいのダイアログを出す。
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        return redirect()->route('top')->with('dialog', AuthMessages::loggedIn($request->user()->name));
    }
}
