<script setup>
// 画面で使う変数や関数を書く
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { login } from '../api/auth'
import { useAuthStore } from '../stores/auth'

// ログイン画面の入力値を入れる箱
const email = ref('')
const password = ref('')
const errorMessage = ref('')
const isLoading = ref(false)

const router = useRouter()
const authStore = useAuthStore()

// ログインAPIを呼び、成功したら認証情報をStoreへ保存してマイリスト画面へ移動する
const handleSubmit = async () => {
    errorMessage.value = ''
    isLoading.value = true

    try {
        const response = await login({
            email: email.value,
            password: password.value,
        })

        authStore.setAuth(response.data)

        router.push('/')
    } catch {
        errorMessage.value = 'メールアドレスまたはパスワードが正しくありません。'
    } finally {
        isLoading.value = false
    }
}
</script>

<!-- HTML（画面の見た目）を書くエリア -->
<template>
    <main class="login">
        <section class="login-card">
            <p class="eyebrow">Utaeru</p>
            <h1>ログイン</h1>
            <p class="lead">メールアドレスとパスワードを入力してください。</p>

            <form class="login-form" @submit.prevent="handleSubmit">
                <label>
                    メールアドレス
                    <!-- email入力欄とemailをつなぐ書き方 -->
                    <input v-model="email" type="email" autocomplete="email" placeholder="user@example.com" required>
                </label>

                <!-- パスワード入力欄とpasswordをつなぐ書き方 -->
                <label>
                    パスワード
                    <input v-model="password" type="password" autocomplete="current-password" placeholder="password" required>
                </label>

                <p v-if="errorMessage" class="error-message">
                    {{ errorMessage }}
                </p>

                <button type="submit" :disabled="isLoading">
                    {{ isLoading ? 'ログイン中...' : 'ログイン' }}
                </button>
            </form>
        </section>
    </main>
</template>
