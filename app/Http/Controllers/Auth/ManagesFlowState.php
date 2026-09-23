<?php

namespace App\Http\Controllers\Auth;

use App\Support\AuthMessages;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 会員登録・カギの作り直しの途中経過を、セッションに持つ。
 *
 * 各段階の画面は1つの画面の中で切り替えるため、どこまで進んだかはここで確かめる。
 */
trait ManagesFlowState
{
    abstract protected function sessionKey(): string;

    protected function flowState(Request $request): array
    {
        return $request->session()->get($this->sessionKey(), []);
    }

    protected function putFlowState(Request $request, array $state): void
    {
        $request->session()->put($this->sessionKey(), $state);
    }

    /**
     * 必要な途中経過がそろっているか確かめる。
     * そろっていなければ、はじめからやり直してもらう（画面側は flow のエラーで最初の段階へ戻す）。
     *
     * @throws ValidationException
     */
    protected function requireFlowState(Request $request, array $keys): array
    {
        $state = $this->flowState($request);

        foreach ($keys as $key) {
            if (empty($state[$key])) {
                throw ValidationException::withMessages(['flow' => AuthMessages::FLOW_EXPIRED]);
            }
        }

        return $state;
    }
}
