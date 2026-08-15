import { createRouter, createWebHistory } from 'vue-router'
import MyListView from '../views/MyListView.vue'
import LoginView from '../views/LoginView.vue'
import SongSearchView from '../views/SongSearchView.vue'
import AdminSongsView from '../views/AdminSongsView.vue'
import PlaylistsView from '../views/PlaylistsView.vue'
import PlaylistDetailView from '../views/PlaylistDetailView.vue'
import SharedPlaylistView from '../views/SharedPlaylistView.vue'
import SongDetailView from '../views/SongDetailView.vue'
import { useAuthStore } from '../stores/auth'

const routes = [
    // Vue Routerでは、URLごとに表示するViewコンポーネントをここで対応させる
    {
        // / にアクセスしたら、ログイン中ユーザーのお気に入り画面を表示する
        path: '/',
        name: 'my-list',
        component: MyListView,
        meta: { requiresAuth: true },
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
        meta: { requiresAuth: true },
    },
    {
        // /admin/songs にアクセスしたら、管理者用の曲マスタ管理画面を表示する
        path: '/admin/songs',
        name: 'admin-songs',
        component: AdminSongsView,
        meta: { requiresAuth: true, admin: true },
    },
    {
        // 再生は曲詳細を開いたときだけ行う
        path: '/songs/:id',
        name: 'song-detail',
        component: SongDetailView,
        meta: { requiresAuth: true },
    },
    {
        path: '/playlists',
        name: 'playlists',
        component: PlaylistsView,
        meta: { requiresAuth: true },
    },
    {
        path: '/playlists/:id',
        name: 'playlist-detail',
        component: PlaylistDetailView,
        meta: { requiresAuth: true },
    },
    {
        path: '/shared/playlists/:token',
        name: 'shared-playlist',
        component: SharedPlaylistView,
        meta: { requiresAuth: true },
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach((to) => {
    const authStore = useAuthStore()

    // ログインが必要な画面は、tokenがなければログイン画面へ戻す
    if (to.meta.requiresAuth && !authStore.isLoggedIn) {
        return '/login'
    }

    // 管理者画面は、フロント側でもadminユーザーだけ通す
    if (to.meta.admin && authStore.user?.role !== 'admin') {
        return '/'
    }
})

export default router
