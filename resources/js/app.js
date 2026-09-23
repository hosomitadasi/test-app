import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';

createInertiaApp({
    title: (title) => title || 'スマホの練習君',
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        watchIdleLogout(props.initialPage.props.sessionLifetime);
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: {
        color: '#1565c0',
    },
});

/**
 * 自動ログアウトの確認。
 *
 * ログアウトそのものはサーバー側のセッションの期限（最後の操作から12時間）で行う。
 * ここでは、画面を開いたまま期限を過ぎてから戻ってきたときに、
 * ログインしているように見える画面が残らないよう読み込み直す。
 */
function watchIdleLogout(lifetimeMinutes) {
    const lifetimeMs = (lifetimeMinutes || 0) * 60 * 1000;
    if (lifetimeMs <= 0) {
        return;
    }

    let lastContact = Date.now();
    router.on('finish', () => {
        lastContact = Date.now();
    });
    window.axios.interceptors.response.use(
        (response) => {
            lastContact = Date.now();
            return response;
        },
        (error) => Promise.reject(error),
    );

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - lastContact > lifetimeMs) {
            window.location.reload();
        }
    });
}
