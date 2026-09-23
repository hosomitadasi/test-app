<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * 文字打ち練習の画面を返すコントローラ。
 *
 * 練習内容選択（contents） → 難易度選択（difficulty） → 練習（play）と進む。
 * 画面操作の練習（PracticeController）とは進み方も出題内容も異なるため、
 * 既存の練習に影響を出さないよう別のコントローラとして持つ。
 * 制限時間・得点・中断のしくみは画面操作の練習と同じ考え方を踏襲する。
 *
 * 通信を伴わないクライアント完結型のため、
 * ここでは出題内容と難易度ごとの設定値の受け渡しのみを担当する。
 */
class TypingPracticeController extends Controller
{
    /** 難易度。URL の {level} に使う。 */
    public const LEVELS = ['easy', 'normal', 'hard'];

    /** 練習の制限時間（秒）。「やさしい」は回数制のため使わない。 */
    private const TIME_LIMIT_SECONDS = 180;

    /** 「やさしい」で結果画面に進むまでの回答数 */
    private const EASY_REQUIRED_COUNT = 10;

    /** 得点の表示桁数（3桁 = 最大999点）。画面操作の練習と揃える。 */
    private const SCORE_DIGITS = 3;

    /** ヒントを1段階進める間隔（ミリ秒）。「やさしい」のみ使う。 */
    private const HINT_INTERVAL_MS = 10000;

    /** 画面に出す練習の名前 */
    private const LABEL = '文字打ち練習';

    /**
     * フリック入力のボタン（12キー）に書かれている文字と、そのボタンで打てる文字。
     *
     * ヒントで「どのボタンを押せばよいか」を伝えるために使う。
     */
    private const KEY_ROWS = [
        'あ' => ['あ', 'い', 'う', 'え', 'お'],
        'か' => ['か', 'き', 'く', 'け', 'こ'],
        'さ' => ['さ', 'し', 'す', 'せ', 'そ'],
        'た' => ['た', 'ち', 'つ', 'て', 'と'],
        'な' => ['な', 'に', 'ぬ', 'ね', 'の'],
        'は' => ['は', 'ひ', 'ふ', 'へ', 'ほ'],
        'ま' => ['ま', 'み', 'む', 'め', 'も'],
        'や' => ['や', 'ゆ', 'よ'],
        'ら' => ['ら', 'り', 'る', 'れ', 'ろ'],
        'わ' => ['わ', 'を', 'ん', 'ー'],
    ];

    /**
     * そのままでは打てない文字と、その打ち方。
     *
     * [元になる文字, 変え方] の形で持つ。
     * 変え方は dakuten（濁点）・handakuten（半濁点）・small（小さい文字）の3種類。
     */
    private const MODIFIED_KANA = [
        'が' => ['か', 'dakuten'], 'ぎ' => ['き', 'dakuten'], 'ぐ' => ['く', 'dakuten'],
        'げ' => ['け', 'dakuten'], 'ご' => ['こ', 'dakuten'],
        'ざ' => ['さ', 'dakuten'], 'じ' => ['し', 'dakuten'], 'ず' => ['す', 'dakuten'],
        'ぜ' => ['せ', 'dakuten'], 'ぞ' => ['そ', 'dakuten'],
        'だ' => ['た', 'dakuten'], 'ぢ' => ['ち', 'dakuten'], 'づ' => ['つ', 'dakuten'],
        'で' => ['て', 'dakuten'], 'ど' => ['と', 'dakuten'],
        'ば' => ['は', 'dakuten'], 'び' => ['ひ', 'dakuten'], 'ぶ' => ['ふ', 'dakuten'],
        'べ' => ['へ', 'dakuten'], 'ぼ' => ['ほ', 'dakuten'],
        'ぱ' => ['は', 'handakuten'], 'ぴ' => ['ひ', 'handakuten'], 'ぷ' => ['ふ', 'handakuten'],
        'ぺ' => ['へ', 'handakuten'], 'ぽ' => ['ほ', 'handakuten'],
        'ぁ' => ['あ', 'small'], 'ぃ' => ['い', 'small'], 'ぅ' => ['う', 'small'],
        'ぇ' => ['え', 'small'], 'ぉ' => ['お', 'small'],
        'っ' => ['つ', 'small'],
        'ゃ' => ['や', 'small'], 'ゅ' => ['ゆ', 'small'], 'ょ' => ['よ', 'small'],
    ];

    /** 濁点・半濁点・小さい文字を打つボタンの、高齢者にも分かる言い表し方 */
    private const MODIFIER_KEY_LABEL = '「ま」と書かれたボタンの下にあるボタン';

