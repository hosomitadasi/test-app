<script setup>
/** 会員登録：名前の入力 */
defineProps({
    form: { type: Object, required: true },
});

const emit = defineEmits(['submit']);
</script>

<template>
    <form class="form" novalidate @submit.prevent="emit('submit')">
        <div class="form-field">
            <label class="form-label" for="name">お名前</label>
            <input
                id="name"
                v-model="form.name"
                class="form-input"
                :class="{ 'is-invalid': form.errors.name }"
                type="text"
                autocomplete="name"
                enterkeyhint="done"
            >
            <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
        </div>
        <button type="submit" class="btn btn--primary" :disabled="form.processing">出来ました！</button>
    </form>
</template>
