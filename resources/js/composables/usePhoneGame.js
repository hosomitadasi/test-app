/* ==========================================================================
   電話の練習（電話を取る練習／電話を切る練習）
   画面は「開始 / 練習 / 中断 / 結果」の4状態を行き来する。

   1問ごとに、画面側で用意した問題（着信画面の種類・指示・相手の名前など）を出し、
   押したボタンが正しいかを judge(正しいか) で受け取る。
   押した直後は「できました！」「おしいです！」を少し見せてから、次の問題へ進む。

   進め方は2通り。
   - streak … 続けて requiredStreak 回できたら練習完了。失敗すると続けてできた回数を0に戻す。
   - total  … totalCount 回やり終えた時点で、passCount 回以上できていれば練習完了（合格）。

   中断・再開・結果の記録・画面を離れたときの片付けは、チャット型練習（useChatGame）と同じ考え方にしている。
   - 中断中は操作を受け付けず、次の問題へ進むまでの待ち時間も止める
   - 他のアプリに切り替えたら自動で中断する
   - 練習完了のときだけ onFinish(得点) を呼ぶ（結果の記録に使う）。途中でやめたものは記録しない
   ========================================================================== */
import { onBeforeUnmount, onMounted, ref } from 'vue';

/** 結果を伝える文言。高齢者が責められていると感じない言い方にする。 */
const SUCCESS_FEEDBACK = { title: 'できました！', text: '正しく操作できました！' };
const FAILURE_TITLE = 'おしいです！';
const FAILURE_TEXT = 'もう一度、ボタンを確認してみましょう。';

/**
 * @param {object} CONFIG
 *   mode            … 'streak' / 'total'
 *   requiredStreak  … streak のとき、続けてできたら練習完了となる回数
 *   totalCount      … total のとき、出題する回数
 *   passCount       … total のとき、合格となる成功回数
 *   makeQuestion    … 次の問題を作る関数（画面側で用意する）
 * @param {object} hooks
 *   onFinish        … 練習完了のときに得点を受け取る
 *   onQuestionStart / onQuestionEnd
 *                   … 問題を出したとき／答えたとき・中断したとき。
 *                     模擬の着信音や振動を足すときは、ここで鳴らす・止める。
 */
