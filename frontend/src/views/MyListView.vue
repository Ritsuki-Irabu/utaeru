<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { deleteMySong, updateMySong } from '../api/mySongs'
import { createTag, fetchTags } from '../api/tags'
import { fetchSongLyrics } from '../api/songs'
import { useMySongsStore } from '../stores/mySongs'

const mySongsStore = useMySongsStore()
const router = useRouter()
const tags = ref([])
const editingSongId = ref(null)
const editingMemo = ref('')
const editingTagIds = ref([])
const newTagName = ref('')
const isCreatingTag = ref(false)
const processingSongId = ref(null)
const message = ref('')
const errorMessage = ref('')
const searchQuery = ref('')

const openingLineFromLyrics = (lyrics) => String(lyrics ?? '')
    .split(/\r?\n/)
    .map((line) => line.trim().replace(/^(?:\[\d{2}:\d{2}(?:\.\d{1,3})?\]\s*)+/u, ''))
    .find(Boolean)

const compactOpeningLine = (lyrics) => {
    const line = openingLineFromLyrics(lyrics)

    return line && line.length > 90 ? `${line.slice(0, 90)}…` : line
}

const normalizeSearchText = (value) => String(value ?? '')
    .normalize('NFKC')
    .toLocaleLowerCase('ja-JP')
    .replace(/\s+/g, '')

const filteredSongs = computed(() => {
    const query = normalizeSearchText(searchQuery.value)

    if (!query) {
        return mySongsStore.songs
    }

    return mySongsStore.songs.filter((mySong) => {
        const song = mySong.song ?? {}
        const target = [
            song.title,
            song.artist,
            song.album,
            song.opening_line,
            mySong.memo,
            ...(mySong.tags ?? []).map((tag) => tag.name),
        ].join(' ')

        return normalizeSearchText(target).includes(query)
    })
})

// 画面が表示されたタイミングで、Laravel APIからお気に入りを取得する
onMounted(async () => {
    await Promise.all([
        mySongsStore.fetchMySongs(),
        loadTags(),
    ])
    await hydrateFavoriteOpeningLines()
})

const hydrateFavoriteOpeningLines = async () => {
    const pendingSongs = mySongsStore.songs.filter((mySong) => mySong.song?.id && !mySong.song?.opening_line)

    const hydrated = await Promise.all(pendingSongs.map(async (mySong) => {
        try {
            const response = await fetchSongLyrics(mySong.song.id)
            const data = response.data.data ?? response.data

            if (!data?.lyrics && !data?.opening_line) {
                return null
            }

            return {
                id: mySong.id,
                opening_line: data.opening_line ?? compactOpeningLine(data.lyrics),
            }
        } catch {
            return null
        }
    }))

    const updates = hydrated.filter(Boolean)

    if (updates.length > 0) {
        mySongsStore.songs = mySongsStore.songs.map((mySong) => {
            const update = updates.find((item) => item.id === mySong.id)

            return update
                ? { ...mySong, song: { ...mySong.song, opening_line: update.opening_line } }
                : mySong
        })
    }
}

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

const openSong = (mySong) => {
    router.push({ name: 'song-detail', params: { id: mySong.song.id } })
}

const cancelEdit = () => {
    editingSongId.value = null
    editingMemo.value = ''
    editingTagIds.value = []
    newTagName.value = ''
}

const handleCreateTag = async () => {
    const name = newTagName.value.trim()

    if (!name || isCreatingTag.value) {
        return
    }

    errorMessage.value = ''
    isCreatingTag.value = true

    try {
        const response = await createTag(name)
        const createdTag = response.data.data ?? response.data

        if (createdTag?.id && !tags.value.some((tag) => String(tag.id) === String(createdTag.id))) {
            tags.value = [...tags.value, createdTag].sort((left, right) => left.name.localeCompare(right.name, 'ja'))
        }

        if (createdTag?.id && !editingTagIds.value.includes(createdTag.id)) {
            editingTagIds.value = [...editingTagIds.value, createdTag.id]
        }

        newTagName.value = ''
    } catch {
        errorMessage.value = 'タグを追加できませんでした。'
    } finally {
        isCreatingTag.value = false
    }
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
        message.value = 'お気に入りを更新しました。'
        await mySongsStore.fetchMySongs()
    } catch {
        errorMessage.value = 'お気に入りの更新に失敗しました。'
    } finally {
        processingSongId.value = null
    }
}

