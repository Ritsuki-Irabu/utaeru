<script setup>
import { onMounted } from 'vue'
import { useMySongsStore } from '../stores/mySongs'

const mySongsStore = useMySongsStore()

// 画面が表示されたタイミングで、Laravel APIからマイリストを取得する
onMounted(() => {
    mySongsStore.fetchMySongs()
})
</script>

<template>
    <main class="my-list">
        <header class="page-header">
            <p class="eyebrow">My Songs</p>
            <h1>マイリスト</h1>
            <p class="lead">登録した曲とメモ・タグを確認できます。</p>
        </header>

        <!-- 読み込み中・エラー・空・一覧ありの順番で表示を切り替える -->
        <p v-if="mySongsStore.isLoading" class="status-message">読み込み中...</p>

        <p v-else-if="mySongsStore.errorMessage" class="error-message">
            {{ mySongsStore.errorMessage }}
        </p>

        <p v-else-if="mySongsStore.songCount === 0" class="status-message">
            マイリストがありません。
        </p>

        <section v-else class="song-list">
            <article v-for="mySong in mySongsStore.songs" :key="mySong.id" class="song-card">
                <div>
                    <!-- APIレスポンスの song オブジェクトから曲情報を表示する -->
                    <h2>{{ mySong.song.title }}</h2>
                    <p class="artist">{{ mySong.song.artist }}</p>
                    <p class="bpm">BPM {{ mySong.song.bpm }}</p>
                </div>

                <p v-if="mySong.memo" class="memo">
                    メモ: {{ mySong.memo }}
                </p>

                <ul v-if="mySong.tags.length" class="tag-list">
                    <!-- tags は配列なので、v-for で1件ずつ表示する -->
                    <li v-for="tag in mySong.tags" :key="tag.id">
                        {{ tag.name }}
                    </li>
                </ul>
            </article>
        </section>
    </main>
</template>
