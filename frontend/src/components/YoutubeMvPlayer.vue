<script setup>
import { computed } from 'vue'
import { useYoutubePlaybackStore } from '../stores/youtubePlayback'

const props = defineProps({
    song: {
        type: Object,
        required: true,
    },
})

const playback = useYoutubePlaybackStore()
const isCurrentSong = computed(() => playback.song?.id === props.song.id)
const isPlayerVisible = computed(() => isCurrentSong.value && playback.isVisible)
const isLoading = computed(() => isCurrentSong.value && playback.isLoading)
const isSwitchingCandidate = computed(() => isCurrentSong.value && playback.isSwitchingCandidate)
const errorMessage = computed(() => isCurrentSong.value ? playback.errorMessage : '')
const playerErrorMessage = computed(() => isCurrentSong.value ? playback.playerErrorMessage : '')

const playMv = () => playback.playMv(props.song)
const findAnotherMv = () => playback.findAnotherMv(props.song)
const closeMv = () => playback.closeMv(props.song)
</script>

<template>
    <div class="youtube-mv-player" :aria-label="`${song.title}のMV再生`">
        <div v-if="!isPlayerVisible && playerErrorMessage" class="mv-error-state" role="alert">
            <div class="mv-error-copy">
                <span class="mv-error-icon" aria-hidden="true">!</span>
                <div>
                    <strong>このMVは再生できません</strong>
                    <p>{{ playerErrorMessage }}</p>
                </div>
            </div>
            <div class="mv-controls">
                <button type="button" class="mv-play-button" :disabled="isLoading" @click="findAnotherMv">
                    <span class="mv-play-icon" aria-hidden="true">↻</span>
                    <span class="mv-play-copy">
                        <strong>{{ isLoading ? '別のMVを検索中...' : '別のMVを探す' }}</strong>
                        <small>再生可能な候補を再検索</small>
                    </span>
                </button>
                <button type="button" class="secondary-button mv-close-button" @click="closeMv">
                    MVを閉じる
                </button>
            </div>
        </div>

        <button
            v-else-if="!isPlayerVisible"
            type="button"
            class="mv-play-button"
            :aria-label="`${song.title}のMVを再生`"
            :disabled="isLoading"
            @click="playMv"
        >
            <span class="mv-play-icon" aria-hidden="true">▶</span>
            <span class="mv-play-copy">
                <strong>{{ isLoading ? 'MVを準備中...' : '再生する' }}</strong>
            </span>
        </button>

        <template v-else>
            <div class="mv-controls">
                <button type="button" class="secondary-button mv-switch-button" :disabled="isLoading" @click="findAnotherMv">
                    {{ isLoading ? '別のMVを確認中...' : '別のMV' }}
                </button>
                <button type="button" class="secondary-button mv-close-button" :disabled="isLoading" @click="closeMv">
                    MVを閉じる
                </button>
            </div>
        </template>

        <p v-if="errorMessage" class="error-message" role="alert">{{ errorMessage }}</p>
    </div>
</template>
