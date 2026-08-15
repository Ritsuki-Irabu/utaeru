<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { addPlaylistSong } from '../api/playlists'
import { addMySong, deleteMySong, fetchMySongs } from '../api/mySongs'
import { fetchSongLyrics, importSong, searchSongs } from '../api/songs'
import { usePlaylistsStore } from '../stores/playlists'

const playlistsStore = usePlaylistsStore()
const route = useRoute()
const router = useRouter()
const keyword = ref('')
const searchField = ref('all')
const sortMode = ref('relevance')
const results = ref([])
const successMessage = ref('')
const errorMessage = ref('')
const isSearching = ref(false)
const hasSearched = ref(false)
const processingKey = ref(null)
const favoriteEntries = ref([])
const resolvedSongIds = ref({})
const playlistMenuTargetKey = ref(null)
const playlistCreationTargetKey = ref(null)
const newPlaylistName = ref('')
const newPlaylistDescription = ref('')
const isCreatingPlaylist = ref(false)
const playlistNameInput = ref(null)
let latestSearchRequest = 0
let searchTimer = null

// 検索前に選べる定番アーティスト。ランキング値ではなく、検索を始めるための入口として表示する。
const recommendedArtists = [
    { name: 'YOASOBI', genre: 'J-POP' },
    { name: 'Mrs. GREEN APPLE', genre: 'J-POP' },
    { name: 'Official髭男dism', genre: 'J-POP' },
    { name: 'Ado', genre: 'J-POP' },
    { name: 'ヨルシカ', genre: 'J-POP' },
    { name: '米津玄師', genre: 'J-POP' },
    { name: 'Vaundy', genre: 'J-POP' },
    { name: 'back number', genre: 'J-POP' },
]

const loadFavoriteEntries = async () => {
    try {
        const response = await fetchMySongs()
        const data = response.data.data ?? response.data
        favoriteEntries.value = Array.isArray(data) ? data : []
    } catch {
        // お気に入り取得に失敗しても、検索・追加操作は継続できる。
        favoriteEntries.value = []
    }
}

onMounted(async () => {
    playlistsStore.fetchPlaylists()
    await loadFavoriteEntries()

    const restoredQuery = String(route.query.q ?? '').trim()

    if (restoredQuery) {
        keyword.value = restoredQuery
        searchField.value = ['all', 'title', 'artist', 'album'].includes(String(route.query.field))
            ? String(route.query.field)
            : 'all'
        sortMode.value = ['relevance', 'title', 'artist', 'bpm'].includes(String(route.query.sort))
            ? String(route.query.sort)
            : 'relevance'
    }
})

// 外部検索結果は取り込み後にidが付与されても同じカードとして扱う。
const resultKey = (result) => result.provider && result.provider_key
    ? `${result.provider}:${result.provider_key}`
    : `song:${result.id}`

// ひらがな入力でもカタカナ表記の曲名・アーティスト名に一致させる。
const normalizeSearchText = (value) => String(value ?? '')
    .normalize('NFKC')
    .replace(/[\u30a1-\u30f6]/g, (character) => String.fromCharCode(character.charCodeAt(0) - 0x60))
    .toLocaleLowerCase('ja-JP')
    .replace(/\s+/g, '')

// 検索結果でも、曲詳細と同じ歌詞の先頭行を表示する。
const openingLine = (result) => {
    if (result?.opening_line) {
        return result.opening_line
    }

    const lyrics = typeof result?.lyrics === 'string' ? result.lyrics : ''
    const firstLine = lyrics
        .split(/\r?\n/)
        .map((line) => line.trim().replace(/^(?:\[\d{2}:\d{2}(?:\.\d{1,3})?\]\s*)+/u, ''))
        .find(Boolean)

    return firstLine ? (firstLine.length > 90 ? `${firstLine.slice(0, 90)}…` : firstLine) : '未登録'
}

const favoriteEntryForResult = (result) => {
    const songId = result?.id ?? resolvedSongIds.value[resultKey(result)]

    return favoriteEntries.value.find((entry) => {
        if (songId && String(entry.song?.id) === String(songId)) {
            return true
        }

        return result?.provider
            && result?.provider_key
            && entry.song?.playback_provider === result.provider
            && String(entry.song?.playback_key) === String(result.provider_key)
    }) ?? null
}

const isFavorite = (result) => Boolean(favoriteEntryForResult(result))

