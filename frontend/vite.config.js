import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      // ViteにPWA機能を追加する
      registerType: 'autoUpdate',
      manifest: { // スマホに追加したときのアプリ名・色・アイコンなどの設定
        name: 'ウタエル',
        short_name: 'ウタエル',
        description: 'カラオケ直前にBPMでリズムを確認するアプリ',
        theme_color: '#0f766e',
        background_color: '#ffffff',
        display: 'standalone',
        start_url: '/',
        icons: [
          {
            src: '/pwa-192x192.png',
            sizes: '192x192',
            type: 'image/png',
          },
          {
            src: '/pwa-512x512.png',
            sizes: '512x512',
            type: 'image/png',
          },
        ],
      },
    }),
  ],
})
