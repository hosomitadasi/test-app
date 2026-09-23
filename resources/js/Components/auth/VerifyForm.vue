<script setup>
/** メールで届いた確認番号（6桁）の入力。会員登録とカギの作り直しで共用する。 */
defineProps({
    form: { type: Object, required: true },
    email: { type: String, default: '' },
});

const emit = defineEmits(['submit']);
</script>

<template>
    <form class="form" novalidate @submit.prevent="emit('submit')">
        <p v-if="email" class="form-note">{{ email }} に<br>6けたの番号を送りました</p>
        <div class="form-field">
            <label class="form-label" for="code">確認番号（6けた）</label>
            <input
                id="code"
                v-model="form.code"
                class="form-input"
                :class="{ 'is-invalid': form.errors.code }"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="12"
                enterkeyhint="done"
            >
            <p v-if="form.errors.code" class="form-error">{{ form.errors.code }}</p>
        </div>
        <button type="submit" class="btn btn--primary" :disabled="form.processing">出来ました！</button>
    </form>
</template>
