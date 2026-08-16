<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { addPlaylistSong } from '../api/playlists'
import { addMySong, deleteMySong, fetchMySongs, updateMySong } from '../api/mySongs'
import { fetchSong, fetchSongLyrics } from '../api/songs'
import RhythmPlayer from '../components/RhythmPlayer.vue'
import YoutubeMvPlayer from '../components/YoutubeMvPlayer.vue'
import { usePlaylistsStore } from '../stores/playlists'

const route = useRoute()
const playlistsStore = usePlaylistsStore()
const song = ref(null)
const isLoading = ref(true)
const errorMessage = ref('')
const favoriteEntry = ref(null)
const actionMessage = ref('')
const actionErrorMessage = ref('')
const isFavoriteProcessing = ref(false)
const isPlaylistProcessing = ref(false)
const isPlaylistMenuOpen = ref(false)
const isRhythmOpen = ref(false)
const isMvOpen = ref(false)
const isLyricsOpen = ref(false)
const isLyricsLoading = ref(false)
const lyricsSourceUrl = ref('')
const lyricsErrorMessage = ref('')
const categoryOrder = ref(['rhythm', 'mv', 'lyrics', 'info'])
const draggedCategory = ref(null)
const dropIndicator = ref({ category: null, position: null })
let loadSequence = 0
let personalDataSequence = 0

const categoryStorageKey = 'utaeru.song-detail-category-order'
const defaultCategoryOrder = ['rhythm', 'mv', 'lyrics', 'info']
let activePointerId = null
let activePointerCategory = null

const songId = computed(() => Number(route.params.id))
const backLink = computed(() => {
    if (route.query.from === 'playlist' && route.query.playlistId) {
        return {
            label: 'プレイリストへ戻る',
            to: { name: 'playlist-detail', params: { id: route.query.playlistId } },
        }
    }

    if (route.query.from === 'shared' && route.query.token) {
        return {
            label: '共有プレイリストへ戻る',
            to: { name: 'shared-playlist', params: { token: route.query.token } },
        }
    }

    if (route.query.from === 'search') {
        const query = {}

        if (route.query.q) {
            query.q = route.query.q
        }

        if (route.query.field) {
            query.field = route.query.field
        }

        if (route.query.sort) {
            query.sort = route.query.sort
        }

        return {
            label: '曲検索へ戻る',
            to: { name: 'song-search', query },
        }
    }

    if (route.query.from === 'admin') {
        return {
            label: '管理画面へ戻る',
            to: { name: 'admin-songs' },
        }
    }

    return {
        label: 'お気に入りへ戻る',
        to: { name: 'my-list' },
    }
})

const lyricsText = computed(() => {
    const lyrics = song.value?.lyrics ?? song.value?.lyrics_text

    return typeof lyrics === 'string' ? lyrics.trim() : ''
})

const lyricsPreview = computed(() => {
    const firstLine = song.value?.opening_line || lyricsText.value
        .split(/\r?\n/)
        .map((line) => line.trim().replace(/^(?:\[\d{2}:\d{2}(?:\.\d{1,3})?\]\s*)+/u, ''))
        .find(Boolean)

    if (!firstLine) {
        return '歌詞は未登録です'
    }

    return firstLine.length > 56 ? `${firstLine.slice(0, 56)}…` : firstLine
})

const lyricsSearchUrl = computed(() => {
    const query = `${song.value?.artist ?? ''} ${song.value?.title ?? ''} 歌詞`.trim()

    return `https://www.google.com/search?q=${encodeURIComponent(query)}`
})

const durationLabel = computed(() => {
    const durationMs = Number(song.value?.duration_ms)

    if (!Number.isFinite(durationMs) || durationMs <= 0) {
        return ''
    }

    const totalSeconds = Math.round(durationMs / 1000)
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = String(totalSeconds % 60).padStart(2, '0')

    return `${minutes}:${seconds}`
})

const isFavorite = computed(() => Boolean(favoriteEntry.value))

