<script setup>
/**
 * 練習難易度選択画面。画面操作の練習・文字打ち練習・チャット型練習で使い回す。
 * ボタンの名前と行き先は、サーバー側で決めて渡す。
 */
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

defineProps({
    label: { type: String, required: true },
    backHref: { type: String, required: true },
    levels: { type: Array, required: true },
});
</script>

<template>
    <DefaultLayout>
        <Head title="難易度を選ぶ" />
        <section class="screen screen--center" data-screen="difficulty">
            <h1 class="title title--small">{{ label }}</h1>
            <div class="button-stack">
                <!-- 練習詳細選択画面へ戻る -->
                <Link class="btn btn--quiet" :href="backHref">練習を選び直す</Link>
                <!-- やさしい／ふつう／むずかしい は、いずれも同じ練習画面へ難易度付きで進む -->
                <Link
                    v-for="level in levels"
                    :key="level.href"
                    class="btn btn--primary"
                    :href="level.href"
                >{{ level.label }}</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
