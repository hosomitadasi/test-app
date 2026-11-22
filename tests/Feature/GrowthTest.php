<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GrowthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 練習結果の記録と、成長の記録画面を確認する。
 */
class GrowthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** やり終えた練習を記録し、声かけの文言を返す */
    public function test_finished_practice_is_recorded_with_praise(): void
    {
        $praise = config('growth.praise');

        $this->postJson(route('practice-records.store'), ['practice_type' => 'tap', 'level' => 'normal', 'score' => 20])
            ->assertCreated()
            ->assertJson(['message' => $praise['first'], 'new_titles' => []]);

        $this->postJson(route('practice-records.store'), ['practice_type' => 'tap', 'level' => 'normal', 'score' => 25])
            ->assertJson(['message' => $praise['best']]);

        $this->postJson(route('practice-records.store'), ['practice_type' => 'tap', 'level' => 'normal', 'score' => 10])
            ->assertJson(['message' => $praise['keep']]);

        $this->assertSame(3, $this->user->practiceRecords()->count());
    }

    public function test_invalid_record_is_rejected(): void
    {
        $this->postJson(route('practice-records.store'), ['practice_type' => 'telephone', 'level' => 'normal', 'score' => 1])
            ->assertJsonValidationErrors('practice_type');
        $this->postJson(route('practice-records.store'), ['practice_type' => 'tap', 'level' => 'expert', 'score' => 1])
            ->assertJsonValidationErrors('level');
        $this->postJson(route('practice-records.store'), ['practice_type' => 'tap', 'level' => 'easy', 'score' => 1000])
            ->assertJsonValidationErrors('score');

        $this->assertSame(0, $this->user->practiceRecords()->count());
    }

    /** 称号の条件が決まるまでは、どれだけ練習しても称号は獲得しない */
    public function test_titles_are_not_awarded_while_conditions_are_undecided(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('practice-records.store'), ['practice_type' => 'tap', 'level' => 'hard', 'score' => 999])
                ->assertJson(['new_titles' => []]);
        }

        $this->assertSame(0, $this->user->titles()->count());
    }

    /** 条件を書き込めば、その条件で称号を獲得する（仕組みの確認） */
    public function test_titles_are_awarded_when_conditions_are_set(): void
    {
        config([
            'growth.practices.tap.titles.beginner.condition' => ['level' => 'easy', 'min_score' => 10, 'times' => 2],
        ]);

        $this->postJson(route('practice-records.store'), ['practice_type' => 'tap', 'level' => 'easy', 'score' => 10])
            ->assertJson(['new_titles' => []]);
        $this->postJson(route('practice-records.store'), ['practice_type' => 'tap', 'level' => 'easy', 'score' => 10])
            ->assertJson(['new_titles' => ['タップ初級']]);

        // 一度獲得した称号は、もう一度は獲得しない
        $this->postJson(route('practice-records.store'), ['practice_type' => 'tap', 'level' => 'easy', 'score' => 10])
            ->assertJson(['new_titles' => []]);

        $this->get(route('growth.index'))->assertInertia(fn (Assert $page) => $page
            ->where('currentTitle.name', 'タップ初級')
            ->where('currentTitle.description', '画面をしっかり押せるようになりました')
            ->where('practices.0.status', GrowthService::STATUS_LEARNED)
            ->where('practices.0.statusLabel', 'できるようになりました'));
    }

    public function test_growth_screen_shows_summary(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');

        // 9/21・9/22・9/23 と3日続けて練習し、8月にも1回練習した
        foreach (['2026-08-10', '2026-09-21', '2026-09-22', '2026-09-23'] as $day) {
            $this->user->practiceRecords()->create([
                'practice_type' => 'tap', 'level' => 'easy', 'score' => 10, 'played_at' => $day.' 09:00:00',
            ]);
        }
        $this->user->practiceRecords()->create([
            'practice_type' => 'typing', 'level' => 'easy', 'score' => 10, 'played_at' => '2026-09-23 09:30:00',
        ]);

        $this->get(route('growth.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Growth/Index')
            ->where('currentTitle', null)
            ->where('monthlyCount', 4)
            ->where('streakDays', 3)
            ->has('practices', 8)
            ->where('practices.0.label', 'タップ')
            ->where('practices.0.statusLabel', '練習中です')
            ->where('practices.1.label', '長押し')
            ->where('practices.1.statusLabel', 'これから練習します')
            ->where('practices.4.label', '文字入力')
            ->where('practices.4.statusLabel', '練習中です')
            ->where('practices.5.label', 'チャット')
            ->where('practices.5.statusLabel', 'これから練習します')
            ->where('practices.6.label', '電話を取る')
            ->where('practices.6.statusLabel', 'これから練習します')
            ->where('practices.7.label', '電話を切る')
            ->where('practices.7.statusLabel', 'これから練習します'));

        $this->assertPageLinksTo('Growth/Index', route('growth.history'));
    }

    /** 今日まだ練習していなくても、昨日まで続いていれば継続日数に数える */
    public function test_streak_counts_until_yesterday(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');

        foreach (['2026-09-21', '2026-09-22'] as $day) {
            $this->user->practiceRecords()->create([
                'practice_type' => 'tap', 'level' => 'easy', 'score' => 10, 'played_at' => $day.' 20:00:00',
            ]);
        }

        $this->get(route('growth.index'))->assertInertia(fn (Assert $page) => $page->where('streakDays', 2));

        Carbon::setTestNow('2026-09-24 10:00:00');
        $this->get(route('growth.index'))->assertInertia(fn (Assert $page) => $page->where('streakDays', 0));
    }

    public function test_history_screen(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');

        foreach ([['2026-09-01', 5], ['2026-09-10', 8], ['2026-09-20', 7]] as [$day, $score]) {
            $this->user->practiceRecords()->create([
                'practice_type' => 'swipe', 'level' => 'normal', 'score' => $score, 'played_at' => $day.' 09:00:00',
            ]);
        }

        $this->get(route('growth.history'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Growth/History')
            ->has('monthlyCounts', 6)
            ->where('monthlyCounts.0', ['label' => '2026年9月', 'count' => 3])
            ->where('monthlyCounts.1', ['label' => '2026年8月', 'count' => 0])
            ->where('bestRecords.0.label', 'なぞる練習')
            ->where('bestRecords.0.rows.0', ['level' => 'ふつう', 'score' => 8, 'date' => '2026年9月10日'])
            ->where('titles', [])
            ->has('improvements', 1)
            ->where('improvements.0', ['label' => 'なぞる練習（ふつう）', 'from' => 5, 'to' => 8, 'date' => '2026年9月10日']));
    }
}
