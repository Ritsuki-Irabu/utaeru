// 共通Axios設定を読み込む
import apiClient from './client'

// ログインフォームの入力値をLaravelのログインAPIへ送る
export const login = (credentials) => {
    return apiClient.post('/auth/login', credentials)
}

export const logout = () => {
    return apiClient.post('/auth/logout')
}
