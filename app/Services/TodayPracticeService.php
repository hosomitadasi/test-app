<?php

namespace App\Services;

use App\Http\Controllers\PracticeController;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * メニュー画面に出す「今日の練習」を決める。
 *
 * 練習の種類と難易度の組み合わせを1列に並べ、日付ごとに続きの3つを選ぶ。
 * 同じ日なら誰が何度開いても同じ練習になり、日付が変わると必ず別の練習に切り替わる。
 *
 * 完了したかどうかは、その日のうちに最後までやり終えた記録（practice_records）があるかで判断する。
 * 記録は最後までやり終えたときだけ残るため、途中で中断した練習は未完了のままとなる。
 */
class TodayPracticeService
{
    /** 1日に出す練習の数 */
    private const DAILY_COUNT = 3;

    /** 練習の種類。並び順がそのまま出題順になる。 */
    private const PRACTICE_TYPES = ['tap', 'long', 'swipe', 'flick', 'typing', 'chat'];

    /**
     * 今日の練習の一覧。
     *
     * @return array<int, array{label: string, href: string, completed: bool}>
     */
    public function forUser(User $user, ?CarbonInterface $date = null): array
    {
        $date = ($date ?? today())->copy()->startOfDay();

        $completed = $user->practiceRecords()
            ->whereBetween('played_at', [$date, $date->copy()->endOfDay()])
            ->get(['practice_type', 'level'])
            ->map(fn ($record) => $record->practice_type.'.'.$record->level)
            ->unique()
            ->all();

        $practices = config('growth.practices');
        $levels = config('growth.levels');

        return array_map(fn (array $item) => [
            'label' => $practices[$item['type']]['practice_label'].'（'.$levels[$item['level']].'）',
            'href' => $this->hrefOf($item['type'], $item['level']),
            'completed' => in_array($item['type'].'.'.$item['level'], $completed, true),
        ], $this->pick($date));
    }

    /**
     * その日の練習を選ぶ。
     *
     * 難易度ごとに練習の種類を並べた列（やさしい → ふつう → むずかしい）から、
     * 日付に応じた位置の3つを取り出す。1日ごとに3つずつずれるため、前の日と同じ練習は出ない。
     *
     * @return array<int, array{type: string, level: string}>
     */
    private function pick(CarbonInterface $date): array
    {
        $candidates = [];
        foreach (PracticeController::LEVELS as $level) {
            foreach (self::PRACTICE_TYPES as $type) {
                $candidates[] = ['type' => $type, 'level' => $level];
            }
        }

        // その地域の暦で数えた通し日数。日付が変わる時刻をアプリのタイムゾーンに合わせる。
        $dayNumber = intdiv($date->getTimestamp() + $date->getOffset(), 86400);
        $start = ($dayNumber * self::DAILY_COUNT) % count($candidates);

        $picked = [];
        for ($i = 0; $i < self::DAILY_COUNT; $i++) {
            $picked[] = $candidates[($start + $i) % count($candidates)];
        }

        return $picked;
    }

    /** 練習開始画面の URL */
    private function hrefOf(string $type, string $level): string
    {
        return match ($type) {
            'typing' => route('practice.typing.play', ['level' => $level], false),
            'chat' => route('practice.typing.chat.play', ['level' => $level], false),
            default => route('practice.play', ['type' => $type, 'level' => $level], false),
        };
    }
}