const updateSearchResult = (resultKeyValue, updates) => {
    results.value = results.value.map((item) => resultKey(item) === resultKeyValue
        ? { ...item, ...updates }
        : item)
}

const hydrateOpeningLine = async (result, requestId) => {
    if (!result?.id || result.opening_line || result.lyrics) {
        return
    }

    try {
        const response = await fetchSongLyrics(result.id)
        const lyricsData = response.data.data ?? response.data

        if (
            requestId !== latestSearchRequest
            || !lyricsData
            || Array.isArray(lyricsData)
            || (!lyricsData.opening_line && typeof lyricsData.lyrics !== 'string')
        ) {
            return
        }

        updateSearchResult(resultKey(result), {
            lyrics: lyricsData.lyrics,
            opening_line: lyricsData.opening_line ?? openingLine(lyricsData),
        })
    } catch {
        // 歌詞未登録・外部API障害時は「未登録」のまま検索結果を表示する。
    }
}

const hydrateOpeningLines = (items, requestId) => {
    void Promise.allSettled(items.map((result) => hydrateOpeningLine(result, requestId)))
}

const visibleResults = computed(() => {
    const query = normalizeSearchText(keyword.value.trim())
    const filtered = results.value.filter((result) => {
        if (!query) {
            return true
        }

        const target = searchField.value === 'title'
            ? result.title
            : searchField.value === 'artist'
                ? result.artist
                : searchField.value === 'album'
                    ? result.album
                    : `${result.title} ${result.artist} ${result.album ?? ''}`

        return normalizeSearchText(target).includes(query)
    })

    if (sortMode.value === 'relevance') {
        return filtered
    }

    return [...filtered].sort((left, right) => {
        if (sortMode.value === 'bpm') {
            const leftBpm = Number(left.bpm) || Number.POSITIVE_INFINITY
            const rightBpm = Number(right.bpm) || Number.POSITIVE_INFINITY

            return leftBpm - rightBpm
        }

        return String(left[sortMode.value] ?? '').localeCompare(
            String(right[sortMode.value] ?? ''),
            'ja',
        )
    })
})

const searchResultCount = computed(() => visibleResults.value.length)

const runSearch = async () => {
    if (searchTimer) {
        clearTimeout(searchTimer)
        searchTimer = null
    }

    const query = keyword.value.trim()
    const requestId = ++latestSearchRequest

    if (query.length < 1) {
        results.value = []
        hasSearched.value = false
        isSearching.value = false
        return
    }

    isSearching.value = true
    errorMessage.value = ''
    hasSearched.value = true

    try {
        const response = await searchSongs(query, searchField.value)

        if (requestId !== latestSearchRequest) {
            return
        }

        results.value = response.data.data
        hydrateOpeningLines(results.value, requestId)
    } catch {
        if (requestId !== latestSearchRequest) {
            return
        }

        errorMessage.value = '曲の検索に失敗しました。'
        results.value = []
    } finally {
        if (requestId === latestSearchRequest) {
            isSearching.value = false
        }
    }
}

const scheduleSearch = () => {
    if (searchTimer) {
        clearTimeout(searchTimer)
    }

    searchTimer = setTimeout(() => {
        runSearch()
    }, 350)
}

const chooseRecommendedArtist = (artist) => {
    keyword.value = artist
    searchField.value = 'artist'
    sortMode.value = 'relevance'
}

// 入力中は少し待ってから検索し、APIへの連続リクエストを避ける。
watch([keyword, searchField], scheduleSearch)

onUnmounted(() => {
    if (searchTimer) {
        clearTimeout(searchTimer)
    }
})

const clearSearch = () => {
    // 進行中の検索レスポンスが、クリア直後の空状態を上書きしないよう無効化する。
    latestSearchRequest += 1

    if (searchTimer) {
        clearTimeout(searchTimer)
        searchTimer = null
    }

    keyword.value = ''
    results.value = []
    hasSearched.value = false
    isSearching.value = false
    errorMessage.value = ''
}

const resolveSongId = async (result) => {
    if (result.id) {
        resolvedSongIds.value = { ...resolvedSongIds.value, [resultKey(result)]: result.id }
        return result.id
    }

    const response = await importSong({
        provider: result.provider,
        provider_key: result.provider_key,
    })
    const song = response.data.data ?? response.data

    resolvedSongIds.value = { ...resolvedSongIds.value, [resultKey(result)]: song.id }
    results.value = results.value.map((item) => item === result
        ? {
            ...item,
            id: song.id,
            bpm: song.bpm ?? item.bpm,
            opening_line: song.opening_line ?? item.opening_line,
        }
        : item)

    return song.id
}

