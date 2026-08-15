<script setup>
import { computed, nextTick, onUnmounted, ref } from 'vue'
import { searchYoutubeVideos } from '../api/songs'

const props = defineProps({
    tracks: {
        type: Array,
        required: true,
    },
})

const playerContainer = ref(null)
const player = ref(null)
const currentIndex = ref(-1)
const currentVideoId = ref('')
const isPlayerVisible = ref(false)
const isLoading = ref(false)
const isPlaying = ref(false)
const errorMessage = ref('')
const resolvedVideoIds = ref({})
let youtubeApiPromise = null

const currentTrack = computed(() => props.tracks[currentIndex.value] ?? null)
const hasTracks = computed(() => props.tracks.length > 0)

const resolveVideoId = async (index) => {
    const track = props.tracks[index]

    if (!track?.song) {
        return null
    }

    if (resolvedVideoIds.value[index]) {
        return resolvedVideoIds.value[index]
    }

    if (track.song.playback_provider === 'youtube' && track.song.playback_key) {
        resolvedVideoIds.value = { ...resolvedVideoIds.value, [index]: track.song.playback_key }
        return track.song.playback_key
    }

    const response = await searchYoutubeVideos(track.song)
    const videoId = response.data.data?.[0]?.video_id

    if (videoId) {
        resolvedVideoIds.value = { ...resolvedVideoIds.value, [index]: videoId }
    }

    return videoId ?? null
}

const loadYoutubeApi = () => {
    if (window.YT?.Player) {
        return Promise.resolve(window.YT)
    }

    if (youtubeApiPromise) {
        return youtubeApiPromise
    }

    youtubeApiPromise = new Promise((resolve, reject) => {
        const previousCallback = window.onYouTubeIframeAPIReady
        const script = document.querySelector('script[src="https://www.youtube.com/iframe_api"]')
        let timeout
        const fail = (error) => {
            window.clearTimeout(timeout)
            youtubeApiPromise = null
            reject(error)
        }
        timeout = window.setTimeout(() => fail(new Error('YouTube Player API timeout')), 10000)

        window.onYouTubeIframeAPIReady = () => {
            window.clearTimeout(timeout)
            previousCallback?.()
            resolve(window.YT)
        }

        if (!script) {
            const newScript = document.createElement('script')
            newScript.src = 'https://www.youtube.com/iframe_api'
            newScript.addEventListener('error', () => fail(new Error('YouTube Player API load failed')), { once: true })
            document.head.appendChild(newScript)
        }
    })

    return youtubeApiPromise
}

const handlePlayerStateChange = (event) => {
    const states = window.YT?.PlayerState

    if (event.data === states?.PLAYING) {
        isPlaying.value = true
        isLoading.value = false
    }

    if (event.data === states?.PAUSED) {
        isPlaying.value = false
    }

    if (event.data === states?.ENDED) {
        isPlaying.value = false

        if (currentIndex.value < props.tracks.length - 1) {
            startFrom(currentIndex.value + 1)
        }
    }
}

const handlePlayerError = () => {
    isPlaying.value = false
    isPlayerVisible.value = false
    errorMessage.value = 'この曲のMVは再生できません。曲詳細から別のMVを選択してください。'
}

const createOrLoadPlayer = async (videoId) => {
    const YT = await loadYoutubeApi()
    await nextTick()

    if (!playerContainer.value) {
        throw new Error('YouTube player container is unavailable')
    }

    if (player.value) {
        player.value.loadVideoById(videoId)
        player.value.playVideo?.()
        return
    }

    // 初期表示では再生せず、ユーザーが「連続再生を開始」した後だけ再生する。
    const playerVars = { autoplay: 0, controls: 1, playsinline: 1, rel: 0 }

    if (/^https?:$/.test(window.location.protocol)) {
        playerVars.origin = window.location.origin
    }

    player.value = new YT.Player(playerContainer.value, {
        width: '100%',
        height: '100%',
        videoId,
        playerVars,
        events: {
            onReady: (event) => {
                event.target.getIframe()?.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin')
                // 連続再生の開始はこのコンポーネントの起点操作で行い、
                // 再生操作は表示された動画内の標準UIに任せる。
                event.target.playVideo()
            },
            onStateChange: handlePlayerStateChange,
            onError: handlePlayerError,
        },
    })
}

const startFrom = async (index = 0) => {
    if (!hasTracks.value || !props.tracks[index]) {
        return
    }

    currentIndex.value = index
    isLoading.value = true
    isPlayerVisible.value = true
    errorMessage.value = ''

    try {
        const videoId = await resolveVideoId(index)

        if (!videoId) {
            throw new Error('MV not found')
        }

        currentVideoId.value = videoId
        await createOrLoadPlayer(videoId)
    } catch (error) {
        isPlayerVisible.value = false
        errorMessage.value = error.response?.data?.message
            ?? 'プレイリストのMVを取得できませんでした。'
    } finally {
        isLoading.value = false
    }
}

const togglePlayback = () => {
    if (!isPlayerVisible.value) {
        startFrom(currentIndex.value >= 0 ? currentIndex.value : 0)
    }
}

const stopPlayback = () => {
    player.value?.stopVideo()
    isPlaying.value = false
    isPlayerVisible.value = false
}

onUnmounted(() => {
    player.value?.destroy()
    player.value = null
})
</script>

<template>
    <section class="playlist-queue-player" aria-label="プレイリスト連続再生">
        <div class="queue-player-header">
            <div>
                <p class="eyebrow">Queue</p>
                <h3>プレイリストを連続再生</h3>
                <p v-if="currentTrack" class="queue-current-track">
                    {{ currentIndex + 1 }} / {{ tracks.length }}　{{ currentTrack.song.title }}
                </p>
            </div>
            <div class="queue-player-actions">
                <button v-if="!isPlayerVisible" type="button" :disabled="!hasTracks || isLoading" @click="togglePlayback">
                    {{ isLoading ? '準備中...' : '連続再生を開始' }}
                </button>
                <button v-if="isPlayerVisible" type="button" class="secondary-button" @click="stopPlayback">
                    閉じる
                </button>
            </div>
        </div>

        <div v-if="isPlayerVisible" class="queue-video-frame">
            <div ref="playerContainer"></div>
        </div>
        <p v-if="!isPlayerVisible" class="detail-hint">曲は上から順に公式MVで再生されます。再生操作は動画内で行えます。</p>
        <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>
        <p v-if="currentVideoId && isPlayerVisible && !isLoading" class="queue-hint">曲が終わると次のMVへ自動で進みます。</p>
    </section>
</template>
