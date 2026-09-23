<script setup>
/**
 * 会員登録。名前 → メールアドレス → 確認番号 → カギ の順に、この画面の中で切り替えて進む。
 * 各段階で送信し、サーバーで確かめてからダイアログを出して次へ進む。
 */
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import DefaultLayout from '@/Layouts/DefaultLayout.vue';
import AppDialog from '@/Components/AppDialog.vue';
import NameForm from '@/Components/auth/NameForm.vue';
import EmailForm from '@/Components/auth/EmailForm.vue';
import VerifyForm from '@/Components/auth/VerifyForm.vue';
import PasswordForm from '@/Components/auth/PasswordForm.vue';
import { firstError, useDialog } from '@/composables/useDialog';

const props = defineProps({
    initialStep: { type: String, default: 'name' },
    name: { type: String, default: '' },
    email: { type: String, default: '' },
});

const LEADS = {
    name: 'お名前を入力してください',
    email: '使っているメールアドレスを<br>入力してください',
    verify: 'メールで届いた番号を<br>入力してください',
    password: 'これから使うカギ（パスワード）を<br>作ってください',
};

const step = ref(props.initialStep);
const { message: dialogMessage, open: openDialog, close: closeDialog } = useDialog();

const nameForm = useForm({ name: props.name });
const emailForm = useForm({ email: props.email });
const sendForm = useForm({});
const codeForm = useForm({ code: '' });
const passwordForm = useForm({ password: '', password_confirmation: '' });

/** 送信して、問題があれば理由をダイアログで伝える */
function send(form, url, onSuccess = null) {
    form.post(url, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => onSuccess && onSuccess(),
        // 途中の内容が残っていない（flow）ときは、ダイアログを閉じたら名前の入力からやり直す
        onError: (errors) => openDialog(firstError(errors), errors.flow ? () => goTo('name') : null),
    });
}

function goTo(next) {
    [nameForm, emailForm, sendForm, codeForm, passwordForm].forEach((form) => form.clearErrors());
    step.value = next;
}

function submitName() {
    send(nameForm, '/register/name', () => openDialog(
        'ありがとうございます！これで名前の登録は完了です。次の行程へ進めます。',
        () => goTo('email'),
    ));
}

function submitEmail() {
    send(emailForm, '/register/email', () => openDialog(
        'ありがとうございます！それでは次に進みます。',
        sendCode,
    ));
}

/** 【分かりました！】を押したら、確認番号をメールで送り、番号の入力へ進む */
function sendCode() {
    send(sendForm, '/register/send-code', () => {
        codeForm.reset();
        goTo('verify');
    });
}

function submitCode() {
    send(codeForm, '/register/verify', () => openDialog(
        'ありがとうございます！これでメールアドレスの登録は完了です。それでは次に進めます。',
        () => {
            passwordForm.reset();
            goTo('password');
        },
    ));
}

/** 登録できたら、サーバーがトップ画面（ログイン後）へ進め、完了のダイアログを出す */
function submitPassword() {
    send(passwordForm, '/register/password');
}
</script>

<template>
    <DefaultLayout>
        <Head title="はじめての方の登録" />
        <section class="screen screen--form" :data-step="step" data-screen="register">
            <h1 class="title title--small">はじめての方の登録</h1>
            <p class="lead lead--note" v-html="LEADS[step]"></p>

            <NameForm v-if="step === 'name'" :form="nameForm" @submit="submitName" />
            <EmailForm v-else-if="step === 'email'" :form="emailForm" @submit="submitEmail" />
            <VerifyForm v-else-if="step === 'verify'" :form="codeForm" :email="emailForm.email" @submit="submitCode" />
            <PasswordForm v-else :form="passwordForm" @submit="submitPassword" />

            <div class="button-stack">
                <Link v-if="step === 'name'" class="btn btn--quiet" href="/">前の画面に戻る</Link>
                <button v-else-if="step === 'email'" type="button" class="btn btn--quiet" @click="goTo('name')">前の画面に戻る</button>
                <button v-else type="button" class="btn btn--quiet" @click="goTo('email')">メールを入れ直す</button>
            </div>
        </section>
        <AppDialog v-if="dialogMessage" :message="dialogMessage" @close="closeDialog" />
    </DefaultLayout>
</template>
