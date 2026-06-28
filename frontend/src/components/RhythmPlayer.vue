<script setup>
import { computed, onUnmounted, ref } from 'vue'

const props = defineProps({
    song: {
        type: Object,
        required: true,
    },
})

const isPlaying = ref(false)
const currentBeat = ref(0)
let timer = null

// BPMは「1分間の拍数」なので、60000msをBPMで割ると1拍ごとの間隔になる
const beatInterval = computed(() => 60000 / props.song.bpm)

const stop = () => {
    isPlaying.value = false
    currentBeat.value = 0

    if (timer) {
        clearInterval(timer)
        timer = null
    }
}

const start = () => {
    stop()
    isPlaying.value = true
    currentBeat.value = 1

    timer = setInterval(() => {
        currentBeat.value = currentBeat.value === 4 ? 1 : currentBeat.value + 1
    }, beatInterval.value)
}

// 画面遷移などでコンポーネントが消えるとき、動き続けているタイマーを必ず止める
onUnmounted(() => {
    stop()
})
</script>

<template>
    <section class="rhythm-player" :aria-label="`${song.title}のリズム再生`">
        <div class="rhythm-header">
            <p class="rhythm-title">リズム確認</p>
            <p class="rhythm-interval">{{ Math.round(beatInterval) }}ms / 拍</p>
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

        <button v-if="!isPlaying" type="button" class="rhythm-button" @click="start">
            再生
        </button>
        <button v-else type="button" class="rhythm-button rhythm-button-stop" @click="stop">
            停止
        </button>
    </section>
</template>

<style scoped>
.rhythm-player {
    display: grid;
    gap: 14px;
    padding: 16px;
    border: 1px solid #ccfbf1;
    border-radius: 8px;
    background: #f0fdfa;
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
    color: #0f2928;
    font-weight: 800;
}

.rhythm-interval {
    color: #0f766e;
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
    background: #99f6e4;
    transform: scale(0.72);
    transition:
        background 0.08s ease,
        box-shadow 0.08s ease,
        transform 0.08s ease;
}

.beat.strong {
    background: #5eead4;
}

.beat.active {
    background: #0f766e;
    box-shadow: 0 0 0 6px rgb(15 118 110 / 16%);
    transform: scale(1);
}

.rhythm-button {
    width: 100%;
}

.rhythm-button-stop {
    background: #b91c1c;
}
</style>
