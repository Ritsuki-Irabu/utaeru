<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
    addPlaylistSong,
    deletePlaylist,
    deletePlaylistSong,
    fetchPlaylist,
    revokePlaylistShare,
    sharePlaylist,
    updatePlaylist,
    updatePlaylistSong,
} from '../api/playlists'
import { useSongsStore } from '../stores/songs'
import PlaylistQueuePlayer from '../components/PlaylistQueuePlayer.vue'

const route = useRoute()
const router = useRouter()
const songsStore = useSongsStore()
const playlist = ref(null)
const form = ref({ name: '', description: '' })
const selectedSongId = ref('')
const songSearch = ref('')
const shareUrl = ref('')
const message = ref('')
const errorMessage = ref('')
const isLoading = ref(true)
const isSaving = ref(false)
const isManagementOpen = ref(false)
const processingSongId = ref(null)

const playlistId = computed(() => Number(route.params.id))
const songs = computed(() => playlist.value?.songs ?? [])
const availableSongs = computed(() => {
    const existingIds = new Set(songs.value.map((playlistSong) => playlistSong.song?.id))
    const keyword = songSearch.value.trim().toLowerCase()

    return songsStore.songs.filter((song) => {
        if (existingIds.has(song.id)) {
            return false
        }

        return !keyword || `${song.title} ${song.artist}`.toLowerCase().includes(keyword)
    })
})

const loadPlaylist = async () => {
    isLoading.value = true
    errorMessage.value = ''

    try {
        const response = await fetchPlaylist(playlistId.value)
        playlist.value = response.data.data ?? response.data
        form.value = {
            name: playlist.value.name,
            description: playlist.value.description ?? '',
        }
    } catch {
        errorMessage.value = 'プレイリストの取得に失敗しました。'
    } finally {
        isLoading.value = false
    }
}

onMounted(loadPlaylist)

const openManagement = async () => {
    isManagementOpen.value = true

    if (songsStore.songs.length === 0) {
        await songsStore.fetchSongs()
    }
}

const closeManagement = () => {
    isManagementOpen.value = false
    songSearch.value = ''
    selectedSongId.value = ''
}

const handleUpdate = async () => {
    message.value = ''
    errorMessage.value = ''
    isSaving.value = true

    try {
        await updatePlaylist(playlistId.value, form.value)
        message.value = 'プレイリストを更新しました。'
        await loadPlaylist()
    } catch {
        errorMessage.value = 'プレイリストの更新に失敗しました。'
    } finally {
        isSaving.value = false
    }
}

const handleDelete = async () => {
    if (!window.confirm('このプレイリストを削除しますか？')) {
        return
    }

    try {
        await deletePlaylist(playlistId.value)
        await router.push('/playlists')
    } catch {
        errorMessage.value = 'プレイリストの削除に失敗しました。'
    }
}

const handleAddSong = async () => {
    if (!selectedSongId.value) {
        return
    }

    message.value = ''
    errorMessage.value = ''

    try {
        await addPlaylistSong(playlistId.value, {
            song_id: Number(selectedSongId.value),
            position: songs.value.length + 1,
        })
        selectedSongId.value = ''
        message.value = '曲を追加しました。'
        await loadPlaylist()
    } catch {
        errorMessage.value = '曲の追加に失敗しました。'
    }
}

const handleUpdateSong = async (playlistSong) => {
    processingSongId.value = playlistSong.id
    errorMessage.value = ''

    try {
        await updatePlaylistSong(playlistId.value, playlistSong.id, {
            position: Number(playlistSong.position),
            memo: playlistSong.memo ?? '',
        })
        message.value = '曲順・メモを更新しました。'
        await loadPlaylist()
    } catch {
        errorMessage.value = '曲順・メモの更新に失敗しました。'
    } finally {
        processingSongId.value = null
    }
}

const handleDeleteSong = async (playlistSong) => {
    processingSongId.value = playlistSong.id
    errorMessage.value = ''

    try {
        await deletePlaylistSong(playlistId.value, playlistSong.id)
        message.value = '曲を削除しました。'
        await loadPlaylist()
    } catch {
        errorMessage.value = '曲の削除に失敗しました。'
    } finally {
        processingSongId.value = null
    }
}

const handleShare = async () => {
    errorMessage.value = ''

    try {
        const response = await sharePlaylist(playlistId.value)
        shareUrl.value = response.data.share_url
        message.value = '共有リンクを発行しました。'
        await loadPlaylist()
    } catch {
        errorMessage.value = '共有リンクの発行に失敗しました。'
    }
}

const handleRevokeShare = async () => {
    errorMessage.value = ''

    try {
        await revokePlaylistShare(playlistId.value)
        shareUrl.value = ''
        message.value = '共有リンクを無効にしました。'
        await loadPlaylist()
    } catch {
        errorMessage.value = '共有リンクの無効化に失敗しました。'
    }
}

const copyShareUrl = async () => {
    if (!shareUrl.value) {
        return
    }

    await navigator.clipboard.writeText(shareUrl.value)
    message.value = '共有リンクをコピーしました。'
}

</script>

