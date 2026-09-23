<script setup>
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
</script>

<template>
    <DefaultLayout>
        <Head title="今日の練習" />
        <section class="screen screen--menu" data-screen="menu">
            <!-- トップ画面（ログイン後）から来るため、トップ画面へ戻る -->
            <div class="menu-top">
                <Link class="btn btn--quiet" href="/">前の画面に戻る</Link>
            </div>

            <div class="menu-body">
                <h1 class="title">今日の練習</h1>
                <p class="lead">文字の部分を押してください</p>

                <!-- ①〜③の遷移先は後日実装するため、現時点では href を設定しない -->
                <nav class="menu-list">
                    <a class="menu-list__item">①○○の練習</a>
                    <a class="menu-list__item">②○○の練習</a>
                    <a class="menu-list__item">③○○の練習</a>
                </nav>
            </div>

            <div class="menu-bottom">
                <Link class="btn btn--primary" href="/practice/contents">さらに他の練習をする方はこちら！</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
