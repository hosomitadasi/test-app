<script setup>
/**
 * 電話を取る練習。Android 端末の着信画面をまねた画面で、「応答」「拒否」を押す。
 *
 * 着信画面は2種類あり、問題ごとにランダムで選ぶ（「やさしい」は画面全体の着信画面のみ）。
 * - popup      … ホーム画面の上部に、着信の通知が出る
 * - fullscreen … 画面全体が着信画面になる
 * アプリの中に作った「まね」の画面のため、実際の電話アプリは動かない。
 */
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

/** 指示の文言。「むずかしい」で、どちらを押すかを問題ごとに伝える。 */
const INSTRUCTIONS = {
    answer: '「応答」してください',
    decline: '「拒否」してください',
};

/** 「やさしい」で拒否を押したときに、正しいボタンを案内する文言 */
const EASY_HINT = 'みどりの「応答」ボタンを おしてみましょう。';

// ホーム画面のまね（通知の後ろに出す、押しても何も起きないアプリの並び）
const HOME_APPS = ['📷', '🗓️', '✉️', '🗺️', '🎵', '☀️', '📰', '⚙️'];

function pick(items) {
    return items[Math.floor(Math.random() * items.length)];
}

function makeQuestion() {
    return {
        layout: pick(props.setting.layouts),
        instruction: props.setting.has_instruction ? pick(Object.keys(INSTRUCTIONS)) : null,
        caller: pick(props.callers),
    };
}

const record = usePracticeRecord(props.recordType, props.level);

// 模擬の着信音・振動を足すときは、onQuestionStart / onQuestionEnd で鳴らす・止める
const game = usePhoneGame({
    mode: props.setting.mode,
    requiredStreak: props.setting.required_streak,
    totalCount: props.setting.total_count,
    passCount: props.setting.pass_count,
    makeQuestion,
}, { onFinish: record.save });

const { question, accepting } = game;

/** 押したボタン（answer / decline）が正しいかを判定する */
function choose(choice) {
    if (!accepting.value) { return; }
    const expected = question.value.instruction || 'answer';
    game.judge(choice === expected, props.level === 'easy' ? EASY_HINT : '');
}
</script>

<template>
    <PhonePractice :type="type" :practice="practice" :setting="setting" :game="game" :record="record">
        <template v-if="question">
            <!-- 「むずかしい」の指示。どちらの着信画面でも、いちばん上に出す。 -->
            <p v-if="question.instruction" class="phone-instruction" aria-live="polite">{{ INSTRUCTIONS[question.instruction] }}</p>

            <!-- ===== 画面全体の着信画面 ===== -->
            <div
                v-if="question.layout === 'fullscreen'"
                class="incoming incoming--fullscreen"
                role="group"
                aria-label="電話が かかってきています"
            >
                <p class="incoming__status">電話が かかってきています</p>
                <span class="incoming__avatar" aria-hidden="true">👤</span>
                <p class="incoming__name">{{ question.caller }}</p>
                <div class="incoming__actions">
                    <button type="button" class="call-btn call-btn--decline" @click="choose('decline')">
                        <span class="call-btn__icon" aria-hidden="true">📞</span>
                        <span class="call-btn__label">拒否</span>
                    </button>
                    <button type="button" class="call-btn call-btn--answer" @click="choose('answer')">
                        <span class="call-btn__icon" aria-hidden="true">📞</span>
                        <span class="call-btn__label">応答</span>
                    </button>
                </div>
            </div>

            <!-- ===== 画面の一部に出る着信の通知 ===== -->
            <div v-else class="home-mock">
                <div class="incoming incoming--popup" role="group" aria-label="電話が かかってきています">
                    <div class="incoming__head">
                        <span class="incoming__avatar incoming__avatar--small" aria-hidden="true">👤</span>
                        <div>
                            <p class="incoming__name">{{ question.caller }}</p>
                            <p class="incoming__status">電話が かかってきています</p>
                        </div>
                    </div>
                    <div class="incoming__actions incoming__actions--popup">
                        <button type="button" class="call-pill call-pill--decline" @click="choose('decline')">
                            <span class="call-pill__icon" aria-hidden="true">📞</span>拒否
                        </button>
                        <button type="button" class="call-pill call-pill--answer" @click="choose('answer')">
                            <span class="call-pill__icon" aria-hidden="true">📞</span>応答
                        </button>
                    </div>
                </div>
                <!-- 通知の後ろのホーム画面。飾りのため、押しても何も起きない。 -->
                <div class="home-mock__apps" aria-hidden="true">
                    <span v-for="app in HOME_APPS" :key="app" class="home-mock__app">{{ app }}</span>
                </div>
            </div>
        </template>
    </PhonePractice>
</template>
