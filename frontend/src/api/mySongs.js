import apiClient from './client'

// お気に入り画面で表示する、ログイン中ユーザー本人の追加曲一覧を取得する
export const fetchMySongs = () => {
    return apiClient.get('/my-songs')
}

// 曲検索画面のハートボタンから、指定した曲を自分のお気に入りに追加する。
// 既存登録の判定と解除は画面側で行い、このAPIは追加専用として扱う。
export const addMySong = (songId) => {
    return apiClient.post('/my-songs', {
        song_id: songId,
    })
}

export const updateMySong = (mySongId, payload) => {
    return apiClient.put(`/my-songs/${mySongId}`, payload)
}

export const deleteMySong = (mySongId) => {
    return apiClient.delete(`/my-songs/${mySongId}`)
}

export const exportMySongs = () => {
    return apiClient.get('/my-songs/export', { responseType: 'blob' })
}
