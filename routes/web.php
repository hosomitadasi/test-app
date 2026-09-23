<?php

use App\Http\Controllers\PracticeController;
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

// トップページはメニュー画面。ここから練習の種類をたどって各練習へ進む。
Route::get('/', [PracticeController::class, 'menu'])->name('practice.menu');

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

    // 文字打ち練習。画面操作の練習とは進み方が異なるため、別の系統として持つ。
    // （contents → difficulty → play。練習詳細画面は経由しない）
    Route::prefix('typing')->name('typing.')->group(function () {
        Route::get('/difficulty', [TypingPracticeController::class, 'difficulty'])
            ->name('difficulty');

        Route::get('/play/{level}', [TypingPracticeController::class, 'play'])
            ->whereIn('level', TypingPracticeController::LEVELS)
            ->name('play');
    });
});
