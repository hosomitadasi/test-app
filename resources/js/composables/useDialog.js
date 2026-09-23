import { ref } from 'vue';

/**
 * 画面の中で出すダイアログの状態。
 * open(文言, 閉じたあとの処理) で出し、【分かりました！】で閉じると続きの処理を行う。
 */
export function useDialog() {
    const message = ref(null);
    let afterClose = null;

    function open(text, callback = null) {
        message.value = text;
        afterClose = callback;
    }

    function close() {
        const callback = afterClose;
        message.value = null;
        afterClose = null;
        if (callback) {
            callback();
        }
    }

    return { message, open, close };
}

/** 入力エラーのうち、ダイアログに出す最初の1つ */
export function firstError(errors) {
    const messages = Object.values(errors || {});
    return messages.length > 0 ? messages[0] : null;
}
