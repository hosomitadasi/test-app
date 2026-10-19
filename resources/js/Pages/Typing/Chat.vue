<script setup>
/**
 * チャット型練習。メッセージアプリのように、相手から届く質問へ答えを送る。
 * 開始・中断・結果の画面の作りは、文字打ち練習（Typing/Play）と同じにしている。
 */
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import { useChatGame } from '@/composables/useChatGame';
import { usePracticeRecord } from '@/composables/usePracticeRecord';

const props = defineProps({
    level: { type: String, required: true },
    label: { type: String, required: true },
    setting: { type: Object, required: true },
    questions: { type: Array, required: true },
    replies: { type: Array, required: true },
    greeting: { type: String, required: true },
    closing: { type: String, required: true },
    requiredCount: { type: Number, required: true },
    isConversion: { type: Boolean, required: true },
    answerMaxLength: { type: Number, required: true },
    scoreDigits: { type: Number, required: true },
});

const record = usePracticeRecord('chat', props.level);
const { message: resultMessage, newTitles } = record;

// Laravel 側で持っている設定値と質問を、練習の処理に渡す
const game = useChatGame({
    requiredCount: props.requiredCount,
    scoreDigits: props.scoreDigits,
    questions: props.questions,
    replies: props.replies,
    greeting: props.greeting,
    closing: props.closing,
}, { onFinish: record.save });

// 画面の要素（ref="inputEl" / ref="logEl"）を練習の処理と結び付ける
const {
    state, guarded, messages, isPartnerTyping, canAnswer, draft, notice, score, remaining, inputEl, logEl,
} = game;
</script>

<template>
    <DefaultLayout>
        <Head :title="label" />

        <!-- ================= 開始画面 ================= -->
        <section class="screen screen--center" :class="{ 'is-hidden': state !== 'start' }" data-screen="start">
            <h1 class="title">{{ label }}</h1>
            <!-- 練習前のかんたんな説明。操作のしかたと、難易度ごとの進め方を伝える。 -->
            <p class="lead">あいてから とどく しつもんを よんで<br>下の わくに こたえを 入力してください</p>
            <p class="lead lead--note" v-html="setting.note"></p>
            <div class="button-stack">
                <button type="button" class="btn btn--primary" @pointerdown="game.start">始める</button>
                <!-- 練習を選び直す場合は難易度選択画面へ戻る -->
                <Link class="btn btn--quiet" href="/practice/typing/chat/difficulty">練習を選び直す</Link>
            </div>
        </section>

        <!-- ================= 練習画面 ================= -->
        <section class="screen screen--play" :class="{ 'is-hidden': state !== 'play' && state !== 'paused' }" data-screen="play">
            <header class="play-header">
                <button type="button" class="btn btn--pause" @pointerdown="game.pause">中断する</button>
                <div class="status">
                    <!-- どの難易度も、制限時間ではなく残りの質問の数で終わる -->
                    <div class="status__item">
                        <span class="status__label">のこり</span>
                        <span class="status__value">{{ remaining }}もん</span>
                    </div>
                    <div class="status__item">
                        <span class="status__label">得点</span>
                        <span class="status__value">{{ game.formatScore(score) }}</span>
                    </div>
                </div>
            </header>

            <!-- やり取りの欄。相手の吹き出しは左、自分の吹き出しは右に出す。 -->
            <div ref="logEl" class="chat-log" aria-live="polite">
                <div
                    v-for="item in messages"
                    :key="item.id"
                    class="chat-row"
                    :class="item.from === 'me' ? 'chat-row--me' : 'chat-row--partner'"
                >
                    <span v-if="item.from === 'partner'" class="chat-avatar" aria-hidden="true">😊</span>
                    <!-- 利用者が入力した文字は、そのまま文字として出す（v-html は使わない） -->
                    <p class="chat-bubble">
                        <span class="is-visually-hidden">{{ item.from === 'me' ? 'あなた：' : 'あいて：' }}</span>{{ item.text }}
                    </p>
                </div>
                <!-- 相手が入力している様子 -->
                <div v-if="isPartnerTyping" class="chat-row chat-row--partner">
                    <span class="chat-avatar" aria-hidden="true">😊</span>
                    <p class="chat-bubble chat-bubble--typing">
                        <span class="is-visually-hidden">あいてが 入力しています</span>
                        <span class="chat-dot" aria-hidden="true"></span>
                        <span class="chat-dot" aria-hidden="true"></span>
                        <span class="chat-dot" aria-hidden="true"></span>
                    </p>
                </div>
            </div>

            <!-- まちがいはこの1か所に出し、画面が動かないようにする -->
            <p class="chat-notice" aria-live="polite">{{ notice }}</p>

            <!-- 入力欄と送るボタン。キーボードは端末のものを使う。 -->
            <div class="chat-input-bar">
                <label class="chat-input-label" for="chat-input">
                    <span class="is-visually-hidden">こたえを入力してください</span>
                    <input
                        id="chat-input"
                        ref="inputEl"
                        v-model="draft"
                        class="chat-input"
                        type="text"
                        inputmode="text"
                        lang="ja"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        enterkeyhint="send"
                        :maxlength="answerMaxLength"
                        :placeholder="canAnswer ? 'ここに入力' : ''"
                        @keydown="game.onInputKeydown"
                        @input="game.onInput"
                    >
                </label>
                <!-- 押しても入力欄の印を外さない（キーボードが閉じて画面が跳ねるのを防ぐ） -->
                <button
                    type="button"
                    class="btn btn--primary chat-send"
                    :disabled="!canAnswer"
                    @pointerdown.prevent
                    @click="game.answer"
                >送る</button>
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
                <!-- 練習を選び直す場合は難易度選択画面へ戻る -->
                <Link class="btn btn--quiet" href="/practice/typing/chat/difficulty">練習を選び直す</Link>
            </div>
        </section>

        <!-- ================= 結果画面 ================= -->
        <section
            class="screen screen--center"
            :class="{ 'is-hidden': state !== 'result', 'is-guarded': guarded === 'result' }"
            data-screen="result"
        >
            <h2 class="title">結果</h2>
            <p class="result-score">{{ game.formatScore(score) }}</p>
            <!-- 練習結果に応じた声かけと、新しくもらった称号 -->
            <p class="result-message" aria-live="polite">{{ resultMessage }}</p>
            <p v-for="title in newTitles" :key="title" class="result-title">「{{ title }}」の称号をもらいました！</p>
            <div class="button-row">
                <button type="button" class="btn btn--primary" @pointerdown="game.start">もう一度練習する</button>
            </div>
            <!-- 練習を選び直す場合はメニュー画面へ戻る -->
            <div class="button-stack button-stack--tight">
                <Link class="btn btn--quiet" href="/menu">練習を選び直す</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
