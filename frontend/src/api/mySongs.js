import apiClient from './client'

// マイリスト画面で表示する、ログイン中ユーザー本人の曲一覧を取得する
export const fetchMySongs = () => {
    return apiClient.get('/my-songs')
}

// 曲検索画面の「追加」ボタンから、指定した曲を自分のマイリストに追加する
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
