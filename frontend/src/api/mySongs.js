import apiClient from './client'

// ログイン中ユーザーのマイリストを取得する
export const fetchMySongs = () => {
    return apiClient.get('/my-songs')
}

// 指定した曲をログイン中ユーザーのマイリストに追加する
export const addMySong = (songId) => {
    return apiClient.post('/my-songs', {
        song_id: songId,
    })
}