    /**
     * 「やさしい」「ふつう」で出す問題。ひらがな10文字以内の身近な言葉。
     *
     * 濁点・半濁点・小さい文字を含む言葉も混ぜ、ヒントで打ち方を学べるようにする。
     */
    private const KANA_WORDS = [
        'りんご', 'みかん', 'たまご', 'さかな', 'でんわ', 'くるま', 'ねこ', 'はなび',
        'ぼうし', 'とけい', 'さとう', 'しんぶん', 'おちゃ', 'たんぽぽ', 'えんぴつ',
        'かばん', 'ざぶとん', 'めがね', 'ゆびわ', 'ぎゅうにゅう', 'きっぷ', 'てがみ',
        'やさい', 'くすり', 'こうえん', 'たいよう', 'ひこうき', 'じてんしゃ', 'みずうみ',
        'ざっし', 'せんぷうき', 'おんがく',
    ];

    /**
     * 「むずかしい」で出す問題。読みがなを見ながら、漢字・カタカナへ変換して入力する。
     *
     * [読みがな, 入力してほしい表記] の形で持つ。
     */
    private const CONVERSION_WORDS = [
        ['りんご', '林檎'],
        ['といれっとぺーぱー', 'トイレットペーパー'],
        ['でんわ', '電話'],
        ['しんぶん', '新聞'],
        ['めがね', '眼鏡'],
        ['びょういん', '病院'],
        ['くすり', '薬'],
        ['れいぞうこ', '冷蔵庫'],
        ['せんたくき', '洗濯機'],
        ['かさ', '傘'],
        ['ゆうびんきょく', '郵便局'],
        ['てれび', 'テレビ'],
        ['こーひー', 'コーヒー'],
        ['ばす', 'バス'],
        ['えき', '駅'],
        ['てがみ', '手紙'],
        ['さんぽ', '散歩'],
        ['たいおんけい', '体温計'],
        ['みそしる', '味噌汁'],
        ['えあこん', 'エアコン'],
    ];

    /** 難易度ごとの表示内容と進め方 */
    private const LEVEL_SETTINGS = [
        'easy' => [
            'label' => 'やさしい',
            // 制限時間なし。決められた回数だけ答えると結果画面へ進む。
            'time_limit_seconds' => 0,
            'required_count' => self::EASY_REQUIRED_COUNT,
            'has_hint' => true,
            'is_conversion' => false,
            'note' => '10もん こたえたら おわりです<br>こまったら まっていると ヒントが 出ます',
        ],
        'normal' => [
            'label' => 'ふつう',
            'time_limit_seconds' => self::TIME_LIMIT_SECONDS,
            'required_count' => 0,
            'has_hint' => false,
            'is_conversion' => false,
            'note' => '3ぷんかん つづけてください<br>1もん せいかいすると 1てんです',
        ],
        'hard' => [
            'label' => 'むずかしい',
            'time_limit_seconds' => self::TIME_LIMIT_SECONDS,
            'required_count' => 0,
            'has_hint' => false,
            // 読みがなを見ながら、漢字やカタカナに変換して入力する。
            'is_conversion' => true,
            'note' => '3ぷんかん つづけてください<br>かんじや カタカナに かえて 入力してください',
        ],
    ];

    /**
     * 難易度選択画面を表示する。
     */
    public function difficulty(): View
    {
        return view('typing.difficulty', [
            'label' => self::LABEL,
            'levels' => self::LEVEL_SETTINGS,
        ]);
    }

    /**
     * 練習画面を表示する。難易度に応じた問題とヒントをまとめて渡す。
     */
    public function play(string $level): View
    {
        $setting = self::LEVEL_SETTINGS[$level];

        return view('typing.play', [
            'level' => $level,
            'label' => self::LABEL,
            'setting' => $setting,
            'questions' => $this->questionsFor($level),
            'timeLimitSeconds' => $setting['time_limit_seconds'],
            'requiredCount' => $setting['required_count'],
            'hasHint' => $setting['has_hint'],
            'isConversion' => $setting['is_conversion'],
            'hintIntervalMs' => self::HINT_INTERVAL_MS,
            'scoreDigits' => self::SCORE_DIGITS,
        ]);
    }

    /**
     * 難易度ごとの出題一覧を作る。
     *
     * 毎回同じ順番にならないよう並べ替えたうえで返す。
     * 1問ごとの内容は
     *   prompt … 画面に大きく出す文字（読みがな）
     *   answer … 正解とみなす入力
     *   target … 入力してほしい表記（「むずかしい」のみ。読みがなの下に小さく出す）
     *   hints  … 時間が経つごとに出すヒント（「やさしい」のみ）
     * とする。
     */
    private function questionsFor(string $level): array
    {
        if (self::LEVEL_SETTINGS[$level]['is_conversion']) {
            $words = self::CONVERSION_WORDS;
            shuffle($words);

            return array_map(static function (array $word): array {
                return [
                    'prompt' => $word[0],
                    'target' => $word[1],
                    // 変換の練習のため、入力してほしい表記と完全に一致したときだけ正解とする。
                    'answer' => $word[1],
                    'hints' => [],
                ];
            }, $words);
        }

        $words = self::KANA_WORDS;
        shuffle($words);
        $withHint = self::LEVEL_SETTINGS[$level]['has_hint'];

        return array_map(function (string $word) use ($withHint): array {
            return [
                'prompt' => $word,
                'target' => '',
                'answer' => $word,
                'hints' => $withHint ? $this->buildHints($word) : [],
            ];
        }, $words);
    }

