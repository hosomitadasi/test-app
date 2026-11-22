<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 文字入力の練習（文字打ち練習・チャット型練習）で出す問題。
 *
 * 1行が1問。どの練習・どの出し方で使うかは practice_type と is_conversion で分ける。
 *   typing ＋ is_conversion=false … やさしい・ふつう。text（ひらがな）をそのまま入力する
 *   typing ＋ is_conversion=true  … むずかしい。text（読みがな）を answer の表記に変換して入力する
 *   chat   ＋ is_conversion=false … やさしい・ふつう。text の質問に自由に答える
 *   chat   ＋ is_conversion=true  … むずかしい。text の質問に answer_format の文字で答える
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            // typing / chat（practice_records.practice_type と同じ値）
            $table->string('practice_type', 20);
            // 漢字・カタカナへの変換を求める問題か（＝「むずかしい」で使う問題か）
            $table->boolean('is_conversion')->default(false);
            // 文字打ち練習では画面に出す読みがな、チャット型練習では相手からの質問
            $table->string('text');
            // 文字打ち練習「むずかしい」で入力してほしい表記。null なら text をそのまま入力する
            $table->string('answer')->nullable();
            // チャット型練習「むずかしい」で答えてほしい文字の種類（katakana / kanji）
            $table->string('answer_format', 10)->nullable();
            $table->timestamps();

            // 同じ問題を二重に登録しない（1回の練習の中で同じ問題が出ないようにするため）
            $table->unique(['practice_type', 'is_conversion', 'text']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