const findFavoriteEntry = (entries, requestedSongId) => entries.find((entry) => (
    entry?.song?.id && String(entry.song.id) === String(requestedSongId)
)) ?? null

const loadFavoriteState = async (requestedSongId) => {
    const sequence = ++personalDataSequence

    favoriteEntry.value = null

    try {
        const response = await fetchMySongs()
        const data = response.data.data ?? response.data

        if (sequence === personalDataSequence) {
            favoriteEntry.value = findFavoriteEntry(Array.isArray(data) ? data : [], requestedSongId)
        }
    } catch {
        // 状態取得に失敗しても、曲詳細・BPM・MV・歌詞の閲覧は継続できる。
    }
}

const toggleFavorite = async () => {
    if (!song.value || isFavoriteProcessing.value) {
        return
    }

    isFavoriteProcessing.value = true
    actionMessage.value = ''
    actionErrorMessage.value = ''

    try {
        if (favoriteEntry.value) {
            await deleteMySong(favoriteEntry.value.id)
            favoriteEntry.value = null
            actionMessage.value = 'お気に入りを解除しました。'
            return
        }

        const response = await addMySong(song.value.id)
        const createdEntry = response.data.data ?? response.data

        if (createdEntry?.id) {
            favoriteEntry.value = {
                ...createdEntry,
                song: createdEntry.song ?? { id: song.value.id },
            }
        } else {
            await loadFavoriteState(song.value.id)
        }

        actionMessage.value = 'お気に入りに追加しました。'
    } catch (error) {
        if (error?.response?.status === 422) {
            // 状態取得が遅れていた場合も、登録済みなら同じタップを解除操作として扱う。
            await loadFavoriteState(song.value.id)

            if (favoriteEntry.value) {
                try {
                    await deleteMySong(favoriteEntry.value.id)
                    favoriteEntry.value = null
                    actionMessage.value = 'お気に入りを解除しました。'
                    return
                } catch {
                    // 共通エラー表示へ進む。
                }
            }
        }

        actionErrorMessage.value = 'お気に入りを更新できませんでした。'
    } finally {
        isFavoriteProcessing.value = false
    }
}

const togglePlaylistMenu = () => {
    actionErrorMessage.value = ''
    isPlaylistMenuOpen.value = !isPlaylistMenuOpen.value

    if (isPlaylistMenuOpen.value && playlistsStore.playlists.length === 0 && !playlistsStore.isLoading) {
        void playlistsStore.fetchPlaylists()
    }
}

const addSongToPlaylist = async (playlist) => {
    if (!song.value || isPlaylistProcessing.value) {
        return
    }

    isPlaylistProcessing.value = true
    actionMessage.value = ''
    actionErrorMessage.value = ''

    try {
        await addPlaylistSong(playlist.id, { song_id: song.value.id })
        actionMessage.value = `「${playlist.name}」に追加しました。`
        isPlaylistMenuOpen.value = false
    } catch {
        actionErrorMessage.value = 'プレイリストへの追加に失敗しました。'
    } finally {
        isPlaylistProcessing.value = false
    }
}

const saveMeasuredBpm = async (bpm) => {
    if (!favoriteEntry.value || !Number.isFinite(Number(bpm))) {
        actionErrorMessage.value = '計測値を登録するには、先にお気に入りへ追加してください。'
        return
    }

    try {
        await updateMySong(favoriteEntry.value.id, { bpm: Math.round(Number(bpm)) })
        favoriteEntry.value = { ...favoriteEntry.value, bpm: Math.round(Number(bpm)) }
        actionMessage.value = '計測したBPMを登録しました。'
        actionErrorMessage.value = ''
    } catch {
        actionErrorMessage.value = '計測したBPMを登録できませんでした。'
    }
}

