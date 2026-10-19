<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * チャット型練習の画面を返すコントローラ。
 *
 * 練習内容選択（contents） → 練習詳細選択（details） → 難易度選択（difficulty） → 練習（play）と進む。
 * メッセージアプリのように、相手から届く質問を読んで、入力欄から答えを送る。
 *
 * 文字打ち練習と同じく通信を伴わないクライアント完結型のため、
 * ここでは質問と難易度ごとの設定値の受け渡しのみを担当する。
 * 利用者が入力した答えはサーバーへ送らず、記録には得点だけを残す。
 */
class ChatPracticeController extends Controller
{
    /** 難易度。URL の {level} に使う。 */
    public const LEVELS = ['easy', 'normal', 'hard'];

    /** 画面に出す練習の名前 */
    public const LABEL = 'チャット型練習';

    /** 得点の表示桁数（3桁 = 最大999点）。ほかの練習と揃える。 */
    private const SCORE_DIGITS = 3;

    /** 答えとして入力できる文字数 */
    private const ANSWER_MAX_LENGTH = 20;

    /**
     * 質問に含めてはいけない言葉。
     *
     * 住所・電話番号・口座番号などの個人情報や、合い言葉（秘密の質問）に使われやすい事柄を
     * 聞き出す練習にならないよう、これらを含む質問は出題しない。
     * 質問を足すときは、ここに当たらないものにする。
     */
    public const FORBIDDEN_WORDS = [
        '住所', '住んで', '電話', '番号', '口座', '銀行', '暗証', 'パスワード', 'カード',
        'メール', '名前', '誕生日', '生年月日', '年齢', '何歳', '出身', '学校', '勤め', '会社',
        '家族', '旧姓', '病気', '保険', 'お金', '収入',
    ];

    /**
     * 「やさしい」「ふつう」で出す質問。身近な好みを聞くもので、どんな答えでもよい。
     */
    private const QUESTIONS = [
        '好きな動物は何ですか？',
        '好きな果物は何ですか？',
        '好きな花は何ですか？',
        '好きな食べ物は何ですか？',
        '好きな飲み物は何ですか？',
        '好きな季節はいつですか？',
        '好きな色は何色ですか？',
        '好きな野菜は何ですか？',
        '好きなお菓子は何ですか？',
        '好きな乗り物は何ですか？',
        '朝ごはんによく食べるものは何ですか？',
        '好きな天気はどんな天気ですか？',
        '好きなスポーツは何ですか？',
        '好きなおかずは何ですか？',
        '好きな麺類は何ですか？',
    ];

    /**
     * 「むずかしい」で出す質問。答えを、指定した文字に変換して入力してもらう。
     *
     * [質問, 変換のしかた] の形で持つ。変換のしかたは katakana（カタカナ）・kanji（漢字）の2種類。
     * 漢字は、答えが漢字で書ける言葉になりやすい質問にだけ使う。
     */
    private const CONVERSION_QUESTIONS = [
        ['嫌いな果物は何ですか？', 'katakana'],
        ['好きな果物は何ですか？', 'katakana'],
        ['好きな動物は何ですか？', 'katakana'],
        ['好きな飲み物は何ですか？', 'katakana'],
        ['好きなスポーツは何ですか？', 'katakana'],
        ['好きな野菜は何ですか？', 'katakana'],
        ['好きなお菓子は何ですか？', 'katakana'],
        ['好きな乗り物は何ですか？', 'katakana'],
        ['好きな季節はいつですか？', 'kanji'],
        ['好きな色は何色ですか？', 'kanji'],
        ['好きな曜日は何曜日ですか？', 'kanji'],
        ['好きな天気はどんな天気ですか？', 'kanji'],
    ];

    /** 変換のしかたごとに、質問のあとに添える頼み方 */
    private const CONVERSION_REQUESTS = [
        'katakana' => 'カタカナで回答してください',
        'kanji' => '漢字を使って回答してください',
    ];

