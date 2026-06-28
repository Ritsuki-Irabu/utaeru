import { defineStore } from 'pinia'
import { fetchMySongs as fetchMySongsApi } from '../api/mySongs'

export const useMySongsStore = defineStore('mySongs', {
    state: () => ({
        // APIから取得したマイリストを保存する
        songs: [],
        // 取得中かどうかを画面で判定するための状態
        isLoading: false,
        // API取得に失敗したときに画面へ表示するメッセージ
        errorMessage: '',
    }),

    getters: {
        // 登録曲数を画面で使いやすい形にする
        songCount: (state) => state.songs.length,
    },

    actions: {
        async fetchMySongs() {
            // 取得開始：画面に「読み込み中」を出せるようにする
            this.isLoading = true
            this.errorMessage = ''

            try {
                // API関数を呼び、Laravelからマイリストを取得する
                const response = await fetchMySongsApi()
                // LaravelのResourceレスポンスは data の中に一覧が入る
                this.songs = response.data.data
            } catch {
                // 失敗した場合は、画面表示用のエラーメッセージを保存する
                this.errorMessage = 'マイリストの取得に失敗しました。'
            } finally {
                // 成功・失敗どちらでも取得処理は終了する
                this.isLoading = false
            }
        },
    },
})
