<script setup>
/**
 * すべての画面の外枠（Android 端末を想定した 700x840px の枠）。
 * 画面が切り替わった直後に出すダイアログ（ログインできた、など）もここで出す。
 */
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppDialog from '@/Components/AppDialog.vue';

const page = usePage();
const flashDialog = ref(null);

watch(
    () => page.props.flash,
    (flash) => {
        if (flash && flash.dialog) {
            flashDialog.value = flash.dialog;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="app-frame">
        <slot />
        <AppDialog v-if="flashDialog" :message="flashDialog" opaque @close="flashDialog = null" />
    </div>
</template>
