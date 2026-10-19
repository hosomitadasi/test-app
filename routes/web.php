<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChatPracticeController;
use App\Http\Controllers\GrowthController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\PracticeRecordController;
use App\Http\Controllers\TopController;
use App\Http\Controllers\TypingPracticeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// トップ画面。ログイン前は会員登録・ログインへ、ログイン後は練習・成長の記録へ進む。
Route::get('/', [TopController::class, 'index'])->name('top');

// ログイン（GET/POST /login）とログアウト（POST /logout）は Fortify が用意する。

Route::middleware('guest')->group(function () {
    // 会員登録（名前 → メールアドレス → 確認番号 → カギ）。画面は1つで、段階ごとに送信する。
    Route::prefix('register')->name('register')->group(function () {
        Route::get('/', [RegisterController::class, 'show']);
        Route::post('/name', [RegisterController::class, 'storeName'])->name('.name');
        Route::post('/email', [RegisterController::class, 'storeEmail'])->name('.email');
        Route::post('/send-code', [RegisterController::class, 'sendCode'])->name('.send-code');
        Route::post('/verify', [RegisterController::class, 'verify'])->name('.verify');
        Route::post('/password', [RegisterController::class, 'storePassword'])->name('.password');
        // メールの URL。どのブラウザで開いても確認番号の入力から続けられる。
        Route::get('/continue/{token}', [RegisterController::class, 'continue'])->name('.continue');
    });

    // カギを忘れたときの作り直し（メールアドレス → 確認番号 → カギ）
    Route::prefix('forgot-password')->name('password.forgot')->group(function () {
        Route::get('/', [ForgotPasswordController::class, 'show']);
        Route::post('/email', [ForgotPasswordController::class, 'storeEmail'])->name('.email');
        Route::post('/send-code', [ForgotPasswordController::class, 'sendCode'])->name('.send-code');
        Route::post('/verify', [ForgotPasswordController::class, 'verify'])->name('.verify');
        Route::post('/password', [ForgotPasswordController::class, 'storePassword'])->name('.password');
        Route::get('/continue/{token}', [ForgotPasswordController::class, 'continue'])->name('.continue');
    });
});

// ここから下は、ログインしている人だけが使える
Route::middleware('auth')->group(function () {
    // メニュー画面（今日の練習）。ここから練習の種類をたどって各練習へ進む。
    Route::get('/menu', [PracticeController::class, 'menu'])->name('practice.menu');

    Route::prefix('practice')->name('practice.')->group(function () {
        // 練習を選ぶための画面（内容 → 詳細 → 難易度）
        Route::get('/contents', [PracticeController::class, 'contents'])->name('contents');
        Route::get('/details', [PracticeController::class, 'details'])->name('details');

        // 難易度選択。どの練習を選んだかを {type} で持ち回る。
        Route::get('/difficulty/{type}', [PracticeController::class, 'difficulty'])
            ->whereIn('type', PracticeController::PRACTICE_TYPES)
            ->name('difficulty');

        // 練習本体。練習の種類と難易度の組み合わせで1つの画面を使い回す。
        Route::get('/play/{type}/{level}', [PracticeController::class, 'play'])
            ->whereIn('type', PracticeController::PRACTICE_TYPES)
            ->whereIn('level', PracticeController::LEVELS)
            ->name('play');

        // 文字入力の練習。画面操作の練習とは出題も進み方も異なるため、別の系統として持つ。
        // （contents → details → difficulty → play。詳細画面で「文字打ち」か「チャット型」を選ぶ）
        Route::prefix('typing')->name('typing.')->group(function () {
            Route::get('/details', [TypingPracticeController::class, 'details'])
                ->name('details');

            // 文字打ち練習
            Route::get('/difficulty', [TypingPracticeController::class, 'difficulty'])
                ->name('difficulty');

            Route::get('/play/{level}', [TypingPracticeController::class, 'play'])
                ->whereIn('level', TypingPracticeController::LEVELS)
                ->name('play');

            // チャット型練習
            Route::prefix('chat')->name('chat.')->group(function () {
                Route::get('/difficulty', [ChatPracticeController::class, 'difficulty'])
                    ->name('difficulty');

                Route::get('/play/{level}', [ChatPracticeController::class, 'play'])
                    ->whereIn('level', ChatPracticeController::LEVELS)
                    ->name('play');
            });
        });
    });

    // 練習をやり終えたときの記録
    Route::post('/practice-records', [PracticeRecordController::class, 'store'])->name('practice-records.store');

    // 成長の記録
    Route::get('/growth', [GrowthController::class, 'index'])->name('growth.index');
    Route::get('/growth/history', [GrowthController::class, 'history'])->name('growth.history');
});
