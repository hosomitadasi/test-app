<script setup>
import { onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

defineProps({
    // 今日の練習。{ label, href, completed } の一覧で、日ごとにサーバーが選ぶ。
    todayPractices: { type: Array, required: true },
});

// 練習を終えてブラウザの「戻る」で来たときは、前に表示した内容のままになるため、
// 完了の状態だけ取り直して、やり終えた練習を押せないようにする。
onMounted(() => {
    router.reload({ only: ['todayPractices'] });
});
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

                <!-- 押すと、その練習の開始画面へ直接進む。やり終えた練習は「完了」とし、押せなくする。 -->
                <nav class="menu-list">
                    <template v-for="(practice, index) in todayPractices" :key="practice.href">
                        <a
                            v-if="practice.completed"
                            class="menu-list__item menu-list__item--completed"
                            aria-disabled="true"
                        >
                            {{ '①②③④⑤'[index] }}{{ practice.label }}
                            <span class="menu-list__badge">完了</span>
                        </a>
                        <Link v-else class="menu-list__item" :href="practice.href">
                            {{ '①②③④⑤'[index] }}{{ practice.label }}
                        </Link>
                    </template>
                </nav>
            </div>

            <div class="menu-bottom">
                <Link class="btn btn--primary" href="/practice/contents">さらに他の練習をする方はこちら！</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