const loadCategoryOrder = () => {
    if (typeof window === 'undefined') {
        return
    }

    try {
        const savedOrder = JSON.parse(window.localStorage.getItem(categoryStorageKey) ?? 'null')

        if (!Array.isArray(savedOrder)) {
            return
        }

        const validOrder = savedOrder.filter((category) => defaultCategoryOrder.includes(category))
        const missingCategories = defaultCategoryOrder.filter((category) => !validOrder.includes(category))

        if (validOrder.length === defaultCategoryOrder.length) {
            categoryOrder.value = validOrder
        } else {
            categoryOrder.value = [...validOrder, ...missingCategories]
        }
    } catch {
        // 保存値が壊れていても、既定順で曲詳細を表示する。
        categoryOrder.value = [...defaultCategoryOrder]
    }
}

const resetCategoryOrder = () => {
    categoryOrder.value = [...defaultCategoryOrder]
}

const persistCategoryOrder = () => {
    if (typeof window !== 'undefined') {
        window.localStorage.setItem(categoryStorageKey, JSON.stringify(categoryOrder.value))
    }
}

const startCategoryDrag = (event, category) => {
    draggedCategory.value = category
    dropIndicator.value = { category: null, position: null }

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move'
        event.dataTransfer.setData('text/plain', category)
    }
}

const reorderCategory = (sourceCategory, targetCategory, position = 'before') => {
    if (!sourceCategory || !targetCategory || sourceCategory === targetCategory) {
        return false
    }

    const nextOrder = [...categoryOrder.value]
    const sourceIndex = nextOrder.indexOf(sourceCategory)
    const targetIndex = nextOrder.indexOf(targetCategory)

    if (sourceIndex < 0 || targetIndex < 0) {
        return false
    }

    nextOrder.splice(sourceIndex, 1)
    const nextTargetIndex = nextOrder.indexOf(targetCategory)
    const insertionIndex = position === 'after' ? nextTargetIndex + 1 : nextTargetIndex
    nextOrder.splice(insertionIndex, 0, sourceCategory)
    categoryOrder.value = nextOrder

    return true
}

const findDropTarget = (clientY) => {
    // CSS orderで並べ替えるため、判定も画面上の位置順に揃える。
    const elements = [...document.querySelectorAll('.song-detail-category')]
        .sort((left, right) => left.getBoundingClientRect().top - right.getBoundingClientRect().top)

    if (elements.length === 0) {
        return null
    }

    const firstRect = elements[0].getBoundingClientRect()
    const lastRect = elements[elements.length - 1].getBoundingClientRect()

    if (clientY <= firstRect.top) {
        return { category: elements[0].dataset.category, position: 'before' }
    }

    for (let index = 0; index < elements.length; index += 1) {
        const element = elements[index]
        const rect = element.getBoundingClientRect()

        if (clientY >= rect.top && clientY <= rect.bottom) {
            return {
                category: element.dataset.category,
                position: clientY <= rect.top + rect.height / 2 ? 'before' : 'after',
            }
        }

        const nextElement = elements[index + 1]

        if (nextElement) {
            const nextRect = nextElement.getBoundingClientRect()

            if (clientY > rect.bottom && clientY < nextRect.top) {
                const gapMiddle = rect.bottom + (nextRect.top - rect.bottom) / 2

                return clientY < gapMiddle
                    ? { category: element.dataset.category, position: 'after' }
                    : { category: nextElement.dataset.category, position: 'before' }
            }
        }
    }

    if (clientY >= lastRect.bottom) {
        return { category: elements[elements.length - 1].dataset.category, position: 'after' }
    }

    return null
}

const updateDropIndicator = (clientY, shouldReorder = false) => {
    const target = findDropTarget(clientY)
    const sourceCategory = activePointerCategory ?? draggedCategory.value

    if (!target || !sourceCategory || target.category === sourceCategory) {
        dropIndicator.value = { category: null, position: null }
        return
    }

    dropIndicator.value = target

    if (shouldReorder) {
        reorderCategory(sourceCategory, target.category, target.position)
    }
}

