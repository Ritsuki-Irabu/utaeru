import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './style.css'
import App from './App.vue'
import router from './router'

createApp(App)
  .use(createPinia()) // 状態管理をVueアプリに登録
  .use(router) // 画面遷移機能をVueアプリに登録
  .mount('#app')
