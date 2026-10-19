<script setup>
/**
 * 練習詳細選択画面。画面操作の練習と文字入力の練習で使い回す。
 * ボタンの名前と行き先は、サーバー側で決めて渡す。
 */
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

defineProps({
    title: { type: String, required: true },
    backHref: { type: String, required: true },
    practices: { type: Array, required: true },
});
</script>

<template>
    <DefaultLayout>
        <Head :title="title" />
        <section class="screen screen--center" data-screen="details">
            <div class="button-stack">
                <Link class="btn btn--quiet" :href="backHref">練習を選び直す</Link>
                <!-- どの練習も、まず難易度選択画面へ進む -->
                <Link
                    v-for="practice in practices"
                    :key="practice.href"
                    class="btn btn--primary"
                    :href="practice.href"
                >{{ practice.label }}</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
