<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * メニュー画面の「今日の練習」を確認する。
 */
class TodayPracticeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-21 10:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** ログイン後のメニュー画面に、今日の練習が3つ表示されること */
    public function test_today_practices_are_displayed(): void
    {
        $this->get(route('practice.menu'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Menu/Menu')
                ->has('todayPractices', 3)
                ->has('todayPractices.0', fn (Assert $practice) => $practice
                    ->whereType('label', 'string')
                    ->whereType('href', 'string')
                    ->where('completed', false)));

        $menu = $this->pageSource('Menu/Menu');
        $this->assertStringContainsString('v-for="(practice, index) in todayPractices"', $menu);
        $this->assertStringContainsString(':href="practice.href"', $menu);
    }

    /** 今日の練習は、それぞれの練習開始画面へ直接進めること */
    public function test_today_practices_link_to_the_play_screen(): void
    {
        foreach ($this->todayPractices() as $practice) {
            $this->get($practice['href'])
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('setting.label', fn ($label) => str_contains($practice['label'], $label)));
        }
    }

    /** 日付が変わると、今日の練習の内容も変わること */
    public function test_today_practices_change_every_day(): void
    {
        $today = $this->todayPractices();

        // 日付が変わる直前までは同じ内容
        Carbon::setTestNow('2026-10-21 23:59:59');
        $this->assertSame($today, $this->todayPractices());

        // 日付が変わると、前の日と1つも重ならない内容になる
        $previous = array_column($today, 'href');
        for ($day = 1; $day <= 7; $day++) {
            Carbon::setTestNow(Carbon::parse('2026-10-21')->addDays($day));
            $hrefs = array_column($this->todayPractices(), 'href');

            $this->assertSame([], array_intersect($previous, $hrefs), $day.'日後の練習が前の日と重なっています');
            $previous = $hrefs;
        }
    }

    /** 今日の練習を最後までやり終えると「完了」となり、押せなくなること */
    public function test_finished_practice_is_marked_as_completed(): void
    {
        [$first, $second] = $this->todayPractices();
        [$type, $level] = $this->typeAndLevelOf($first['href']);

        $this->postJson(route('practice-records.store'), ['practice_type' => $type, 'level' => $level, 'score' => 10])
            ->assertCreated();

        $practices = $this->todayPractices();
        $this->assertTrue($practices[0]['completed']);
        $this->assertFalse($practices[1]['completed']);
        $this->assertSame($second['href'], $practices[1]['href']);

        // 完了した練習はリンクにせず、「完了」と表示する
        $menu = $this->pageSource('Menu/Menu');
        $this->assertStringContainsString('v-if="practice.completed"', $menu);
        $this->assertStringContainsString('aria-disabled="true"', $menu);
        $this->assertStringContainsString('完了', $menu);
    }

    /** 途中で中断した練習は記録が残らないため、未完了のままであること */
    public function test_interrupted_practice_stays_incomplete(): void
    {
        foreach ($this->todayPractices() as $practice) {
            $this->get($practice['href'])->assertOk();
        }

        foreach ($this->todayPractices() as $practice) {
            $this->assertFalse($practice['completed']);
        }
    }

    /** 前の日にやり終えた練習や、今日の練習以外の練習では「完了」にならないこと */
    public function test_only_today_records_of_the_same_practice_count(): void
    {
        [$type, $level] = $this->typeAndLevelOf($this->todayPractices()[0]['href']);

        $this->user->practiceRecords()->create([
            'practice_type' => $type, 'level' => $level, 'score' => 10, 'played_at' => now()->subDay(),
        ]);
        $otherLevel = $level === 'hard' ? 'easy' : 'hard';
        $this->user->practiceRecords()->create([
            'practice_type' => $type, 'level' => $otherLevel, 'score' => 10, 'played_at' => now(),
        ]);

        $this->assertFalse($this->todayPractices()[0]['completed']);
    }

    /** メニュー画面に渡される今日の練習 */
    private function todayPractices(): array
    {
        $practices = null;
        $this->get(route('practice.menu'))->assertInertia(function (Assert $page) use (&$practices) {
            $practices = $page->toArray()['props']['todayPractices'];
        });

        return $practices;
    }

    /** 練習開始画面の URL から、記録に使う練習の種類と難易度を取り出す */
    private function typeAndLevelOf(string $href): array
    {
        return match (true) {
            (bool) preg_match('#^/practice/typing/chat/play/(\w+)$#', $href, $m) => ['chat', $m[1]],
            (bool) preg_match('#^/practice/typing/play/(\w+)$#', $href, $m) => ['typing', $m[1]],
            (bool) preg_match('#^/practice/play/(\w+)/(\w+)$#', $href, $m) => [$m[1], $m[2]],
        };
    }
}
