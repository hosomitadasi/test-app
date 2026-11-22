<?php

/*
|--------------------------------------------------------------------------
| 成長の記録
|--------------------------------------------------------------------------
|
| 練習項目ごとの称号と、練習結果に応じた声かけの文言をまとめる。
|
| ■ 称号の獲得条件（condition）について
|   電話の練習以外は獲得条件がまだ決まっていないため、null（＝獲得しない）にしている。
|   決まったら、次の形で書き込むと、その練習をやり終えたときに判定される。
|
|     'condition' => ['level' => 'normal', 'min_score' => 20, 'times' => 3],
|       … 難易度「ふつう」で 20点以上を 3回 とったら獲得
|
|   全体称号（master）は、次の形で書き込む。
|
|     'condition' => ['requires' => 'advanced'],
|       … すべての練習項目で「上達」の称号を獲得したら獲得
|
| ■ 「できるようになりました」の判定について（仮）
|   achieved_rank に書いた段階の称号を獲得した練習を「できるようになりました」とする。
|   記録がない練習は「これから練習します」、記録はあるがその称号がない練習は「練習中です」。
|
*/

return [

    // 練習項目。キーは練習結果の practice_type と同じ。
    'practices' => [
        'tap' => [
            'label' => 'タップ',
            'practice_label' => '軽く押す練習',
            'titles' => [
                'beginner' => ['name' => 'タップ初級', 'description' => '画面をしっかり押せるようになりました', 'condition' => null],
                'advanced' => ['name' => 'タップ上達', 'description' => '画面をすばやく、ねらって押せるようになりました', 'condition' => null],
            ],
        ],
        'long' => [
            'label' => '長押し',
            'practice_label' => '長く押す練習',
            'titles' => [
                'beginner' => ['name' => '長押し初級', 'description' => '画面を長く押し続けられるようになりました', 'condition' => null],
                'advanced' => ['name' => '長押し上達', 'description' => '長押しを、落ち着いて何度もできるようになりました', 'condition' => null],
            ],
        ],
        'swipe' => [
            'label' => 'なぞる',
            'practice_label' => 'なぞる練習',
            'titles' => [
                'beginner' => ['name' => 'なぞる初級', 'description' => '画面をゆびでなぞれるようになりました', 'condition' => null],
                'advanced' => ['name' => 'なぞる上達', 'description' => 'やじるしの向きに、思いどおりになぞれるようになりました', 'condition' => null],
            ],
        ],
        'flick' => [
            'label' => 'はらう',
            'practice_label' => 'はらう練習',
            'titles' => [
                'beginner' => ['name' => 'はらう初級', 'description' => '画面をゆびではらえるようになりました', 'condition' => null],
                'advanced' => ['name' => 'はらう上達', 'description' => 'やじるしの向きに、すばやくはらえるようになりました', 'condition' => null],
            ],
        ],
        'typing' => [
            'label' => '文字入力',
            'practice_label' => '文字打ち練習',
            'titles' => [
                'beginner' => ['name' => '文字入力初級', 'description' => 'ひらがなを入力できるようになりました', 'condition' => null],
                'advanced' => ['name' => '文字入力上達', 'description' => 'かんじやカタカナにも変えられるようになりました', 'condition' => null],
            ],
        ],
        'chat' => [
            'label' => 'チャット',
            'practice_label' => 'チャット型練習',
            'titles' => [
                'beginner' => ['name' => 'チャット初級', 'description' => 'しつもんに 文字で こたえられるようになりました', 'condition' => null],
                'advanced' => ['name' => 'チャット上達', 'description' => 'カタカナや かんじで 返事を送れるようになりました', 'condition' => null],
            ],
        ],
        // 電話の練習は、合格したとき（練習完了）だけ記録が残り、得点は「できた回数」とする。
        // 電話の練習の称号は条件を決めて追加した。ほかの練習の条件（null）は変えていない。
        'phone_answer' => [
            'label' => '電話を取る',
            'practice_label' => '電話を取る練習',
            'titles' => [
                // 「やさしい」で 3回続けて「応答」を押せたら獲得
                'beginner' => ['name' => '電話を取る初級', 'description' => 'かかってきた電話に 出られるようになりました', 'condition' => ['level' => 'easy', 'min_score' => 3, 'times' => 1]],
                // 「むずかしい」で 5回中4回以上、指示どおりに押せたら獲得
                'advanced' => ['name' => '電話を取る上達', 'description' => '着信の 出かたが かわっても、落ち着いて 応答・拒否が できるようになりました', 'condition' => ['level' => 'hard', 'min_score' => 4, 'times' => 1]],
            ],
        ],
        'phone_hangup' => [
            'label' => '電話を切る',
            'practice_label' => '電話を切る練習',
            'titles' => [
                // 「やさしい」で 3回続けて「通話終了」を押せたら獲得
                'beginner' => ['name' => '電話を切る初級', 'description' => 'お話が おわったら 電話を 切れるようになりました', 'condition' => ['level' => 'easy', 'min_score' => 3, 'times' => 1]],
                // 「むずかしい」で 10回中9回以上できたら獲得
                'advanced' => ['name' => '電話を切る上達', 'description' => 'ほかの ボタンと まちがえずに 電話を 切れるようになりました', 'condition' => ['level' => 'hard', 'min_score' => 9, 'times' => 1]],
            ],
        ],
    ],

    // 最終的な全体称号
    'master' => [
        'name' => 'スマホ練習マスター',
        'description' => 'スマホの操作を、ひととおり身につけました',
        'condition' => null,
    ],

    // 「できるようになりました」とみなす称号の段階（仮）
    'achieved_rank' => 'beginner',

    // 難易度の表示名
    'levels' => [
        'easy' => 'やさしい',
        'normal' => 'ふつう',
        'hard' => 'むずかしい',
    ],

    // 練習をやり終えたときの声かけ（仮の文言）
    'praise' => [
        'first' => 'はじめての練習、おつかれさまでした！さいごまでできましたね。',
        'best' => 'これまでで いちばんの点数です！すばらしいです！',
        'keep' => 'おつかれさまでした！つづけると、もっと上手になりますよ。',
    ],
];