const openResult = async (result) => {
    const key = resultKey(result)
    processingKey.value = key

    try {
        const songId = await resolveSongId(result)
        await router.push({
            name: 'song-detail',
            params: { id: songId },
            query: {
                from: 'search',
                q: keyword.value.trim(),
                field: searchField.value,
                sort: sortMode.value,
            },
        })
    } catch {
        errorMessage.value = '曲の詳細を開けませんでした。'
    } finally {
        processingKey.value = null
    }
}

const handleResultKeydown = (event, result) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return
    }

    event.preventDefault()
    openResult(result)
}

const handleAddFavorite = async (result) => {
    successMessage.value = ''
    errorMessage.value = ''
    const key = resultKey(result)
    processingKey.value = key

    try {
        const existing = favoriteEntryForResult(result)

        if (existing) {
            await deleteMySong(existing.id)
            favoriteEntries.value = favoriteEntries.value.filter((entry) => entry.id !== existing.id)
            successMessage.value = 'お気に入りを解除しました。'
            return
        }

        const songId = await resolveSongId(result)
        const response = await addMySong(songId)
        const createdEntry = response.data.data ?? response.data

        if (createdEntry?.id) {
            const normalizedEntry = {
                ...createdEntry,
                song: createdEntry.song ?? {
                    id: songId,
                    title: result.title,
                    artist: result.artist,
                    playback_provider: result.provider,
                    playback_key: result.provider_key,
                },
                tags: createdEntry.tags ?? [],
            }

            favoriteEntries.value = [
                ...favoriteEntries.value.filter((entry) => entry.id !== normalizedEntry.id && String(entry.song?.id) !== String(songId)),
                normalizedEntry,
            ]
        } else {
            await loadFavoriteEntries()
        }

        successMessage.value = 'お気に入りに追加しました。'
    } catch (error) {
        if (error?.response?.status === 422) {
            // 表示が古い場合も、再取得後に同じタップを解除操作として完了する。
            await loadFavoriteEntries()
            const existing = favoriteEntryForResult(result)

            if (existing) {
                try {
                    await deleteMySong(existing.id)
                    favoriteEntries.value = favoriteEntries.value.filter((entry) => entry.id !== existing.id)
                    successMessage.value = 'お気に入りを解除しました。'
                    return
                } catch {
                    // 下の共通エラー表示へ進む。
                }
            }
        }

        errorMessage.value = 'お気に入りの更新に失敗しました。'
    } finally {
        processingKey.value = null
    }
}

const handleAddToPlaylist = async (result, playlistId) => {
    successMessage.value = ''
    errorMessage.value = ''
    const key = resultKey(result)

    processingKey.value = key

    try {
        const songId = await resolveSongId(result)
        await addPlaylistSong(playlistId, { song_id: songId })
        successMessage.value = 'プレイリストに追加しました。'
        playlistMenuTargetKey.value = null
    } catch {
        errorMessage.value = 'プレイリストへの追加に失敗しました。'
    } finally {
        processingKey.value = null
    }
}

const togglePlaylistMenu = async (result) => {
    const key = resultKey(result)
    errorMessage.value = ''

    // プレイリストが1件だけなら、アイコン1回でそのプレイリストへ追加する。
    if (playlistsStore.playlists.length === 1) {
        await handleAddToPlaylist(result, playlistsStore.playlists[0].id)
        return
    }

    playlistCreationTargetKey.value = null
    playlistMenuTargetKey.value = playlistMenuTargetKey.value === key ? null : key
}

const openPlaylistCreate = (result) => {
    errorMessage.value = ''
    playlistMenuTargetKey.value = null
    playlistCreationTargetKey.value = resultKey(result)
    newPlaylistName.value = ''
    newPlaylistDescription.value = ''

    nextTick(() => playlistNameInput.value?.focus())
}

const closePlaylistCreate = () => {
    playlistCreationTargetKey.value = null
    newPlaylistName.value = ''
    newPlaylistDescription.value = ''
}

