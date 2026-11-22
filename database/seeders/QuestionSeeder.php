<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Seeder;

/**
 * 文字入力の練習で出す最初の問題を登録する。
 *
 * 何度実行しても同じ問題が二重に入らないよう、
 * （練習の種類・変換の有無・問題文）が同じものは上書きする。
 */
class QuestionSeeder extends Seeder
{
    /**
     * 文字打ち練習「やさしい」「ふつう」の問題。ひらがな10文字以内の身近な言葉。
     * 濁点・半濁点・小さい文字を含む言葉も混ぜ、ヒントで打ち方を学べるようにする。
     */
    private const TYPING_KANA_WORDS = [
        'りんご', 'みかん', 'たまご', 'さかな', 'でんわ', 'くるま', 'ねこ', 'はなび',
        'ぼうし', 'とけい', 'さとう', 'しんぶん', 'おちゃ', 'たんぽぽ', 'えんぴつ',
        'かばん', 'ざぶとん', 'めがね', 'ゆびわ', 'ぎゅうにゅう', 'きっぷ', 'てがみ',
        'やさい', 'くすり', 'こうえん', 'たいよう', 'ひこうき', 'じてんしゃ', 'みずうみ',
        'ざっし', 'せんぷうき', 'おんがく',
        'おにぎり', 'せんたく', 'ふうせん', 'かぼちゃ', 'すいか', 'とうふ', 'しゃしん', 'さくら',
    ];

    /**
     * 文字打ち練習「むずかしい」の問題。[読みがな, 入力してほしい表記]
     */
    private const TYPING_CONVERSION_WORDS = [
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
        ['ぎゅうにゅう', '牛乳'],
        ['やきゅう', '野球'],
        ['ぱん', 'パン'],
        ['ほん', '本'],
        ['くつ', '靴'],
    ];

    /**
     * チャット型練習「やさしい」「ふつう」の質問。身近な好みを聞くもので、どんな答えでもよい。
     * 個人情報を聞き出す質問は入れない（ChatPracticeController::FORBIDDEN_WORDS に当たらないものにする）。
     */
    private const CHAT_QUESTIONS = [
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
     * チャット型練習「むずかしい」の質問。[質問, 答えの文字の種類（katakana / kanji）]
     * 漢字は、答えが漢字で書ける言葉になりやすい質問にだけ使う。
     */
    private const CHAT_CONVERSION_QUESTIONS = [
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

    public function run(): void
    {
        $rows = [];

        foreach (self::TYPING_KANA_WORDS as $word) {
            $rows[] = $this->row(Question::TYPE_TYPING, false, $word);
        }
        foreach (self::TYPING_CONVERSION_WORDS as [$reading, $notation]) {
            $rows[] = $this->row(Question::TYPE_TYPING, true, $reading, answer: $notation);
        }
        foreach (self::CHAT_QUESTIONS as $text) {
            $rows[] = $this->row(Question::TYPE_CHAT, false, $text);
        }
        foreach (self::CHAT_CONVERSION_QUESTIONS as [$text, $format]) {
            $rows[] = $this->row(Question::TYPE_CHAT, true, $text, format: $format);
        }

        Question::upsert(
            $rows,
            ['practice_type', 'is_conversion', 'text'],
            ['answer', 'answer_format', 'updated_at'],
        );
    }

    private function row(string $type, bool $isConversion, string $text, ?string $answer = null, ?string $format = null): array
    {
        $now = now();

        return [
            'practice_type' => $type,
            'is_conversion' => $isConversion,
            'text' => $text,
            'answer' => $answer,
            'answer_format' => $format,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
