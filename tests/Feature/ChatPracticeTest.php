<?php

namespace Tests\Feature;

use App\Http\Controllers\ChatPracticeController;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\QuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * チャット型練習の画面遷移と、難易度ごとの質問を確認する。
 */
class ChatPracticeTest extends TestCase
{
    use RefreshDatabase;

    /** 難易度（URL の {level}）と画面に出す名前 */
    private const LEVELS = [
        'easy' => 'やさしい',
        'normal' => 'ふつう',
        'hard' => 'むずかしい',
    ];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 質問は questions テーブルから読むため、最初の質問を登録しておく
        $this->seed(QuestionSeeder::class);

        // 練習の画面は、ログインしている人だけが使える
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** 難易度選択画面は画面操作の練習と同じ画面を使い、3つの難易度へ進めること */
    public function test_difficulty_screen_links_to_every_level(): void
    {
        $this->get(route('practice.typing.chat.difficulty'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Difficulty')
                ->where('label', 'チャット型練習')
                ->where('backHref', '/practice/typing/details')
                ->where('levels', array_map(fn (string $level, string $label) => [
                    'label' => $label,
                    'href' => '/practice/typing/chat/play/'.$level,
                ], array_keys(self::LEVELS), self::LEVELS)));
    }

    /** 「練習を選び直す」が想定どおりの画面へ戻ること */
    public function test_back_links_point_to_the_expected_screen(): void
    {
        $chat = $this->pageSource('Typing/Chat');
        // 開始画面・中断画面からは難易度選択画面へ戻る
        $this->assertSame(2, substr_count($chat, 'href="'.parse_url(route('practice.typing.chat.difficulty'), PHP_URL_PATH).'"'));
        // 結果画面からはメニュー画面まで戻る
        $this->assertPageLinksTo('Typing/Chat', route('practice.menu'));
    }

    /** 練習画面が吹き出しでやり取りする形で、中断・残り・得点・入力欄・送るボタンがあること */
    public function test_play_screen_has_the_expected_parts(): void
    {
        foreach (array_keys(self::LEVELS) as $level) {
            $this->get(route('practice.typing.chat.play', ['level' => $level]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Typing/Chat')
                    ->where('setting.label', self::LEVELS[$level]));
        }

        $chat = $this->pageSource('Typing/Chat');
        $this->assertStringContainsString('中断する', $chat);
        $this->assertStringContainsString('再開する', $chat);
        $this->assertStringContainsString('もう一度練習する', $chat);
        $this->assertStringContainsString('のこり', $chat);
        $this->assertStringContainsString('class="chat-log"', $chat);
        $this->assertStringContainsString('chat-row--me', $chat);
        $this->assertStringContainsString('chat-row--partner', $chat);
        $this->assertStringContainsString('type="text"', $chat);
        $this->assertStringContainsString('送る', $chat);
        // 練習結果は「チャット型練習」として記録する
        $this->assertStringContainsString("usePracticeRecord('chat', props.level)", $chat);
    }

    /** 難易度ごとの、やり取りの回数と変換の有無が画面へ渡っていること */
    public function test_level_settings_are_passed_to_the_screen(): void
    {
        // やさしい: 3回やり取りしたら終わり
        $this->get(route('practice.typing.chat.play', ['level' => 'easy']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('requiredCount', 3)
                ->where('isConversion', false));

        // ふつう: 5つの質問に答える
        $this->get(route('practice.typing.chat.play', ['level' => 'normal']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('requiredCount', 5)
                ->where('isConversion', false));

        // むずかしい: 5つの質問に、カタカナや漢字へ変換して答える
        $this->get(route('practice.typing.chat.play', ['level' => 'hard']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('requiredCount', 5)
                ->where('isConversion', true));
    }

    /** どの難易度も、答える数より多くの質問を、重ならずに用意していること */
    public function test_enough_distinct_questions_are_given(): void
    {
        foreach (array_keys(self::LEVELS) as $level) {
            $props = $this->propsOf($level);
            $texts = array_column($props['questions'], 'text');

            $this->assertGreaterThan($props['requiredCount'], count($texts));
            $this->assertSame($texts, array_values(array_unique($texts)));
        }
    }

    /** やさしい・ふつうは、変換を求めない質問を出すこと */
    public function test_easy_and_normal_questions_need_no_conversion(): void
    {
        foreach (['easy', 'normal'] as $level) {
            foreach ($this->propsOf($level)['questions'] as $question) {
                $this->assertSame('', $question['format']);
                $this->assertStringEndsWith('？', $question['text']);
            }
        }

        $texts = array_column($this->propsOf('easy')['questions'], 'text');
        $this->assertContains('好きな動物は何ですか？', $texts);
    }

    /** むずかしいは、質問ごとにカタカナや漢字への変換を求めること */
    public function test_hard_questions_ask_for_conversion(): void
    {
        $requests = [
            'katakana' => '　カタカナで回答してください',
            'kanji' => '　漢字を使って回答してください',
        ];

        foreach ($this->propsOf('hard')['questions'] as $question) {
            $this->assertArrayHasKey($question['format'], $requests);
            $this->assertStringEndsWith($requests[$question['format']], $question['text']);
        }

        $texts = array_column($this->propsOf('hard')['questions'], 'text');
        $this->assertContains('嫌いな果物は何ですか？　カタカナで回答してください', $texts);
    }

    /** 住所・電話番号・口座番号など、個人情報につながる質問は出さないこと */
    public function test_questions_do_not_ask_for_personal_information(): void
    {
        $forbidden = array_merge(ChatPracticeController::FORBIDDEN_WORDS, ['住所', '電話番号', '口座番号']);

        foreach (array_keys(self::LEVELS) as $level) {
            foreach ($this->propsOf($level)['questions'] as $question) {
                foreach ($forbidden as $word) {
                    $this->assertStringNotContainsString($word, $question['text'], $level.' の質問に「'.$word.'」が含まれています');
                }
            }
        }
    }

    /** 練習をやり終えたら、チャット型練習として記録できること */
    public function test_finished_practice_is_recorded(): void
    {
        $this->postJson(route('practice-records.store'), ['practice_type' => 'chat', 'level' => 'hard', 'score' => 5])
            ->assertCreated();

        $this->assertSame(1, $this->user->practiceRecords()->where('practice_type', 'chat')->count());
    }

    /** 想定しない難易度は404にすること */
    public function test_unknown_level_is_not_found(): void
    {
        $this->get('/practice/typing/chat/play/unknown')->assertNotFound();
    }

    /** 質問はコントローラの定数ではなく、questions テーブルから読むこと */
    public function test_questions_are_read_from_the_database(): void
    {
        $controller = new \ReflectionClass(ChatPracticeController::class);
        $this->assertFalse($controller->hasConstant('QUESTIONS'));
        $this->assertFalse($controller->hasConstant('CONVERSION_QUESTIONS'));

        Question::create(['practice_type' => 'chat', 'is_conversion' => false, 'text' => '好きな歌は何ですか？']);
        Question::create(['practice_type' => 'chat', 'is_conversion' => true, 'text' => '好きな鳥は何ですか？', 'answer_format' => 'katakana']);
        // 文字打ち練習の問題は、チャット型練習には出さない
        Question::create(['practice_type' => 'typing', 'is_conversion' => false, 'text' => 'もじうちだけ']);

        $this->assertContains('好きな歌は何ですか？', array_column($this->propsOf('easy')['questions'], 'text'));
        $this->assertNotContains('もじうちだけ', array_column($this->propsOf('easy')['questions'], 'text'));
        $this->assertContains('好きな鳥は何ですか？　カタカナで回答してください', array_column($this->propsOf('hard')['questions'], 'text'));
    }

    /** 個人情報を聞く質問がテーブルに入ってしまっても、画面には出さないこと */
    public function test_forbidden_questions_in_the_database_are_not_shown(): void
    {
        Question::create(['practice_type' => 'chat', 'is_conversion' => false, 'text' => 'ご住所はどこですか？']);

        $this->assertNotContains('ご住所はどこですか？', array_column($this->propsOf('normal')['questions'], 'text'));
    }

    /** 1回の練習で同じ質問を出さないよう、番号つきで渡し、画面側もくり返さないこと */
    public function test_questions_are_not_repeated_within_one_practice(): void
    {
        foreach (array_keys(self::LEVELS) as $level) {
            $ids = array_column($this->propsOf($level)['questions'], 'id');
            $this->assertSame($ids, array_values(array_unique($ids)));
        }

        $script = file_get_contents(resource_path('js/composables/useChatGame.js'));
        $this->assertStringContainsString('createQuestionDeck', $script);
        $this->assertStringNotContainsString('% QUESTIONS.length', $script);
    }

    /** 画面に渡している内容を取り出す */
    private function propsOf(string $level): array
    {
        return $this->get(route('practice.typing.chat.play', ['level' => $level]))
            ->assertOk()
            ->viewData('page')['props'];
    }
}
