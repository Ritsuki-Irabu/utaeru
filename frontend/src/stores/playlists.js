import { defineStore } from 'pinia'
import { createPlaylist as createPlaylistApi, fetchPlaylists as fetchPlaylistsApi } from '../api/playlists'

export const usePlaylistsStore = defineStore('playlists', {
    state: () => ({
        playlists: [],
        isLoading: false,
        errorMessage: '',
    }),

    actions: {
        async fetchPlaylists() {
            this.isLoading = true
            this.errorMessage = ''

            try {
                const response = await fetchPlaylistsApi()
                this.playlists = response.data.data
            } catch {
                this.errorMessage = 'プレイリスト一覧の取得に失敗しました。'
            } finally {
                this.isLoading = false
            }
        },

        async createPlaylist(payload) {
            const response = await createPlaylistApi(payload)
            const createdPlaylist = response.data.data ?? response.data

            this.playlists = [createdPlaylist, ...this.playlists]

            return createdPlaylist
        },
    },
})
