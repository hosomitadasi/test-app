<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * 文字打ち練習の画面遷移と、難易度ごとの出題内容を確認する。
 */
class TypingPracticeTest extends TestCase
{
    /** 難易度（URL の {level}）と画面に出す名前 */
    private const LEVELS = [
        'easy' => 'やさしい',
        'normal' => 'ふつう',
        'hard' => 'むずかしい',
    ];

    /** 練習内容選択画面から難易度選択画面へ進めること */
    public function test_contents_links_to_the_difficulty_screen(): void
    {
        $this->get(route('practice.contents'))
            ->assertOk()
            ->assertSee('href="'.route('practice.typing.difficulty').'"', false)
            ->assertSee('文字入力の練習をする');
    }

    /** 難易度選択画面から3つの難易度へ進めること */
    public function test_difficulty_screen_links_to_every_level(): void
    {
        $difficulty = $this->get(route('practice.typing.difficulty'))
            ->assertOk()
            ->assertSee('文字打ち練習');

        foreach (self::LEVELS as $level => $label) {
            $difficulty->assertSee('href="'.route('practice.typing.play', ['level' => $level]).'"', false);
            $difficulty->assertSee($label);
        }
    }

    /** 「練習を選び直す」が想定どおりの画面へ戻ること */
    public function test_back_links_point_to_the_expected_screen(): void
    {
        // 文字打ち練習は練習詳細画面を経由しないため、練習内容選択画面へ戻る
        $this->get(route('practice.typing.difficulty'))
            ->assertSee('href="'.route('practice.contents').'"', false);

        $play = $this->get(route('practice.typing.play', ['level' => 'normal']));
        // 開始画面・中断画面からは難易度選択画面へ戻る
        $play->assertSee('href="'.route('practice.typing.difficulty').'"', false);
        // 結果画面からはメニュー画面まで戻る
        $play->assertSee('href="'.route('practice.menu').'"', false);
    }