    /**
     * 「やさしい」で時間が経つごとに出すヒントを作る。
     *
     * 1段階目はどのボタンを押すか、2段階目は濁点などの打ち方を伝える。
     * 高齢者が読んで分かるよう、ボタンに書かれている文字をそのまま使って説明する。
     */
    private function buildHints(string $word): array
    {
        $characters = $this->splitCharacters($word);

        return [
            $this->buildKeyHint($characters),
            $this->buildModifierHint($characters),
        ];
    }

    /**
     * 1段階目のヒント。文字ごとに「どのボタンを押すか」を並べて伝える。
     *
     * 例：「り」は「ら」と書かれているボタン、「ご」はまず「か」と書かれているボタンを押して「こ」を入れてみてください。
     */
    private function buildKeyHint(array $characters): string
    {
        $parts = [];
        $lastIsModified = false;

        foreach ($this->uniqueCharacters($characters) as $character) {
            $modified = self::MODIFIED_KANA[$character] ?? null;
            $base = $modified === null ? $character : $modified[0];
            $key = $this->keyOf($base);

            if ($key === null) {
                continue;   // ボタンの分からない文字（記号など）は説明しない
            }

            $parts[] = $modified === null
                ? '「'.$character.'」は「'.$key.'」と書かれているボタン'
                : '「'.$character.'」はまず「'.$key.'」と書かれているボタンを押して「'.$base.'」';
            $lastIsModified = $modified !== null;
        }

        if ($parts === []) {
            return 'ゆっくりで だいじょうぶです。1もじずつ 入れてみてください。';
        }

        // 文の終わりが「ボタン」なら「押して」、打ちたい文字なら「入れて」で結ぶ
        $ending = $lastIsModified ? 'を入れてみてください。' : 'を押してみてください。';

        return implode('、', $parts).$ending;
    }

    /**
     * 2段階目のヒント。濁点・半濁点・小さい文字の打ち方を伝える。
     *
     * 例：「ご」は「こ」と打った後、「ま」と書かれたボタンの下にあるボタンを押してみてください。
     */
    private function buildModifierHint(array $characters): string
    {
        $parts = [];

        foreach ($this->uniqueCharacters($characters) as $character) {
            if (!isset(self::MODIFIED_KANA[$character])) {
                continue;
            }

            [$base, $kind] = self::MODIFIED_KANA[$character];
            $times = $this->modifierPressCount($base, $kind);
            $count = $times > 1 ? $times.'回' : '';

            $parts[] = '「'.$character.'」は「'.$base.'」と打った後、'
                .self::MODIFIER_KEY_LABEL.'を'.$count.'押してみてください。';
        }

        if ($parts === []) {
            // 濁点などを使わない言葉では、あせらず1文字ずつ入れるよう伝える。
            $reading = '「'.implode('」「', $characters).'」';

            return 'ゆっくりで だいじょうぶです。'.$reading.' の じゅんに 1もじずつ 入れてみてください。';
        }

        return implode('', $parts);
    }

    /**
     * 濁点などのボタンを何回押せばその文字になるかを求める。
     *
     * ボタンは押すたびに 濁点 → 半濁点 → 小さい文字 の順に切り替わるため、
     * 濁点のある行の小さい文字や半濁点は2回押す必要がある。
     */
    private function modifierPressCount(string $base, string $kind): int
    {
        if ($kind === 'dakuten') {
            return 1;
        }

        if ($kind === 'handakuten') {
            return 2;   // 濁点のあとに半濁点へ変わる
        }

        // 小さい文字。濁点にできる文字（「つ」など）は濁点を飛ばすため2回押す。
        $hasDakuten = false;
        foreach (self::MODIFIED_KANA as [$modifiedBase, $modifiedKind]) {
            if ($modifiedBase === $base && $modifiedKind === 'dakuten') {
                $hasDakuten = true;
                break;
            }
        }

        return $hasDakuten ? 2 : 1;
    }

    /** その文字が、どのボタンに書かれている行のものかを返す */
    private function keyOf(string $character): ?string
    {
        foreach (self::KEY_ROWS as $key => $characters) {
            if (in_array($character, $characters, true)) {
                return $key;
            }
        }

        return null;
    }

    /** 言葉を1文字ずつに分ける */
    private function splitCharacters(string $word): array
    {
        return preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** 同じ文字の説明を繰り返さないよう、出てきた順に重複を取り除く */
    private function uniqueCharacters(array $characters): array
    {
        return array_values(array_unique($characters));
    }
}
