<script setup>
/**
 * ログイン画面。送信先は Fortify（POST /login）。
 * ログインできたら、サーバーがトップ画面（ログイン後）へ進め、お帰りなさいのダイアログを出す。
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import AppDialog from '@/Components/AppDialog.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { firstError, useDialog } from '@/composables/useDialog';

const { message: dialogMessage, open: openDialog, close: closeDialog } = useDialog();
const form = useForm({ email: '', password: '' });

function submit() {
    form.post('/login', {
        preserveState: true,
        preserveScroll: true,
        onError: (errors) => openDialog(firstError(errors)),
    });
}

function forgotPassword() {
    openDialog(
        '分かりました！ではまずこのアプリを使っていたかどうか調べるので、メールアドレスを教えてください。',
        () => router.visit('/forgot-password'),
    );
}
</script>

<template>
    <DefaultLayout>
        <Head title="ログイン" />
        <section class="screen screen--form" data-screen="login">
            <h1 class="title title--small">使ったことがある方</h1>
            <p class="lead lead--note">メールアドレスとカギを<br>入力してください</p>

            <form class="form" novalidate @submit.prevent="submit">
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
                    >
                    <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
                </div>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    label="カギ（パスワード）"
                    :error="form.errors.password"
                    autocomplete="current-password"
                />
                <button type="submit" class="btn btn--primary" :disabled="form.processing">出来ました！</button>
            </form>

            <div class="button-stack">
                <button type="button" class="btn btn--quiet" @click="forgotPassword">カギを忘れました！</button>
                <Link class="btn btn--quiet" href="/">前の画面に戻る</Link>
            </div>
        </section>
        <AppDialog v-if="dialogMessage" :message="dialogMessage" @close="closeDialog" />
    </DefaultLayout>
</template>
