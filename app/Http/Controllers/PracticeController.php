<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * 練習アプリの画面を返すコントローラ。
 *
 * メニュー → 練習内容 → 練習詳細 → 難易度 → 各練習、という順で画面が進む。
 * 選択系の画面は表示のみで状態を持たないため、それぞれビューを返すだけとする。
 *
 * 各練習は通信を伴わないクライアント完結型のため、
 * ここでは画面の入り口（練習の種類・難易度に応じた設定値の受け渡し）のみを担当する。
 */
class PracticeController extends Controller
{
    /** 練習の種類。URL の {type} に使う。 */
    public const PRACTICE_TYPES = ['tap', 'long', 'swipe', 'flick'];

    /** 難易度。URL の {level} に使う。 */
    public const LEVELS = ['easy', 'normal', 'hard'];

    /** 練習の制限時間（秒）。「やさしい」は回数制のため使わない。 */
    private const TIME_LIMIT_SECONDS = 180;

    /** 「やさしい」で結果画面に進むまでの操作回数 */
    private const EASY_REQUIRED_COUNT = 10;

    /** 得点の表示桁数（3桁 = 最大999点） */
    private const SCORE_DIGITS = 3;

    /** 「長く押す練習」で加点となる長押し時間（ミリ秒） */
    private const HOLD_MS = 1000;

    /**
     * 練習の種類ごとの表示内容。
     *
     * lead は練習前に表示する説明文。高齢者が読みやすいよう短く区切る。
     */
    private const PRACTICES = [
        'tap' => [
            'label' => '軽く押す練習',
            'menu_label' => '軽く押す練習をする',
            'lead' => '画面に出てくる まるを<br>かるく さわってください',
            'target_label' => 'ここをさわってください',
        ],
        'long' => [
            'label' => '長く押す練習',
            'menu_label' => '長く押す練習をする',
            'lead' => '画面に出てくる まるを<br>1びょう いじょう おしつづけてください',
            'target_label' => 'ここを おしつづけてください',
        ],
        'swipe' => [
            'label' => 'なぞる練習',
            'menu_label' => 'なぞる練習をする',
            'lead' => 'やじるしが 出たら<br>ゆびを つけたまま<br>やじるしの むきへ なぞってください',
            'target_label' => 'やじるしの むきへ なぞってください',
        ],
        'flick' => [
            'label' => 'はらう練習',
            'menu_label' => 'はらう練習をする',
            'lead' => 'やじるしが 出たら<br>やじるしの むきへ<br>すばやく はらってください',
            'target_label' => 'やじるしの むきへ はらってください',
        ],
    ];

    /** 難易度ごとの表示内容と進め方 */
    private const LEVEL_SETTINGS = [
        'easy' => [
            'label' => 'やさしい',
            // 制限時間なし。決められた回数だけ操作すると結果画面へ進む。
            'time_limit_seconds' => 0,
            'required_count' => self::EASY_REQUIRED_COUNT,
            'has_decoy' => false,
            'note' => '10かい できたら おわりです',
        ],
        'normal' => [
            'label' => 'ふつう',
            'time_limit_seconds' => self::TIME_LIMIT_SECONDS,
            'required_count' => 0,
            'has_decoy' => false,
            'note' => '3ぷんかん つづけてください',
        ],
        'hard' => [
            'label' => 'むずかしい',
            'time_limit_seconds' => self::TIME_LIMIT_SECONDS,
            'required_count' => 0,
            // ただしいアイコンと一緒に、操作してはいけないアイコンを1つ出す。
            'has_decoy' => true,
            'note' => '3ぷんかん つづけてください<br>×の ついた ものは さわらないでください',
        ],
    ];

    /**
     * メニュー画面（今日の練習）を表示する。
     */
    public function menu(): Response
    {
        return Inertia::render('Menu/Menu');
    }

    /**
     * 練習内容選択画面（画面操作／文字入力／電話）を表示する。
     */
    public function contents(): Response
    {
        return Inertia::render('Menu/Contents');
    }

    /**
     * 練習詳細選択画面（軽く押す／長く押す／なぞる／はらう）を表示する。
     *
     * 文字入力の練習と同じ画面を使うため、ボタンの名前と行き先をここで決めて渡す。
     */
    public function details(): Response
    {
        return Inertia::render('Menu/Details', [
            'title' => '画面操作の練習を選ぶ',
            'backHref' => route('practice.contents', [], false),
            'practices' => array_map(fn (string $type) => [
                'label' => self::PRACTICES[$type]['menu_label'],
                'href' => route('practice.difficulty', ['type' => $type], false),
            ], self::PRACTICE_TYPES),
        ]);
    }

    /**
     * 練習難易度選択画面（やさしい／ふつう／むずかしい）を表示する。
     *
     * 文字入力の練習と同じ画面を使うため、ボタンの名前と行き先をここで決めて渡す。
     */
    public function difficulty(string $type): Response
    {
        return Inertia::render('Menu/Difficulty', [
            'label' => self::PRACTICES[$type]['label'],
            'backHref' => route('practice.details', [], false),
            'levels' => array_map(fn (string $level) => [
                'label' => self::LEVEL_SETTINGS[$level]['label'],
                'href' => route('practice.play', ['type' => $type, 'level' => $level], false),
            ], self::LEVELS),
        ]);
    }

    /**
     * 練習画面を表示する。種類と難易度の組み合わせで1つのビューを使い回す。
     */
    public function play(string $type, string $level): Response
    {
        $practice = self::PRACTICES[$type];
        $setting = self::LEVEL_SETTINGS[$level];

        return Inertia::render('Practice/Play', [
            'type' => $type,
            'level' => $level,
            'practice' => $practice,
            'setting' => $setting,
            'timeLimitSeconds' => $setting['time_limit_seconds'],
            'requiredCount' => $setting['required_count'],
            'hasDecoy' => $setting['has_decoy'],
            'holdMs' => self::HOLD_MS,
            'scoreDigits' => self::SCORE_DIGITS,
        ]);
    }
}
