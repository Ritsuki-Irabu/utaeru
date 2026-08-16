<script setup>
import { computed } from 'vue'
import { useRhythmPlaybackStore } from '../stores/rhythmPlayback'

const playback = useRhythmPlaybackStore()
const bpmLabel = computed(() => `BPM ${Math.round(playback.bpm)}`)
</script>

<template>
    <aside v-if="playback.isPlaying && playback.song" class="rhythm-playback-dock" aria-label="バックグラウンド再生中">
        <div class="rhythm-playback-dock-copy">
            <span class="rhythm-playback-dock-icon" aria-hidden="true">♩</span>
            <div>
                <strong>{{ playback.song.title }}</strong>
                <small>{{ playback.song.artist }} · {{ bpmLabel }}</small>
            </div>
        </div>
        <div class="rhythm-playback-dock-beats" aria-label="現在の拍">
            <span
                v-for="beat in 4"
                :key="beat"
                :class="{ active: playback.currentBeat === beat, strong: beat === 1 }"
            />
        </div>
        <button type="button" class="rhythm-playback-dock-stop" aria-label="リズム再生を停止" @click="playback.stop">
            ■
        </button>
    </aside>
</template>
