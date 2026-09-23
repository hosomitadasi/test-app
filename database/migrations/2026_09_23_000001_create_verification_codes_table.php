<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * メールで送る確認番号（6桁）を記録する。
 *
 * 会員登録と、カギ（パスワード）の作り直しの両方で使う。
 * 番号とメール内の URL の記号は、そのままでは保存せずハッシュにして持つ。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            // register（会員登録）または password_reset（カギの作り直し）
            $table->string('purpose', 20);
            // 会員登録のときだけ、メールの URL から続きを始められるよう名前も持つ
            $table->string('name')->nullable();
            $table->string('code_hash');
            $table->string('link_token_hash', 64)->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('link_used_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_codes');
    }
};
