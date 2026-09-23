<script setup>
/**
 * これまでの記録。
 * 一度にたくさん並べると分かりにくいため、見たい項目のボタンを押して1つずつ切り替えて見せる。
 */
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';

defineProps({
    monthlyCounts: { type: Array, required: true },
    bestRecords: { type: Array, required: true },
    titles: { type: Array, required: true },
    improvements: { type: Array, required: true },
});

const TABS = [
    { key: 'monthly', label: '月ごとの回数' },
    { key: 'best', label: 'いちばんの記録' },
    { key: 'titles', label: 'もらった称号' },
    { key: 'improvements', label: '上達した記録' },
];

const current = ref('monthly');
</script>

<template>
    <DefaultLayout>
        <Head title="これまでの記録" />
        <section class="screen growth" data-screen="growth-history">
            <h1 class="title title--small">これまでの記録</h1>

            <div class="history-tabs" role="group" aria-label="見たい記録を選ぶ">
                <button
                    v-for="tab in TABS"
                    :key="tab.key"
                    type="button"
                    class="btn btn--quiet"
                    :aria-pressed="current === tab.key ? 'true' : 'false'"
                    @click="current = tab.key"
                >{{ tab.label }}</button>
            </div>

            <!-- 月ごとの練習回数 -->
            <section v-if="current === 'monthly'" class="growth-section">
                <h2 class="growth-section__heading">月ごとの練習回数</h2>
                <ul class="history-list">
                    <li v-for="month in monthlyCounts" :key="month.label" class="history-list__item">
                        <span>{{ month.label }}</span>
                        <span class="history-list__value">{{ month.count }}回</span>
                    </li>
                </ul>
            </section>

            <!-- 練習ごとのいちばんの記録 -->
            <section v-else-if="current === 'best'" class="growth-section">
                <h2 class="growth-section__heading">練習ごとの いちばんの記録</h2>
                <p v-if="bestRecords.length === 0" class="history-empty">まだ記録がありません。練習をすると、ここにのります。</p>
                <div v-for="practice in bestRecords" :key="practice.label" class="history-group">
                    <h3 class="history-group__name">{{ practice.label }}</h3>
                    <ul class="history-list">
                        <li v-for="row in practice.rows" :key="row.level" class="history-list__item">
                            <span>{{ row.level }}</span>
                            <span class="history-list__value">{{ row.score }}点</span>
                            <span>{{ row.date }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- もらった称号（初級クリア日など） -->
            <section v-else-if="current === 'titles'" class="growth-section">
                <h2 class="growth-section__heading">もらった称号</h2>
                <p v-if="titles.length === 0" class="history-empty">まだ称号はありません。練習を続けると、称号がもらえます。</p>
                <ul v-else class="history-list">
                    <li v-for="title in titles" :key="title.name" class="history-list__item">
                        <span class="history-list__value">{{ title.name }}</span>
                        <span>{{ title.date }}</span>
                    </li>
                </ul>
            </section>

            <!-- 上達した記録（自分のいちばんの点数を更新したとき） -->
            <section v-else class="growth-section">
                <h2 class="growth-section__heading">上達した記録</h2>
                <p v-if="improvements.length === 0" class="history-empty">まだありません。前より良い点数がとれると、ここにのります。</p>
                <ul v-else class="history-list">
                    <li v-for="(item, index) in improvements" :key="index" class="history-list__item">
                        <span>{{ item.label }}</span>
                        <span class="history-list__value">{{ item.from }}点 → {{ item.to }}点</span>
                        <span>{{ item.date }}</span>
                    </li>
                </ul>
            </section>

            <div class="button-stack">
                <Link class="btn btn--quiet" href="/growth">成長の記録に戻る</Link>
            </div>
        </section>
    </DefaultLayout>
</template>
