<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1回ごとの練習結果を記録する。最後までやり終えた練習だけを記録する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // tap / long / swipe / flick / typing / chat
            $table->string('practice_type', 20);
            // easy / normal / hard
            $table->string('level', 10);
            $table->unsignedSmallInteger('score');
            $table->timestamp('played_at');
            $table->timestamps();

            $table->index(['user_id', 'played_at']);
            $table->index(['user_id', 'practice_type', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_records');
    }
};
