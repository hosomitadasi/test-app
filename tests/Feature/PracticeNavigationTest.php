<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * メニュー画面から各練習までの画面遷移を確認する。
 */
class PracticeNavigationTest extends TestCase
{
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

    /** 選択画面が表示できること */
    public function test_selection_screens_are_displayed(): void
    {
        $this->get(route('practice.menu'))->assertOk()->assertSee('今日の練習');
        $this->get(route('practice.contents'))->assertOk()->assertSee('画面操作の練習をする');
        $this->get(route('practice.details'))->assertOk()->assertSee('軽く押す練習をする');
    }

    /** 4種類すべての練習が、3つの難易度で表示できること */
    public function test_every_practice_and_level_is_displayed(): void
    {
        foreach (self::TYPES as $type => $label) {
            $this->get(route('practice.difficulty', ['type' => $type]))
                ->assertOk()
                ->assertSee($label);

            foreach (self::LEVELS as $level => $levelLabel) {
                $this->get(route('practice.play', ['type' => $type, 'level' => $level]))
                    ->assertOk()
                    ->assertSee($label);
            }
        }
    }

    /** 進む向きの遷移リンクが、それぞれ次の画面を指していること */
    public function test_forward_links_point_to_the_next_screen(): void
    {
        $this->get(route('practice.menu'))
            ->assertSee('href="'.route('practice.contents').'"', false);

        $this->get(route('practice.contents'))
            ->assertSee('href="'.route('practice.details').'"', false);

        // 文字入力の練習は、練習詳細画面を経由せず難易度選択画面へ進む
        $this->get(route('practice.contents'))
            ->assertSee('href="'.route('practice.typing.difficulty').'"', false);

        // 練習詳細画面からは、4種類それぞれの難易度選択画面へ進む
        $details = $this->get(route('practice.details'));
        foreach (self::TYPES as $type => $label) {
            $details->assertSee('href="'.route('practice.difficulty', ['type' => $type]).'"', false);
            $details->assertSee($label.'をする');
        }

        // 難易度選択画面からは、同じ練習の3難易度へ進む
        foreach (self::TYPES as $type => $label) {
            $difficulty = $this->get(route('practice.difficulty', ['type' => $type]));
            foreach (array_keys(self::LEVELS) as $level) {
                $difficulty->assertSee(
                    'href="'.route('practice.play', ['type' => $type, 'level' => $level]).'"',
                    false
                );
            }
        }
    }

    /** 「練習を選び直す」が想定どおりの画面へ戻ること */
    public function test_back_links_point_to_the_expected_screen(): void
    {
        $this->get(route('practice.contents'))
            ->assertSee('href="'.route('practice.menu').'"', false);

        $this->get(route('practice.details'))
            ->assertSee('href="'.route('practice.contents').'"', false);

        foreach (array_keys(self::TYPES) as $type) {
            $this->get(route('practice.difficulty', ['type' => $type]))
                ->assertSee('href="'.route('practice.details').'"', false);

            $play = $this->get(route('practice.play', ['type' => $type, 'level' => 'normal']));
            // 開始画面・中断画面からは難易度選択画面へ戻る
            $play->assertSee('href="'.route('practice.difficulty', ['type' => $type]).'"', false);
            // 結果画面からはメニュー画面まで戻る
            $play->assertSee('href="'.route('practice.menu').'"', false);
        }
    }

    /** 難易度ごとの設定値が練習画面へ渡っていること */
    public function test_level_settings_are_passed_to_the_screen(): void
    {
        // やさしい: 制限時間なし・10回で終わり・まちがったアイコンなし
        $easy = $this->get(route('practice.play', ['type' => 'tap', 'level' => 'easy']))->getContent();
        $this->assertStringContainsString('timeLimitSeconds: 0,', $easy);
        $this->assertStringContainsString('requiredCount: 10,', $easy);
        $this->assertStringContainsString('hasDecoy: false,', $easy);
        $this->assertStringContainsString('のこり', $easy);
        $this->assertStringNotContainsString('id="decoy"', $easy);

        // ふつう: 制限時間3分・回数制限なし・まちがったアイコンなし
        $normal = $this->get(route('practice.play', ['type' => 'tap', 'level' => 'normal']))->getContent();
        $this->assertStringContainsString('timeLimitSeconds: 180,', $normal);
        $this->assertStringContainsString('requiredCount: 0,', $normal);
        $this->assertStringContainsString('hasDecoy: false,', $normal);
        $this->assertStringContainsString('制限時間', $normal);

        // むずかしい: 制限時間3分に加えて、操作してはいけないアイコンを1つ出す
        $hard = $this->get(route('practice.play', ['type' => 'tap', 'level' => 'hard']))->getContent();
        $this->assertStringContainsString('timeLimitSeconds: 180,', $hard);
        $this->assertStringContainsString('hasDecoy: true,', $hard);
        $this->assertStringContainsString('id="decoy"', $hard);
    }

    /** なぞる・はらう ではやじるしを表示し、押す練習では表示しないこと */
    public function test_arrow_icon_is_shown_only_for_swipe_and_flick(): void
    {
        foreach (['swipe', 'flick'] as $type) {
            $this->get(route('practice.play', ['type' => $type, 'level' => 'normal']))
                ->assertSee('target__arrow', false);
        }

        foreach (['tap', 'long'] as $type) {
            $this->get(route('practice.play', ['type' => $type, 'level' => 'normal']))
                ->assertDontSee('target__arrow', false);
        }
    }

    /** 練習前にかんたんな説明を表示すること */
    public function test_instruction_is_shown_before_the_practice(): void
    {
        $this->get(route('practice.play', ['type' => 'long', 'level' => 'easy']))
            ->assertSee('1びょう いじょう おしつづけてください')
            ->assertSee('10かい できたら おわりです');

        $this->get(route('practice.play', ['type' => 'flick', 'level' => 'hard']))
            ->assertSee('すばやく はらってください')
            ->assertSee('×の ついた ものは さわらないでください');
    }

    /** 終了画面は廃止し、結果画面から「終了する」も出さないこと */
    public function test_exit_screen_is_removed(): void
    {
        $play = $this->get(route('practice.play', ['type' => 'tap', 'level' => 'normal']))->getContent();
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
        $menu = $this->get(route('practice.menu'))->getContent();
        $this->assertStringContainsString('<a class="menu-list__item">①○○の練習</a>', $menu);
        $this->assertStringContainsString('<a class="menu-list__item">②○○の練習</a>', $menu);
        $this->assertStringContainsString('<a class="menu-list__item">③○○の練習</a>', $menu);

        $contents = $this->get(route('practice.contents'))->getContent();
        // 電話の練習のみ、後日実装のため遷移させずボタンのままとする
        $this->assertStringContainsString(
            '<button type="button" class="btn btn--primary">電話の練習をする</button>',
            $contents,
            '電話の練習をする は遷移させずボタンのみとする'
        );
    }
}
