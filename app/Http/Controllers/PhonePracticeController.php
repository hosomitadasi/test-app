<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * 電話の練習（電話を取る練習／電話を切る練習）の画面を返すコントローラ。
 *
 * 練習内容選択（contents） → 練習詳細選択（details） → 難易度選択（difficulty） → 練習（play）と進む。
 * 画面操作の練習・文字入力の練習とは出題も進み方も異なるため、別のコントローラとして持つ。
 *
 * 着信画面・通話中画面は、アプリの中に作った「まね」の画面であり、
 * 実際の電話アプリを動かしたり、電話をかけたりすることはない。
 * 通信を伴わないクライアント完結型のため、ここでは難易度ごとの設定値の受け渡しのみを担当する。
 */
class PhonePracticeController extends Controller
{
    /** 練習の種類。URL の {type} に使う。 */
    public const PRACTICE_TYPES = ['answer', 'hangup'];

    /** 難易度。URL の {level} に使う。 */
    public const LEVELS = ['easy', 'normal', 'hard'];

    /** 着信画面の種類。popup は画面の一部に出る通知、fullscreen は画面全体の着信画面。 */
    private const LAYOUT_POPUP = 'popup';
    private const LAYOUT_FULLSCREEN = 'fullscreen';

    /**
     * 練習の種類ごとの表示内容。
     *
     * record_type は練習結果の practice_type（config/growth.php のキー）。
     * page はこの練習で使う画面。
     */
    private const PRACTICES = [
        'answer' => [
            'label' => '電話を取る練習',
            'menu_label' => '電話を取る練習をする',
            'record_type' => 'phone_answer',
            'page' => 'Phone/Answer',
            'lead' => '電話が かかってきたら<br>みどりの「応答」ボタンを おしてください',
        ],
        'hangup' => [
            'label' => '電話を切る練習',
            'menu_label' => '電話を切る練習をする',
            'record_type' => 'phone_hangup',
            'page' => 'Phone/HangUp',
            'lead' => 'お話が おわったら<br>したの あかい「通話終了」ボタンを おしてください',
        ],
    ];

    /**
     * 練習の種類・難易度ごとの進め方。
     *
     * mode が streak なら、続けて required_streak 回できたら練習完了（失敗すると0回に戻る）。
     * mode が total なら、total_count 回やり終えた時点で、pass_count 回以上できていれば練習完了。
     * layouts は出題に使う着信画面の種類（電話を取る練習のみ）。2つ以上あれば問題ごとにランダムで選ぶ。
     * has_instruction は「応答してください／拒否してください」の指示を出すか（電話を取る練習のみ）。
     */
    private const LEVEL_SETTINGS = [
        'answer' => [
            'easy' => [
                'label' => 'やさしい',
                'mode' => 'streak',
                'required_streak' => 3,
                'total_count' => 0,
                'pass_count' => 0,
                'layouts' => [self::LAYOUT_FULLSCREEN],
                'has_instruction' => false,
                'note' => '3かい つづけて できたら おわりです',
            ],
            'normal' => [
                'label' => 'ふつう',
                'mode' => 'total',
                'required_streak' => 0,
                'total_count' => 5,
                'pass_count' => 4,
                'layouts' => [self::LAYOUT_POPUP, self::LAYOUT_FULLSCREEN],
                'has_instruction' => false,
                'note' => '5かいのうち 4かい できたら ごうかくです<br>着信の 出かたが かわります',
            ],
            'hard' => [
                'label' => 'むずかしい',
                'mode' => 'total',
                'required_streak' => 0,
                'total_count' => 5,
                'pass_count' => 4,
                'layouts' => [self::LAYOUT_POPUP, self::LAYOUT_FULLSCREEN],
                // 「応答してください」「拒否してください」の指示どおりに押す
                'has_instruction' => true,
                'note' => '5かいのうち 4かい できたら ごうかくです<br>画面の しじに あわせて<br>「応答」か「拒否」を おしてください',
            ],
        ],
        'hangup' => [
            'easy' => [
                'label' => 'やさしい',
                'mode' => 'streak',
                'required_streak' => 3,
                'total_count' => 0,
                'pass_count' => 0,
                'layouts' => [],
                'has_instruction' => false,
                'note' => '3かい つづけて できたら おわりです',
            ],
            'normal' => [
                'label' => 'ふつう',
                'mode' => 'total',
                'required_streak' => 0,
                'total_count' => 10,
                'pass_count' => 8,
                'layouts' => [],
                'has_instruction' => false,
                'note' => '10かいのうち 8かい できたら ごうかくです<br>ほかの ボタンは おさないでください',
            ],
            'hard' => [
                'label' => 'むずかしい',
                'mode' => 'total',
                'required_streak' => 0,
                'total_count' => 10,
                'pass_count' => 9,
                'layouts' => [],
                'has_instruction' => false,
                'note' => '10かいのうち 9かい できたら ごうかくです<br>ほかの ボタンは おさないでください',
            ],
        ],
    ];

    /**
     * 着信画面・通話中画面に出す相手の名前。
     * 実在の人や番号に見えないよう、よくある名字だけにし、電話番号は出さない。
     */
    private const CALLERS = [
        'やまだ さん', 'さとう さん', 'すずき さん', 'たなか さん', 'たかはし さん',
        'いとう さん', 'わたなべ さん', 'なかむら さん', 'こばやし さん', 'かとう さん',
    ];

    /**
     * 電話の練習詳細選択画面（電話を取る練習／電話を切る練習）を表示する。
     * 画面操作の練習と同じ画面を使う。
     */
    public function details(): Response
    {
        return Inertia::render('Menu/Details', [
            'title' => '電話の練習を選ぶ',
            'backHref' => route('practice.contents', [], false),
            'practices' => array_map(fn (string $type) => [
                'label' => self::PRACTICES[$type]['menu_label'],
                'href' => route('practice.phone.difficulty', ['type' => $type], false),
            ], self::PRACTICE_TYPES),
        ]);
    }

    /**
     * 難易度選択画面を表示する。画面操作の練習と同じ画面を使う。
     */
    public function difficulty(string $type): Response
    {
        return Inertia::render('Menu/Difficulty', [
            'label' => self::PRACTICES[$type]['label'],
            'backHref' => route('practice.phone.details', [], false),
            'levels' => array_map(fn (string $level) => [
                'label' => self::LEVEL_SETTINGS[$type][$level]['label'],
                'href' => route('practice.phone.play', ['type' => $type, 'level' => $level], false),
            ], self::LEVELS),
        ]);
    }

    /**
     * 練習画面を表示する。電話を取る練習と電話を切る練習で、画面を分ける。
     */
    public function play(string $type, string $level): Response
    {
        $practice = self::PRACTICES[$type];
        $setting = self::LEVEL_SETTINGS[$type][$level];

        return Inertia::render($practice['page'], [
            'type' => $type,
            'level' => $level,
            'recordType' => $practice['record_type'],
            'practice' => $practice,
            'setting' => $setting,
            'callers' => self::CALLERS,
        ]);
    }
}
