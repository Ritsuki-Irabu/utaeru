<script setup>
import { computed, onMounted, ref } from 'vue'
import { addMySong } from '../api/mySongs'
import { useSongsStore } from '../stores/songs'

const songsStore = useSongsStore()

const keyword = ref('')
const successMessage = ref('')
const errorMessage = ref('')
const addingSongId = ref(null)

// 画面表示時に、公開曲マスタ一覧を取得する
onMounted(() => {
    songsStore.fetchSongs()
})

// 入力されたキーワードに一致する曲だけを表示する
const filteredSongs = computed(() => {
    if (!keyword.value) {
        return songsStore.songs
    }

    return songsStore.songs.filter((song) => {
        const searchText = `${song.title} ${song.artist}`.toLowerCase()

        return searchText.includes(keyword.value.toLowerCase())
    })
})

// 追加ボタンからmy_songs登録APIを呼び、処理中の曲だけボタン表示を切り替える
const handleAdd = async (songId) => {
    successMessage.value = ''
    errorMessage.value = ''
    addingSongId.value = songId

    try {
        await addMySong(songId)

        successMessage.value = 'マイリストに追加しました。'
    } catch (error) {
        errorMessage.value = 'マイリストへの追加に失敗しました。'
    } finally {
        addingSongId.value = null
    }
}
</script>

<template>
    <main class="my-list">
        <header class="page-header">
            <p class="eyebrow">Songs</p>
            <h1>曲検索</h1>
            <p class="lead">公開曲マスタから曲を探して、マイリストに追加できます。</p>
        </header>

        <label class="search-field">
            曲名・アーティストで検索
            <input v-model="keyword" type="search" placeholder="Pretender">
        </label>

        <p v-if="successMessage" class="status-message">
            {{ successMessage }}
        </p>

        <p v-if="errorMessage" class="error-message">
            {{ errorMessage }}
        </p>

        <p v-if="songsStore.isLoading" class="status-message">
            読み込み中...
        </p>

        <p v-else-if="songsStore.errorMessage" class="error-message">
            {{ songsStore.errorMessage }}
        </p>

        <p v-else-if="filteredSongs.length === 0" class="status-message">
            該当する曲がありません。
        </p>

        <section v-else class="song-list">
            <article v-for="song in filteredSongs" :key="song.id" class="song-card">
                <div>
                    <h2>{{ song.title }}</h2>
                    <p class="artist">{{ song.artist }}</p>
                    <p class="bpm">BPM {{ song.bpm }}</p>
                </div>

                <button type="button" :disabled="addingSongId === song.id" @click="handleAdd(song.id)">
                    {{ addingSongId === song.id ? '追加中...' : '追加' }}
                </button>
            </article>
        </section>
    </main>
</template>
