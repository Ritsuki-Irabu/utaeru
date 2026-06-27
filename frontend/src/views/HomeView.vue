<script setup>
import { useAppStore } from '../stores/app'
import { useAuthStore } from '../stores/auth'

const appStore = useAppStore()
const authStore = useAuthStore()

const setupItems = [
  'Vue Router で画面遷移を準備',
  'Pinia で状態管理を準備',
  'Axios でLaravel API通信を準備',
  'PWA設定とアイコンを追加',
]
</script>

<template>
  <main class="home">
    <p class="eyebrow">Issue #9 Vue.js + PWA 環境構築</p>
    <h1>{{ appStore.appName }}</h1>
    <p class="lead">ログイン画面やマイリスト画面を作るための土台が動いています。</p>

    <section v-if="authStore.isLoggedIn" class="auth-panel">
      <p>ログイン中: {{ authStore.user?.name ?? 'ユーザー' }}</p>
      <button type="button" @click="authStore.logout()">ログアウト</button>
    </section>
    <section v-else class="auth-panel">
      <p>ログインすると、token と user が保存されます。</p>
      <RouterLink to="/login">ログイン画面へ</RouterLink>
    </section>

    <ul class="setup-list">
      <li v-for="item in setupItems" :key="item">
        {{ item }}
      </li>
    </ul>
  </main>
</template>