const finishPointerCategoryDrag = () => {
    const target = dropIndicator.value

    if (activePointerCategory && target.category && activePointerCategory !== target.category
        && reorderCategory(activePointerCategory, target.category, target.position)) {
        persistCategoryOrder()
    }

    window.removeEventListener('pointermove', handlePointerCategoryMove)
    window.removeEventListener('pointerup', finishPointerCategoryDrag)
    window.removeEventListener('pointercancel', finishPointerCategoryDrag)
    activePointerId = null
    activePointerCategory = null
    endCategoryDrag()
}

const handlePointerCategoryMove = (event) => {
    if (event.pointerId !== activePointerId || !activePointerCategory) {
        return
    }

    event.preventDefault()

    updateDropIndicator(event.clientY)
}

const startPointerCategoryDrag = (event, category) => {
    if (event.pointerType === 'mouse' && event.button !== 0) {
        return
    }

    event.preventDefault()
    event.currentTarget.setPointerCapture?.(event.pointerId)
    activePointerId = event.pointerId
    activePointerCategory = category
    draggedCategory.value = category
    dropIndicator.value = { category: null, position: null }
    window.addEventListener('pointermove', handlePointerCategoryMove, { passive: false })
    window.addEventListener('pointerup', finishPointerCategoryDrag, { once: true })
    window.addEventListener('pointercancel', finishPointerCategoryDrag, { once: true })
}

const handleCategoryPointerDown = (event, category) => {
    // カテゴリ内の再生・歌詞・リンク操作はドラッグ開始と競合させない。
    if (event.target.closest('button, a, input, textarea, select, summary')) {
        return
    }

    startPointerCategoryDrag(event, category)
}

const dragOverCategory = (event, category) => {
    if (!draggedCategory.value || draggedCategory.value === category) {
        if (draggedCategory.value === category) {
            event.preventDefault()
            dropIndicator.value = { category: null, position: null }
        }

        return
    }

    event.preventDefault()
    updateDropIndicator(event.clientY)

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'move'
    }
}

const dropCategory = (event, targetCategory) => {
    event.preventDefault()
    event.stopPropagation()

    const sourceCategory = draggedCategory.value
        ?? event.dataTransfer?.getData('text/plain')
    const target = dropIndicator.value.category
        ? dropIndicator.value
        : { category: targetCategory, position: 'before' }

    if (!sourceCategory || sourceCategory === target.category) {
        endCategoryDrag()
        return
    }

    if (reorderCategory(sourceCategory, target.category, target.position)) {
        persistCategoryOrder()
    }

    endCategoryDrag()
}

const dragOverCategoryList = (event) => {
    if (!draggedCategory.value) {
        return
    }

    event.preventDefault()
    updateDropIndicator(event.clientY)
}

const dropCategoryList = (event) => {
    event.preventDefault()
    event.stopPropagation()

    const sourceCategory = draggedCategory.value
        ?? event.dataTransfer?.getData('text/plain')
    const target = dropIndicator.value

    if (sourceCategory && target.category && sourceCategory !== target.category
        && reorderCategory(sourceCategory, target.category, target.position)) {
        persistCategoryOrder()
    }

    endCategoryDrag()
}

const endCategoryDrag = () => {
    draggedCategory.value = null
    dropIndicator.value = { category: null, position: null }
}

