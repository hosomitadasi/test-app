/* ==========================================================================
   出題の山札（文字打ち練習・チャット型練習で共通）
   1回の練習の中では、一度出した問題を練習が終わるまで二度と出さない。
   - 練習を始めるたびに reset() で並べ替え、使った問題をすべて山札に戻す
   - next() は、まだ出していない問題を1つ取り出す。出し尽くしたら null を返す
   ========================================================================== */

/** 同じ問題（同じ id）が二重に渡されても、1つにまとめる */
function uniqueQuestions(questions) {
    const seen = new Set();
    const result = [];

    (Array.isArray(questions) ? questions : []).forEach((question) => {
        const key = question && question.id != null ? 'id:' + question.id : JSON.stringify(question);
        if (seen.has(key)) { return; }
        seen.add(key);
        result.push(question);
    });

    return result;
}

/** 並び順をばらばらにする（Fisher–Yates） */
function shuffle(list) {
    for (let i = list.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));
        [list[i], list[j]] = [list[j], list[i]];
    }
    return list;
}

export function createQuestionDeck(questions) {
    const all = uniqueQuestions(questions);
    let order = all.slice();   // 最初はサーバーで並べ替えた順のまま使う
    let index = 0;             // 次に出す問題の位置（これより前は、この練習で出し終えた問題）

    return {
        /** 用意されている問題の数 */
        get size() { return all.length; },

        /** この練習でまだ出していない問題があるか */
        hasNext() { return index < order.length; },

        /** まだ出していない問題を1つ取り出す。出し尽くしたら null */
        next() {
            if (index >= order.length) { return null; }
            const question = order[index];
            index += 1;
            return question;
        },

        /** 新しい練習を始めるときに呼ぶ。並べ替えて、すべての問題を出せる状態に戻す */
        reset() {
            order = shuffle(all.slice());
            index = 0;
        },
    };
}
