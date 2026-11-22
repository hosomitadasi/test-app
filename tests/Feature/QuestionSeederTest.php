<?php

namespace Tests\Feature;

use App\Http\Controllers\ChatPracticeController;
use App\Models\Question;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 文字入力の練習で出す、最初の問題の登録を確認する。
 */
class QuestionSeederTest extends TestCase
{
    use RefreshDatabase;

    /** 50〜100問を、練習の種類・出し方ごとに登録すること */
    public function test_seeder_registers_initial_questions(): void
    {
        $this->seed(QuestionSeeder::class);

        $total = Question::count();
        $this->assertGreaterThanOrEqual(50, $total);
        $this->assertLessThanOrEqual(100, $total);

        // やさしいの文字打ちは10問、チャット型は最大5問答えるため、それより多く用意する
        $this->assertGreaterThan(10, Question::forPractice('typing', false)->count());
        $this->assertGreaterThan(5, Question::forPractice('typing', true)->count());
        $this->assertGreaterThan(5, Question::forPractice('chat', false)->count());
        $this->assertGreaterThan(5, Question::forPractice('chat', true)->count());
    }

    /** 何度実行しても、同じ問題が二重に登録されないこと */
    public function test_seeder_can_run_twice_without_duplicates(): void
    {
        $this->seed(QuestionSeeder::class);
        $count = Question::count();

        $this->seed(QuestionSeeder::class);

        $this->assertSame($count, Question::count());
    }

    /** 登録した問題が、それぞれの出し方に必要な値を持っていること */
    public function test_seeded_questions_have_the_needed_values(): void
    {
        $this->seed(QuestionSeeder::class);

        foreach (Question::forPractice('typing', true)->get() as $question) {
            $this->assertNotEmpty($question->answer, $question->text.' に入力してほしい表記がありません');
        }
        foreach (Question::forPractice('chat', true)->get() as $question) {
            $this->assertContains($question->answer_format, ['katakana', 'kanji']);
        }
        foreach (Question::where('practice_type', 'chat')->get() as $question) {
            foreach (ChatPracticeController::FORBIDDEN_WORDS as $word) {
                $this->assertStringNotContainsString($word, $question->text);
            }
        }
    }
}
