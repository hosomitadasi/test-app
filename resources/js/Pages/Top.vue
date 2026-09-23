<script setup>
/**
 * トップ画面。ログインしているかどうかで、出すボタンを切り替える。
 */
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <DefaultLayout>
        <Head title="スマホの練習君" />
        <section class="screen screen--center" data-screen="top">
            <h1 class="app-title">スマホの練習君</h1>
            <div v-if="user" class="button-stack">
                <Link class="btn btn--primary" href="/menu">早速練習をする</Link>
                <Link class="btn btn--primary" href="/growth">成長の記録を見る</Link>
            </div>
            <div v-else class="button-stack">
                <Link class="btn btn--primary" href="/register">初めての方はこちら</Link>
                <Link class="btn btn--primary" href="/login">使ったことがある方はこちら</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
