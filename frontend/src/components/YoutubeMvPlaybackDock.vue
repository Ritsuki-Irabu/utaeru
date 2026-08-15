<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useYoutubePlaybackStore } from '../stores/youtubePlayback'

const playback = useYoutubePlaybackStore()
const route = useRoute()
const iframe = ref(null)
const isSongDetail = computed(() => route.name === 'song-detail')
const teleportTarget = computed(() => isSongDetail.value ? '#song-detail-mv-host' : '#youtube-mv-mini-player-host')

const requestPlayback = () => {
    const target = iframe.value?.contentWindow

    if (!target) {
        return
    }

    const post = (message) => target.postMessage(JSON.stringify(message), 'https://www.youtube.com')

    post({ event: 'listening', id: 'utaeru-global-mv', channel: 'widget' })
    post({ event: 'command', func: 'addEventListener', args: ['onError'] })
}

const handleYoutubeMessage = (event) => {
    if (!['https://www.youtube.com', 'https://www.youtube-nocookie.com'].includes(event.origin)
        || (event.source && event.source !== iframe.value?.contentWindow)) {
        return
    }

    let message

    try {
        message = typeof event.data === 'string' ? JSON.parse(event.data) : event.data
    } catch {
        return
    }

    if (message?.event === 'onError') {
        void playback.handlePlayerError(playback.videoId)
    }
}

onMounted(() => {
    window.addEventListener('message', handleYoutubeMessage)
})

onUnmounted(() => {
    window.removeEventListener('message', handleYoutubeMessage)
})
</script>

<template>
    <Teleport :to="teleportTarget">
    <aside v-if="playback.isVisible && playback.embedUrl" class="youtube-mv-playback-dock" :class="{ 'is-detail': isSongDetail }" :aria-label="isSongDetail ? '曲詳細のMV再生' : 'バックグラウンドMV再生中'">
        <div class="youtube-mv-playback-dock-header">
            <div>
                <span class="eyebrow">MV再生中</span>
                <strong>{{ playback.song?.title }}</strong>
                <small>{{ playback.song?.artist }}</small>
                <RouterLink v-if="!isSongDetail && playback.song?.id" class="youtube-mv-mini-detail-link" :to="{ name: 'song-detail', params: { id: playback.song.id } }">
                    詳細でMVを表示
                </RouterLink>
            </div>
            <button type="button" class="secondary-button" @click="playback.closeMv(playback.song)">
                MVを閉じる
            </button>
        </div>
        <iframe
            ref="iframe"
            class="youtube-embed"
            :key="playback.videoId"
            :src="playback.embedUrl"
            :title="`${playback.song?.title}のYouTube再生`"
            referrerpolicy="strict-origin-when-cross-origin"
            allow="autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
            @load="requestPlayback"
        ></iframe>
        <p v-if="playback.isSwitchingCandidate" class="mv-switch-overlay" role="status">再生可能なMVを確認中...</p>
    </aside>
    </Teleport>
</template>
