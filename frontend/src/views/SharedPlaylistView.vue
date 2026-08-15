<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { copySharedPlaylist, fetchSharedPlaylist } from '../api/playlists'

const route = useRoute()
const router = useRouter()
const playlist = ref(null)
const errorMessage = ref('')
const message = ref('')
const isLoading = ref(true)
const isCopying = ref(false)

const loadPlaylist = async () => {
    try {
        const response = await fetchSharedPlaylist(route.params.token)
        playlist.value = response.data.data ?? response.data
    } catch {
        errorMessage.value = '共有リンクが無効か、閲覧できません。'
    } finally {
        isLoading.value = false
    }
}

onMounted(loadPlaylist)

const handleCopy = async () => {
    isCopying.value = true
    errorMessage.value = ''

    try {
        const response = await copySharedPlaylist(route.params.token)
        message.value = 'プレイリストをコピーしました。'
        const copiedPlaylist = response.data.data ?? response.data
        await router.push(`/playlists/${copiedPlaylist.id}`)
    } catch {
        errorMessage.value = 'プレイリストのコピーに失敗しました。'
    } finally {
        isCopying.value = false
    }
}
</script>

<template>
    <main class="my-list">
        <p><RouterLink class="back-link" to="/playlists">← プレイリスト一覧</RouterLink></p>
        <p v-if="isLoading" class="status-message">読み込み中...</p>
        <p v-else-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

        <template v-else-if="playlist">
            <header class="page-header">
                <p class="eyebrow">Shared Playlist</p>
                <h1>{{ playlist.name }}</h1>
                <p class="lead">{{ playlist.description || '説明はありません。' }}</p>
                <div class="page-actions">
                    <button type="button" :disabled="isCopying" @click="handleCopy">
                        {{ isCopying ? 'コピー中...' : '自分のプレイリストへコピー' }}
                    </button>
                </div>
            </header>

            <p v-if="message" class="status-message" aria-live="polite">{{ message }}</p>
            <section class="playlist-track-section" aria-labelledby="shared-track-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Tracks</p>
                        <h2 id="shared-track-heading">曲一覧</h2>
                    </div>
                    <p class="playlist-meta">曲を選択して詳細を表示</p>
                </div>
                <p v-if="playlist.songs.length === 0" class="status-empty">このプレイリストに曲はありません。</p>
                <ol v-else class="playlist-track-list">
                    <li v-for="playlistSong in playlist.songs" :key="playlistSong.id" class="playlist-track-item">
                        <RouterLink
                            class="playlist-track-link"
                            :to="{
                                name: 'song-detail',
                                params: { id: playlistSong.song.id },
                                query: { from: 'shared', token: route.params.token },
                            }"
                        >
                            <span class="playlist-position" aria-hidden="true">{{ playlistSong.position }}</span>
                            <span class="playlist-track-copy">
                                <strong>{{ playlistSong.song.title }}</strong>
                                <small>{{ playlistSong.song.artist }}</small>
                                <small class="playlist-opening-line">
                                    <span>歌いだし</span>
                                    {{ playlistSong.song.opening_line || '未登録' }}
                                </small>
                            </span>
                        </RouterLink>
                    </li>
                </ol>
            </section>
        </template>
    </main>
</template>
