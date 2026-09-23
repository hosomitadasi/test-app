/* ==========================================================================
   文字打ち練習（やさしい／ふつう／むずかしい）
   画面は「開始 / 練習 / 中断 / 結果」の4状態を行き来する。

   Blade で作っていたころの public/js/typing-play.js を、ほぼそのまま移したもの。
   判定やヒントの出し方は変えず、次の2点だけを足している。
   - 練習をやり終えたら onFinish(得点) を呼ぶ（結果の記録に使う）
   - 画面を離れたら、タイマーや画面全体に付けたイベントを片付ける
     （Inertia では画面を読み込み直さずに切り替わるため）
   ========================================================================== */
import { onBeforeUnmount, onMounted } from 'vue';

export function useTypingGame(CONFIG, { onFinish } = {}) {
    var TIME_LIMIT_MS = (CONFIG.timeLimitSeconds || 0) * 1000; // 0 なら制限時間なし
    var REQUIRED_COUNT = CONFIG.requiredCount || 0;            // 0 なら問題数での終了なし
    var HAS_HINT = CONFIG.hasHint === true;
    var IS_CONVERSION = CONFIG.isConversion === true;
    var HINT_INTERVAL_MS = CONFIG.hintIntervalMs || 10000;     // ヒントを1段階進める間隔
    var SCORE_DIGITS = CONFIG.scoreDigits || 3;
    var MAX_SCORE = Math.pow(10, SCORE_DIGITS) - 1;            // 3桁 = 999点まで
    var QUESTIONS = Array.isArray(CONFIG.questions) ? CONFIG.questions : [];

    var ALERT_REMAIN_MS = 30 * 1000; // 残りこの時間から時間表示を赤くする
    var GUARD_MS = 700;              // 画面が変わった直後、指が残っていても誤って押させない時間
    var NEXT_DELAY_MS = 700;         // 正解を見せてから次の問題に移るまでの間

    // ---------- 要素（画面が表示されてから取得する） ----------
    var screens, promptValue, targetValue, input, message, hintBox;
    var timeValue, countValue, scoreValue, resultScore;

    // ---------- 状態 ----------
    var state = 'start';
    var score = 0;
    var doneCount = 0;         // 「やさしい」で数える、正解できた問題数
    var questionIndex = 0;     // 今出している問題（一巡したら先頭へ戻る）
    var current = null;
    var elapsedMs = 0;         // 中断中を除いた累計経過時間
    var segmentStart = 0;      // 現在計測中の区間の開始時刻（performance.now）
    var tickHandle = null;
    var isLocked = true;       // 正解の演出中に二重で加点しないためのガード
    var questionStart = 0;     // 今の問題を出した時刻（ヒントの時間を計る）
    var hintElapsedMs = 0;     // 中断中を除いた、今の問題の経過時間
    var hintStage = 0;         // 出し終えたヒントの段階
    var hintHandle = null;
    var nextHandle = null;
    var wakeLock = null;

    // ---------- 表示ユーティリティ ----------

    /** 秒数を 00:00 形式にする（切り上げ: 残り0.4秒でも 00:01 と見せる） */
    function formatTime(remainMs) {
        var totalSec = Math.max(0, Math.ceil(remainMs / 1000));
        var min = Math.floor(totalSec / 60);
        var sec = totalSec % 60;
        return (min < 10 ? '0' : '') + min + ':' + (sec < 10 ? '0' : '') + sec;
    }

    /** 得点を 000点 形式にする */
    function formatScore(value) {
        var text = String(value);
        while (text.length < SCORE_DIGITS) {
            text = '0' + text;
        }
        return text + '点';
    }

    /**
     * 画面が出た直後の数百ミリ秒はボタンを効かなくする。
     * 練習中に触れていた指がそのまま新しい画面のボタンを押してしまい、
     * 結果を見る前に画面が変わってしまうのを防ぐ。
     */
    function guardScreen(element) {
        element.classList.add('is-guarded');
        window.setTimeout(function () {
            element.classList.remove('is-guarded');
        }, GUARD_MS);
    }

    function showScreen(name) {
        Object.keys(screens).forEach(function (key) {
            screens[key].classList.toggle('is-hidden', key !== name);
        });
        state = name;
    }

    function renderStatus(currentElapsedMs) {
        if (timeValue) {
            var used = typeof currentElapsedMs === 'number' ? currentElapsedMs : elapsedMs;
            var remain = Math.max(0, TIME_LIMIT_MS - used);
            timeValue.textContent = formatTime(remain);
            timeValue.classList.toggle('is-alert', remain <= ALERT_REMAIN_MS);
        }
        if (countValue) {
            countValue.textContent = Math.max(0, REQUIRED_COUNT - doneCount) + 'もん';
        }
        scoreValue.textContent = formatScore(score);
    }

    function setMessage(text, kind) {
        message.textContent = text;
        message.classList.toggle('is-ng', kind === 'ng');
        message.classList.toggle('is-ok', kind === 'ok');
    }

    // ---------- 入力の判定 ----------

    /**
     * 入力された文字をくらべやすい形に整える。
     * 前後の空白や、途中に入った空白は打ち間違いとみなさず取り除く。
     */
    function normalize(text) {
        return String(text == null ? '' : text).replace(/[\s　]/g, '');
    }

    function isCorrect(text) {
        // 入力してほしい表記と完全に一致したときだけ正解とする
        return current !== null && normalize(text) === normalize(current.answer);
    }

    // ---------- 出題 ----------

    /** 次の問題を画面に出す。入力欄を空にして、すぐ打てる状態にする。 */
    function showQuestion() {
        if (QUESTIONS.length === 0) { return; }

        current = QUESTIONS[questionIndex % QUESTIONS.length];
        questionIndex += 1;

        promptValue.textContent = current.prompt;
        if (targetValue) {
            targetValue.textContent = IS_CONVERSION ? current.target : '';
        }
        input.value = '';
        setMessage('', '');
        resetHint();
        isLocked = false;
        focusInput();
    }

    /**
     * 入力欄に印を戻し、端末のキーボードを出す。
     * 画面が切り替わった直後は端末側が受け付けないことがあるため、少し待ってから呼ぶ。
     */
    function focusInput() {
        window.setTimeout(function () {
            if (state !== 'play') { return; }
            try { input.focus({ preventScroll: true }); } catch (e) { input.focus(); }
        }, 0);
    }

    // ---------- ヒント（やさしいのみ） ----------

    function resetHint() {
        clearHintTimer();
        hintStage = 0;
        hintElapsedMs = 0;
        questionStart = performance.now();
        if (hintBox) {
            hintBox.textContent = '';
            hintBox.classList.add('is-hidden');
        }
        startHintTimer();
    }

    function clearHintTimer() {
        if (hintHandle !== null) {
            window.clearTimeout(hintHandle);
            hintHandle = null;
        }
    }

    /** 次のヒントを出すまでの残り時間を計って待つ（中断中は進めない） */
    function startHintTimer() {
        if (!HAS_HINT || !hintBox || current === null) { return; }
        if (hintStage >= current.hints.length) { return; }

        questionStart = performance.now();
        var wait = HINT_INTERVAL_MS * (hintStage + 1) - hintElapsedMs;
        hintHandle = window.setTimeout(revealHint, Math.max(0, wait));
    }

    /** ヒントを1段階進めて表示する。前に出したヒントは消さずに残す。 */
    function revealHint() {
        hintHandle = null;
        if (state !== 'play' || current === null) { return; }
        if (hintStage >= current.hints.length) { return; }

        var line = document.createElement('p');
        line.className = 'typing-hint__line';
        line.textContent = current.hints[hintStage];
        hintBox.appendChild(line);
        hintBox.classList.remove('is-hidden');

        hintStage += 1;
        hintElapsedMs = HINT_INTERVAL_MS * hintStage;
        startHintTimer();
    }

    /** 中断したときに、今の問題の経過時間を確定させる */
    function holdHint() {
        if (!HAS_HINT) { return; }
        hintElapsedMs += performance.now() - questionStart;
        clearHintTimer();
    }

    // ---------- 回答 ----------

    function answer() {
        if (state !== 'play' || isLocked) { return; }

        if (normalize(input.value) === '') {
            setMessage('まだ 入力されていません', 'ng');
            focusInput();
            return;
        }

        if (!isCorrect(input.value)) {
            // まちがいのときは加点も減点もせず、同じ問題をそのまま続ける
            setMessage('ちがいます', 'ng');
            focusInput();
            return;
        }

        isLocked = true;
        clearHintTimer();
        if (score < MAX_SCORE) { score += 1; }
        doneCount += 1;
        if (navigator.vibrate) {
            navigator.vibrate(30);      // 正解したことを触覚でも伝える（対応端末のみ）
        }
        setMessage('せいかいです', 'ok');
        renderStatus();

        if (REQUIRED_COUNT > 0 && doneCount >= REQUIRED_COUNT) {
            finishPractice();
            return;
        }

        // 正解の表示をひと呼吸見せてから次の問題に移る
        nextHandle = window.setTimeout(function () {
            nextHandle = null;
            if (state !== 'play') { return; }
            showQuestion();
        }, NEXT_DELAY_MS);
    }

    // ---------- タイマー ----------

    function tick() {
        if (state !== 'play') { return; }
        var now = performance.now();
        var currentElapsed = elapsedMs + (now - segmentStart);

        if (currentElapsed >= TIME_LIMIT_MS) {
            elapsedMs = TIME_LIMIT_MS;
            renderStatus();
            finishPractice();
            return;
        }

        renderStatus(currentElapsed);
        tickHandle = window.requestAnimationFrame(tick);
    }

    function startTicking() {
        if (TIME_LIMIT_MS <= 0) { return; }   // 「やさしい」は問題数で終わるため時間を計らない
        segmentStart = performance.now();
        tickHandle = window.requestAnimationFrame(tick);
    }

    function stopTicking() {
        if (tickHandle !== null) {
            window.cancelAnimationFrame(tickHandle);
            tickHandle = null;
        }
    }

    // ---------- 画面スリープ防止（対応ブラウザのみ） ----------

    function requestWakeLock() {
        if (!navigator.wakeLock || wakeLock) { return; }
        navigator.wakeLock.request('screen').then(function (lock) {
            wakeLock = lock;
            lock.addEventListener('release', function () { wakeLock = null; });
        }).catch(function () { /* 非対応・失敗時は何もしない */ });
    }

    function releaseWakeLock() {
        if (wakeLock) {
            wakeLock.release().catch(function () {});
            wakeLock = null;
        }
    }

    // ---------- 練習の進行 ----------

    /** 得点・制限時間・出題をすべて初期値に戻す */
    function resetPractice() {
        stopTicking();
        clearHintTimer();
        if (nextHandle !== null) {
            window.clearTimeout(nextHandle);
            nextHandle = null;
        }
        score = 0;
        doneCount = 0;
        elapsedMs = 0;
        questionIndex = 0;
        current = null;
        isLocked = true;
        input.value = '';
        setMessage('', '');
        renderStatus();
        releaseWakeLock();
    }

    function startPractice() {
        resetPractice();
        showScreen('play');
        showQuestion();
        startTicking();
        requestWakeLock();
    }

    function pausePractice() {
        if (state !== 'play') { return; }
        // 中断した時点までの経過時間を確定させる（= 時間が進まなくなる）
        if (TIME_LIMIT_MS > 0) {
            elapsedMs += performance.now() - segmentStart;
        }
        holdHint();
        stopTicking();
        // キーボードを閉じ、中断画面が隠れないようにする
        input.blur();
        renderStatus();
        state = 'paused';
        screens.pause.classList.remove('is-hidden');
        guardScreen(screens.pause);
        releaseWakeLock();
    }

    function resumePractice() {
        if (state !== 'paused') { return; }
        screens.pause.classList.add('is-hidden');
        state = 'play';
        startTicking();          // 中断していた時間から再開する
        startHintTimer();        // ヒントも中断していたところから数え直す
        requestWakeLock();
        focusInput();
    }

    function finishPractice() {
        stopTicking();
        clearHintTimer();
        input.blur();
        releaseWakeLock();
        resultScore.textContent = formatScore(score);
        showScreen('result');
        guardScreen(screens.result);
        if (onFinish) { onFinish(score); }   // やり終えた練習だけを記録する
    }

    // ---------- イベント登録 ----------

    var listeners = [];

    /** 画面を離れるときにまとめて外せるよう、付けたイベントを覚えておく */
    function listen(element, type, handler) {
        element.addEventListener(type, handler);
        listeners.push([element, type, handler]);
    }

    // キーボードの確定キー（Enter）でも回答できるようにする
    function onInputKeydown(event) {
        if (event.key !== 'Enter') { return; }
        // 日本語変換の途中の Enter は「変換の確定」なので、回答としては扱わない
        if (event.isComposing || event.keyCode === 229) { return; }
        event.preventDefault();
        answer();
    }

    // 打ち直し始めたら、前のまちがい表示は消す
    function onInput() {
        if (message.classList.contains('is-ng')) {
            setMessage('', '');
        }
    }

    // 画面を離れた（他アプリに切り替えた）ときは自動的に中断し、時間の進みを止める
    function onVisibilityChange() {
        if (document.hidden && state === 'play') {
            pausePractice();
        }
    }

    onMounted(function () {
        screens = {
            start: document.getElementById('screen-start'),
            play: document.getElementById('screen-play'),
            pause: document.getElementById('screen-pause'),
            result: document.getElementById('screen-result')
        };
        promptValue = document.getElementById('typing-prompt');
        targetValue = document.getElementById('typing-target');
        input = document.getElementById('typing-input');
        message = document.getElementById('typing-message');
        hintBox = document.getElementById('typing-hint');
        timeValue = document.getElementById('time-value');
        countValue = document.getElementById('count-value');
        scoreValue = document.getElementById('score-value');
        resultScore = document.getElementById('result-score');

        listen(input, 'keydown', onInputKeydown);
        listen(input, 'input', onInput);
        listen(document, 'visibilitychange', onVisibilityChange);

        // ---------- 初期表示 ----------
        renderStatus();
        showScreen('start');

        // 動作確認用（開発時のみ利用）
        window.__typing = {
            getState: function () { return state; },
            getScore: function () { return score; },
            getDoneCount: function () { return doneCount; },
            getPrompt: function () { return promptValue.textContent; },
            getAnswer: function () { return current ? current.answer : ''; },
            getHintText: function () { return hintBox ? hintBox.textContent : ''; },
            getTimeText: function () { return timeValue ? timeValue.textContent : ''; },
            getCountText: function () { return countValue ? countValue.textContent : ''; },
            skipTo: function (remainSeconds) {
                elapsedMs = TIME_LIMIT_MS - remainSeconds * 1000;
                segmentStart = performance.now();
            },
            answerWith: function (text) { input.value = text; answer(); },
            revealHint: function () { revealHint(); }
        };
    });

    onBeforeUnmount(function () {
        stopTicking();
        clearHintTimer();
        if (nextHandle !== null) {
            window.clearTimeout(nextHandle);
            nextHandle = null;
        }
        releaseWakeLock();
        state = 'left';          // 残っている待ち時間の処理を動かさない
        listeners.forEach(function (entry) {
            entry[0].removeEventListener(entry[1], entry[2]);
        });
        listeners = [];
        delete window.__typing;
    });

    /**
     * 開始・中断・再開・もう一度は pointerdown（触れた瞬間）で受け取る（画面側で割り当てる）。
     * 「回答です」は指を離したとき（click）に確定させる。
     * pointerdown で受けると、入力欄から印が外れる前に判定してしまう端末があるため。
     */
    return {
        start: function () { startPractice(); },
        pause: function () { pausePractice(); },
        resume: function () { resumePractice(); },
        answer: function () { answer(); }
    };
}
