<?php

namespace App\Exceptions;

use App\Support\AuthMessages;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // 自動ログアウト（セッション切れ）のあとに画面から送信したときは、
        // エラー画面ではなくトップ画面へ戻し、理由をダイアログで伝える。
        $this->renderable(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419 && !$request->expectsJson()) {
                return redirect()->route('top')->with('dialog', AuthMessages::SESSION_EXPIRED);
            }

            return null;
        });
    }
}