const loadSong = async () => {
    const sequence = ++loadSequence
    const requestedSongId = songId.value
    isLoading.value = true
    errorMessage.value = ''
    isRhythmOpen.value = false
    isMvOpen.value = false
    isLyricsOpen.value = false
    isLyricsLoading.value = false
    lyricsSourceUrl.value = ''
    lyricsErrorMessage.value = ''

    try {
        const response = await fetchSong(requestedSongId)
        song.value = response.data.data ?? response.data
        void loadFavoriteState(requestedSongId)
        void playlistsStore.fetchPlaylists()

        if (sequence !== loadSequence || songId.value !== requestedSongId || lyricsText.value) {
            return
        }

        isLyricsLoading.value = true

        try {
            const lyricsResponse = await fetchSongLyrics(requestedSongId)
            const lyricsData = lyricsResponse.data.data ?? lyricsResponse.data

            if (sequence === loadSequence && songId.value === requestedSongId && lyricsData?.lyrics) {
                song.value = {
                    ...song.value,
                    lyrics: lyricsData.lyrics,
                    opening_line: lyricsData.opening_line ?? song.value.opening_line,
                }
                lyricsSourceUrl.value = lyricsData.source_url ?? ''
            }
        } catch (error) {
            // 未設定・未登録（404）は通常表示として扱い、通信障害だけ利用者に知らせる。
            if (error.response?.status !== 404 && sequence === loadSequence) {
                lyricsErrorMessage.value = '歌詞サービスに接続できませんでした。'
            }
        } finally {
            if (sequence === loadSequence) {
                isLyricsLoading.value = false
            }
        }
    } catch {
        errorMessage.value = '曲の詳細を取得できませんでした。'
    } finally {
        isLoading.value = false
    }
}

const handlePanelToggle = (panel, event) => {
    const isOpen = event.currentTarget.open

    if (panel === 'rhythm') {
        isRhythmOpen.value = isOpen
    } else if (panel === 'mv') {
        isMvOpen.value = isOpen
    } else {
        isLyricsOpen.value = isOpen
    }
}

// 同じ詳細コンポーネントを再利用した曲間遷移でも、歌詞・BPM・再生元を取り直す。
onMounted(() => {
    loadCategoryOrder()
    window.addEventListener('utaeru:reset-category-order', resetCategoryOrder)
})

onUnmounted(() => {
    window.removeEventListener('utaeru:reset-category-order', resetCategoryOrder)
})
watch(songId, loadSong, { immediate: true })
</script>

