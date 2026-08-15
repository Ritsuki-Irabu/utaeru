<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { logout } from './api/auth'
import SettingsMenu from './components/SettingsMenu.vue'
import YoutubeMvPlaybackDock from './components/YoutubeMvPlaybackDock.vue'
import { useAuthStore } from './stores/auth'
import { useRhythmPlaybackStore } from './stores/rhythmPlayback'
import { useYoutubePlaybackStore } from './stores/youtubePlayback'

const authStore = useAuthStore()
const rhythmPlayback = useRhythmPlaybackStore()
const youtubePlayback = useYoutubePlaybackStore()
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
        rhythmPlayback.stop()
        youtubePlayback.$reset()
        authStore.logout()
        isLoggingOut.value = false
        await router.push('/login')
    }
}

const resetCategoryOrder = () => {
    if (typeof window !== 'undefined') {
        window.localStorage.removeItem('utaeru.song-detail-category-order')
        window.dispatchEvent(new CustomEvent('utaeru:reset-category-order'))
    }
}
</script>

<template>
  <header v-if="authStore.isLoggedIn" class="app-header">
    <nav class="app-nav" aria-label="メインメニュー">
      <RouterLink to="/" class="app-brand" title="ウタエル" aria-label="ウタエル">
        <svg class="brand-icon" viewBox="0 0 32 32" role="img" aria-hidden="true">
          <path d="M12 6v13.5a5.5 5.5 0 1 1-2-4.23V6.8l13-3.1v12.8a5.5 5.5 0 1 1-2-4.23V5.95L12 8.1V6Z" fill="currentColor" />
          <path d="M25 7.5h2v2h-2zM25 11.5h2v2h-2z" fill="currentColor" />
        </svg>
        <span class="sr-only">ウタエル</span>
      </RouterLink>
      <div class="app-links">
        <RouterLink to="/songs">曲を探す</RouterLink>
        <RouterLink to="/">お気に入り</RouterLink>
        <RouterLink to="/playlists">プレイリスト</RouterLink>
        <RouterLink v-if="authStore.user?.role === 'admin'" to="/admin/songs">
          管理画面
        </RouterLink>
      </div>
      <SettingsMenu
        :is-logging-out="isLoggingOut"
        @logout="handleLogout"
        @reset-category-order="resetCategoryOrder"
      />
    </nav>
  </header>

  <Transition name="slide" mode="out-in">
    <RouterView v-slot="{ Component }">
      <KeepAlive exclude="LoginView">
        <component :is="Component" :key="route.fullPath" />
      </KeepAlive>
    </RouterView>
  </Transition>

  <!-- MVのiframeを画面遷移で失わないためのミニプレーヤー移動先 -->
  <div id="youtube-mv-mini-player-host"></div>
  <YoutubeMvPlaybackDock v-if="authStore.isLoggedIn" />
</template>