const handleDelete = async (mySongId) => {
    message.value = ''
    errorMessage.value = ''
    processingSongId.value = mySongId

    try {
        await deleteMySong(mySongId)
        message.value = 'お気に入りを解除しました。'
        await mySongsStore.fetchMySongs()
    } catch {
        errorMessage.value = 'お気に入りを解除できませんでした。'
    } finally {
        processingSongId.value = null
    }
}

</script>

<template>
    <main class="my-list">
        <header class="page-header">
            <div class="favorites-heading-row">
                <div>
                    <p class="eyebrow">Favorites</p>
                    <h1>お気に入り</h1>
                </div>
            </div>
            <p class="lead">追加したすべての曲とメモ・タグを確認できます。</p>
        </header>

        <p v-if="message" class="status-message" aria-live="polite">{{ message }}</p>

        <p v-if="errorMessage" class="error-message favorites-alert" role="alert" aria-live="assertive">
            {{ errorMessage }}
        </p>

        <!-- 読み込み中・エラー・空・一覧ありの順番で表示を切り替える -->
        <p v-if="mySongsStore.isLoading" class="status-message">読み込み中...</p>

        <p v-else-if="mySongsStore.errorMessage" class="error-message">
            {{ mySongsStore.errorMessage }}
        </p>

        <div v-else-if="mySongsStore.songCount === 0" class="status-empty">
            <p>お気に入りがありません。</p>
            <RouterLink class="primary-link" to="/songs">曲を探す</RouterLink>
        </div>

        <template v-else>
            <label class="favorites-search">
                曲を検索
                <input v-model="searchQuery" type="search" placeholder="曲名・アーティスト・メモ・タグ">
            </label>

            <p v-if="filteredSongs.length === 0" class="status-message favorites-no-results">
                検索条件に一致するお気に入りがありません。
            </p>

            <section v-else class="song-list">
            <article v-for="mySong in filteredSongs" :key="mySong.id" class="song-card favorite-song-card">
                <button
                    v-if="editingSongId !== mySong.id"
                    type="button"
                    class="song-card-button"
                    :aria-label="`${mySong.song.title}の詳細を開く`"
                    @click="openSong(mySong)"
                >
                    <!-- APIレスポンスの song オブジェクトから曲情報を表示する -->
                    <div class="favorite-song-title-row">
                        <h2>{{ mySong.song.title }}</h2>
                        <span v-if="mySong.tags.length" class="tag-list">
                            <span v-for="tag in mySong.tags" :key="tag.id">
                                {{ tag.name }}
                            </span>
                        </span>
                    </div>
                    <p class="artist">{{ mySong.song.artist }}</p>
                    <p class="bpm">BPM {{ mySong.song.bpm ?? mySong.bpm ?? '未登録' }}<span v-if="!mySong.song.bpm && mySong.bpm" class="bpm-source">（自分の計測）</span></p>
                    <p class="opening-line">
                        <span>歌いだし</span>
                        {{ mySong.song.opening_line || '未登録' }}
                    </p>
                    <p v-if="mySong.memo" class="memo">メモ: {{ mySong.memo }}</p>

                </button>

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

                    <div class="tag-create-row">
                        <label for="new-tag-name">新しいタグ</label>
                        <div class="tag-create-controls">
                            <input
                                id="new-tag-name"
                                v-model="newTagName"
                                type="text"
                                maxlength="100"
                                placeholder="例：今週練習"
                            >
                            <button type="button" class="secondary-button" :disabled="isCreatingTag || !newTagName.trim()" @click="handleCreateTag">
                                {{ isCreatingTag ? '追加中...' : 'タグを追加' }}
                            </button>
                        </div>
                    </div>

                    <div class="song-card-actions">
                        <button type="submit" :disabled="processingSongId === mySong.id">
                            {{ processingSongId === mySong.id ? '更新中...' : '更新' }}
                        </button>
                        <button type="button" class="secondary-button" @click="cancelEdit">
                            キャンセル
                        </button>
                    </div>
                </form>

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
        </template>
    </main>
</template>
