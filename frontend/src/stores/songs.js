import { defineStore } from 'pinia'
import { fetchSongs as fetchSongsApi } from '../api/songs'

export const useSongsStore = defineStore('songs', {
    state: () => ({
        songs: [],
        isLoading: false,
        errorMessage: '',
    }),

    getters: {
        // 画面側で「曲が0件かどうか」を判定しやすくするため、曲数を返す
        songCount: (state) => state.songs.length,
    },

    actions: {
        async fetchSongs() {
            this.isLoading = true
            this.errorMessage = ''

            try {
                // API層の関数を呼び出し、Laravelの公開曲マスタ一覧を取得する
                const response = await fetchSongsApi()

                // Laravel API Resourceの実データは data の中に入っている
                this.songs = response.data.data
            } catch (error) {
                this.errorMessage = '曲一覧の取得に失敗しました。'
            } finally {
                this.isLoading = false
            }
        },
    },
})
