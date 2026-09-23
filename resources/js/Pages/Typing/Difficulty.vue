<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

defineProps({
    label: { type: String, required: true },
    levels: { type: Object, required: true },
});
</script>

<template>
    <DefaultLayout>
        <Head title="難易度を選ぶ" />
        <section class="screen screen--center" data-screen="typing-difficulty">
            <h1 class="title title--small">{{ label }}</h1>
            <div class="button-stack">
                <!-- 文字打ち練習は練習詳細画面を経由しないため、練習内容選択画面へ戻る -->
                <Link class="btn btn--quiet" href="/practice/contents">練習を選び直す</Link>
                <!-- やさしい／ふつう／むずかしい は、いずれも同じ練習画面へ難易度付きで進む -->
                <Link
                    v-for="(setting, level) in levels"
                    :key="level"
                    class="btn btn--primary"
                    :href="`/practice/typing/play/${level}`"
                >{{ setting.label }}</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
