<script setup>
/** カギ（パスワード）の登録。会員登録とカギの作り直しで共用する。 */
import { ref } from 'vue';
import PasswordInput from '@/Components/PasswordInput.vue';

defineProps({
    form: { type: Object, required: true },
});

const emit = defineEmits(['submit']);
const visible = ref(false);
</script>

<template>
    <form class="form" novalidate @submit.prevent="emit('submit')">
        <p class="form-note">8文字以上で、アルファベットと数字を<br>まぜて作ってください</p>
        <PasswordInput
            id="password"
            v-model="form.password"
            :visible="visible"
            label="カギ（パスワード）"
            :error="form.errors.password"
            autocomplete="new-password"
        />
        <PasswordInput
            id="password_confirmation"
            v-model="form.password_confirmation"
            v-model:visible="visible"
            label="確認のため、もう一度同じカギ"
            autocomplete="new-password"
            show-toggle
        />
        <button type="submit" class="btn btn--primary" :disabled="form.processing">出来ました！</button>
    </form>
</template>
