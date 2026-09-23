<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * メニュー画面から各練習までの画面遷移を確認する。
 */
class PracticeNavigationTest extends TestCase
{
    use RefreshDatabase;

    /** 練習の種類（URL の {type}）と画面に出す名前 */
    private const TYPES = [
        'tap' => '軽く押す練習',
        'long' => '長く押す練習',
        'swipe' => 'なぞる練習',
        'flick' => 'はらう練習',
    ];

    /** 難易度（URL の {level}）と画面に出す名前 */
    private const LEVELS = [
        'easy' => 'やさしい',
        'normal' => 'ふつう',
        'hard' => 'むずかしい',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // 練習の画面は、ログインしている人だけが使える
        $this->actingAs(User::factory()->create());
    }

    /** 選択画面が表示できること */
    public function test_selection_screens_are_displayed(): void
    {
        $this->get(route('practice.menu'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Menu/Menu'));
        $this->assertStringContainsString('今日の練習', $this->pageSource('Menu/Menu'));

        $this->get(route('practice.contents'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Menu/Contents'));
        $this->assertStringContainsString('画面操作の練習をする', $this->pageSource('Menu/Contents'));

        $this->get(route('practice.details'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Details')
                ->where('practices.tap.menu_label', '軽く押す練習をする'));
    }

    /** 4種類すべての練習が、3つの難易度で表示できること */
    public function test_every_practice_and_level_is_displayed(): void
    {
        foreach (self::TYPES as $type => $label) {
            $this->get(route('practice.difficulty', ['type' => $type]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('Menu/Difficulty')
                    ->where('practice.label', $label));

            foreach (self::LEVELS as $level => $levelLabel) {
                $this->get(route('practice.play', ['type' => $type, 'level' => $level]))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->component('Practice/Play')
                        ->where('practice.label', $label)
                        ->where('setting.label', $levelLabel));
            }
        }
    }

    /** 進む向きの遷移リンクが、それぞれ次の画面を指していること */
    public function test_forward_links_point_to_the_next_screen(): void
    {
        $this->assertPageLinksTo('Menu/Menu', route('practice.contents'));
        $this->assertPageLinksTo('Menu/Contents', route('practice.details'));

        // 文字入力の練習は、練習詳細画面を経由せず難易度選択画面へ進む
        $this->assertPageLinksTo('Menu/Contents', route('practice.typing.difficulty'));

        // 練習詳細画面からは、4種類それぞれの難易度選択画面へ進む
        $this->assertStringContainsString(':href="`/practice/difficulty/${type}`"', $this->pageSource('Menu/Details'));
        $this->get(route('practice.details'))->assertInertia(fn (Assert $page) => $page
            ->has('practices', count(self::TYPES))
            ->where('practices.tap.menu_label', self::TYPES['tap'].'をする')
            ->where('practices.long.menu_label', self::TYPES['long'].'をする')
            ->where('practices.swipe.menu_label', self::TYPES['swipe'].'をする')
            ->where('practices.flick.menu_label', self::TYPES['flick'].'をする'));
        foreach (array_keys(self::TYPES) as $type) {
            $this->assertSame('/practice/difficulty/'.$type, parse_url(route('practice.difficulty', ['type' => $type]), PHP_URL_PATH));
        }

        // 難易度選択画面からは、同じ練習の3難易度へ進む
        $this->assertStringContainsString(':href="`/practice/play/${type}/${level}`"', $this->pageSource('Menu/Difficulty'));
        foreach (array_keys(self::TYPES) as $type) {
            $this->get(route('practice.difficulty', ['type' => $type]))->assertInertia(fn (Assert $page) => $page
                ->where('type', $type)
                ->has('levels', count(self::LEVELS))
                ->has('levels.easy')->has('levels.normal')->has('levels.hard'));
        }
    }

    /** 戻る向きの遷移リンクが、想定どおりの画面を指していること */
    public function test_back_links_point_to_the_expected_screen(): void
    {
        $this->assertPageLinksTo('Menu/Contents', route('practice.menu'));
        $this->assertPageLinksTo('Menu/Details', route('practice.contents'));
        $this->assertPageLinksTo('Menu/Difficulty', route('practice.details'));

        $play = $this->pageSource('Practice/Play');
        // 開始画面・中断画面からは難易度選択画面へ戻る
        $this->assertSame(2, substr_count($play, ':href="`/practice/difficulty/${type}`"'));
        // 結果画面からはメニュー画面まで戻る
        $this->assertPageLinksTo('Practice/Play', route('practice.menu'));
    }

    /** 難易度ごとの設定値が練習画面へ渡っていること */
    public function test_level_settings_are_passed_to_the_screen(): void
    {
        // やさしい: 制限時間なし・10回で終わり・まちがったアイコンなし
        $this->get(route('practice.play', ['type' => 'tap', 'level' => 'easy']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('timeLimitSeconds', 0)
                ->where('requiredCount', 10)
                ->where('hasDecoy', false));

        // ふつう: 制限時間3分・回数制限なし・まちがったアイコンなし
        $this->get(route('practice.play', ['type' => 'tap', 'level' => 'normal']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('timeLimitSeconds', 180)
                ->where('requiredCount', 0)
                ->where('hasDecoy', false));

        // むずかしい: 制限時間3分に加えて、操作してはいけないアイコンを1つ出す
        $this->get(route('practice.play', ['type' => 'tap', 'level' => 'hard']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('timeLimitSeconds', 180)
                ->where('hasDecoy', true));

        // 画面側は、回数制なら「のこり」、時間制なら「制限時間」を出し、decoy は hasDecoy のときだけ出す
        $play = $this->pageSource('Practice/Play');
        $this->assertStringContainsString('v-if="requiredCount > 0"', $play);
        $this->assertStringContainsString('のこり', $play);
        $this->assertStringContainsString('制限時間', $play);
        $this->assertStringContainsString('v-if="hasDecoy" id="decoy"', $play);
    }

    /** なぞる・はらう ではやじるしを表示し、押す練習では表示しないこと */
    public function test_arrow_icon_is_shown_only_for_swipe_and_flick(): void
    {
        $play = $this->pageSource('Practice/Play');
        $this->assertStringContainsString("const hasArrow = ['swipe', 'flick'].includes(props.type);", $play);
        $this->assertSame(2, substr_count($play, '<span v-if="hasArrow" class="target__arrow"'));
    }

    /** 練習前にかんたんな説明を表示すること */
    public function test_instruction_is_shown_before_the_practice(): void
    {
        $this->get(route('practice.play', ['type' => 'long', 'level' => 'easy']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('practice.lead', fn ($lead) => str_contains($lead, '1びょう いじょう おしつづけてください'))
                ->where('setting.note', fn ($note) => str_contains($note, '10かい できたら おわりです')));

        $this->get(route('practice.play', ['type' => 'flick', 'level' => 'hard']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('practice.lead', fn ($lead) => str_contains($lead, 'すばやく はらってください'))
                ->where('setting.note', fn ($note) => str_contains($note, '×の ついた ものは さわらないでください')));

        $this->assertStringContainsString('v-html="practice.lead"', $this->pageSource('Practice/Play'));
    }

    /** 終了画面は廃止し、結果画面から「終了する」も出さないこと */
    public function test_exit_screen_is_removed(): void
    {
        $play = $this->pageSource('Practice/Play');
        $this->assertStringNotContainsString('screen-exit', $play);
        $this->assertStringNotContainsString('終了する', $play);
        $this->assertStringContainsString('もう一度練習する', $play);
    }

    /** 想定しない練習・難易度は404にすること */
    public function test_unknown_type_or_level_is_not_found(): void
    {
        $this->get('/practice/difficulty/unknown')->assertNotFound();
        $this->get('/practice/play/tap/unknown')->assertNotFound();
        $this->get('/practice/play/unknown/normal')->assertNotFound();
    }

    /** 後日実装する項目には遷移先を設定していないこと */
    public function test_unimplemented_items_have_no_link(): void
    {
        $menu = $this->pageSource('Menu/Menu');
        $this->assertStringContainsString('<a class="menu-list__item">①○○の練習</a>', $menu);
        $this->assertStringContainsString('<a class="menu-list__item">②○○の練習</a>', $menu);
        $this->assertStringContainsString('<a class="menu-list__item">③○○の練習</a>', $menu);

        // 電話の練習のみ、後日実装のため遷移させずボタンのままとする
        $this->assertStringContainsString(
            '<button type="button" class="btn btn--primary">電話の練習をする</button>',
            $this->pageSource('Menu/Contents'),
            '電話の練習をする は遷移させずボタンのみとする'
        );
    }

    /** メニュー画面の「前の画面に戻る」は、トップ画面（ログイン後）へ戻ること */
    public function test_menu_back_button_returns_to_top(): void
    {
        $this->assertPageLinksTo('Menu/Menu', route('top'));
    }
}
