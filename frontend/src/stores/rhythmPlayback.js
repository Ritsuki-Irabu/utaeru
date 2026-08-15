import { defineStore } from 'pinia'

let timer = null

const normalizeBpm = (value) => {
    const bpm = Number(value)

    return Number.isFinite(bpm) && bpm > 0 ? bpm : null
}

// リズム再生のタイマーを一元管理する。MV連動時だけ画面遷移後も内部状態を保持する。
export const useRhythmPlaybackStore = defineStore('rhythmPlayback', {
    state: () => ({
        isPlaying: false,
        currentBeat: 0,
        bpm: null,
        song: null,
        source: null,
    }),

    getters: {
        hasPlayback: (state) => Boolean(state.song && state.bpm),
    },

    actions: {
        start(song, bpm, source = 'manual') {
            const normalizedBpm = normalizeBpm(bpm)

            if (!song || !normalizedBpm) {
                return false
            }

            this.stop()
            this.song = {
                id: song.id,
                title: song.title,
                artist: song.artist,
            }
            this.bpm = normalizedBpm
            this.source = source
            this.currentBeat = 1
            this.isPlaying = true

            if (typeof window !== 'undefined') {
                timer = window.setInterval(() => {
                    this.currentBeat = this.currentBeat === 4 ? 1 : this.currentBeat + 1
                }, 60000 / normalizedBpm)
            }

            return true
        },

        stop() {
            if (timer !== null && typeof window !== 'undefined') {
                window.clearInterval(timer)
            }

            timer = null
            this.isPlaying = false
            this.currentBeat = 0
            this.bpm = null
            this.song = null
            this.source = null
        },
    },
})
