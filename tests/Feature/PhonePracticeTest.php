<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 電話の練習（電話を取る練習／電話を切る練習）の画面遷移・難易度ごとの設定・記録と称号を確認する。
 */
class PhonePracticeTest extends TestCase
{
    use RefreshDatabase;

    /** 練習の種類（URL の {type}）と、画面に出す名前・記録に使う種類・画面 */
    private const TYPES = [
        'answer' => ['label' => '電話を取る練習', 'record_type' => 'phone_answer', 'page' => 'Phone/Answer'],
        'hangup' => ['label' => '電話を切る練習', 'record_type' => 'phone_hangup', 'page' => 'Phone/HangUp'],
    ];

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

        // 練習の画面は、ログインしている人だけが使える
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** 練習内容選択画面の「電話の練習をする」から、電話の練習詳細画面へ進めること */
    public function test_contents_screen_links_to_phone_details(): void
    {
        $this->assertPageLinksTo('Menu/Contents', route('practice.phone.details'));
    }

    /** 電話の練習詳細画面に「電話を取る」「電話を切る」の2つが出て、それぞれの難易度選択画面へ進めること */
    public function test_details_screen_offers_answer_and_hangup(): void
    {
        $this->get(route('practice.phone.details'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Details')
                ->where('title', '電話の練習を選ぶ')
                ->where('backHref', '/practice/contents')
                ->where('practices', [
                    ['label' => '電話を取る練習をする', 'href' => '/practice/phone/difficulty/answer'],
                    ['label' => '電話を切る練習をする', 'href' => '/practice/phone/difficulty/hangup'],
                ]));
    }

    /** 難易度選択画面はほかの練習と同じ画面を使い、3つの難易度へ進めること */
    public function test_difficulty_screen_links_to_every_level(): void
    {
        foreach (self::TYPES as $type => $practice) {
            $this->get(route('practice.phone.difficulty', ['type' => $type]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Menu/Difficulty')
                    ->where('label', $practice['label'])
                    ->where('backHref', '/practice/phone/details')
                    ->where('levels', array_map(fn (string $level, string $label) => [
                        'label' => $label,
                        'href' => '/practice/phone/play/'.$type.'/'.$level,
                    ], array_keys(self::LEVELS), self::LEVELS)));
        }
    }

    /** どの練習・難易度も、練習画面を表示できること */
    public function test_every_practice_and_level_is_displayed(): void
    {
        foreach (self::TYPES as $type => $practice) {
            foreach (self::LEVELS as $level => $label) {
                $this->get(route('practice.phone.play', ['type' => $type, 'level' => $level]))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->component($practice['page'])
                        ->where('recordType', $practice['record_type'])
                        ->where('practice.label', $practice['label'])
                        ->where('setting.label', $label)
                        ->has('callers'));
            }
        }
    }

    /** 電話を取る練習の、難易度ごとの進め方 */
    public function test_answer_level_settings(): void
    {
        // やさしい: 画面全体の着信画面だけ・3回続けてできたら完了
        $this->assertSetting('answer', 'easy', [
            'mode' => 'streak', 'required_streak' => 3, 'layouts' => ['fullscreen'], 'has_instruction' => false,
        ]);
        // ふつう: 通知と画面全体の両方・5回中4回で合格
        $this->assertSetting('answer', 'normal', [
            'mode' => 'total', 'total_count' => 5, 'pass_count' => 4, 'layouts' => ['popup', 'fullscreen'], 'has_instruction' => false,
        ]);
        // むずかしい: ふつうに加えて「応答してください／拒否してください」の指示を出す
        $this->assertSetting('answer', 'hard', [
            'mode' => 'total', 'total_count' => 5, 'pass_count' => 4, 'layouts' => ['popup', 'fullscreen'], 'has_instruction' => true,
        ]);
    }

    /** 電話を切る練習の、難易度ごとの進め方 */
    public function test_hangup_level_settings(): void
    {
        $this->assertSetting('hangup', 'easy', ['mode' => 'streak', 'required_streak' => 3]);
        $this->assertSetting('hangup', 'normal', ['mode' => 'total', 'total_count' => 10, 'pass_count' => 8]);
        $this->assertSetting('hangup', 'hard', ['mode' => 'total', 'total_count' => 10, 'pass_count' => 9]);
    }

    /** 着信画面に、文字の付いた「応答」「拒否」ボタンと「むずかしい」の指示があること */
    public function test_answer_screen_has_the_expected_parts(): void
    {
        $answer = $this->pageSource('Phone/Answer');
        $this->assertStringContainsString('incoming--fullscreen', $answer);
        $this->assertStringContainsString('incoming--popup', $answer);
        $this->assertStringContainsString('call-btn--answer', $answer);
        $this->assertStringContainsString('call-btn--decline', $answer);
        $this->assertSame(2, substr_count($answer, '<span class="call-btn__label">'));
        $this->assertStringContainsString('応答', $answer);
        $this->assertStringContainsString('拒否', $answer);
        $this->assertStringContainsString("'「応答」してください'", $answer);
        $this->assertStringContainsString("'「拒否」してください'", $answer);
    }

    /** 通話中画面に、赤い📞の通話終了ボタンと、押しまちがえる場所のボタンがあること */
    public function test_hangup_screen_has_the_expected_parts(): void
    {
        $hangUp = $this->pageSource('Phone/HangUp');
        $this->assertStringContainsString('call-btn--end', $hangUp);
        $this->assertStringContainsString('📞', $hangUp);
        $this->assertStringContainsString('通話終了', $hangUp);
        $this->assertStringContainsString('@click="pressOther"', $hangUp);
        $this->assertStringContainsString('@click="hangUp"', $hangUp);
    }

    /** 開始・中断・再開・結果・称号ダイアログの作りが、ほかの練習と同じであること */
    public function test_shared_screens_follow_other_practices(): void
    {
        $frame = file_get_contents(resource_path('js/Components/phone/PhonePractice.vue'));
        $this->assertStringContainsString('始める', $frame);
        $this->assertStringContainsString('中断する', $frame);
        $this->assertStringContainsString('再開する', $frame);
        $this->assertStringContainsString('もう一度練習する', $frame);
        $this->assertStringContainsString('問題数', $frame);
        $this->assertStringContainsString('成功数', $frame);
        $this->assertStringContainsString('失敗数', $frame);
        // 開始画面・中断画面からは難易度選択画面へ、結果画面からはメニュー画面へ戻る
        $this->assertSame(2, substr_count($frame, ':href="difficultyHref"'));
        $this->assertStringContainsString('href="/menu"', $frame);
        // 新しい称号は、実際の称号名を入れたダイアログで知らせる
        $this->assertStringContainsString('の称号を獲得しました！', $frame);
        $this->assertStringContainsString('<AppDialog', $frame);

        // 練習結果は、電話を取る・切るを別の練習として記録する
        foreach (self::TYPES as $practice) {
            $this->assertStringContainsString('usePracticeRecord(props.recordType, props.level)', $this->pageSource($practice['page']));
        }
    }

    /** 成功・失敗の声かけと、練習完了のときだけ記録する作りになっていること */
    public function test_game_feedback_and_recording_rules(): void
    {
        $game = file_get_contents(resource_path('js/composables/usePhoneGame.js'));
        $this->assertStringContainsString("'できました！'", $game);
        $this->assertStringContainsString("'正しく操作できました！'", $game);
        $this->assertStringContainsString("'おしいです！'", $game);
        $this->assertStringContainsString("'もう一度、ボタンを確認してみましょう。'", $game);
        // 合格（練習完了）したときだけ、1回だけ記録する
        $this->assertStringContainsString('if (passed.value && onFinish) { onFinish(scoreOf()); }', $game);
        $this->assertStringContainsString('if (finished) { return; }', $game);
    }

    /** 本物の電話をかけたり、電話アプリを開いたりするリンクがないこと */
    public function test_no_real_phone_call_is_made(): void
    {
        $sources = [
            $this->pageSource('Phone/Answer'),
            $this->pageSource('Phone/HangUp'),
            file_get_contents(resource_path('js/Components/phone/PhonePractice.vue')),
            file_get_contents(resource_path('js/composables/usePhoneGame.js')),
        ];

        foreach ($sources as $source) {
            $this->assertStringNotContainsString('tel:', $source);
            $this->assertStringNotContainsString('callto:', $source);
            $this->assertStringNotContainsString('window.open', $source);
            $this->assertStringNotContainsString('location.href', $source);
        }
    }

    /** 練習をやり終えたら、電話を取る・切るをそれぞれ別の練習として記録できること */
    public function test_finished_practice_is_recorded_separately(): void
    {
        $this->postJson(route('practice-records.store'), ['practice_type' => 'phone_answer', 'level' => 'normal', 'score' => 4])
            ->assertCreated();
        $this->postJson(route('practice-records.store'), ['practice_type' => 'phone_hangup', 'level' => 'normal', 'score' => 8])
            ->assertCreated();

        $this->assertSame(1, $this->user->practiceRecords()->where('practice_type', 'phone_answer')->count());
        $this->assertSame(1, $this->user->practiceRecords()->where('practice_type', 'phone_hangup')->count());
    }

    /** 電話を取る練習の称号は、条件を満たしたときだけ獲得すること */
    public function test_answer_titles_are_awarded_only_when_conditions_are_met(): void
    {
        // ふつうを完了しても、初級（やさしいを完了）の条件ではないため獲得しない
        $this->postJson(route('practice-records.store'), ['practice_type' => 'phone_answer', 'level' => 'normal', 'score' => 5])
            ->assertJson(['new_titles' => []]);

        $this->postJson(route('practice-records.store'), ['practice_type' => 'phone_answer', 'level' => 'easy', 'score' => 3])
            ->assertJson(['new_titles' => ['電話を取る初級']]);

        // 一度獲得した称号は、もう一度は獲得しない
        $this->postJson(route('practice-records.store'), ['practice_type' => 'phone_answer', 'level' => 'easy', 'score' => 3])
            ->assertJson(['new_titles' => []]);

        $this->postJson(route('practice-records.store'), ['practice_type' => 'phone_answer', 'level' => 'hard', 'score' => 4])
            ->assertJson(['new_titles' => ['電話を取る上達']]);
    }

    /** 電話を切る練習の称号は、条件を満たしたときだけ獲得し、電話を取る練習とは別に数えること */
    public function test_hangup_titles_are_awarded_only_when_conditions_are_met(): void
    {
        // 電話を取る練習の記録では、電話を切る練習の称号は獲得しない
        $this->postJson(route('practice-records.store'), ['practice_type' => 'phone_answer', 'level' => 'hard', 'score' => 5])
            ->assertJson(['new_titles' => ['電話を取る上達']]);

        // むずかしいで 10回中8回は、上達（9回以上）の条件に足りない
        $this->postJson(route('practice-records.store'), ['practice_type' => 'phone_hangup', 'level' => 'hard', 'score' => 8])
            ->assertJson(['new_titles' => []]);

        $this->postJson(route('practice-records.store'), ['practice_type' => 'phone_hangup', 'level' => 'hard', 'score' => 9])
            ->assertJson(['new_titles' => ['電話を切る上達']]);

        $this->postJson(route('practice-records.store'), ['practice_type' => 'phone_hangup', 'level' => 'easy', 'score' => 3])
            ->assertJson(['new_titles' => ['電話を切る初級']]);
    }

    /** ほかの練習の称号の条件は、変えていないこと */
    public function test_other_title_conditions_are_unchanged(): void
    {
        foreach (['tap', 'long', 'swipe', 'flick', 'typing', 'chat'] as $type) {
            foreach (config('growth.practices.'.$type.'.titles') as $title) {
                $this->assertNull($title['condition']);
            }
        }
        $this->assertNull(config('growth.master.condition'));
    }

    /** 想定しない練習・難易度は404にすること */
    public function test_unknown_type_or_level_is_not_found(): void
    {
        $this->get('/practice/phone/difficulty/unknown')->assertNotFound();
        $this->get('/practice/phone/play/answer/unknown')->assertNotFound();
        $this->get('/practice/phone/play/unknown/easy')->assertNotFound();
    }

    /** 練習画面に渡している難易度の設定が、期待どおりであること */
    private function assertSetting(string $type, string $level, array $expected): void
    {
        $setting = $this->get(route('practice.phone.play', ['type' => $type, 'level' => $level]))
            ->assertOk()
            ->viewData('page')['props']['setting'];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $setting[$key], $type.' / '.$level.' の '.$key);
        }
    }
}
