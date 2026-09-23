<script setup>
/**
 * カギ（パスワード）を忘れたときの作り直し。
 * メールアドレス → 確認番号 → 新しいカギ の順に、この画面の中で切り替えて進む。
 */
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import AppDialog from '@/Components/AppDialog.vue';
import EmailForm from '@/Components/auth/EmailForm.vue';
import VerifyForm from '@/Components/auth/VerifyForm.vue';
import PasswordForm from '@/Components/auth/PasswordForm.vue';
import { firstError, useDialog } from '@/composables/useDialog';

const props = defineProps({
    initialStep: { type: String, default: 'email' },
    email: { type: String, default: '' },
});

const LEADS = {
    email: '使っているメールアドレスを<br>入力してください',
    verify: 'メールで届いた番号を<br>入力してください',
    password: '新しいカギ（パスワード）を<br>作ってください',
};

const step = ref(props.initialStep);
const { message: dialogMessage, open: openDialog, close: closeDialog } = useDialog();

const emailForm = useForm({ email: props.email });
const sendForm = useForm({});
const codeForm = useForm({ code: '' });
const passwordForm = useForm({ password: '', password_confirmation: '' });

function send(form, url, onSuccess = null) {
    form.post(url, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => onSuccess && onSuccess(),
        // 途中の内容が残っていない（flow）ときは、ダイアログを閉じたらメールアドレスの入力からやり直す
        onError: (errors) => openDialog(firstError(errors), errors.flow ? () => goTo('email') : null),
    });
}

function goTo(next) {
    [emailForm, sendForm, codeForm, passwordForm].forEach((form) => form.clearErrors());
    step.value = next;
}

function submitEmail() {
    send(emailForm, '/forgot-password/email', () => openDialog(
        'ありがとうございます！それでは次に進みます。',
        sendCode,
    ));
}

function sendCode() {
    send(sendForm, '/forgot-password/send-code', () => {
        codeForm.reset();
        goTo('verify');
    });
}

function submitCode() {
    send(codeForm, '/forgot-password/verify', () => openDialog(
        '登録されているのが見つかりました！それでは新しいカギも作り直しましょう。',
        () => {
            passwordForm.reset();
            goTo('password');
        },
    ));
}

/** 作り直せたら、サーバーがトップ画面（ログイン後）へ進め、完了のダイアログを出す */
function submitPassword() {
    send(passwordForm, '/forgot-password/password');
}
</script>

<template>
    <DefaultLayout>
        <Head title="カギの作り直し" />
        <section class="screen screen--form" :data-step="step" data-screen="forgot-password">
            <h1 class="title title--small">カギの作り直し</h1>
            <p class="lead lead--note" v-html="LEADS[step]"></p>

            <EmailForm v-if="step === 'email'" :form="emailForm" @submit="submitEmail" />
            <VerifyForm v-else-if="step === 'verify'" :form="codeForm" :email="emailForm.email" @submit="submitCode" />
            <PasswordForm v-else :form="passwordForm" @submit="submitPassword" />

            <div class="button-stack">
                <Link v-if="step === 'email'" class="btn btn--quiet" href="/login">前の画面に戻る</Link>
                <button v-else type="button" class="btn btn--quiet" @click="goTo('email')">メールを入れ直す</button>
            </div>
        </section>
        <AppDialog v-if="dialogMessage" :message="dialogMessage" @close="closeDialog" />
    </DefaultLayout>
</template>
