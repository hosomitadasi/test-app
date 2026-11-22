<script setup>
/**
 * 電話を切る練習。Android 端末の通話中画面をまねた画面で、下の赤い「通話終了」ボタンを押す。
 *
 * 通話が始まったあとの画面を出し、通話終了ボタンのほかに、ミュート・キーパッドなどのボタンも並べる。
 * それらは押しまちがえる場所として置いた「まね」のボタンで、押しても何も起きない（失敗として数えるだけ）。
 * 実際の電話アプリを開いたり、電話をかけたりすることはない。
 */
import { onBeforeUnmount, ref } from 'vue';
import PhonePractice from '@/Components/phone/PhonePractice.vue';
import { usePhoneGame } from '@/composables/usePhoneGame';
import { usePracticeRecord } from '@/composables/usePracticeRecord';

const props = defineProps({
    type: { type: String, required: true },
    level: { type: String, required: true },
    recordType: { type: String, required: true },
    practice: { type: Object, required: true },
    setting: { type: Object, required: true },
    callers: { type: Array, required: true },
});

/** 通話中画面に並ぶ、通話終了以外のボタン（押しまちがえる場所） */
const CALL_CONTROLS = [
    { icon: '🔇', label: 'ミュート' },
    { icon: '🔢', label: 'キーパッド' },
    { icon: '🔊', label: 'スピーカー' },
    { icon: '➕', label: '通話を追加' },
    { icon: '⏸️', label: '保留' },
    { icon: '📹', label: 'ビデオ' },
];

/** 「やさしい」でほかのボタンを押したときに、正しいボタンを案内する文言 */
const EASY_HINT = 'したの あかい「通話終了」ボタンを おしてみましょう。';

function makeQuestion() {
    return {
        caller: props.callers[Math.floor(Math.random() * props.callers.length)],
    };
}

// ---------- 通話時間の表示（見た目だけ。中断中は止める） ----------

const callSeconds = ref(0);
let callHandle = null;
let timedQuestion = null;

function startCallTimer(current) {
    // 新しい電話のときだけ0秒から数え直す（再開のときは続きから）
    if (current !== timedQuestion) {
        timedQuestion = current;
        // 話し終えるころの電話を想定し、少し進んだ時間から始める
        callSeconds.value = 30 + Math.floor(Math.random() * 120);
    }
    stopCallTimer();
    callHandle = window.setInterval(() => { callSeconds.value += 1; }, 1000);
}

function stopCallTimer() {
    window.clearInterval(callHandle);
    callHandle = null;
}

function formatCallTime(seconds) {
    const min = Math.floor(seconds / 60);
    const sec = seconds % 60;
    return String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
}

onBeforeUnmount(stopCallTimer);

const record = usePracticeRecord(props.recordType, props.level);

const game = usePhoneGame({
    mode: props.setting.mode,
    requiredStreak: props.setting.required_streak,
    totalCount: props.setting.total_count,
    passCount: props.setting.pass_count,
    makeQuestion,
}, {
    onFinish: record.save,
    onQuestionStart: startCallTimer,
    onQuestionEnd: stopCallTimer,
});

const { question } = game;

function hangUp() {
    game.judge(true);
}

/** 通話終了以外のボタンを押したとき。何も起こさず、失敗として数える。 */
function pressOther() {
    game.judge(false, props.level === 'easy' ? EASY_HINT : '');
}
</script>

<template>
    <PhonePractice :type="type" :practice="practice" :setting="setting" :game="game" :record="record">
        <div v-if="question" class="incall" role="group" aria-label="通話中の 画面">
            <div class="incall__info">
                <span class="incoming__avatar" aria-hidden="true">👤</span>
                <p class="incoming__name">{{ question.caller }}</p>
                <p class="incall__time">通話中 {{ formatCallTime(callSeconds) }}</p>
            </div>

            <!-- 押しまちがえる場所。どれも押しても何も起きない。 -->
            <div class="incall__controls">
                <button
                    v-for="control in CALL_CONTROLS"
                    :key="control.label"
                    type="button"
                    class="incall__control"
                    @click="pressOther"
                >
                    <span class="incall__control-icon" aria-hidden="true">{{ control.icon }}</span>
                    <span class="incall__control-label">{{ control.label }}</span>
                </button>
            </div>

            <!-- 通話終了ボタン。画面の下に、大きく置く。 -->
            <button type="button" class="call-btn call-btn--end" @click="hangUp">
                <span class="call-btn__icon" aria-hidden="true">📞</span>
                <span class="call-btn__label">通話終了</span>
            </button>
        </div>
    </PhonePractice>
</template>
