<?php

namespace App\Services;

use App\Models\PracticeRecord;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * 成長の記録。練習結果の保存、称号の判定、画面に出す内容の集計を行う。
 *
 * 称号・声かけの文言は config/growth.php に持つ。
 */
class GrowthService
{
    public const STATUS_LEARNED = 'learned';
    public const STATUS_PRACTICING = 'practicing';
    public const STATUS_NOT_STARTED = 'not_started';

    private const STATUS_LABELS = [
        self::STATUS_LEARNED => 'できるようになりました',
        self::STATUS_PRACTICING => '練習中です',
        self::STATUS_NOT_STARTED => 'これから練習します',
    ];

    /** 「これまでの記録」で、月ごとの練習回数を出す月数 */
    private const HISTORY_MONTHS = 6;

    /** 「これまでの記録」で、上達した記録を出す件数 */
    private const IMPROVEMENT_LIMIT = 5;

    /**
     * やり終えた練習の結果を記録し、声かけの文言と新しく獲得した称号を返す。
     *
     * @param  array{practice_type: string, level: string, score: int}  $data
     * @return array{message: string, new_titles: array<int, string>}
     */
    public function record(User $user, array $data): array
    {
        $previousBest = $user->practiceRecords()
            ->where('practice_type', $data['practice_type'])
            ->where('level', $data['level'])
            ->max('score');

        $user->practiceRecords()->create([
            'practice_type' => $data['practice_type'],
            'level' => $data['level'],
            'score' => $data['score'],
            'played_at' => now(),
        ]);

        $praise = config('growth.praise');
        $message = match (true) {
            $previousBest === null => $praise['first'],
            $data['score'] > (int) $previousBest => $praise['best'],
            default => $praise['keep'],
        };

        return [
            'message' => $message,
            'new_titles' => $this->awardTitles($user),
        ];
    }

    /**
     * 条件を満たした称号を獲得させ、新しく獲得した称号の名前を返す。
     * 条件が決まっていない（null の）称号は獲得させない。
     *
     * @return array<int, string>
     */
    public function awardTitles(User $user): array
    {
        $owned = $user->titles()->pluck('title_code')->all();
        $awarded = [];

        foreach ($this->titleDefinitions() as $code => $title) {
            if (in_array($code, $owned, true) || $title['condition'] === null) {
                continue;
            }

            $met = $code === 'master'
                ? $this->masterConditionMet($owned, $title['condition'])
                : $this->practiceConditionMet($user, $title['type'], $title['condition']);

            if ($met) {
                $user->titles()->create(['title_code' => $code, 'acquired_at' => now()]);
                $owned[] = $code;
                $awarded[] = $title['name'];
            }
        }

        return $awarded;
    }

