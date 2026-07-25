<script setup>
import { onMounted, ref } from 'vue'
import { createSong, deleteSong, updateSong } from '../api/songs'
import { useSongsStore } from '../stores/songs'

const songsStore = useSongsStore()

// 新規登録フォームの入力値をまとめて管理する
const form = ref({
    title: '',
    artist: '',
    bpm: '',
})

// 編集中の曲IDと、編集フォームの入力値を管理する
const editingSongId = ref(null)
const editingForm = ref({
    title: '',
    artist: '',
    bpm: '',
})

// 登録・更新・削除の結果や処理中状態を画面に出すための状態
const message = ref('')
const errorMessage = ref('')
const isSubmitting = ref(false)
const processingSongId = ref(null)

// 管理画面を開いたタイミングで、公開曲マスタ一覧を取得する
onMounted(() => {
    songsStore.fetchSongs()
})

const resetForm = () => {
    form.value = {
        title: '',
        artist: '',
        bpm: '',
    }
}

// 登録フォームの内容をAPIへ送り、成功したら一覧を取り直す
const handleCreate = async () => {
    message.value = ''
    errorMessage.value = ''
    isSubmitting.value = true

    try {
        await createSong({
            title: form.value.title,
            artist: form.value.artist,
            bpm: Number(form.value.bpm),
        })

        resetForm()
        message.value = '曲を登録しました。'
        await songsStore.fetchSongs()
    } catch (error) {
        errorMessage.value = '曲の登録に失敗しました。'
    } finally {
        isSubmitting.value = false
    }
}

// 一覧表示から編集フォームへ切り替え、既存の曲情報をフォームに入れる
const startEdit = (song) => {
    editingSongId.value = song.id
    editingForm.value = {
        title: song.title,
        artist: song.artist,
        bpm: song.bpm,
    }
}

// 編集状態を解除し、編集フォームを空に戻す
const cancelEdit = () => {
    editingSongId.value = null
    editingForm.value = {
        title: '',
        artist: '',
        bpm: '',
    }
}

// 編集フォームの内容をAPIへ送り、成功したら一覧を取り直す
const handleUpdate = async (songId) => {
    message.value = ''
    errorMessage.value = ''
    processingSongId.value = songId

    try {
        await updateSong(songId, {
            title: editingForm.value.title,
            artist: editingForm.value.artist,
            bpm: Number(editingForm.value.bpm),
        })

        cancelEdit()
        message.value = '曲を更新しました。'
        await songsStore.fetchSongs()
    } catch (error) {
        errorMessage.value = '曲の更新に失敗しました。'
    } finally {
        processingSongId.value = null
    }
}

// 確認ダイアログを出してから削除APIを呼び、成功したら一覧を取り直す
const handleDelete = async (songId) => {
    if (!confirm('この曲を削除しますか？')) {
        return
    }

    message.value = ''
    errorMessage.value = ''
    processingSongId.value = songId

    try {
        await deleteSong(songId)

        message.value = '曲を削除しました。'
        await songsStore.fetchSongs()
    } catch (error) {
        errorMessage.value = '曲の削除に失敗しました。'
    } finally {
        processingSongId.value = null
    }
}
</script>

<template>
    <main class="my-list">
        <header class="page-header">
            <p class="eyebrow">Admin</p>
            <h1>曲マスタ管理</h1>
            <p class="lead">公開曲マスタの登録・編集・削除を行います。</p>
        </header>

        <form class="admin-form" @submit.prevent="handleCreate">
            <label>
                曲名
                <input v-model="form.title" type="text" required>
            </label>

            <label>
                アーティスト
                <input v-model="form.artist" type="text" required>
            </label>

            <label>
                BPM
                <input v-model="form.bpm" type="number" min="1" max="300" required>
            </label>

            <button type="submit" :disabled="isSubmitting">
                {{ isSubmitting ? '登録中...' : '登録' }}
            </button>
        </form>

        <p v-if="message" class="status-message" aria-live="polite">
            {{ message }}
        </p>

        <p v-if="errorMessage" class="error-message" role="alert">
            {{ errorMessage }}
        </p>

        <p v-if="songsStore.isLoading" class="status-message">
            読み込み中...
        </p>

        <p v-else-if="songsStore.errorMessage" class="error-message">
            {{ songsStore.errorMessage }}
        </p>

        <p v-else-if="songsStore.songCount === 0" class="status-message">
            曲が登録されていません。
        </p>

        <section v-else class="song-list">
            <article v-for="song in songsStore.songs" :key="song.id" class="song-card">
                <form v-if="editingSongId === song.id" class="admin-edit-form" @submit.prevent="handleUpdate(song.id)">
                    <label>
                        曲名
                        <input v-model="editingForm.title" type="text" required>
                    </label>

                    <label>
                        アーティスト
                        <input v-model="editingForm.artist" type="text" required>
                    </label>

                    <label>
                        BPM
                        <input v-model="editingForm.bpm" type="number" min="1" max="300" required>
                    </label>

                    <div class="admin-actions">
                        <button type="submit" :disabled="processingSongId === song.id">
                            {{ processingSongId === song.id ? '更新中...' : '更新' }}
                        </button>
                        <button type="button" class="secondary-button" @click="cancelEdit">
                            キャンセル
                        </button>
                    </div>
                </form>

                <div v-else class="admin-song-row">
                    <div>
                        <h2>{{ song.title }}</h2>
                        <p class="artist">{{ song.artist }}</p>
                        <p class="bpm">BPM {{ song.bpm }}</p>
                    </div>

                    <div class="admin-actions">
                        <button type="button" @click="startEdit(song)">
                            編集
                        </button>
                        <button
                            type="button"
                            class="danger-button"
                            :disabled="processingSongId === song.id"
                            @click="handleDelete(song.id)"
                        >
                            {{ processingSongId === song.id ? '削除中...' : '削除' }}
                        </button>
                    </div>
                </div>
            </article>
        </section>
    </main>
</template>