<template>
    <main class="my-list playlist-detail-page">
        <p><RouterLink class="back-link" to="/playlists">← プレイリスト一覧</RouterLink></p>
        <p v-if="isLoading" class="status-message">読み込み中...</p>
        <p v-else-if="errorMessage && !playlist" class="error-message" role="alert">{{ errorMessage }}</p>

        <template v-else-if="playlist">
            <header class="page-header">
                <div class="page-title-row">
                    <div>
                        <p class="eyebrow">Playlist</p>
                        <h1>{{ playlist.name }}</h1>
                    </div>
                    <button type="button" @click="isManagementOpen ? closeManagement() : openManagement()">
                        {{ isManagementOpen ? '管理を閉じる' : 'プレイリストを管理' }}
                    </button>
                </div>
                <p class="lead">{{ playlist.description || '説明はありません。' }}</p>
                <div class="playlist-summary">
                    <span>{{ songs.length }}曲</span>
                    <span>{{ playlist.is_shared ? '共有中' : '非公開' }}</span>
                </div>
            </header>

            <p v-if="message" class="status-message" aria-live="polite">{{ message }}</p>
            <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

            <section class="playlist-track-section" aria-labelledby="playlist-track-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Tracks</p>
                        <h2 id="playlist-track-heading">曲一覧</h2>
                    </div>
                    <p class="playlist-meta">曲を選択して詳細を表示</p>
                </div>
                <PlaylistQueuePlayer :tracks="songs" />
                <p v-if="songs.length === 0" class="status-empty">
                    プレイリストに曲がありません。管理画面から曲を追加できます。
                </p>
                <ol v-else class="playlist-track-list">
                    <li v-for="(playlistSong, index) in songs" :key="playlistSong.id" class="playlist-track-item">
                        <RouterLink
                            class="playlist-track-link"
                            :to="{
                                name: 'song-detail',
                                params: { id: playlistSong.song.id },
                                query: { from: 'playlist', playlistId: playlist.id },
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

            <section v-if="isManagementOpen" class="playlist-management" aria-labelledby="playlist-management-heading">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Manage</p>
                        <h2 id="playlist-management-heading">プレイリストを管理</h2>
                    </div>
                    <button type="button" class="secondary-button" @click="closeManagement">閉じる</button>
                </div>

                <form class="playlist-form" @submit.prevent="handleUpdate">
                    <h3>基本情報</h3>
                    <label>
                        プレイリスト名
                        <input v-model="form.name" type="text" maxlength="100" required>
                    </label>
                    <label>
                        説明
                        <textarea v-model="form.description" rows="3" maxlength="1000"></textarea>
                    </label>
                    <div class="page-actions">
                        <button type="submit" :disabled="isSaving">{{ isSaving ? '保存中...' : '変更を保存' }}</button>
                        <button type="button" class="danger-button" @click="handleDelete">プレイリストを削除</button>
                    </div>
                </form>

                <section class="playlist-add-song">
                    <h3>曲を追加</h3>
                    <label>
                        曲を検索
                        <input v-model="songSearch" type="search" placeholder="曲名・アーティスト">
                    </label>
                    <div class="playlist-add-row">
                        <select v-model="selectedSongId" aria-label="追加する曲">
                            <option value="">曲を選択してください</option>
                            <option v-for="song in availableSongs" :key="song.id" :value="song.id">
                                {{ song.title }} / {{ song.artist }}
                            </option>
                        </select>
                        <button type="button" :disabled="!selectedSongId" @click="handleAddSong">追加</button>
                    </div>
                    <p v-if="songsStore.isLoading" class="artist">曲を読み込み中...</p>
                    <p v-else-if="songsStore.errorMessage" class="error-message" role="alert">{{ songsStore.errorMessage }}</p>
                    <p v-else-if="availableSongs.length === 0" class="artist">追加できる曲がありません。</p>
                </section>

                <section class="playlist-song-management">
                    <h3>曲順・メモ・削除</h3>
                    <p v-if="songs.length === 0" class="artist">編集できる曲がありません。</p>
                    <details v-for="playlistSong in songs" :key="playlistSong.id" class="playlist-song-editor">
                        <summary>{{ playlistSong.position }}. {{ playlistSong.song.title }}</summary>
                        <form class="playlist-song-form" @submit.prevent="handleUpdateSong(playlistSong)">
                            <label>
                                曲順
                                <input v-model="playlistSong.position" type="number" min="1" required>
                            </label>
                            <label>
                                メモ
                                <textarea v-model="playlistSong.memo" rows="2" maxlength="1000"></textarea>
                            </label>
                            <div class="page-actions">
                                <button type="submit" :disabled="processingSongId === playlistSong.id">
                                    {{ processingSongId === playlistSong.id ? '保存中...' : '保存' }}
                                </button>
                                <button type="button" class="danger-button" :disabled="processingSongId === playlistSong.id" @click="handleDeleteSong(playlistSong)">
                                    削除
                                </button>
                            </div>
                        </form>
                    </details>
                </section>

                <section class="playlist-share-panel">
                    <h3>共有設定</h3>
                    <p>共有リンクを知っているログイン済みユーザーだけが閲覧できます。</p>
                    <div class="page-actions">
                        <button type="button" @click="handleShare">共有リンクを発行・更新</button>
                        <button v-if="playlist.is_shared" type="button" class="secondary-button" @click="handleRevokeShare">
                            共有を停止
                        </button>
                    </div>
                    <div v-if="shareUrl" class="share-url-row">
                        <input :value="shareUrl" type="text" readonly aria-label="共有リンク">
                        <button type="button" class="secondary-button" @click="copyShareUrl">コピー</button>
                    </div>
                </section>
            </section>
        </template>
    </main>
</template>
