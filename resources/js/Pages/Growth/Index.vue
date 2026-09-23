<script setup>
/**
 * 成長の記録。
 * 「自分は何ができるようになったのか」が最初に分かるよう、
 * 称号 → できるようになったこと → 今月の練習回数 → 継続日数 の順に出す。
 * 詳しい記録は「これまでの記録」画面に分ける。
 */
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

defineProps({
    currentTitle: { type: Object, default: null },
    practices: { type: Array, required: true },
    monthlyCount: { type: Number, required: true },
    streakDays: { type: Number, required: true },
});
</script>

<template>
    <DefaultLayout>
        <Head title="成長の記録" />
        <section class="screen growth" data-screen="growth">
            <h1 class="title title--small">成長の記録</h1>

            <!-- ① 現在の称号 -->
            <section class="growth-section" aria-labelledby="growth-title-heading">
                <h2 id="growth-title-heading" class="growth-section__heading">いまの称号</h2>
                <template v-if="currentTitle">
                    <p class="growth-title__name">{{ currentTitle.name }}</p>
                    <p class="growth-title__description">{{ currentTitle.description }}</p>
                </template>
                <template v-else>
                    <p class="growth-title__name">まだありません</p>
                    <p class="growth-title__description">練習を続けると、称号がもらえます</p>
                </template>
            </section>

            <!-- ② できるようになったこと（色だけでなく、必ず文字でも状態を書く） -->
            <section class="growth-section" aria-labelledby="growth-skill-heading">
                <h2 id="growth-skill-heading" class="growth-section__heading">できるようになったこと</h2>
                <ul class="skill-list">
                    <li v-for="practice in practices" :key="practice.type" :class="`skill skill--${practice.status}`">
                        <span class="skill__mark" aria-hidden="true"></span>
                        <span class="skill__label">{{ practice.label }}</span>
                        <span class="skill__status">{{ practice.statusLabel }}</span>
                    </li>
                </ul>
            </section>

            <!-- ③ 今月の練習回数 -->
            <p class="growth-count">今月の練習<span class="growth-count__value">{{ monthlyCount }}</span>回</p>

            <!-- ④ 継続日数（主役にしないため、控えめに出す） -->
            <p class="growth-streak">現在の継続　{{ streakDays }}日</p>

            <!-- ⑤ これまでの記録 -->
            <div class="button-stack">
                <Link class="btn btn--primary" href="/growth/history">これまでの記録を見る</Link>
                <Link class="btn btn--quiet" href="/">前の画面に戻る</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
