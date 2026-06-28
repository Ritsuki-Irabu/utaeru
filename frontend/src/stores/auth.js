import { defineStore } from 'pinia'

// ログイン状態をアプリ全体で使うための認証ストア
export const useAuthStore = defineStore('auth', {
  state: () => ({
    // 再読み込み後もログイン状態を復元できるよう、localStorageから初期値を読む
    token: localStorage.getItem('token'),
    user: JSON.parse(localStorage.getItem('user')),
  }),

  getters: {
    // tokenがあれば「ログイン中」と判定する
    isLoggedIn: (state) => Boolean(state.token),
  },

  actions: {
    // ログイン成功時に、tokenとuserをストアとブラウザへ保存する
    setAuth({ token, user }) {
      this.token = token
      this.user = user

      localStorage.setItem('token', token)
      localStorage.setItem('user', JSON.stringify(user))
    },

    // ログアウト時に、認証情報をストアとブラウザから削除する
    logout() {
      this.token = null
      this.user = null

      localStorage.removeItem('token')
      localStorage.removeItem('user')
    },
  },
})
