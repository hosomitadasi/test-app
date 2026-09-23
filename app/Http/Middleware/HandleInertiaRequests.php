<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * すべての画面に共通して渡す内容。
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                // 画面に出すのは名前だけ
                'user' => fn () => $request->user()?->only('name'),
            ],
            'flash' => [
                // 画面が切り替わった直後に出すダイアログの文言
                'dialog' => fn () => $request->session()->get('dialog'),
            ],
            // 自動ログアウトまでの時間（分）。画面を長く開いたままにしたときの確認に使う。
            'sessionLifetime' => (int) config('session.lifetime'),
        ]);
    }
}