<template>
    <main class="my-list song-detail-page">
        <p class="song-detail-back">
            <RouterLink class="back-link" :to="backLink.to">← {{ backLink.label }}</RouterLink>
        </p>

        <p v-if="isLoading" class="status-message">読み込み中...</p>
        <p v-else-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>

        <template v-else-if="song">
            <header class="song-detail-hero">
                <div class="song-detail-cover-wrap">
                    <img
                        v-if="song.artwork_url"
                        :src="song.artwork_url"
                        :alt="`${song.title}のジャケット`"
                        class="song-detail-artwork"
                    >
                    <div v-else class="song-detail-cover-placeholder" aria-hidden="true">♫</div>
                </div>

                <div class="song-detail-hero-copy">
                    <p class="eyebrow">Song detail</p>
                    <h1>{{ song.title }}</h1>
                    <p class="lead">{{ song.artist }}<span v-if="song.album"> / {{ song.album }}</span></p>

                    <div class="song-detail-meta" aria-label="曲のメタデータ">
                        <span class="song-detail-chip">
                            <span aria-hidden="true">♩</span>
                            BPM {{ song.bpm ?? '未登録' }}
                        </span>
                        <span v-if="durationLabel" class="song-detail-chip">
                            <span aria-hidden="true">◷</span>
                            {{ durationLabel }}
                        </span>
                    </div>

                    <div class="song-detail-actions">
                        <button
                            type="button"
                            class="icon-button favorite-button"
                            :class="{ 'is-favorite': isFavorite }"
                            :disabled="isFavoriteProcessing"
                            :aria-pressed="isFavorite"
                            :aria-label="isFavorite ? 'お気に入りを解除' : 'お気に入りに追加'"
                            :title="isFavorite ? 'お気に入りを解除' : 'お気に入りに追加'"
                            @click="toggleFavorite"
                        >
                            <span class="favorite-icon" aria-hidden="true">
                                {{ isFavorite ? '♥' : '♡' }}
                            </span>
                            <span class="sr-only">{{ isFavorite ? 'お気に入りを解除' : 'お気に入りに追加' }}</span>
                        </button>

                        <button
                            type="button"
                            class="icon-button playlist-action-button"
                            :aria-expanded="isPlaylistMenuOpen"
                            aria-controls="song-detail-playlist-menu"
                            :disabled="isPlaylistProcessing"
                            aria-label="プレイリストに追加"
                            title="プレイリストに追加"
                            @click="togglePlaylistMenu"
                        >
                            <span aria-hidden="true">＋</span>
                            <span class="sr-only">プレイリストに追加</span>
                        </button>
                    </div>

                    <div
                        v-if="isPlaylistMenuOpen"
                        id="song-detail-playlist-menu"
                        class="song-detail-playlist-menu"
                        aria-label="追加先プレイリスト"
                    >
                        <span class="song-detail-playlist-menu-label">追加先</span>
                        <p v-if="playlistsStore.isLoading" class="song-detail-playlist-status">プレイリストを読み込み中...</p>
                        <template v-else-if="playlistsStore.playlists.length > 0">
                            <button
                                v-for="playlist in playlistsStore.playlists"
                                :key="playlist.id"
                                type="button"
                                class="song-detail-playlist-option"
                                :disabled="isPlaylistProcessing"
                                @click="addSongToPlaylist(playlist)"
                            >
                                {{ playlist.name }}
                            </button>
                        </template>
                        <div v-else class="song-detail-playlist-empty">
                            <p>プレイリストがありません。</p>
                            <RouterLink to="/playlists">プレイリストを作成</RouterLink>
                        </div>
                    </div>

                    <p v-if="actionMessage" class="song-detail-action-message" aria-live="polite">
                        {{ actionMessage }}
                    </p>
                    <p v-if="actionErrorMessage" class="song-detail-action-error" role="alert">
                        {{ actionErrorMessage }}
                    </p>
                </div>
            </header>

            <div class="song-detail-category-board" aria-label="曲詳細カテゴリ">
                <div class="song-detail-category-list" @dragover="dragOverCategoryList" @drop="dropCategoryList">
                    <template v-for="category in defaultCategoryOrder" :key="category">
                        <div
                            class="song-detail-category"
                            :class="{
                                'is-dragging': draggedCategory === category,
                                'is-drop-target': dropIndicator.category === category,
                            }"
                            :style="{ order: categoryOrder.indexOf(category) }"
                            :data-category="category"
                            draggable="true"
                            @pointerdown="handleCategoryPointerDown($event, category)"
                            @dragstart="startCategoryDrag($event, category)"
                            @dragover="dragOverCategory($event, category)"
                            @drop="dropCategory($event, category)"
                            @dragend="endCategoryDrag"
                        >
                        <div
                            v-if="dropIndicator.category === category && dropIndicator.position === 'before'"
                            class="song-detail-drop-line"
                            aria-hidden="true"
                        ></div>
                        <details v-if="category === 'rhythm'" id="rhythm-section" class="song-detail-panel song-detail-collapsible" :open="isRhythmOpen" @toggle="handlePanelToggle('rhythm', $event)">
                            <summary class="song-detail-panel-summary">
                                <span class="song-detail-panel-icon" aria-hidden="true">♩</span>
                                <div class="song-detail-panel-heading">
                                    <div>
                                        <p class="eyebrow">Practice mode</p>
                                        <h2 id="rhythm-section-heading">リズムを確認する</h2>
                                    </div>
                                </div>
                                <span class="song-detail-panel-toggle" aria-hidden="true">⌄</span>
                            </summary>
                            <div class="song-detail-panel-body">
                                <p class="song-detail-panel-description">
                                    BPMに合わせて4拍の視覚リズムを再生します。カラオケ前の歌い出し確認に使えます。
                                </p>
                                <RhythmPlayer
                                    :song="song"
                                    :saved-bpm="favoriteEntry?.bpm"
                                    :can-save-bpm="Boolean(favoriteEntry) && !song.bpm"
                                    @save-bpm="saveMeasuredBpm"
                                />
                            </div>
                        </details>

                        <details v-else-if="category === 'mv'" id="mv-section" class="song-detail-panel song-detail-collapsible" :open="isMvOpen" @toggle="handlePanelToggle('mv', $event)">
                            <summary class="song-detail-panel-summary">
                                <span class="song-detail-panel-icon song-detail-panel-icon-video" aria-hidden="true">▶</span>
                                <div class="song-detail-panel-heading">
                                    <div>
                                        <p class="eyebrow">Official MV</p>
                                        <h2 id="mv-section-heading">MVを再生する</h2>
                                    </div>
                                </div>
                                <span class="song-detail-panel-toggle" aria-hidden="true">⌄</span>
                            </summary>
                            <div class="song-detail-panel-body">
                                <div id="song-detail-mv-host" class="song-detail-mv-host"></div>
                                <YoutubeMvPlayer :song="song" />
                            </div>
                        </details>

                        <details v-else-if="category === 'lyrics'" id="lyrics-section" class="lyrics-card" :open="isLyricsOpen" @toggle="handlePanelToggle('lyrics', $event)">
                            <summary>
                                <span class="song-detail-panel-icon song-detail-panel-icon-lyrics" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path d="M6.5 3.5h8l3 3v14h-11z" />
                                        <path d="M14.5 3.5v4h3M9 12h6M9 15.5h6" />
                                    </svg>
                                </span>
                                <span class="lyrics-card-title">
                                    <span class="eyebrow">Lyrics</span>
                                    <strong>歌詞を表示する</strong>
                                </span>
                                <span v-if="!isLyricsOpen" class="lyrics-card-preview">
                                    <span class="lyrics-card-preview-icon" role="img" aria-label="歌いだし">♪~</span>
                                    {{ lyricsPreview }}
                                </span>
                                <span class="lyrics-card-toggle">
                                    <span aria-hidden="true">⌄</span>
                                </span>
                            </summary>
                            <div class="lyrics-card-body">
                                <p v-if="isLyricsLoading" class="status-message" role="status">利用許諾済みの歌詞を確認中...</p>
                                <p v-else-if="lyricsText" class="lyrics-text">{{ lyricsText }}</p>
                                <a
                                    v-if="lyricsText && lyricsSourceUrl"
                                    class="lyrics-source-link"
                                    :href="lyricsSourceUrl"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    歌詞提供元を開く
                                </a>
                                <div v-if="!isLyricsLoading && !lyricsText" class="lyrics-empty">
                                    <span class="lyrics-empty-icon" aria-hidden="true">♫</span>
                                    <strong>歌詞はまだ登録されていません</strong>
                                    <p v-if="lyricsErrorMessage" class="status-message" role="status">{{ lyricsErrorMessage }}</p>
                                    <p v-else>許諾済みの歌詞データが登録された曲は、ここで確認できます。</p>
                                    <a
                                        v-if="lyricsSourceUrl"
                                        class="lyrics-source-link"
                                        :href="lyricsSourceUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        歌詞提供元を開く
                                    </a>
                                    <a
                                        v-else
                                        class="lyrics-source-link"
                                        :href="lyricsSearchUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        歌詞サイトで確認する
                                    </a>
                                </div>
                            </div>
                        </details>

                        <section v-else class="song-info-card" aria-labelledby="song-info-heading">
                            <p class="eyebrow">About this song</p>
                            <h2 id="song-info-heading">曲の情報</h2>
                            <dl class="song-info-list">
                                <div>
                                    <dt>アーティスト</dt>
                                    <dd>{{ song.artist }}</dd>
                                </div>
                                <div v-if="song.album">
                                    <dt>アルバム</dt>
                                    <dd>{{ song.album }}</dd>
                                </div>
                                <div>
                                    <dt>BPM</dt>
                                    <dd>{{ song.bpm ?? '未登録' }}</dd>
                                </div>
                            </dl>
                        </section>
                        <div
                            v-if="dropIndicator.category === category && dropIndicator.position === 'after'"
                            class="song-detail-drop-line"
                            aria-hidden="true"
                        ></div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </main>
</template>