    /**
     * 成長の記録画面に出す内容。
     */
    public function summary(User $user): array
    {
        $owned = $user->titles()->pluck('title_code')->all();
        $practicedTypes = $user->practiceRecords()->distinct()->pluck('practice_type')->all();
        $achievedRank = config('growth.achieved_rank');

        $practices = [];
        foreach (config('growth.practices') as $type => $practice) {
            $status = match (true) {
                in_array($type.'.'.$achievedRank, $owned, true)
                    || in_array($type.'.advanced', $owned, true) => self::STATUS_LEARNED,
                in_array($type, $practicedTypes, true) => self::STATUS_PRACTICING,
                default => self::STATUS_NOT_STARTED,
            };

            $practices[] = [
                'type' => $type,
                'label' => $practice['label'],
                'status' => $status,
                'statusLabel' => self::STATUS_LABELS[$status],
            ];
        }

        return [
            'currentTitle' => $this->currentTitle($user),
            'practices' => $practices,
            'monthlyCount' => $user->practiceRecords()
                ->whereBetween('played_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'streakDays' => $this->streakDays($user),
        ];
    }

    /**
     * これまでの記録画面に出す内容。一度に出しすぎないよう、画面側で項目ごとに切り替えて見せる。
     */
    public function history(User $user): array
    {
        /** @var Collection<int, PracticeRecord> $records */
        $records = $user->practiceRecords()
            ->orderBy('played_at')
            ->orderBy('id')
            ->get(['practice_type', 'level', 'score', 'played_at']);

        return [
            'monthlyCounts' => $this->monthlyCounts($records),
            'bestRecords' => $this->bestRecords($records),
            'titles' => $this->acquiredTitles($user),
            'improvements' => $this->improvements($records),
        ];
    }

    /**
     * 称号の一覧。キーは user_titles.title_code と同じ。
     *
     * @return array<string, array{name: string, description: string, condition: ?array, type: ?string}>
     */
    public function titleDefinitions(): array
    {
        $titles = [];
        foreach (config('growth.practices') as $type => $practice) {
            foreach ($practice['titles'] as $rank => $title) {
                $titles[$type.'.'.$rank] = $title + ['type' => $type];
            }
        }
        $titles['master'] = config('growth.master') + ['type' => null];

        return $titles;
    }

    // ---------- 称号の判定 ----------

    /** 指定の難易度で min_score 点以上を times 回とったか */
    private function practiceConditionMet(User $user, string $type, array $condition): bool
    {
        return $user->practiceRecords()
            ->where('practice_type', $type)
            ->where('level', $condition['level'])
            ->where('score', '>=', $condition['min_score'])
            ->count() >= $condition['times'];
    }

    /** すべての練習項目で、指定の段階の称号を獲得したか */
    private function masterConditionMet(array $owned, array $condition): bool
    {
        foreach (array_keys(config('growth.practices')) as $type) {
            if (!in_array($type.'.'.$condition['requires'], $owned, true)) {
                return false;
            }
        }

        return true;
    }

    /** 最後に獲得した称号。まだない場合は null。 */
    private function currentTitle(User $user): ?array
    {
        $definitions = $this->titleDefinitions();

        foreach ($user->titles()->orderByDesc('acquired_at')->orderByDesc('id')->get() as $owned) {
            if (isset($definitions[$owned->title_code])) {
                $title = $definitions[$owned->title_code];

                return ['name' => $title['name'], 'description' => $title['description']];
            }
        }

        return null;
    }

    // ---------- 集計 ----------

    /** 今日（まだ今日練習していなければ昨日）から、毎日続けて練習している日数 */
    private function streakDays(User $user): int
    {
        $days = $user->practiceRecords()
            ->where('played_at', '>=', now()->subDays(400))
            ->orderByDesc('played_at')
            ->pluck('played_at')
            ->map(fn (CarbonInterface $playedAt) => $playedAt->toDateString())
            ->unique()
            ->values();

        $expected = today();
        if ($days->first() !== $expected->toDateString()) {
            $expected = today()->subDay();
        }

        $streak = 0;
        foreach ($days as $day) {
            if ($day !== $expected->toDateString()) {
                break;
            }
            $streak++;
            $expected = $expected->subDay();
        }

        return $streak;
    }

    /** 直近の月ごとの練習回数（新しい月が先） */
    private function monthlyCounts(Collection $records): array
    {
        $counts = $records->countBy(fn (PracticeRecord $record) => $record->played_at->format('Y-m'));

        $months = [];
        for ($i = 0; $i < self::HISTORY_MONTHS; $i++) {
            $month = now()->startOfMonth()->subMonths($i);
            $months[] = [
                'label' => $month->format('Y年n月'),
                'count' => $counts->get($month->format('Y-m'), 0),
            ];
        }

        return $months;
    }

    /** 練習・難易度ごとの、いちばんよい点数と、それをとった日 */
    private function bestRecords(Collection $records): array
    {
        $levels = config('growth.levels');
        $result = [];

        foreach (config('growth.practices') as $type => $practice) {
            $rows = [];
            foreach ($levels as $level => $levelLabel) {
                $best = $records
                    ->where('practice_type', $type)
                    ->where('level', $level)
                    ->sortBy([['score', 'desc'], ['played_at', 'asc']])
                    ->first();

                if ($best !== null) {
                    $rows[] = [
                        'level' => $levelLabel,
                        'score' => $best->score,
                        'date' => $best->played_at->format('Y年n月j日'),
                    ];
                }
            }

            if ($rows !== []) {
                $result[] = ['label' => $practice['practice_label'], 'rows' => $rows];
            }
        }

        return $result;
    }

    /** 獲得した称号と、獲得した日（初級クリア日など） */
    private function acquiredTitles(User $user): array
    {
        $definitions = $this->titleDefinitions();

        return $user->titles()
            ->orderBy('acquired_at')
            ->orderBy('id')
            ->get()
            ->filter(fn ($owned) => isset($definitions[$owned->title_code]))
            ->map(fn ($owned) => [
                'name' => $definitions[$owned->title_code]['name'],
                'date' => $owned->acquired_at->format('Y年n月j日'),
            ])
            ->values()
            ->all();
    }

    /** 自分のいちばんよい点数を更新した記録（新しいものから） */
    private function improvements(Collection $records): array
    {
        $practices = config('growth.practices');
        $levels = config('growth.levels');
        $bests = [];
        $improvements = [];

        foreach ($records as $record) {
            $key = $record->practice_type.'.'.$record->level;

            if (isset($bests[$key]) && $record->score > $bests[$key] && isset($practices[$record->practice_type])) {
                $improvements[] = [
                    'label' => $practices[$record->practice_type]['practice_label'].'（'.($levels[$record->level] ?? '').'）',
                    'from' => $bests[$key],
                    'to' => $record->score,
                    'date' => $record->played_at->format('Y年n月j日'),
                ];
            }

            $bests[$key] = max($bests[$key] ?? 0, $record->score);
        }

        return array_slice(array_reverse($improvements), 0, self::IMPROVEMENT_LIMIT);
    }
}
