<script setup>
/**
 * 電話の練習（電話を取る／電話を切る）で共通の画面の枠。
 * 開始・中断・結果の画面の作りは、ほかの練習（Practice/Play・Typing/Chat）と同じにしている。
 * 着信画面・通話中画面そのものは、練習ごとの画面から既定のスロットで差し込む。
 */
import { computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import AppDialog from '@/Components/AppDialog.vue';
import { useDialog } from '@/composables/useDialog';

const props = defineProps({
    type: { type: String, required: true },
    practice: { type: Object, required: true },
    setting: { type: Object, required: true },
    // usePhoneGame の戻り値
    game: { type: Object, required: true },
    // usePracticeRecord の戻り値
    record: { type: Object, required: true },
});

// 中の ref を画面でそのまま使えるよう、ここで取り出す
const {
    state, guarded, feedback, questionNumber, successCount, failCount, streak, passed,
} = props.game;
const { message: resultMessage, newTitles } = props.record;
const isStreak = props.game.mode === 'streak';
const difficultyHref = `/practice/phone/difficulty/${props.type}`;

// 問題数は「今の問題が何問目か」を出す。やり終えたあとも出題数を超えないようにする。
const currentNumber = computed(() => Math.min(questionNumber.value, props.game.totalCount));

const resultText = computed(() => {
    if (isStreak) {
        return `${props.game.requiredStreak}かい つづけて できました！`;
    }
    return `${props.game.totalCount}かいのうち ${successCount.value}かい できました`;
});

// 新しい称号をもらったときは、結果画面の上にダイアログで知らせる
const { message: dialogMessage, open: openDialog, close: closeDialog } = useDialog();
watch(newTitles, (titles) => {
    if (titles && titles.length > 0) {
        openDialog(titles.map((name) => `「${name}」の称号を獲得しました！`).join('\n') + '\nおめでとうございます！');
    }
});
</script>

<template>
    <DefaultLayout>
        <Head :title="practice.label" />

        <!-- ================= 開始画面 ================= -->
        <section class="screen screen--center" :class="{ 'is-hidden': state !== 'start' }" data-screen="start">
            <h1 class="title">{{ practice.label }}</h1>
            <!-- 練習前のかんたんな説明。操作のしかたと、難易度ごとの進め方を伝える。 -->
            <p class="lead" v-html="practice.lead"></p>
            <p class="lead lead--note" v-html="setting.note"></p>
            <div class="button-stack">
                <button type="button" class="btn btn--primary" @pointerdown="game.start">始める</button>
                <!-- 練習を選び直す場合は難易度選択画面へ戻る -->
                <Link class="btn btn--quiet" :href="difficultyHref">練習を選び直す</Link>
            </div>
        </section>

        <!-- ================= 練習画面 ================= -->
        <section class="screen screen--play" :class="{ 'is-hidden': state !== 'play' && state !== 'paused' }" data-screen="play">
            <header class="play-header">
                <button type="button" class="btn btn--pause" @pointerdown="game.pause">中断する</button>
                <div class="status status--phone">
                    <!-- 「やさしい」は、続けてできた回数で終わる -->
                    <div v-if="isStreak" class="status__item">
                        <span class="status__label">つづけて できた</span>
                        <span class="status__value">{{ streak }} / {{ game.requiredStreak }}かい</span>
                    </div>
                    <template v-else>
                        <div class="status__item">
                            <span class="status__label">問題数</span>
                            <span class="status__value">{{ currentNumber }} / {{ game.totalCount }}</span>
                        </div>
                        <div class="status__item">
                            <span class="status__label">成功数</span>
                            <span class="status__value">{{ successCount }}</span>
                        </div>
                        <div class="status__item">
                            <span class="status__label">失敗数</span>
                            <span class="status__value">{{ failCount }}</span>
                        </div>
                    </template>
                </div>
            </header>

            <!-- 着信画面・通話中画面。押した直後は結果を上に重ねて、ほかのボタンを押させない。 -->
            <div class="phone-field">
                <slot />
                <div
                    v-if="feedback"
                    class="phone-feedback"
                    :class="feedback.ok ? 'phone-feedback--ok' : 'phone-feedback--ng'"
                    role="status"
                    aria-live="assertive"
                >
                    <span class="phone-feedback__mark" aria-hidden="true">{{ feedback.ok ? '◯' : '△' }}</span>
                    <p class="phone-feedback__title">{{ feedback.title }}</p>
                    <p class="phone-feedback__text">{{ feedback.text }}</p>
                </div>
            </div>
        </section>

        <!-- ================= 中断画面 ================= -->
        <section
            class="screen screen--center screen--overlay"
            :class="{ 'is-hidden': state !== 'paused', 'is-guarded': guarded === 'pause' }"
            data-screen="pause"
        >
            <h2 class="title">中断中</h2>
            <div class="button-stack">
                <button type="button" class="btn btn--primary" @pointerdown="game.resume">再開する</button>
                <!-- 練習を選び直す場合は難易度選択画面へ戻る。途中の結果は記録しない。 -->
                <Link class="btn btn--quiet" :href="difficultyHref">練習を選び直す</Link>
            </div>
        </section>

        <!-- ================= 結果画面 ================= -->
        <section
            class="screen screen--center"
            :class="{ 'is-hidden': state !== 'result', 'is-guarded': guarded === 'result' }"
            data-screen="result"
        >
            <h2 class="title">{{ passed ? '練習完了' : 'もう少しです' }}</h2>
            <p class="phone-result">{{ resultText }}</p>
            <template v-if="passed">
                <!-- 練習結果に応じた声かけと、新しくもらった称号 -->
                <p class="result-message" aria-live="polite">{{ resultMessage }}</p>
                <p v-for="title in newTitles" :key="title" class="result-title">「{{ title }}」の称号をもらいました！</p>
            </template>
            <p v-else class="result-message phone-result__retry">
                ごうかくまで あと少しです。<br>もう一度 ちょうせん してみましょう。
            </p>
            <div class="button-row">
                <button type="button" class="btn btn--primary" @pointerdown="game.start">もう一度練習する</button>
            </div>
            <!-- 練習を選び直す場合はメニュー画面へ戻る -->
            <div class="button-stack button-stack--tight">
                <Link class="btn btn--quiet" href="/menu">練習を選び直す</Link>
            </div>
        </section>

        <AppDialog v-if="dialogMessage" :message="dialogMessage" @close="closeDialog" />
    </DefaultLayout>
</template>
