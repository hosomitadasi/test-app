import { ref } from 'vue';

/**
 * 練習をやり終えたときに結果を記録し、結果画面に出す声かけの文言を受け取る。
 */
export function usePracticeRecord(practiceType, level) {
    const message = ref('');
    const newTitles = ref([]);
    let sequence = 0;

    async function save(score) {
        // 「もう一度」ですぐ次をやり終えたときに、前の返事で上書きしないようにする
        const current = ++sequence;
        message.value = '';
        newTitles.value = [];

        try {
            const { data } = await window.axios.post('/practice-records', {
                practice_type: practiceType,
                level,
                score,
            });
            if (current !== sequence) {
                return;
            }
            message.value = data.message;
            newTitles.value = data.new_titles || [];
        } catch (error) {
            if (current !== sequence) {
                return;
            }
            const status = error.response ? error.response.status : 0;
            message.value = status === 401 || status === 419
                ? 'しばらく使われていなかったため、今回の記録は残せませんでした。'
                : '通信がうまくいかず、今回の記録は残せませんでした。';
        }
    }

    return { message, newTitles, save };
}
