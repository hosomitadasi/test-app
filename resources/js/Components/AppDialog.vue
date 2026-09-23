<script setup>
/**
 * 【分かりました！】ボタンだけを持つダイアログ。全画面で共用する。
 *
 * 出た直後は、直前の操作の指が残っていても押されないよう、少しの間ボタンを効かなくする。
 */
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    message: { type: String, required: true },
    // 後ろの画面を見せない（画面が切り替わる前後に出すとき）
    opaque: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const GUARD_MS = 500;
const guarded = ref(true);
const button = ref(null);
let guardHandle = null;

onMounted(() => {
    // 入力欄からフォーカスを外し、端末のキーボードを閉じてダイアログを見やすくする
    nextTick(() => button.value && button.value.focus());
    guardHandle = window.setTimeout(() => {
        guarded.value = false;
    }, GUARD_MS);
});

onBeforeUnmount(() => window.clearTimeout(guardHandle));
</script>

<template>
    <div class="dialog-backdrop" :class="{ 'dialog-backdrop--opaque': opaque }">
        <div class="dialog" role="alertdialog" aria-modal="true" aria-labelledby="dialog-message">
            <p id="dialog-message" class="dialog__message">{{ message }}</p>
            <button
                ref="button"
                type="button"
                class="btn btn--primary"
                :class="{ 'is-guarded': guarded }"
                @click="emit('close')"
            >分かりました！</button>
        </div>
    </div>
</template>
