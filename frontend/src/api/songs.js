import apiClient from './client'

// 公開曲マスタの一覧を取得する
export const fetchSongs = () => {
    return apiClient.get('/songs')
}

// 管理者が曲を新規登録する
export const createSong = (song) => {
    return apiClient.post('/songs', song)
}

// 管理者が既存曲を更新する
export const updateSong = (songId, song) => {
    return apiClient.put(`/songs/${songId}`, song)
}

// 管理者が曲を削除する
export const deleteSong = (songId) => {
    return apiClient.delete(`/songs/${songId}`)
}
