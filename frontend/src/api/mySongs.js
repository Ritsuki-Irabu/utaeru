import apiClient from './client'

// Laravel APIに「ログイン中ユーザーのマイリストを取得して」と依頼する
export const fetchMySongs = () => {
    return apiClient.get('/my-songs')
}