const handleCreatePlaylistAndAdd = async (result) => {
    const name = newPlaylistName.value.trim()

    if (!name) {
        errorMessage.value = 'プレイリスト名を入力してください。'
        return
    }

    successMessage.value = ''
    errorMessage.value = ''
    const key = resultKey(result)
    processingKey.value = key
    isCreatingPlaylist.value = true

    try {
        // 外部検索結果の取り込みに失敗した場合は、空のプレイリストを作らない。
        const songId = await resolveSongId(result)
        const playlist = await playlistsStore.createPlaylist({
            name,
            description: newPlaylistDescription.value.trim(),
        })

        await addPlaylistSong(playlist.id, { song_id: songId })
        successMessage.value = `「${playlist.name}」を作成し、プレイリストに追加しました。`
        closePlaylistCreate()
    } catch {
        errorMessage.value = 'プレイリストの作成または曲の追加に失敗しました。'
    } finally {
        processingKey.value = null
        isCreatingPlaylist.value = false
    }
}
</script>

<template>
    <main class="my-list">
        <header class="page-header">
            <p class="eyebrow">Songs</p>
            <h1>曲検索</h1>
            <p class="lead">曲名・アーティストから探して、お気に入りやプレイリストへ追加できます。</p>
        </header>

        <div class="song-search-toolbar">
            <label class="search-field">
                キーワード
                <input v-model="keyword" type="search" placeholder="Pretender" @keyup.enter="runSearch">
            </label>

            <label class="search-field search-filter-field">
                検索対象
                <select v-model="searchField">
                    <option value="all">すべて</option>
                    <option value="title">曲名</option>
                    <option value="artist">アーティスト</option>
                    <option value="album">アルバム</option>
                </select>
            </label>

            <label class="search-field search-filter-field">
                並び順
                <select v-model="sortMode">
                    <option value="relevance">おすすめ順</option>
                    <option value="title">曲名順</option>
                    <option value="artist">アーティスト順</option>
                    <option value="bpm">BPM順</option>
                </select>
            </label>

            <button type="button" class="secondary-button search-submit" :disabled="isSearching || keyword.trim().length < 1" @click="runSearch">
                {{ isSearching ? '検索中...' : '検索' }}
            </button>
            <button v-if="keyword" type="button" class="text-button" @click="clearSearch">
                クリア
            </button>
        </div>

        <section
            v-if="!keyword.trim() && !hasSearched"
            class="artist-recommendations"
            aria-labelledby="artist-recommendations-heading"
        >
            <div class="artist-recommendations-heading">
                <div>
                    <p class="eyebrow">Discover artists</p>
                    <h2 id="artist-recommendations-heading">人気アーティストから探す</h2>
                </div>
                <span class="artist-recommendations-icon" aria-hidden="true">♫</span>
            </div>
            <p class="artist-recommendations-description">気になるアーティストを選ぶと、そのアーティストの曲を検索します。</p>
            <div class="artist-recommendation-list">
                <button
                    v-for="artist in recommendedArtists"
                    :key="artist.name"
                    type="button"
                    class="artist-recommendation-button"
                    :aria-label="`${artist.name}でアーティスト検索`"
                    @click="chooseRecommendedArtist(artist.name)"
                >
                    <span class="artist-recommendation-mark" aria-hidden="true">♪</span>
                    <span>
                        <strong>{{ artist.name }}</strong>
                        <small>{{ artist.genre }}</small>
                    </span>
                    <span class="artist-recommendation-arrow" aria-hidden="true">→</span>
                </button>
            </div>
        </section>

        <p v-if="successMessage" class="status-message" aria-live="polite">
            {{ successMessage }}
        </p>

        <p v-if="errorMessage" class="error-message" role="alert">
            {{ errorMessage }}
        </p>

        <p v-if="hasSearched && !isSearching && searchResultCount > 0" class="search-result-summary" aria-live="polite">
            {{ searchResultCount }}件の曲
        </p>

        <p v-if="isSearching" class="status-message">
            読み込み中...
        </p>

        <aside v-if="!playlistsStore.isLoading && playlistsStore.playlists.length === 0 && visibleResults.length > 0" class="playlist-empty-guidance">
            <strong>プレイリストがまだありません</strong>
            <p>曲カードの＋から、作成と曲の追加を続けて行えます。</p>
        </aside>

        <p v-else-if="hasSearched && searchResultCount === 0" class="status-message">
            該当する曲がありません。
        </p>

        <section v-if="!isSearching && hasSearched && searchResultCount > 0" class="song-list">
            <article v-for="result in visibleResults" :key="resultKey(result)" class="song-card song-search-result-card">
                <div
                    role="button"
                    tabindex="0"
                    class="song-result-button"
                    :aria-label="`${result.title}の詳細を開く`"
                    @click="openResult(result)"
                    @keydown="handleResultKeydown($event, result)"
                >
                    <img v-if="result.artwork_url" :src="result.artwork_url" :alt="`${result.title}のジャケット`" class="song-artwork">
                    <div class="song-result-copy">
                        <p v-if="result.source === 'external'" class="external-source">検索サービスの候補</p>
                        <h2>{{ result.title }}</h2>
                        <p class="artist">{{ result.artist }}<span v-if="result.album"> / {{ result.album }}</span></p>
                        <p class="bpm">BPM {{ result.bpm ?? '未登録' }}</p>
                        <p class="opening-line">
                            <span>歌いだし</span>
                            {{ openingLine(result) }}
                        </p>
                    </div>

                </div>

                <div class="song-card-actions" @click.stop @keydown.stop>
                    <button
                        type="button"
                        class="icon-button favorite-button"
                        :class="{ 'is-favorite': isFavorite(result) }"
                        :disabled="processingKey === resultKey(result)"
                        :aria-pressed="isFavorite(result)"
                        :aria-label="isFavorite(result) ? 'お気に入りを解除' : 'お気に入りに追加'"
                        :title="isFavorite(result) ? 'お気に入りを解除' : 'お気に入りに追加'"
                        @click="handleAddFavorite(result)"
                    >
                        <span class="favorite-icon" aria-hidden="true">
                            {{ processingKey === resultKey(result) ? '…' : isFavorite(result) ? '♥' : '♡' }}
                        </span>
                        <span class="sr-only">{{ isFavorite(result) ? 'お気に入りを解除' : 'お気に入りに追加' }}</span>
                    </button>

                    <button
                        type="button"
                        class="icon-button playlist-action-button"
                        :disabled="playlistsStore.isLoading || processingKey === resultKey(result)"
                        :aria-expanded="playlistMenuTargetKey === resultKey(result)"
                        :aria-label="`${result.title}をプレイリストに追加`"
                        title="プレイリストに追加"
                        @click="togglePlaylistMenu(result)"
                    >
                        <span aria-hidden="true">＋</span>
                        <span class="sr-only">プレイリストに追加</span>
                    </button>
                </div>

                <div
                    v-if="playlistMenuTargetKey === resultKey(result)"
                    class="playlist-add-menu"
                    :aria-label="`${result.title}の追加先プレイリスト`"
                    @click.stop
                    @keydown.stop
                >
                    <span class="playlist-add-menu-label">追加先</span>
                    <template v-if="playlistsStore.playlists.length > 1">
                        <button
                            v-for="playlist in playlistsStore.playlists"
                            :key="playlist.id"
                            type="button"
                            class="playlist-option-button"
                            :disabled="processingKey === resultKey(result)"
                            @click="handleAddToPlaylist(result, playlist.id)"
                        >
                            {{ playlist.name }}
                        </button>
                    </template>
                    <button v-else type="button" class="playlist-option-button" @click="openPlaylistCreate(result)">
                        プレイリストを作成
                    </button>
                </div>

                <form
                    v-if="playlistCreationTargetKey === resultKey(result)"
                    class="inline-playlist-form"
                    @submit.prevent="handleCreatePlaylistAndAdd(result)"
                    @click.stop
                    @keydown.stop
                >
                    <label>
                        新しいプレイリスト名
                        <input ref="playlistNameInput" v-model="newPlaylistName" type="text" maxlength="100" required placeholder="例：練習用">
                    </label>
                    <label>
                        説明（任意）
                        <textarea v-model="newPlaylistDescription" rows="2" maxlength="2000" placeholder="例：今週練習する曲"></textarea>
                    </label>
                    <div class="inline-playlist-actions">
                        <button type="submit" :disabled="isCreatingPlaylist">
                            {{ isCreatingPlaylist ? '作成中...' : '作成して曲を追加' }}
                        </button>
                        <button type="button" class="secondary-button" :disabled="isCreatingPlaylist" @click="closePlaylistCreate">
                            キャンセル
                        </button>
                    </div>
                </form>

            </article>
        </section>
    </main>
</template>
