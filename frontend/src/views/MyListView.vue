<script setup>
import { onMounted, ref } from 'vue'
import { deleteMySong, exportMySongs, updateMySong } from '../api/mySongs'
import { fetchTags } from '../api/tags'
import RhythmPlayer from '../components/RhythmPlayer.vue'
import { useMySongsStore } from '../stores/mySongs'

const mySongsStore = useMySongsStore()
const tags = ref([])
const editingSongId = ref(null)
const editingMemo = ref('')
const editingTagIds = ref([])
const processingSongId = ref(null)
const message = ref('')
const errorMessage = ref('')
const isExporting = ref(false)

// 画面が表示されたタイミングで、Laravel APIからマイリストを取得する
onMounted(async () => {
    await Promise.all([
        mySongsStore.fetchMySongs(),
        loadTags(),
    ])
})

const loadTags = async () => {
    try {
        const response = await fetchTags()
        tags.value = response.data.data
    } catch {
        // タグ一覧が取得できなくても、既存の曲表示とメモ編集は継続できる
        tags.value = []
    }
}

const startEdit = (mySong) => {
    message.value = ''
    errorMessage.value = ''
    editingSongId.value = mySong.id
    editingMemo.value = mySong.memo ?? ''
    editingTagIds.value = mySong.tags.map((tag) => tag.id)
}

const cancelEdit = () => {
    editingSongId.value = null
    editingMemo.value = ''
    editingTagIds.value = []
}

const handleUpdate = async (mySongId) => {
    message.value = ''
    errorMessage.value = ''
    processingSongId.value = mySongId

    try {
        await updateMySong(mySongId, {
            memo: editingMemo.value,
            tag_ids: editingTagIds.value,
        })
        cancelEdit()
        message.value = 'マイリストを更新しました。'
        await mySongsStore.fetchMySongs()
    } catch {
        errorMessage.value = 'マイリストの更新に失敗しました。'
    } finally {
        processingSongId.value = null
    }
}

const handleDelete = async (mySongId) => {
    if (!window.confirm('この曲をマイリストから削除しますか？')) {
        return
    }

    message.value = ''
    errorMessage.value = ''
    processingSongId.value = mySongId

    try {
        await deleteMySong(mySongId)
        message.value = 'マイリストから削除しました。'
        await mySongsStore.fetchMySongs()
    } catch {
        errorMessage.value = 'マイリストからの削除に失敗しました。'
    } finally {
        processingSongId.value = null
    }
}

const handleExport = async () => {
    isExporting.value = true
    errorMessage.value = ''

    try {
        const response = await exportMySongs()
        const blobUrl = URL.createObjectURL(response.data)
        const link = document.createElement('a')
        link.href = blobUrl
        link.download = 'my-songs.csv'
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.setTimeout(() => URL.revokeObjectURL(blobUrl), 0)
    } catch {
        errorMessage.value = 'CSVの出力に失敗しました。'
    } finally {
        isExporting.value = false
    }
}
</script>

<template>
    <main class="my-list">
        <header class="page-header">
            <p class="eyebrow">My Songs</p>
            <h1>マイリスト</h1>
            <p class="lead">登録した曲とメモ・タグを確認できます。</p>
            <div class="page-actions">
                <button type="button" :disabled="isExporting" @click="handleExport">
                    {{ isExporting ? '出力中...' : 'CSVを出力' }}
                </button>
            </div>
        </header>

        <p v-if="message" class="status-message" aria-live="polite">{{ message }}</p>

        <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

        <!-- 読み込み中・エラー・空・一覧ありの順番で表示を切り替える -->
        <p v-if="mySongsStore.isLoading" class="status-message">読み込み中...</p>

        <p v-else-if="mySongsStore.errorMessage" class="error-message">
            {{ mySongsStore.errorMessage }}
        </p>

        <div v-else-if="mySongsStore.songCount === 0" class="status-empty">
            <p>マイリストがありません。</p>
            <RouterLink class="primary-link" to="/songs">曲を探す</RouterLink>
        </div>

        <section v-else class="song-list">
            <article v-for="mySong in mySongsStore.songs" :key="mySong.id" class="song-card">
                <div>
                    <!-- APIレスポンスの song オブジェクトから曲情報を表示する -->
                    <h2>{{ mySong.song.title }}</h2>
                    <p class="artist">{{ mySong.song.artist }}</p>
                    <p class="bpm">BPM {{ mySong.song.bpm }}</p>
                </div>

                <p v-if="mySong.memo && editingSongId !== mySong.id" class="memo">
                    メモ: {{ mySong.memo }}
                </p>

                <ul v-if="mySong.tags.length && editingSongId !== mySong.id" class="tag-list">
                    <!-- tags は配列なので、v-for で1件ずつ表示する -->
                    <li v-for="tag in mySong.tags" :key="tag.id">
                        {{ tag.name }}
                    </li>
                </ul>

                <form v-if="editingSongId === mySong.id" class="my-song-edit-form" @submit.prevent="handleUpdate(mySong.id)">
                    <label>
                        メモ
                        <textarea v-model="editingMemo" rows="3" maxlength="1000"></textarea>
                    </label>

                    <fieldset v-if="tags.length" class="tag-fieldset">
                        <legend>タグ</legend>
                        <label v-for="tag in tags" :key="tag.id" class="tag-option">
                            <input v-model="editingTagIds" type="checkbox" :value="tag.id">
                            {{ tag.name }}
                        </label>
                    </fieldset>

                    <div class="song-card-actions">
                        <button type="submit" :disabled="processingSongId === mySong.id">
                            {{ processingSongId === mySong.id ? '更新中...' : '更新' }}
                        </button>
                        <button type="button" class="secondary-button" @click="cancelEdit">
                            キャンセル
                        </button>
                    </div>
                </form>

                <!-- songオブジェクトを渡して、BPMに合わせた視覚リズムを表示する -->
                <RhythmPlayer :song="mySong.song" />

                <div v-if="editingSongId !== mySong.id" class="song-card-actions">
                    <button type="button" @click="startEdit(mySong)">編集</button>
                    <button
                        type="button"
                        class="danger-button"
                        :disabled="processingSongId === mySong.id"
                        @click="handleDelete(mySong.id)"
                    >
                        {{ processingSongId === mySong.id ? '削除中...' : '削除' }}
                    </button>
                </div>
            </article>
        </section>
    </main>
</template>
