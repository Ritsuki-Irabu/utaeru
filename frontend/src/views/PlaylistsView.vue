<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { createPlaylist } from '../api/playlists'
import { usePlaylistsStore } from '../stores/playlists'

const playlistsStore = usePlaylistsStore()
const router = useRouter()
const isCreating = ref(false)
const form = ref({ name: '', description: '' })
const errorMessage = ref('')
const isSubmitting = ref(false)

onMounted(() => {
    playlistsStore.fetchPlaylists()
})

const openCreateForm = () => {
    errorMessage.value = ''
    isCreating.value = true
}

const closeCreateForm = () => {
    errorMessage.value = ''
    isCreating.value = false
    form.value = { name: '', description: '' }
}

const handleCreate = async () => {
    errorMessage.value = ''
    isSubmitting.value = true

    try {
        const response = await createPlaylist(form.value)
        const createdPlaylist = response.data.data ?? response.data
        await router.push(`/playlists/${createdPlaylist.id}`)
    } catch {
        errorMessage.value = 'プレイリストの作成に失敗しました。'
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <main class="my-list playlists-page">
        <header class="page-header">
            <p class="eyebrow">Playlists</p>
            <div class="page-title-row">
                <div>
                    <h1>プレイリスト</h1>
                    <p class="lead">お気に入りの曲を、用途ごとのグループに整理できます。</p>
                </div>
                <button type="button" @click="isCreating ? closeCreateForm() : openCreateForm()">
                    {{ isCreating ? '作成をキャンセル' : '新規プレイリスト' }}
                </button>
            </div>
        </header>

        <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

        <form v-if="isCreating" class="playlist-form playlist-create-form" @submit.prevent="handleCreate">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">New Playlist</p>
                    <h2>プレイリストを作成</h2>
                </div>
                <button type="button" class="secondary-button" @click="closeCreateForm">閉じる</button>
            </div>
            <label>
                プレイリスト名
                <input v-model="form.name" type="text" maxlength="100" required>
            </label>
            <label>
                説明（任意）
                <textarea v-model="form.description" rows="3" maxlength="1000"></textarea>
            </label>
            <button type="submit" :disabled="isSubmitting">
                {{ isSubmitting ? '作成中...' : 'プレイリストを作成' }}
            </button>
        </form>

        <p v-if="playlistsStore.isLoading" class="status-message">読み込み中...</p>
        <p v-else-if="playlistsStore.errorMessage" class="error-message" role="alert">
            {{ playlistsStore.errorMessage }}
        </p>
        <section v-else class="playlist-list-section" aria-labelledby="playlist-list-heading">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Your Library</p>
                    <h2 id="playlist-list-heading">プレイリスト一覧</h2>
                </div>
                <span class="playlist-count">{{ playlistsStore.playlists.length }}件</span>
            </div>

            <div v-if="playlistsStore.playlists.length" class="playlist-list">
                <RouterLink
                    v-for="playlist in playlistsStore.playlists"
                    :key="playlist.id"
                    class="playlist-card playlist-card-link"
                    :to="`/playlists/${playlist.id}`"
                >
                    <div class="playlist-card-content">
                        <p class="playlist-visibility">{{ playlist.is_shared ? '共有中' : '非公開' }}</p>
                        <h3>{{ playlist.name }}</h3>
                        <p v-if="playlist.description" class="artist">{{ playlist.description }}</p>
                        <p class="playlist-meta">{{ playlist.song_count ?? 0 }}曲</p>
                    </div>
                    <span class="playlist-card-arrow" aria-hidden="true">→</span>
                    <span class="sr-only">{{ playlist.name }}を開く</span>
                </RouterLink>
            </div>

            <div v-else class="status-empty">
                <p>まだプレイリストがありません。</p>
                <button type="button" @click="openCreateForm">最初のプレイリストを作成</button>
            </div>
        </section>
    </main>
</template>
