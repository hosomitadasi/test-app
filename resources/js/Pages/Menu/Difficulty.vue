<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

defineProps({
    type: { type: String, required: true },
    practice: { type: Object, required: true },
    levels: { type: Object, required: true },
});
</script>

<template>
    <DefaultLayout>
        <Head title="難易度を選ぶ" />
        <section class="screen screen--center" data-screen="difficulty">
            <h1 class="title title--small">{{ practice.label }}</h1>
            <div class="button-stack">
                <Link class="btn btn--quiet" href="/practice/details">練習を選び直す</Link>
                <!-- やさしい／ふつう／むずかしい は、いずれも同じ練習画面へ難易度付きで進む -->
                <Link
                    v-for="(setting, level) in levels"
                    :key="level"
                    class="btn btn--primary"
                    :href="`/practice/play/${type}/${level}`"
                >{{ setting.label }}</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
