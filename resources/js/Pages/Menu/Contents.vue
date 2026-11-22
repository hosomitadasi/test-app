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
                <Link class="btn btn--primary" href="/practice/typing/details">文字入力の練習をする</Link>
                <Link class="btn btn--primary" href="/practice/phone/details">電話の練習をする</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
