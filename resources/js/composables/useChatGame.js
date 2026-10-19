/* ==========================================================================
   チャット型練習（やさしい／ふつう／むずかしい）
   画面は「開始 / 練習 / 中断 / 結果」の4状態を行き来する。

   相手から届く質問を読み、入力欄から答えを送る。決められた数の質問に答えると結果画面へ進む。
   中断・再開・結果の記録・画面を離れたときの片付けは、文字打ち練習（useTypingGame）と同じ考え方にしている。
   - 中断中は、相手の返事を待つ時間も止める
   - 他のアプリに切り替えたら自動で中断する
   - やり終えたら onFinish(得点) を呼ぶ（結果の記録に使う）
   ========================================================================== */
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

/** 答えの文字の種類ごとの、確かめ方と、合っていないときの声かけ */
const FORMATS = {
    // カタカナだけで書かれていること（長音・中黒も含む）
    katakana: { test: (text) => /^[ァ-ヺー・]+$/u.test(text), message: 'カタカナで 入力してください' },
    // 漢字を1文字以上使っていること（「晴れ」「月曜日」のような書き方も認める）
    kanji: { test: (text) => /\p{Script=Han}/u.test(text), message: 'かんじを つかって 入力してください' },
};

export function useChatGame(CONFIG, { onFinish } = {}) {
    const REQUIRED_COUNT = CONFIG.requiredCount || 0;
    const SCORE_DIGITS = CONFIG.scoreDigits || 3;
    const MAX_SCORE = Math.pow(10, SCORE_DIGITS) - 1;          // 3桁 = 999点まで
    const QUESTIONS = Array.isArray(CONFIG.questions) ? CONFIG.questions : [];
    const REPLIES = Array.isArray(CONFIG.replies) && CONFIG.replies.length > 0 ? CONFIG.replies : ['そうなんですね！'];

    const GUARD_MS = 700;            // 画面が変わった直後、指が残っていても誤って押させない時間
    const TYPING_MS = 1200;          // 相手が入力している様子（…）を見せる時間
    const RESUME_MS = 500;           // 再開してから、止めていた相手の返事を出すまでの間
    const FINISH_DELAY_MS = 1500;    // 最後のあいさつを読んでもらってから結果画面へ進むまでの間

    // ---------- 画面に出す状態 ----------
    const state = ref('start');      // start / play / paused / result
    const guarded = ref('');         // 誤操作よけをかけている画面
    const messages = ref([]);        // やり取りの吹き出し { id, from: 'partner' | 'me', text }
    const isPartnerTyping = ref(false);
    const canAnswer = ref(false);    // 質問が届いて、答えを送れる状態か
    const draft = ref('');           // 入力欄の文字
    const notice = ref('');          // 入力のまちがいを伝える1行
    const score = ref(0);
    const remaining = ref(REQUIRED_COUNT);

    // 画面側で ref="inputEl" / ref="logEl" を付けた要素が入る
    const inputEl = ref(null);
    const logEl = ref(null);

    // ---------- 内部の状態 ----------
    let current = null;              // 今答えてもらっている質問
    let questionIndex = 0;           // 次に出す質問。「もう一度」では続きの質問から出す
    let messageId = 0;
    let replyIndex = 0;
    let pendingStep = null;          // 相手の次の発言など、時間をおいて行う処理
    let pendingHandle = null;
    let guardHandle = null;
    let wakeLock = null;

    // ---------- 表示ユーティリティ ----------

    /** 得点を 000点 形式にする */
    function formatScore(value) {
        return String(value).padStart(SCORE_DIGITS, '0') + '点';
    }

    /**
     * 画面が出た直後の数百ミリ秒はボタンを効かなくする。
     * 練習中に触れていた指がそのまま新しい画面のボタンを押してしまうのを防ぐ。
     */
    function guardScreen(name) {
        guarded.value = name;
        window.clearTimeout(guardHandle);
        guardHandle = window.setTimeout(() => { guarded.value = ''; }, GUARD_MS);
    }

    /** いちばん新しい吹き出しが見えるよう、やり取りの欄を下まで送る */
    function scrollToBottom() {
        nextTick(() => {
            if (logEl.value) {
                logEl.value.scrollTop = logEl.value.scrollHeight;
            }
        });
    }

    function addMessage(from, text) {
        messageId += 1;
        messages.value.push({ id: messageId, from, text });
        scrollToBottom();
    }

    /**
     * 入力欄に印を戻し、端末のキーボードを出す。
     * 画面が切り替わった直後は端末側が受け付けないことがあるため、少し待ってから呼ぶ。
     */
    function focusInput() {
        window.setTimeout(() => {
            if (state.value !== 'play' || !inputEl.value) { return; }
            try { inputEl.value.focus({ preventScroll: true }); } catch (e) { inputEl.value.focus(); }
        }, 0);
    }

    // ---------- 時間をおいて行う処理（中断中は止める） ----------

    function schedule(step, waitMs) {
        cancelSchedule();
        pendingStep = step;
        pendingHandle = window.setTimeout(() => {
            pendingHandle = null;
            pendingStep = null;
            step();
        }, waitMs);
    }

    /** 待っている処理を止める。止めた処理は pendingStep に残し、再開のときに続ける。 */
    function holdSchedule() {
        if (pendingHandle !== null) {
            window.clearTimeout(pendingHandle);
            pendingHandle = null;
        }
    }

    function cancelSchedule() {
        holdSchedule();
        pendingStep = null;
    }

    // ---------- 相手の発言 ----------

    /** 相手が入力している様子を少し見せてから、吹き出しを出す */
    function partnerSay(text, then) {
        isPartnerTyping.value = true;
        scrollToBottom();
        schedule(() => {
            isPartnerTyping.value = false;
            addMessage('partner', text);
            if (then) { then(); }
        }, TYPING_MS);
    }

    function askQuestion() {
        if (QUESTIONS.length === 0) { return; }

        current = QUESTIONS[questionIndex % QUESTIONS.length];
        questionIndex += 1;

        partnerSay(current.text, () => {
            canAnswer.value = true;
            focusInput();
        });
    }

    function nextReply() {
        const reply = REPLIES[replyIndex % REPLIES.length];
        replyIndex += 1;
        return reply;
    }

    // ---------- 回答 ----------

    /** 前後の空白は取り除き、途中の空白は文字の種類の確かめでは数えない */
    function checkAnswer(text) {
        if (text === '') {
            return 'まだ 入力されていません';
        }
        const format = current && FORMATS[current.format];
        if (format && !format.test(text.replace(/[\s　]/g, ''))) {
            return format.message;
        }
        return '';
    }

    function answer() {
        if (state.value !== 'play' || !canAnswer.value) { return; }

        const text = draft.value.trim();
        const problem = checkAnswer(text);
        if (problem !== '') {
            // まちがいのときは加点も減点もせず、同じ質問にそのまま答え直してもらう
            notice.value = problem;
            focusInput();
            return;
        }

        canAnswer.value = false;
        notice.value = '';
        draft.value = '';
        addMessage('me', text);
        if (score.value < MAX_SCORE) { score.value += 1; }
        remaining.value = Math.max(0, remaining.value - 1);
        if (navigator.vibrate) {
            navigator.vibrate(30);      // 送れたことを触覚でも伝える（対応端末のみ）
        }

        if (remaining.value <= 0) {
            // あいづちと最後のあいさつを読んでもらってから結果画面へ進む
            partnerSay(nextReply(), () => {
                partnerSay(CONFIG.closing, () => schedule(finishPractice, FINISH_DELAY_MS));
            });
            return;
        }

        partnerSay(nextReply(), askQuestion);
    }

    // ---------- 画面スリープ防止（対応ブラウザのみ） ----------

    function requestWakeLock() {
        if (!navigator.wakeLock || wakeLock) { return; }
        navigator.wakeLock.request('screen').then((lock) => {
            wakeLock = lock;
            lock.addEventListener('release', () => { wakeLock = null; });
        }).catch(() => { /* 非対応・失敗時は何もしない */ });
    }

    function releaseWakeLock() {
        if (wakeLock) {
            wakeLock.release().catch(() => {});
            wakeLock = null;
        }
    }

    // ---------- 練習の進行 ----------

    /** 得点・やり取り・残りの数をすべて初期値に戻す */
    function resetPractice() {
        cancelSchedule();
        score.value = 0;
        remaining.value = REQUIRED_COUNT;
        messages.value = [];
        isPartnerTyping.value = false;
        canAnswer.value = false;
        draft.value = '';
        notice.value = '';
        current = null;
        releaseWakeLock();
    }

    function startPractice() {
        resetPractice();
        state.value = 'play';
        partnerSay(CONFIG.greeting, askQuestion);
        requestWakeLock();
    }

    function pausePractice() {
        if (state.value !== 'play') { return; }
        // 相手の返事を待っている途中なら、その待ち時間も止める
        holdSchedule();
        // キーボードを閉じ、中断画面が隠れないようにする
        if (inputEl.value) { inputEl.value.blur(); }
        state.value = 'paused';
        guardScreen('pause');
        releaseWakeLock();
    }

    function resumePractice() {
        if (state.value !== 'paused') { return; }
        state.value = 'play';
        if (pendingStep !== null) {
            schedule(pendingStep, RESUME_MS);   // 止めていたところから続ける
        }
        requestWakeLock();
        if (canAnswer.value) { focusInput(); }
    }

    function finishPractice() {
        cancelSchedule();
        if (inputEl.value) { inputEl.value.blur(); }
        releaseWakeLock();
        state.value = 'result';
        guardScreen('result');
        if (onFinish) { onFinish(score.value); }   // やり終えた練習だけを記録する
    }

    // ---------- イベント ----------

    // キーボードの確定キー（Enter）でも送れるようにする
    function onInputKeydown(event) {
        if (event.key !== 'Enter') { return; }
        // 日本語変換の途中の Enter は「変換の確定」なので、送信としては扱わない
        if (event.isComposing || event.keyCode === 229) { return; }
        event.preventDefault();
        answer();
    }

    // 打ち直し始めたら、前のまちがい表示は消す
    function onInput() {
        notice.value = '';
    }

    // 画面を離れた（他アプリに切り替えた）ときは自動的に中断する
    function onVisibilityChange() {
        if (document.hidden && state.value === 'play') {
            pausePractice();
        }
    }

    onMounted(() => {
        document.addEventListener('visibilitychange', onVisibilityChange);
    });

    onBeforeUnmount(() => {
        cancelSchedule();
        window.clearTimeout(guardHandle);
        releaseWakeLock();
        state.value = 'left';          // 残っている待ち時間の処理を動かさない
        document.removeEventListener('visibilitychange', onVisibilityChange);
    });

    /**
     * 開始・中断・再開・もう一度は pointerdown（触れた瞬間）で受け取る（画面側で割り当てる）。
     * 「送る」は指を離したとき（click）に確定させる。
     * pointerdown で受けると、入力欄から印が外れる前に判定してしまう端末があるため。
     */
    return {
        state,
        guarded,
        messages,
        isPartnerTyping,
        canAnswer,
        draft,
        notice,
        score,
        remaining,
        inputEl,
        logEl,
        formatScore,
        onInputKeydown,
        onInput,
        start: startPractice,
        pause: pausePractice,
        resume: resumePractice,
        answer,
    };
}
