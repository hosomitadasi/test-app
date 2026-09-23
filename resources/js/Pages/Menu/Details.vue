<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

defineProps({
    practices: { type: Object, required: true },
});
</script>

<template>
    <DefaultLayout>
        <Head title="画面操作の練習を選ぶ" />
        <section class="screen screen--center" data-screen="details">
            <div class="button-stack">
                <Link class="btn btn--quiet" href="/practice/contents">練習を選び直す</Link>
                <!-- どの練習も、まず難易度選択画面へ進む -->
                <Link
                    v-for="(practice, type) in practices"
                    :key="type"
                    class="btn btn--primary"
                    :href="`/practice/difficulty/${type}`"
                >{{ practice.menu_label }}</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
