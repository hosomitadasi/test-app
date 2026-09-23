/* ==========================================================================
   画面操作の練習（軽く押す／長く押す／なぞる／はらう）
   画面は「開始 / 練習 / 中断 / 結果」の4状態を行き来する。

   Blade で作っていたころの public/js/practice-play.js を、ほぼそのまま移したもの。
   判定や時間の計り方は変えず、次の2点だけを足している。
   - 練習をやり終えたら onFinish(得点) を呼ぶ（結果の記録に使う）
   - 画面を離れたら、タイマーや画面全体に付けたイベントを片付ける
     （Inertia では画面を読み込み直さずに切り替わるため）
   ========================================================================== */
import { onBeforeUnmount, onMounted } from 'vue';

export function usePracticeGame(CONFIG, { onFinish } = {}) {
    var TYPE = CONFIG.type || 'tap';                       // tap / long / swipe / flick
    var TIME_LIMIT_MS = (CONFIG.timeLimitSeconds || 0) * 1000; // 0 なら制限時間なし
    var REQUIRED_COUNT = CONFIG.requiredCount || 0;        // 0 なら回数での終了なし
    var HAS_DECOY = CONFIG.hasDecoy === true;
    var HOLD_MS = CONFIG.holdMs || 1000;                   // 長く押す練習で加点となる時間
    var SCORE_DIGITS = CONFIG.scoreDigits || 3;
    var MAX_SCORE = Math.pow(10, SCORE_DIGITS) - 1;        // 3桁 = 999点まで

    var EDGE_MARGIN = 12;        // 画面端からの余白（押しにくい端を避ける）
    var MIN_MOVE_DISTANCE = 120; // 前回位置から最低限離す距離（同じ場所に見えるのを防ぐ）
    var MIN_GAP = 1.4;           // ただしいアイコンとまちがったアイコンの最低間隔（アイコン何個分か）
    var ALERT_REMAIN_MS = 30 * 1000; // 残りこの時間から時間表示を赤くする
    var GUARD_MS = 700;          // 画面が変わった直後、指が残っていても誤って押させない時間

    // なぞる・はらう の判定値
    var FLICK_MAX_MS = 500;      // これより長くかかったものは「はらう」とみなさない
    var FLICK_MIN_DISTANCE = 60; // はらうと判定する最短距離
    var DIRECTIONS = ['up', 'down', 'left', 'right'];

    // ---------- 要素（画面が表示されてから取得する） ----------
    var screens, field, target, decoy, timeValue, countValue, scoreValue, resultScore, icons;

    // ---------- 状態 ----------
    var state = 'start';
    var score = 0;
    var doneCount = 0;       // 「やさしい」で数える、ただしく操作できた回数
    var elapsedMs = 0;       // 中断中を除いた累計経過時間
    var segmentStart = 0;    // 現在計測中の区間の開始時刻（performance.now）
    var tickHandle = null;
    var isLocked = true;     // 消えかけのアイコンへの連打で二重加減点しないためのガード
    var lastPosition = null;
    var wakeLock = null;
    var gesture = null;      // 進行中の操作（pointerdown〜pointerup）

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
            countValue.textContent = Math.max(0, REQUIRED_COUNT - doneCount) + 'かい';
        }
        scoreValue.textContent = formatScore(score);
    }

    // ---------- アイコンの配置 ----------

    /** アイコンを置ける範囲（画面中央から下部）を求める */
    function placeableArea() {
        var fieldRect = field.getBoundingClientRect();
        var size = target.offsetWidth || 112;

        // 出現領域の上端は「画面の高さの50%」以上とする（= 画面中央より下）。
        // 画面は端末を模した枠の中に収まっているため、枠（練習画面）を基準に測る。
        var screenRect = screens.play.getBoundingClientRect();
        var centerLine = screenRect.top + screenRect.height * 0.5;
        var minTop = Math.max(EDGE_MARGIN, centerLine - fieldRect.top);

        var maxTop = fieldRect.height - size - EDGE_MARGIN;
        var maxLeft = fieldRect.width - size - EDGE_MARGIN;

        // 極端に狭い画面でも必ず領域内に収める
        if (maxTop < minTop) { minTop = Math.max(0, maxTop); }
        if (maxLeft < EDGE_MARGIN) { maxLeft = Math.max(0, maxLeft); }

        return { size: size, minTop: minTop, maxTop: maxTop, maxLeft: maxLeft };
    }

    function randomPoint(area) {
        return {
            left: EDGE_MARGIN + Math.random() * Math.max(0, area.maxLeft - EDGE_MARGIN),
            top: area.minTop + Math.random() * Math.max(0, area.maxTop - area.minTop)
        };
    }

    function distance(a, b) {
        var dx = a.left - b.left;
        var dy = a.top - b.top;
        return Math.sqrt(dx * dx + dy * dy);
    }

    /** アイコンを指定位置・指定方向で表示する */
    function showIcon(element, point, direction) {
        element.style.left = point.left + 'px';
        element.style.top = point.top + 'px';
        DIRECTIONS.forEach(function (dir) {
            element.classList.toggle('is-dir-' + dir, dir === direction);
        });
        element.dataset.direction = direction || '';
        element.classList.remove('is-tapped', 'is-holding', 'is-hidden');
    }

    function randomDirection() {
        return DIRECTIONS[Math.floor(Math.random() * DIRECTIONS.length)];
    }

    /**
     * ただしいアイコン（むずかしい難易度ではまちがったアイコンも）を
     * 「画面中央から下部」のランダムな位置に置く。
     * 画面外・端すぎる位置・ヘッダーと重なる位置には置かない。
     */
    function placeTargets() {
        var area = placeableArea();

        var point;
        // 前回位置と近すぎる場合は引き直す（最大20回で打ち切り、無限ループを防ぐ）
        for (var i = 0; i < 20; i++) {
            point = randomPoint(area);
            if (!lastPosition || distance(point, lastPosition) >= MIN_MOVE_DISTANCE) { break; }
        }
        lastPosition = point;
        showIcon(target, point, randomDirection());

        if (decoy) {
            var decoyPoint;
            // ただしいアイコンと重ならない位置に置く（押し間違いではなく見分けを練習させる）
            for (var j = 0; j < 30; j++) {
                decoyPoint = randomPoint(area);
                if (distance(decoyPoint, point) >= area.size * MIN_GAP) { break; }
            }
            showIcon(decoy, decoyPoint, randomDirection());
        }

        isLocked = false;
    }

    function hideTargets() {
        isLocked = true;
        cancelGesture();
        icons.forEach(function (element) {
            element.classList.add('is-hidden');
            element.classList.remove('is-tapped', 'is-holding');
        });
    }

    /** 次の出題へ進む。演出が終わるのを待ってから置き直す。 */
    function nextTargets() {
        isLocked = true;
        window.setTimeout(function () {
            if (state !== 'play') { return; }
            icons.forEach(function (element) {
                element.classList.add('is-hidden');
                element.classList.remove('is-tapped', 'is-holding');
            });
            placeTargets();
        }, 140);
    }

    // ---------- 操作の判定 ----------

    /** ただしく操作できたときの処理。まちがったアイコンなら減点する。 */
    function onSuccess(element) {
        if (state !== 'play' || isLocked) { return; }
        isLocked = true;

        if (element === decoy) {
            // まちがったアイコンを操作した: 1点減点し、ただしいアイコンも消す
            score = Math.max(0, score - 1);
        } else {
            if (score < MAX_SCORE) { score += 1; }
            doneCount += 1;
            if (navigator.vibrate) {
                navigator.vibrate(30);      // 反応したことを触覚でも伝える（対応端末のみ）
            }
        }
        renderStatus();

        // 操作したアイコンだけ演出を付け、まとめて消す
        element.classList.add('is-tapped');

        if (REQUIRED_COUNT > 0 && doneCount >= REQUIRED_COUNT) {
            finishPractice();
            return;
        }
        nextTargets();
    }

    function cancelGesture() {
        if (!gesture) { return; }
        if (gesture.holdHandle) { window.clearTimeout(gesture.holdHandle); }
        gesture.element.classList.remove('is-holding');
        try { gesture.element.releasePointerCapture(gesture.pointerId); } catch (e) { /* 解放済み */ }
        gesture = null;
    }

    /** 移動量が「やじるしの向き」と一致するか調べる */
    function matchesDirection(dx, dy, direction, minDistance) {
        var absX = Math.abs(dx);
        var absY = Math.abs(dy);
        if (direction === 'left') { return dx <= -minDistance && absX > absY; }
        if (direction === 'right') { return dx >= minDistance && absX > absY; }
        if (direction === 'up') { return dy <= -minDistance && absY > absX; }
        if (direction === 'down') { return dy >= minDistance && absY > absX; }
        return false;
    }

    function onPointerDown(event) {
        if (state !== 'play' || isLocked || gesture) { return; }
        event.preventDefault();
        var element = event.currentTarget;

        gesture = {
            element: element,
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            startTime: performance.now(),
            holdHandle: null
        };
        // 指がアイコンの外へ出ても操作を追い続ける
        try { element.setPointerCapture(event.pointerId); } catch (e) { /* 非対応環境 */ }

        if (TYPE === 'tap') {
            cancelGesture();
            onSuccess(element);
            return;
        }

        if (TYPE === 'long') {
            element.classList.add('is-holding');
            gesture.holdHandle = window.setTimeout(function () {
                var held = gesture && gesture.element;
                cancelGesture();
                if (held) { onSuccess(held); }
            }, HOLD_MS);
        }
    }

    function onPointerMove(event) {
        if (!gesture || event.pointerId !== gesture.pointerId) { return; }
        if (TYPE !== 'swipe') { return; }

        var dx = event.clientX - gesture.startX;
        var dy = event.clientY - gesture.startY;
        // アイコン1つ分なぞれたら成功とする
        var need = target.offsetWidth || 112;
        if (matchesDirection(dx, dy, gesture.element.dataset.direction, need)) {
            var moved = gesture.element;
            cancelGesture();
            onSuccess(moved);
        }
    }

    function onPointerUp(event) {
        if (!gesture || event.pointerId !== gesture.pointerId) { return; }

        if (TYPE === 'flick') {
            var dx = event.clientX - gesture.startX;
            var dy = event.clientY - gesture.startY;
            var elapsed = performance.now() - gesture.startTime;
            var flicked = gesture.element;
            var direction = flicked.dataset.direction;
            cancelGesture();
            // 短い時間で、やじるしの向きへ一定以上動いたものだけ「はらう」とみなす
            if (elapsed <= FLICK_MAX_MS && matchesDirection(dx, dy, direction, FLICK_MIN_DISTANCE)) {
                onSuccess(flicked);
            }
            return;
        }

        // 長く押す: 1秒に届かず離した場合は加点しない（アイコンはそのまま残して再挑戦できる）
        cancelGesture();
    }

    // ---------- タイマー ----------

    function tick() {
        if (state !== 'play') { return; }
        var now = performance.now();
        var current = elapsedMs + (now - segmentStart);

        if (current >= TIME_LIMIT_MS) {
            elapsedMs = TIME_LIMIT_MS;
            renderStatus();
            finishPractice();
            return;
        }

        renderStatus(current);
        tickHandle = window.requestAnimationFrame(tick);
    }

    function startTicking() {
        if (TIME_LIMIT_MS <= 0) { return; }   // 「やさしい」は回数制のため時間を計らない
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

    /** 得点・制限時間・練習状態をすべて初期値に戻す */
    function resetPractice() {
        stopTicking();
        score = 0;
        doneCount = 0;
        elapsedMs = 0;
        lastPosition = null;
        hideTargets();
        renderStatus();
        releaseWakeLock();
    }

    function startPractice() {
        resetPractice();
        showScreen('play');
        placeTargets();
        startTicking();
        requestWakeLock();
    }

    function pausePractice() {
        if (state !== 'play') { return; }
        // 中断した時点までの経過時間を確定させる（= 時間が進まなくなる）
        if (TIME_LIMIT_MS > 0) {
            elapsedMs += performance.now() - segmentStart;
        }
        stopTicking();
        cancelGesture();
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
        requestWakeLock();
    }

    function finishPractice() {
        stopTicking();
        hideTargets();
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

    function onContextMenu(event) { event.preventDefault(); }

    // 画面を離れた（他アプリに切り替えた）ときは自動的に中断し、時間の進みを止める
    function onVisibilityChange() {
        if (document.hidden && state === 'play') {
            pausePractice();
        }
    }

    // 画面回転などでサイズが変わったら、アイコンが画面外に残らないよう置き直す
    function onResize() {
        if (state === 'play' && !target.classList.contains('is-hidden')) {
            cancelGesture();
            lastPosition = null;
            placeTargets();
        }
    }

    onMounted(function () {
        screens = {
            start: document.getElementById('screen-start'),
            play: document.getElementById('screen-play'),
            pause: document.getElementById('screen-pause'),
            result: document.getElementById('screen-result')
        };
        field = document.getElementById('play-field');
        target = document.getElementById('target');
        decoy = document.getElementById('decoy');
        timeValue = document.getElementById('time-value');
        countValue = document.getElementById('count-value');
        scoreValue = document.getElementById('score-value');
        resultScore = document.getElementById('result-score');
        icons = decoy ? [target, decoy] : [target];

        icons.forEach(function (element) {
            listen(element, 'pointerdown', onPointerDown);
            listen(element, 'pointermove', onPointerMove);
            listen(element, 'pointerup', onPointerUp);
            listen(element, 'pointercancel', function () { cancelGesture(); });
        });

        // アイコン以外を押しても何も起きない（減点しない）。長押しメニューだけ抑止する。
        listen(document, 'contextmenu', onContextMenu);
        listen(document, 'visibilitychange', onVisibilityChange);
        listen(window, 'resize', onResize);

        // ---------- 初期表示 ----------
        renderStatus();
        showScreen('start');

        // 動作確認用（開発時のみ利用）
        window.__practice = {
            getState: function () { return state; },
            getScore: function () { return score; },
            getDoneCount: function () { return doneCount; },
            getTimeText: function () { return timeValue ? timeValue.textContent : ''; },
            getCountText: function () { return countValue ? countValue.textContent : ''; },
            skipTo: function (remainSeconds) {
                elapsedMs = TIME_LIMIT_MS - remainSeconds * 1000;
                segmentStart = performance.now();
            },
            succeed: function (which) { onSuccess(which === 'decoy' ? decoy : target); }
        };
    });

    onBeforeUnmount(function () {
        stopTicking();
        cancelGesture();
        releaseWakeLock();
        state = 'left';          // 残っている待ち時間の処理を動かさない
        listeners.forEach(function (entry) {
            entry[0].removeEventListener(entry[1], entry[2]);
        });
        listeners = [];
        delete window.__practice;
    });

    /**
     * ボタンは pointerdown（触れた瞬間）で受け取る（画面側で @pointerdown に割り当てる）。
     * click を待つと約300msの遅延が出るため、「軽く押す」感触が損なわれる。
     * 「もう一度練習する」はリセットしてすぐ練習を始める（操作を1回減らす）。
     */
    return {
        start: function () { startPractice(); },
        pause: function () { pausePractice(); },
        resume: function () { resumePractice(); }
    };
}
