import apiClient from './client'

// user画面・admin画面の両方で使う公開曲マスタの一覧を取得する
export const fetchSongs = () => {
    return apiClient.get('/songs')
}

// 管理者画面の登録フォームから送られた曲情報を保存する
export const createSong = (song) => {
    return apiClient.post('/songs', song)
}

// 管理者画面の編集フォームから送られた曲情報で更新する
export const updateSong = (songId, song) => {
    return apiClient.put(`/songs/${songId}`, song)
}

// 管理者画面で指定した曲を曲マスタから削除する
export const deleteSong = (songId) => {
    return apiClient.delete(`/songs/${songId}`)
}