export function usePhoneGame(CONFIG, { onFinish, onQuestionStart, onQuestionEnd } = {}) {
    const MODE = CONFIG.mode === 'total' ? 'total' : 'streak';
    const REQUIRED_STREAK = CONFIG.requiredStreak || 0;
    const TOTAL_COUNT = CONFIG.totalCount || 0;
    const PASS_COUNT = CONFIG.passCount || 0;

    const GUARD_MS = 700;            // 画面が変わった直後、指が残っていても誤って押させない時間
    const FEEDBACK_MS = 1800;        // 「できました！」などを読んでもらう時間
    const RESUME_MS = 500;           // 再開してから、止めていた続きの処理を行うまでの間
    const NEXT_QUESTION_MS = 400;    // 次の電話が来るまでの、ひと呼吸の間

    // ---------- 画面に出す状態 ----------
    const state = ref('start');      // start / play / paused / result
    const guarded = ref('');         // 誤操作よけをかけている画面
    const question = ref(null);      // 今出している問題（null の間は何も出さない）
    const accepting = ref(false);    // ボタンを受け付ける状態か
    const feedback = ref(null);      // 押した直後に出す { ok, title, text }
    const questionNumber = ref(0);   // 何問目か（答え終えた数ではなく、今の問題の番号）
    const successCount = ref(0);
    const failCount = ref(0);
    const streak = ref(0);           // 続けてできた回数（streak のときに使う）
    const passed = ref(false);       // 練習完了（合格）したか

    // ---------- 内部の状態 ----------
    let pendingStep = null;          // 次の問題へ進むなど、時間をおいて行う処理
    let pendingHandle = null;
    let guardHandle = null;
    let wakeLock = null;
    let finished = false;            // 同じ練習の結果を二重に記録しないための印

    /**
     * 画面が出た直後の数百ミリ秒はボタンを効かなくする。
     * 練習中に触れていた指がそのまま新しい画面のボタンを押してしまうのを防ぐ。
     */
    function guardScreen(name) {
        guarded.value = name;
        window.clearTimeout(guardHandle);
        guardHandle = window.setTimeout(() => { guarded.value = ''; }, GUARD_MS);
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

    // ---------- 出題と判定 ----------

    function askQuestion() {
        feedback.value = null;
        questionNumber.value += 1;
        question.value = CONFIG.makeQuestion(questionNumber.value);
        accepting.value = true;
        if (onQuestionStart) { onQuestionStart(question.value); }
    }

    /** 練習完了の条件を満たしたか（total では、決められた回数をやり終えたか） */
    function isOver() {
        if (MODE === 'streak') {
            return streak.value >= REQUIRED_STREAK;
        }
        return successCount.value + failCount.value >= TOTAL_COUNT;
    }

    /**
     * 押したボタンの判定を受け取る。
     * @param {boolean} ok        正しいボタンだったか
     * @param {string}  hint      失敗のときに添える、正しいボタンの案内（省略時は共通の文言）
     */
    function judge(ok, hint = '') {
        if (state.value !== 'play' || !accepting.value) { return; }

        accepting.value = false;
        if (onQuestionEnd) { onQuestionEnd(question.value); }

        if (ok) {
            successCount.value += 1;
            streak.value += 1;
            feedback.value = { ok: true, ...SUCCESS_FEEDBACK };
        } else {
            failCount.value += 1;
            streak.value = 0;       // 失敗したら、続けてできた回数は0から数え直す
            feedback.value = { ok: false, title: FAILURE_TITLE, text: hint || FAILURE_TEXT };
        }
        if (navigator.vibrate) {
            navigator.vibrate(30);      // 押せたことを触覚でも伝える（対応端末のみ）
        }

        if (isOver()) {
            schedule(finishPractice, FEEDBACK_MS);
            return;
        }
        schedule(() => {
            // 前の画面を一度消してから次の電話を出し、別の電話が来たと分かるようにする
            question.value = null;
            feedback.value = null;
            schedule(askQuestion, NEXT_QUESTION_MS);
        }, FEEDBACK_MS);
    }

    /** 結果の記録に使う得点。streak は続けてできた回数、total はできた回数。 */
    function scoreOf() {
        return MODE === 'streak' ? streak.value : successCount.value;
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

    /** 問題数・成功数・失敗数・続けてできた回数をすべて初期値に戻す */
    function resetPractice() {
        cancelSchedule();
        if (question.value && accepting.value && onQuestionEnd) { onQuestionEnd(question.value); }
        question.value = null;
        accepting.value = false;
        feedback.value = null;
        questionNumber.value = 0;
        successCount.value = 0;
        failCount.value = 0;
        streak.value = 0;
        passed.value = false;
        finished = false;
        releaseWakeLock();
    }

    function startPractice() {
        resetPractice();
        state.value = 'play';
        askQuestion();
        requestWakeLock();
    }

    function pausePractice() {
        if (state.value !== 'play') { return; }
        // 次の問題へ進むまでの待ち時間も止める。数はそのまま残す。
        holdSchedule();
        if (accepting.value && onQuestionEnd) { onQuestionEnd(question.value); }
        state.value = 'paused';
        guardScreen('pause');
        releaseWakeLock();
    }

    function resumePractice() {
        if (state.value !== 'paused') { return; }
        state.value = 'play';
        if (pendingStep !== null) {
            schedule(pendingStep, RESUME_MS);   // 止めていたところから続ける
        } else if (accepting.value && onQuestionStart) {
            onQuestionStart(question.value);    // 答える前に中断した電話は、そのまま続ける
        }
        requestWakeLock();
    }

    function finishPractice() {
        if (finished) { return; }
        finished = true;
        cancelSchedule();
        releaseWakeLock();
        accepting.value = false;
        passed.value = MODE === 'streak' || successCount.value >= PASS_COUNT;
        state.value = 'result';
        guardScreen('result');
        // 練習完了（合格）したときだけ記録する
        if (passed.value && onFinish) { onFinish(scoreOf()); }
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
        if (accepting.value && onQuestionEnd) { onQuestionEnd(question.value); }
        window.clearTimeout(guardHandle);
        releaseWakeLock();
        state.value = 'left';          // 残っている待ち時間の処理を動かさない
        document.removeEventListener('visibilitychange', onVisibilityChange);
    });

    /**
     * 開始・中断・再開・もう一度は pointerdown（触れた瞬間）で受け取る（画面側で割り当てる）。
     * 応答・拒否・通話終了などの練習のボタンは、指を離したとき（click）に確定させる。
     * 押しまちがえたと気づいた指をずらせば、取り消せるようにするため。
     */
    return {
        state,
        guarded,
        question,
        accepting,
        feedback,
        questionNumber,
        successCount,
        failCount,
        streak,
        passed,
        mode: MODE,
        requiredStreak: REQUIRED_STREAK,
        totalCount: TOTAL_COUNT,
        passCount: PASS_COUNT,
        judge,
        start: startPractice,
        pause: pausePractice,
        resume: resumePractice,
    };
}
