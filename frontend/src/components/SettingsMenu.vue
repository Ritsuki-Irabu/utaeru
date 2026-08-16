<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { exportMySongs } from '../api/mySongs'

defineProps({
    isLoggingOut: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['logout', 'reset-category-order'])
const isOpen = ref(false)
const isExporting = ref(false)
const message = ref('')
const errorMessage = ref('')
const menuElement = ref(null)

const closeOnOutsideClick = (event) => {
    if (!menuElement.value?.contains(event.target)) {
        isOpen.value = false
    }
}

const closeOnEscape = (event) => {
    if (event.key === 'Escape') {
        isOpen.value = false
    }
}

const toggleMenu = () => {
    isOpen.value = !isOpen.value
    message.value = ''
    errorMessage.value = ''
}

const handleExport = async () => {
    isExporting.value = true
    message.value = ''
    errorMessage.value = ''

    try {
        const response = await exportMySongs()
        const blobUrl = URL.createObjectURL(response.data)
        const link = document.createElement('a')
        link.href = blobUrl
        link.download = 'favorites.csv'
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.setTimeout(() => URL.revokeObjectURL(blobUrl), 0)
        message.value = 'CSVを出力しました。'
    } catch {
        errorMessage.value = 'CSVの出力に失敗しました。'
    } finally {
        isExporting.value = false
    }
}

const handleResetCategoryOrder = () => {
    emit('reset-category-order')
    message.value = '曲詳細カテゴリの並び順を初期状態に戻しました。'
}

onMounted(() => {
    document.addEventListener('click', closeOnOutsideClick)
    document.addEventListener('keydown', closeOnEscape)
})

onUnmounted(() => {
    document.removeEventListener('click', closeOnOutsideClick)
    document.removeEventListener('keydown', closeOnEscape)
})
</script>

<template>
    <div ref="menuElement" class="settings-menu-wrap">
        <button
            type="button"
            class="icon-button settings-toggle"
            aria-label="設定"
            title="設定"
            :aria-expanded="isOpen"
            aria-controls="settings-menu"
            @click.stop="toggleMenu"
        >
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9.6 3.5h4.8l.6 2.1c.5.2 1 .5 1.4.8l2.1-.7 2.4 4.1-1.6 1.4c.1.5.1 1.1 0 1.6l1.6 1.4-2.4 4.1-2.1-.7c-.4.3-.9.6-1.4.8l-.6 2.1H9.6L9 18.4c-.5-.2-1-.5-1.4-.8l-2.1.7-2.4-4.1 1.6-1.4a6.4 6.4 0 0 1 0-1.6L3.1 9.8l2.4-4.1 2.1.7c.4-.3.9-.6 1.4-.8l.6-2.1Z" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="1.5" />
                <circle cx="12" cy="12" r="2.8" fill="none" stroke="currentColor" stroke-width="1.5" />
            </svg>
        </button>

        <div v-if="isOpen" id="settings-menu" class="settings-menu" role="menu" @click.stop>
            <p class="settings-menu-title">設定</p>
            <button type="button" class="settings-menu-item" :disabled="isExporting" @click="handleExport">
                <span class="settings-menu-item-icon" aria-hidden="true">⇩</span>
                <span>{{ isExporting ? 'CSVを出力中...' : 'お気に入りをCSV出力' }}</span>
            </button>
            <button type="button" class="settings-menu-item" @click="handleResetCategoryOrder">
                <span class="settings-menu-item-icon" aria-hidden="true">↕</span>
                <span>曲詳細の並び順をリセット</span>
            </button>
            <p v-if="message" class="settings-menu-message" role="status">{{ message }}</p>
            <p v-if="errorMessage" class="settings-menu-error" role="alert">{{ errorMessage }}</p>
            <button
                type="button"
                class="settings-menu-logout"
                :disabled="isLoggingOut"
                @click="emit('logout')"
            >
                {{ isLoggingOut ? 'ログアウト中...' : 'ログアウト' }}
            </button>
        </div>
    </div>
</template>
