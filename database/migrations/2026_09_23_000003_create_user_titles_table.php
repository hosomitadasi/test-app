<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 利用者が獲得した称号を記録する。
 *
 * 称号そのもの（名前・説明・獲得条件）は config/growth.php に持ち、
 * ここには「いつ、どの称号を獲得したか」だけを残す。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title_code', 50);
            $table->timestamp('acquired_at');
            $table->timestamps();

            $table->unique(['user_id', 'title_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_titles');
    }
};
