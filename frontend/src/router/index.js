import { createRouter, createWebHistory } from 'vue-router'
import MyListView from '../views/MyListView.vue'
import LoginView from '../views/LoginView.vue'
import SongSearchView from '../views/SongSearchView.vue'

const routes = [
    {
        // / にアクセスしたら、ログイン中ユーザーのマイリスト画面を表示する
        path: '/',
        name: 'my-list',
        component: MyListView,
    },
    {
        // /login にアクセスしたら LoginView を表示する
        path: '/login',
        name: 'login',
        component: LoginView,
    },
    {
        // /songs にアクセスしたら、公開曲マスタの検索・追加画面を表示する
        path: '/songs',
        name: 'song-search',
        component: SongSearchView,
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

export default router
