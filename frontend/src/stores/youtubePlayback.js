import { defineStore } from 'pinia'
import { searchYoutubeVideos } from '../api/songs'
import { useRhythmPlaybackStore } from './rhythmPlayback'

const songSnapshot = (song) => song
    ? { id: song.id, title: song.title, artist: song.artist }
    : null

const normalizeBpm = (value) => {
    const bpm = Number(value)

    return Number.isFinite(bpm) && bpm >= 40 && bpm <= 300 ? Math.round(bpm) : null
}

// YouTube iframeを画面遷移の外側で1つだけ保持するストア。
// ルートコンポーネントの再生成やカテゴリ並び替えで、再生位置を失わないようにする。
export const useYoutubePlaybackStore = defineStore('youtubePlayback', {
    state: () => ({
        song: null,
        videoId: '',
        candidateVideos: [],
        candidateIndex: -1,
        isVisible: false,
        isLoading: false,
        isSwitchingCandidate: false,
        errorMessage: '',
        playerErrorMessage: '',
        unavailableVideoIds: [],
    }),

    getters: {
        embedUrl: (state) => {
            if (!state.videoId) {
                return ''
            }

            const params = new URLSearchParams({
                autoplay: '0',
                controls: '1',
                enablejsapi: '1',
                modestbranding: '1',
                playsinline: '1',
                rel: '0',
            })

            if (typeof window !== 'undefined' && /^https?:$/.test(window.location.protocol)) {
                params.set('origin', window.location.origin)
                params.set('widget_referrer', window.location.href)
            }

            return `https://www.youtube.com/embed/${encodeURIComponent(state.videoId)}?${params.toString()}`
        },
    },

    actions: {
        syncRhythm(song, bpm) {
            const rhythm = useRhythmPlaybackStore()
            const linkedBpm = normalizeBpm(bpm) ?? normalizeBpm(song?.bpm)

            if (linkedBpm) {
                rhythm.start(song, linkedBpm, 'mv')
            } else if (rhythm.song?.id === song?.id && rhythm.source === 'mv') {
                rhythm.stop()
            }
        },

        useSong(song) {
            if (this.song?.id === song?.id) {
                return
            }

            this.song = songSnapshot(song)
            this.videoId = song?.playback_provider === 'youtube' ? song.playback_key ?? '' : ''
            this.candidateVideos = []
            this.candidateIndex = -1
            this.isVisible = false
            this.isSwitchingCandidate = false
            this.errorMessage = ''
            this.playerErrorMessage = ''
        },

        async showCandidate(song, candidate, index) {
            this.song = songSnapshot(song)
            this.videoId = candidate.video_id
            this.candidateIndex = index
            this.playerErrorMessage = ''
            this.isVisible = true
            // YouTube検索結果に明記されたBPMを優先し、なければ曲マスタ値を使う。
            this.syncRhythm(song, candidate.bpm)
        },

        async searchCandidates(song) {
            const response = await searchYoutubeVideos(song)
            const candidates = (response.data.data ?? []).filter((candidate) => candidate?.video_id
                && candidate.is_embeddable !== false
                && !this.unavailableVideoIds.includes(candidate.video_id))

            if (candidates.length === 0) {
                throw new Error('MV not found')
            }

            this.candidateVideos = candidates
            await this.showCandidate(song, candidates[0], 0)
        },

        async searchAndPlayMv(song) {
            this.song = songSnapshot(song)
            this.errorMessage = ''
            this.playerErrorMessage = ''
            this.isSwitchingCandidate = false
            this.isLoading = true
            this.isVisible = false
            this.videoId = ''

            try {
                await this.searchCandidates(song)
            } catch (error) {
                this.errorMessage = error.response?.data?.message
                    ?? 'この曲のMVを見つけられませんでした。時間をおいて再度お試しください。'
                this.syncRhythm(song, null)
            } finally {
                this.isLoading = false
            }
        },

        async playMv(song) {
            this.errorMessage = ''

            if (this.song?.id !== song.id) {
                this.song = songSnapshot(song)
                this.videoId = song.playback_provider === 'youtube' ? song.playback_key ?? '' : ''
                this.candidateVideos = []
                this.candidateIndex = -1
                this.playerErrorMessage = ''
            }

            if (this.videoId && !this.playerErrorMessage) {
                this.isVisible = true
                this.syncRhythm(song, song.bpm)
                return
            }

            await this.searchAndPlayMv(song)
        },

        async findAnotherMv(song) {
            this.errorMessage = ''
            this.playerErrorMessage = ''

            if (this.song?.id !== song.id) {
                this.song = songSnapshot(song)
            }

            if (this.candidateIndex >= 0 && this.candidateIndex < this.candidateVideos.length - 1) {
                this.isLoading = true

                try {
                    await this.showCandidate(song, this.candidateVideos[this.candidateIndex + 1], this.candidateIndex + 1)
                } finally {
                    this.isLoading = false
                }

                return
            }

            if (this.videoId && !this.unavailableVideoIds.includes(this.videoId)) {
                this.unavailableVideoIds = [...this.unavailableVideoIds, this.videoId]
            }

            await this.searchAndPlayMv(song)
        },

        async handlePlayerError(failedVideoId) {
            if (!this.isVisible || !this.song) {
                return
            }

            const failedId = failedVideoId || this.videoId

            if (failedId && !this.unavailableVideoIds.includes(failedId)) {
                this.unavailableVideoIds = [...this.unavailableVideoIds, failedId]
            }

            if (this.candidateIndex >= 0 && this.candidateIndex < this.candidateVideos.length - 1) {
                this.isSwitchingCandidate = true

                try {
                    await this.findAnotherMv(this.song)
                } finally {
                    this.isSwitchingCandidate = false
                }

                return
            }

            this.isVisible = false
            this.videoId = ''
            this.playerErrorMessage = 'このMVは埋め込み再生できません。別のMVを検索してください。'
            this.errorMessage = ''
            this.syncRhythm(this.song, null)
        },

        closeMv(song) {
            if (this.song?.id !== song.id) {
                return
            }

            this.isVisible = false
            this.isSwitchingCandidate = false
            this.playerErrorMessage = ''
            this.errorMessage = ''
            this.videoId = ''
            const rhythm = useRhythmPlaybackStore()

            if (rhythm.song?.id === song.id && rhythm.source === 'mv') {
                rhythm.stop()
            }
        },
    },
})
