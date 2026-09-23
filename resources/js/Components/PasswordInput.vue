<script setup>
/**
 * カギ（パスワード）の入力欄。showToggle を付けると、表示／非表示のチェックボックスを下に出す。
 */
const model = defineModel({ type: String, default: '' });
const visible = defineModel('visible', { type: Boolean, default: false });

defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    error: { type: String, default: '' },
    autocomplete: { type: String, default: 'current-password' },
    showToggle: { type: Boolean, default: false },
});
</script>

<template>
    <div class="form-field">
        <label class="form-label" :for="id">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            class="form-input"
            :class="{ 'is-invalid': error }"
            :type="visible ? 'text' : 'password'"
            :autocomplete="autocomplete"
            autocapitalize="off"
            autocorrect="off"
            spellcheck="false"
        >
        <p v-if="error" class="form-error">{{ error }}</p>
        <label v-if="showToggle" class="form-check">
            <input v-model="visible" type="checkbox">
            入れたカギを表示する
        </label>
    </div>
</template>
