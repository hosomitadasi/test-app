<script setup>
/**
 * 文字打ち練習。難易度に応じた問題とヒントを受け取り、この1画面で出題する。
 */
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { useTypingGame } from '@/composables/useTypingGame';
import { usePracticeRecord } from '@/composables/usePracticeRecord';

const props = defineProps({
    level: { type: String, required: true },
    label: { type: String, required: true },
    setting: { type: Object, required: true },
    questions: { type: Array, required: true },
    timeLimitSeconds: { type: Number, required: true },
    requiredCount: { type: Number, required: true },
    hasHint: { type: Boolean, required: true },
    isConversion: { type: Boolean, required: true },
    hintIntervalMs: { type: Number, required: true },
    scoreDigits: { type: Number, required: true },
});

const record = usePracticeRecord('typing', props.level);
const { message: resultMessage, newTitles } = record;

// Laravel 側で持っている設定値と出題内容を、練習の処理に渡す
const game = useTypingGame({
    level: props.level,
    timeLimitSeconds: props.timeLimitSeconds,
    requiredCount: props.requiredCount,
    hasHint: props.hasHint,
    isConversion: props.isConversion,
    hintIntervalMs: props.hintIntervalMs,
    scoreDigits: props.scoreDigits,
    questions: props.questions,
}, { onFinish: record.save });
</script>

<template>
    <DefaultLayout>
        <Head :title="label" />

        <!-- ================= 開始画面 ================= -->
        <section id="screen-start" class="screen screen--center" data-screen="start">
            <h1 class="title">{{ label }}</h1>
            <!-- 練習前のかんたんな説明。操作のしかたと、難易度ごとの進め方を伝える。 -->
            <p class="lead">画面に出てくる ことばを<br>下の わくに 入力してください</p>
            <p class="lead lead--note" v-html="setting.note"></p>
            <div class="button-stack">
                <button id="btn-start" type="button" class="btn btn--primary" @pointerdown="game.start">始める</button>
                <!-- 練習を選び直す場合は難易度選択画面へ戻る -->
                <Link id="btn-exit-start" class="btn btn--quiet" href="/practice/typing/difficulty">練習を選び直す</Link>
            </div>
        </section>

        <!-- ================= 練習画面 ================= -->
        <section id="screen-play" class="screen screen--play is-hidden" data-screen="play">
            <header class="play-header">
                <button id="btn-pause" type="button" class="btn btn--pause" @pointerdown="game.pause">中断する</button>
                <div class="status">
                    <!-- 難易度「やさしい」は制限時間ではなく残り問題数で終わる -->
                    <div v-if="requiredCount > 0" class="status__item">
                        <span class="status__label">のこり</span>
                        <span id="count-value" class="status__value">{{ requiredCount }}もん</span>
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

            <!-- お題・入力欄・回答ボタンを縦に並べる。キーボードは端末のものを使う。 -->
            <div id="typing-field" class="typing-field">
                <p id="typing-prompt" class="typing-prompt" aria-live="polite">　</p>
                <!-- 「むずかしい」だけ、入力してほしい表記を読みがなの下に添える -->
                <p v-if="isConversion" id="typing-target" class="typing-target"></p>

                <!-- 端末に設定されているソフトウェアキーボード（Gboard など）をそのまま使う -->
                <label class="typing-input-label" for="typing-input">
                    <span class="is-visually-hidden">こたえを入力してください</span>
                    <input
                        id="typing-input"
                        class="typing-input"
                        type="text"
                        inputmode="text"
                        lang="ja"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        enterkeyhint="done"
                    >
                </label>

                <!-- 押しても入力欄の印を外さない（キーボードが閉じて画面が跳ねるのを防ぐ） -->
                <button
                    id="btn-answer"
                    type="button"
                    class="btn btn--primary typing-answer"
                    @pointerdown.prevent
                    @click="game.answer"
                >回答です</button>

                <!-- まちがい・ヒントはこの1か所にまとめて出し、画面が動かないようにする -->
                <p id="typing-message" class="typing-message" aria-live="polite"></p>
                <div v-if="hasHint" id="typing-hint" class="typing-hint is-hidden" aria-live="polite"></div>
            </div>
        </section>

        <!-- ================= 中断画面 ================= -->
        <section id="screen-pause" class="screen screen--center screen--overlay is-hidden" data-screen="pause">
            <h2 class="title">中断中</h2>
            <div class="button-stack">
                <button id="btn-resume" type="button" class="btn btn--primary" @pointerdown="game.resume">再開する</button>
                <!-- 練習を選び直す場合は難易度選択画面へ戻る -->
                <Link id="btn-quit" class="btn btn--quiet" href="/practice/typing/difficulty">練習を選び直す</Link>
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
