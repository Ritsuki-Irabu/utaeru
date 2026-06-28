import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
// ログイン画面ファイルを読み込む
import LoginView from '../views/LoginView.vue'

const routes = [
    {
        path: '/',
        name: 'home',
        component: HomeView,
    },
    {
        // /login にアクセスしたら LoginView を表示する
        path: '/login',
        name: 'login',
        component: LoginView,
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

export default router
