import axios from 'axios'

// Laravel APIへ通信するための共通Axiosインスタンス
const apiClient = axios.create({
    baseURL: import.meta.env.VITE_API_URL,
    headers: {
        Accept: 'application/json',
    },
})

// API通信の直前に、保存済みtokenがあれば認証ヘッダーへ付ける
apiClient.interceptors.request.use((config) => {
    const token = localStorage.getItem('token')

    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }

    return config
})

export default apiClient
