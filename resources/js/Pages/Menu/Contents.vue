<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
</script>

<template>
    <DefaultLayout>
        <Head title="練習を選ぶ" />
        <section class="screen screen--center" data-screen="contents">
            <div class="button-stack">
                <Link class="btn btn--quiet" href="/menu">練習を選び直す</Link>
                <Link class="btn btn--primary" href="/practice/details">画面操作の練習をする</Link>
                <Link class="btn btn--primary" href="/practice/typing/difficulty">文字入力の練習をする</Link>
                <!-- 電話の練習は後日実装するため、遷移は設定しない -->
                <button type="button" class="btn btn--primary">電話の練習をする</button>
            </div>
        </section>
    </DefaultLayout>
</template>
