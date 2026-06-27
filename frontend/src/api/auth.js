// 共通Axios設定を読み込む
import apiClient from './client'

export const login = (credentials) => {
    return apiClient.post('/auth/login', credentials)
}