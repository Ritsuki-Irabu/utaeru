import axios from 'axios'

const configuredApiUrl = (import.meta.env.VITE_API_URL || 'http://localhost/api').replace(/\/$/, '')

// ViteをLAN公開したスマホからも、localhostをスマホ自身として解決しないようにする。
// E2Eの127.0.0.1/localhost接続は従来どおり固定URLを使う。
const apiBaseUrl = typeof window !== 'undefined'
    && window.location.hostname !== 'localhost'
    && window.location.hostname !== '127.0.0.1'
    && /^https?:\/\/localhost(?::\d+)?/i.test(configuredApiUrl)
    ? `${window.location.protocol}//${window.location.hostname}/api`
    : configuredApiUrl

// Laravel APIへ通信するための共通Axiosインスタンス
const apiClient = axios.create({
    // .env未作成の初回起動でも、同一ホストのLaravel APIへ接続できる既定値を持たせる。
    baseURL: apiBaseUrl,
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
