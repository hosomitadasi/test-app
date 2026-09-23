<script setup>
/** メールアドレスの入力。会員登録とカギの作り直しで共用する。 */
defineProps({
    form: { type: Object, required: true },
});

const emit = defineEmits(['submit']);
</script>

<template>
    <form class="form" novalidate @submit.prevent="emit('submit')">
        <div class="form-field">
            <label class="form-label" for="email">メールアドレス</label>
            <input
                id="email"
                v-model="form.email"
                class="form-input"
                :class="{ 'is-invalid': form.errors.email }"
                type="email"
                inputmode="email"
                autocomplete="email"
                autocapitalize="off"
                autocorrect="off"
                spellcheck="false"
                enterkeyhint="done"
            >
            <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
        </div>
        <button type="submit" class="btn btn--primary" :disabled="form.processing">出来ました！</button>
    </form>
</template>