    /** 練習画面に中断・制限時間・得点・入力欄・回答ボタンがあること */
    public function test_play_screen_has_the_expected_parts(): void
    {
        $play = $this->get(route('practice.typing.play', ['level' => 'normal']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('中断する', $play);
        $this->assertStringContainsString('id="time-value"', $play);
        $this->assertStringContainsString('000点', $play);
        $this->assertStringContainsString('id="typing-prompt"', $play);
        $this->assertStringContainsString('id="typing-input"', $play);
        $this->assertStringContainsString('回答です', $play);
        // 端末のキーボードを使うため、アプリ内でキーボードは作らない
        $this->assertStringContainsString('type="text"', $play);
    }

    /** 難易度ごとの設定値が練習画面へ渡っていること */
    public function test_level_settings_are_passed_to_the_screen(): void
    {
        // やさしい: 制限時間なし・10問で終わり・ヒントあり
        $easy = $this->get(route('practice.typing.play', ['level' => 'easy']))->getContent();
        $this->assertStringContainsString('timeLimitSeconds: 0,', $easy);
        $this->assertStringContainsString('requiredCount: 10,', $easy);
        $this->assertStringContainsString('hasHint: true,', $easy);
        $this->assertStringContainsString('isConversion: false,', $easy);
        $this->assertStringContainsString('hintIntervalMs: 10000,', $easy);
        $this->assertStringContainsString('のこり', $easy);
        $this->assertStringContainsString('id="typing-hint"', $easy);

        // ふつう: 制限時間3分・問題数の制限なし・ヒントなし
        $normal = $this->get(route('practice.typing.play', ['level' => 'normal']))->getContent();
        $this->assertStringContainsString('timeLimitSeconds: 180,', $normal);
        $this->assertStringContainsString('requiredCount: 0,', $normal);
        $this->assertStringContainsString('hasHint: false,', $normal);
        $this->assertStringContainsString('isConversion: false,', $normal);
        $this->assertStringContainsString('制限時間', $normal);
        $this->assertStringNotContainsString('id="typing-hint"', $normal);

        // むずかしい: 制限時間3分・漢字やカタカナへの変換
        $hard = $this->get(route('practice.typing.play', ['level' => 'hard']))->getContent();
        $this->assertStringContainsString('timeLimitSeconds: 180,', $hard);
        $this->assertStringContainsString('hasHint: false,', $hard);
        $this->assertStringContainsString('isConversion: true,', $hard);
        $this->assertStringContainsString('id="typing-target"', $hard);
        $this->assertStringNotContainsString('id="typing-hint"', $hard);
    }

    /** やさしい・ふつうは、ひらがな10文字以内の言葉を出題すること */
    public function test_kana_questions_are_short_hiragana_words(): void
    {
        foreach (['easy', 'normal'] as $level) {
            foreach ($this->questionsOf($level) as $question) {
                $this->assertMatchesRegularExpression('/\A[ぁ-ゖー]+\z/u', $question['prompt']);
                $this->assertLessThanOrEqual(10, mb_strlen($question['prompt']));
                // 読みをそのまま入力すれば正解になる
                $this->assertSame($question['prompt'], $question['answer']);
            }
        }
    }

    /** やさしいだけ、時間経過で出すヒントを持っていること */
    public function test_only_easy_level_has_hints(): void
    {
        foreach ($this->questionsOf('easy') as $question) {
            // 10秒ごとに1段階ずつ出すため、2段階分を用意する
            $this->assertCount(2, $question['hints']);
            foreach ($question['hints'] as $hint) {
                $this->assertNotSame('', $hint);
            }
        }

        foreach (['normal', 'hard'] as $level) {
            foreach ($this->questionsOf($level) as $question) {
                $this->assertSame([], $question['hints']);
            }
        }
    }

    /** ヒントが、押すボタンと濁点の打ち方を順に伝えること */
    public function test_hint_explains_which_button_to_press(): void
    {
        $question = $this->findQuestion('easy', 'りんご');

        $this->assertSame(
            '「り」は「ら」と書かれているボタン、「ん」は「わ」と書かれているボタン、'
                .'「ご」はまず「か」と書かれているボタンを押して「こ」を入れてみてください。',
            $question['hints'][0]
        );

        $this->assertSame(
            '「ご」は「こ」と打った後、「ま」と書かれたボタンの下にあるボタンを押してみてください。',
            $question['hints'][1]
        );
    }

    /** 濁点などを使わない言葉でも、2段階目のヒントを出すこと */
    public function test_hint_is_given_even_without_modified_kana(): void
    {
        $question = $this->findQuestion('easy', 'ねこ');

        $this->assertSame(
            '「ね」は「な」と書かれているボタン、「こ」は「か」と書かれているボタンを押してみてください。',
            $question['hints'][0]
        );
        $this->assertStringContainsString('1もじずつ', $question['hints'][1]);
    }

    /** むずかしいは、読みがなと入力してほしい表記の両方を持つこと */
    public function test_hard_level_shows_reading_and_target_notation(): void
    {
        foreach ($this->questionsOf('hard') as $question) {
            $this->assertMatchesRegularExpression('/\A[ぁ-ゖー]+\z/u', $question['prompt']);
            $this->assertNotSame('', $question['target']);
            // 入力してほしい表記と完全に一致したときだけ正解とする
            $this->assertSame($question['target'], $question['answer']);
            $this->assertNotSame($question['prompt'], $question['answer']);
        }

        $question = $this->findQuestion('hard', 'りんご');
        $this->assertSame('林檎', $question['answer']);
    }

    /** 想定しない難易度は404にすること */
    public function test_unknown_level_is_not_found(): void
    {
        $this->get('/practice/typing/play/unknown')->assertNotFound();
    }

    /** 画面に渡している出題内容を取り出す */
    private function questionsOf(string $level): array
    {
        $view = $this->get(route('practice.typing.play', ['level' => $level]))
            ->assertOk()
            ->original;

        return $view->getData()['questions'];
    }

    /** 出題内容の中から、読みがなが一致するものを1つ取り出す */
    private function findQuestion(string $level, string $prompt): array
    {
        foreach ($this->questionsOf($level) as $question) {
            if ($question['prompt'] === $prompt) {
                return $question;
            }
        }

        $this->fail($prompt.' が '.$level.' の出題に含まれていません');
    }
}