    /** 答えを受け取ったときの、相手からのあいづち。順番に使う。 */
    private const REPLIES = [
        'そうなんですね！',
        'いいですね！',
        'なるほど！',
        'おしえてくれて ありがとうございます！',
    ];

    /** 練習を始めたときに相手から届くあいさつ */
    private const GREETING = 'こんにちは！ これから いくつか しつもんを しますね。';

    /** すべての質問に答え終えたときに相手から届くあいさつ */
    private const CLOSING = 'おはなし してくれて ありがとうございました！';

    /** 難易度ごとの表示内容と進め方 */
    private const LEVEL_SETTINGS = [
        'easy' => [
            'label' => 'やさしい',
            // どの難易度も制限時間はなく、決められた数の質問に答えると結果画面へ進む。
            'required_count' => 3,
            'is_conversion' => false,
            'note' => '3かい やりとりしたら おわりです',
        ],
        'normal' => [
            'label' => 'ふつう',
            'required_count' => 5,
            'is_conversion' => false,
            'note' => '5つの しつもんに こたえてください<br>1つ こたえると 1てんです',
        ],
        'hard' => [
            'label' => 'むずかしい',
            'required_count' => 5,
            // 質問ごとに、カタカナや漢字へ変換して答えてもらう。
            'is_conversion' => true,
            'note' => '5つの しつもんに こたえてください<br>カタカナや かんじに かえて 入力してください',
        ],
    ];

    /**
     * 難易度選択画面を表示する。画面操作の練習と同じ画面を使う。
     */
    public function difficulty(): Response
    {
        return Inertia::render('Menu/Difficulty', [
            'label' => self::LABEL,
            'backHref' => route('practice.typing.details', [], false),
            'levels' => array_map(fn (string $level) => [
                'label' => self::LEVEL_SETTINGS[$level]['label'],
                'href' => route('practice.typing.chat.play', ['level' => $level], false),
            ], self::LEVELS),
        ]);
    }

    /**
     * 練習画面を表示する。難易度に応じた質問をまとめて渡す。
     */
    public function play(string $level): Response
    {
        $setting = self::LEVEL_SETTINGS[$level];

        return Inertia::render('Typing/Chat', [
            'level' => $level,
            'label' => self::LABEL,
            'setting' => $setting,
            'questions' => $this->questionsFor($level),
            'replies' => self::REPLIES,
            'greeting' => self::GREETING,
            'closing' => self::CLOSING,
            'requiredCount' => $setting['required_count'],
            'isConversion' => $setting['is_conversion'],
            'answerMaxLength' => self::ANSWER_MAX_LENGTH,
            'scoreDigits' => self::SCORE_DIGITS,
        ]);
    }

    /**
     * 難易度ごとの質問の一覧を作る。
     *
     * 毎回同じ順番にならないよう並べ替えたうえで、すべての質問を返す。
     * 画面側は「もう一度」のたびに続きの質問から必要な数だけ使う。
     * 1問ごとの内容は
     *   text   … 相手の吹き出しに出す質問
     *   format … 答えの文字の種類（katakana / kanji。「むずかしい」以外は空）
     * とする。
     */
    private function questionsFor(string $level): array
    {
        if (self::LEVEL_SETTINGS[$level]['is_conversion']) {
            $questions = array_map(static fn (array $question) => [
                'text' => $question[0].'　'.self::CONVERSION_REQUESTS[$question[1]],
                'format' => $question[1],
            ], self::CONVERSION_QUESTIONS);
        } else {
            $questions = array_map(static fn (string $text) => [
                'text' => $text,
                'format' => '',
            ], self::QUESTIONS);
        }

        $questions = array_values(array_filter($questions, fn (array $question) => !$this->asksPersonalInfo($question['text'])));
        shuffle($questions);

        return $questions;
    }

    /** 個人情報などを聞き出す質問になっていないか */
    private function asksPersonalInfo(string $text): bool
    {
        foreach (self::FORBIDDEN_WORDS as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }
}
