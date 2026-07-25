<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { logout } from './api/auth'
import { useAuthStore } from './stores/auth'

const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()
const isLoggingOut = ref(false)

const handleLogout = async () => {
    isLoggingOut.value = true

    try {
        await logout()
    } catch {
        // トークン切れでもローカルの認証情報は確実に破棄する
    } finally {
        authStore.logout()
        isLoggingOut.value = false
        await router.push('/login')
    }
}
</script>

<template>
  <header v-if="authStore.isLoggedIn" class="app-header">
    <nav class="app-nav" aria-label="メインメニュー">
      <RouterLink to="/" class="app-brand">ウタエル</RouterLink>
      <div class="app-links">
        <RouterLink to="/">マイリスト</RouterLink>
        <RouterLink to="/songs">曲を探す</RouterLink>
        <RouterLink v-if="authStore.user?.role === 'admin'" to="/admin/songs">
          管理画面
        </RouterLink>
      </div>
      <button type="button" class="logout-button" :disabled="isLoggingOut" @click="handleLogout">
        {{ isLoggingOut ? 'ログアウト中...' : 'ログアウト' }}
      </button>
    </nav>
  </header>

  <RouterView :key="route.fullPath" />
</template>
