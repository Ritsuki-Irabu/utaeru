<script setup>
import { computed, onDeactivated, onUnmounted, ref, watch } from 'vue'
import { useRhythmPlaybackStore } from '../stores/rhythmPlayback'

const props = defineProps({
    song: {
        type: Object,
        required: true,
    },
    savedBpm: {
        type: [Number, String],
        default: null,
    },
    canSaveBpm: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['save-bpm'])

const playback = useRhythmPlaybackStore()
const estimatedBpm = ref(null)
const tapTimes = ref([])

const hasRegisteredBpm = computed(() => Number.isFinite(Number(props.song.bpm)) && Number(props.song.bpm) > 0)
const canMeasureBpm = computed(() => !hasRegisteredBpm.value)
const personalBpm = computed(() => {
    if (hasRegisteredBpm.value) {
        return null
    }

    const bpm = Number(props.savedBpm)

    return Number.isFinite(bpm) && bpm >= 40 && bpm <= 300 ? Math.round(bpm) : null
})
const effectiveBpm = computed(() => personalBpm.value ?? estimatedBpm.value ?? (hasRegisteredBpm.value ? Number(props.song.bpm) : null))
const hasBpm = computed(() => Number.isFinite(Number(effectiveBpm.value)) && Number(effectiveBpm.value) > 0)
const bpmStorageKey = computed(() => `utaeru.bpm-estimate.${props.song.id ?? 'unknown'}`)
const isCurrentSongPlaying = computed(() => playback.isPlaying && playback.song?.id === props.song.id)
const currentBeat = computed(() => isCurrentSongPlaying.value ? playback.currentBeat : 0)

const loadEstimatedBpm = () => {
    estimatedBpm.value = null
    tapTimes.value = []

    if (typeof window === 'undefined') {
        return
    }

    const saved = Number(window.localStorage.getItem(bpmStorageKey.value))

    if (Number.isFinite(saved) && saved >= 40 && saved <= 240) {
        estimatedBpm.value = Math.round(saved)
    }
}

const tapTempo = () => {
    const now = Date.now()
    const recentTaps = [...tapTimes.value, now].slice(-8)
    tapTimes.value = recentTaps

    if (recentTaps.length < 2) {
        return
    }

    const intervals = recentTaps.slice(1).map((time, index) => time - recentTaps[index])
    const averageInterval = intervals.reduce((total, interval) => total + interval, 0) / intervals.length
    let bpm = Math.round(60000 / averageInterval)

    if (!Number.isFinite(bpm) || bpm <= 0) {
        return
    }

    // 倍テン・半テンでタップした場合も、練習に使える一般的な範囲へ補正する。
    while (bpm < 40) {
        bpm *= 2
    }

    while (bpm > 240) {
        bpm = Math.round(bpm / 2)
    }

    bpm = Math.max(40, Math.min(240, bpm))

    estimatedBpm.value = bpm

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(bpmStorageKey.value, String(bpm))
    }
}

const saveMeasuredBpm = () => {
    if (estimatedBpm.value && canMeasureBpm.value && props.canSaveBpm) {
        emit('save-bpm', estimatedBpm.value)
    }
}

const resetTapTempo = () => {
    tapTimes.value = []

    estimatedBpm.value = null

    if (typeof window !== 'undefined') {
        window.localStorage.removeItem(bpmStorageKey.value)
    }
}

const start = () => {
    if (!hasBpm.value) {
        return
    }

    playback.start(props.song, effectiveBpm.value, 'manual')
}

const stop = () => {
    if (playback.song?.id === props.song.id) {
        playback.stop()
    }
}

watch(() => props.song.id, () => {
    if (playback.song?.id && playback.song.id !== props.song.id) {
        playback.stop()
    }

    loadEstimatedBpm()
}, { immediate: true })

// テンポは詳細画面内の練習用機能。MVとは異なり、画面を離れたらタイマーを止める。
onDeactivated(() => {
    if (playback.song?.id === props.song.id && playback.source !== 'mv') {
        playback.stop()
    }
})

onUnmounted(() => {
    if (playback.song?.id === props.song.id && playback.source !== 'mv') {
        playback.stop()
    }
})
</script>

<template>
    <section class="rhythm-player" :aria-label="`${song.title}のリズム再生`">
        <div class="rhythm-header">
            <p class="rhythm-title">リズム確認</p>
            <p class="rhythm-interval">
                {{ personalBpm ? `BPM ${personalBpm}（保存値）` : estimatedBpm ? `BPM ${estimatedBpm}（タップ計測）` : hasRegisteredBpm ? `BPM ${song.bpm}` : 'BPM未登録' }}
            </p>
        </div>

        <div class="beats" aria-hidden="true">
            <span
                v-for="beat in 4"
                :key="beat"
                class="beat"
                :class="{
                    active: currentBeat === beat,
                    strong: beat === 1,
                }"
            />
        </div>

        <div v-if="canMeasureBpm" class="rhythm-tap-actions">
            <button type="button" class="secondary-button rhythm-tap-button" @click="tapTempo">
                タップでBPM計測{{ tapTimes.length ? `（${tapTimes.length}回）` : '' }}
            </button>
            <button v-if="estimatedBpm && canSaveBpm && estimatedBpm !== personalBpm" type="button" class="secondary-button rhythm-save-button" @click="saveMeasuredBpm">
                計測値を登録
            </button>
            <button v-if="estimatedBpm" type="button" class="text-button rhythm-reset-button" @click="resetTapTempo">
                リセット
            </button>
        </div>
        <p v-if="estimatedBpm && canMeasureBpm && !canSaveBpm" class="rhythm-save-hint">お気に入りに追加すると計測値を登録できます。</p>
        <button v-if="hasBpm && !isCurrentSongPlaying" type="button" class="rhythm-button" @click="start">
            再生
        </button>
        <button v-else-if="hasBpm" type="button" class="rhythm-button rhythm-button-stop" @click="stop">
            停止
        </button>
        <p v-else class="rhythm-unavailable">上のボタンを一定の間隔で4回以上タップすると、暫定BPMで確認できます。</p>
    </section>
</template>

<style scoped>
.rhythm-player {
    display: grid;
    gap: 14px;
    padding: 16px;
    border: 1px solid #26364d;
    border-radius: 8px;
    background: #0f172a;
}

.rhythm-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.rhythm-title,
.rhythm-interval {
    margin: 0;
}

.rhythm-title {
    color: #f8fafc;
    font-weight: 800;
}

.rhythm-interval {
    color: #5eead4;
    font-size: 0.9rem;
    font-weight: 700;
}

.beats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    align-items: center;
}

.beat {
    aspect-ratio: 1;
    border-radius: 999px;
    background: #24505a;
    transform: scale(0.72);
    transition:
        background 0.08s ease,
        box-shadow 0.08s ease,
        transform 0.08s ease;
}

.beat.strong {
    background: #2d7880;
}

.beat.active {
    background: #14b8a6;
    box-shadow: 0 0 0 6px rgb(20 184 166 / 20%);
    transform: scale(1);
}

.rhythm-button {
    width: 100%;
}

.rhythm-button-stop {
    background: #b91c1c;
}

.rhythm-tap-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
}

.rhythm-tap-button {
    flex: 1 1 220px;
}

.rhythm-reset-button {
    min-height: 40px;
}

.rhythm-unavailable {
    margin: 0;
    color: #94a3b8;
    font-size: 0.9rem;
}
</style>
