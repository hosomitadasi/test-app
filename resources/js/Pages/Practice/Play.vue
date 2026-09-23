<script setup>
/**
 * 画面操作の練習（軽く押す／長く押す／なぞる／はらう）。
 * 種類と難易度の組み合わせで、この1画面を使い回す。
 */
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { usePracticeGame } from '@/composables/usePracticeGame';
import { usePracticeRecord } from '@/composables/usePracticeRecord';

const props = defineProps({
    type: { type: String, required: true },
    level: { type: String, required: true },
    practice: { type: Object, required: true },
    setting: { type: Object, required: true },
    timeLimitSeconds: { type: Number, required: true },
    requiredCount: { type: Number, required: true },
    hasDecoy: { type: Boolean, required: true },
    holdMs: { type: Number, required: true },
    scoreDigits: { type: Number, required: true },
});

const hasArrow = ['swipe', 'flick'].includes(props.type);
const record = usePracticeRecord(props.type, props.level);
const { message: resultMessage, newTitles } = record;

// Laravel 側で持っている設定値を、練習の処理に渡す
const game = usePracticeGame({
    type: props.type,
    level: props.level,
    timeLimitSeconds: props.timeLimitSeconds,
    requiredCount: props.requiredCount,
    hasDecoy: props.hasDecoy,
    holdMs: props.holdMs,
    scoreDigits: props.scoreDigits,
}, { onFinish: record.save });
</script>

<template>
    <DefaultLayout>
        <Head :title="practice.label" />

        <!-- ================= 開始画面 ================= -->
        <section id="screen-start" class="screen screen--center" data-screen="start">
            <h1 class="title">{{ practice.label }}</h1>
            <!-- 練習前のかんたんな説明。操作のしかたと、難易度ごとの進め方を伝える。 -->
            <p class="lead" v-html="practice.lead"></p>
            <p class="lead lead--note" v-html="setting.note"></p>
            <div class="button-stack">
                <button id="btn-start" type="button" class="btn btn--primary" @pointerdown="game.start">始める</button>
                <!-- 練習を選び直す場合は難易度選択画面へ戻る -->
                <Link id="btn-exit-start" class="btn btn--quiet" :href="`/practice/difficulty/${type}`">練習を選び直す</Link>
            </div>
        </section>

        <!-- ================= 練習画面 ================= -->
        <section id="screen-play" class="screen screen--play is-hidden" data-screen="play">
            <header class="play-header">
                <button id="btn-pause" type="button" class="btn btn--pause" @pointerdown="game.pause">中断する</button>
                <div class="status">
                    <!-- 難易度「やさしい」は制限時間ではなく残り回数で終わる -->
                    <div v-if="requiredCount > 0" class="status__item">
                        <span class="status__label">のこり</span>
                        <span id="count-value" class="status__value">{{ requiredCount }}かい</span>
                    </div>
                    <div v-else class="status__item">
                        <span class="status__label">制限時間</span>
                        <span id="time-value" class="status__value">03:00</span>
                    </div>
                    <div class="status__item">
                        <span class="status__label">得点</span>
                        <span id="score-value" class="status__value">000点</span>
                    </div>
                </div>
            </header>

            <!-- アイコンはこの領域（画面中央〜下部）の中だけに出現する -->
            <div id="play-field" class="play-field" aria-live="off">
                <button id="target" type="button" :class="`target target--${type} is-hidden`" :aria-label="practice.target_label">
                    <span v-if="hasArrow" class="target__arrow" aria-hidden="true"></span>
                </button>
                <!-- 操作してはいけないアイコン。ただしいアイコンと必ず一緒に出す。 -->
                <button v-if="hasDecoy" id="decoy" type="button" :class="`target target--${type} target--decoy is-hidden`" aria-label="さわってはいけません">
                    <span v-if="hasArrow" class="target__arrow" aria-hidden="true"></span>
                </button>
            </div>
        </section>

        <!-- ================= 中断画面 ================= -->
        <section id="screen-pause" class="screen screen--center screen--overlay is-hidden" data-screen="pause">
            <h2 class="title">中断中</h2>
            <div class="button-stack">
                <button id="btn-resume" type="button" class="btn btn--primary" @pointerdown="game.resume">再開する</button>
                <!-- 練習を選び直す場合は難易度選択画面へ戻る -->
                <Link id="btn-quit" class="btn btn--quiet" :href="`/practice/difficulty/${type}`">練習を選び直す</Link>
            </div>
        </section>

        <!-- ================= 結果画面 ================= -->
        <section id="screen-result" class="screen screen--center is-hidden" data-screen="result">
            <h2 class="title">結果</h2>
            <p id="result-score" class="result-score">000点</p>
            <!-- 練習結果に応じた声かけと、新しくもらった称号 -->
            <p class="result-message" aria-live="polite">{{ resultMessage }}</p>
            <p v-for="title in newTitles" :key="title" class="result-title">「{{ title }}」の称号をもらいました！</p>
            <div class="button-row">
                <button id="btn-again" type="button" class="btn btn--primary" @pointerdown="game.start">もう一度練習する</button>
            </div>
            <!-- 練習を選び直す場合はメニュー画面へ戻る -->
            <div class="button-stack button-stack--tight">
                <Link class="btn btn--quiet" href="/menu">練習を選び直す</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
