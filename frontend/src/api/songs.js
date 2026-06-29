import apiClient from './client'

// 公開曲マスタの一覧を取得する
export const fetchSongs = () => {
    return apiClient.get('/songs')
}