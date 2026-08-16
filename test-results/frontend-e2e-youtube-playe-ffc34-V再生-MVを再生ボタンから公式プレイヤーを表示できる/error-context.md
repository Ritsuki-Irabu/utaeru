# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: frontend/e2e/youtube-player.spec.js >> 曲詳細のYouTube MV再生 >> MVを再生ボタンから公式プレイヤーを表示できる
- Location: frontend/e2e/youtube-player.spec.js:5:5

# Error details

```
Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
Call log:
  - navigating to "/songs/1", waiting until "load"

```

# Test source

```ts
  1   | import { test, expect } from '@playwright/test'
  2   | import { authenticate, songs } from './helpers'
  3   | 
  4   | test.describe('曲詳細のYouTube MV再生', () => {
  5   |     test('MVを再生ボタンから公式プレイヤーを表示できる', async ({ page }) => {
  6   |         await authenticate(page)
  7   | 
  8   |         await page.route('**://localhost/api/songs/1', async (route) => {
  9   |             await route.fulfill({
  10  |                 status: 200,
  11  |                 contentType: 'application/json',
  12  |                 body: JSON.stringify({
  13  |                     data: {
  14  |                         ...songs[0],
  15  |                         lyrics: 'これはテスト用の歌詞です。\nリズムを確認します。',
  16  |                     },
  17  |                 }),
  18  |             })
  19  |         })
  20  | 
  21  |         await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
  22  |             expect(route.request().url()).toContain('official+music+video')
  23  |             await route.fulfill({
  24  |                 status: 200,
  25  |                 contentType: 'application/json',
  26  |                 body: JSON.stringify({
  27  |                     data: [{
  28  |                         provider: 'youtube',
  29  |                         video_id: 'youtube123',
  30  |                         title: 'Pretender - Official MV',
  31  |                         is_embeddable: true,
  32  |                     }],
  33  |                 }),
  34  |             })
  35  |         })
  36  | 
> 37  |         await page.goto('/songs/1')
      |                    ^ Error: page.goto: Protocol error (Page.navigate): Cannot navigate to invalid URL
  38  | 
  39  |         await expect(page.getByRole('heading', { name: '音を出さずにリズムを確認' })).toBeVisible()
  40  |         await expect(page.getByRole('heading', { name: 'MVをアプリ内で再生' })).toBeVisible()
  41  |         await expect(page.getByText('歌詞カード')).toBeVisible()
  42  |         await expect(page.getByText('これはテスト用の歌詞です。')).toBeVisible()
  43  |         await page.locator('.lyrics-card summary').click()
  44  |         await expect(page.locator('.lyrics-card-preview')).toContainText('歌いだし')
  45  |         await expect(page.locator('.lyrics-card-preview')).toContainText('これはテスト用の歌詞です。')
  46  |         await page.locator('.lyrics-card summary').click()
  47  |         await expect(page.getByText('YouTubeで動画を検索')).toHaveCount(0)
  48  |         await expect(page.getByRole('button', { name: 'PretenderのMVを再生' })).toBeVisible()
  49  |         await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()
  50  | 
  51  |         const youtubeIframe = page.locator('iframe[title="PretenderのYouTube再生"]')
  52  |         await expect(youtubeIframe).toBeVisible()
  53  |         await expect(youtubeIframe).toHaveAttribute('referrerpolicy', 'strict-origin-when-cross-origin')
  54  |         await expect(youtubeIframe).toHaveAttribute('src', /autoplay=1/)
  55  |         const iframeBox = await youtubeIframe.boundingBox()
  56  |         expect(iframeBox?.height).toBeGreaterThan(200)
  57  |         await expect(page.locator('script[src="https://www.youtube.com/iframe_api"]')).toHaveCount(0)
  58  |         await expect(page.getByText('再生・一時停止・音量・シークは動画内のYouTubeコントロールを操作してください。')).toBeVisible()
  59  |         await expect(page.getByRole('button', { name: 'Pretenderの再生を開始' })).toHaveCount(0)
  60  |         await expect(page.getByRole('button', { name: '別のMVを探す', exact: true })).toHaveCount(0)
  61  |         await page.screenshot({ path: 'test-results/issue-20-youtube-mv.png', fullPage: true })
  62  |     })
  63  | 
  64  |     test('登録済みMVは動画内の標準コントロールを表示する', async ({ page }) => {
  65  |         await authenticate(page)
  66  |         await page.addInitScript(() => {
  67  |             window.YT = {
  68  |                 PlayerState: { PLAYING: 1, PAUSED: 2, ENDED: 0 },
  69  |                 Player: class {
  70  |                     constructor(element, options) {
  71  |                         this.element = element
  72  |                         this.options = options
  73  |                         setTimeout(() => options.events.onReady({ target: this }), 0)
  74  |                     }
  75  | 
  76  |                     getIframe() { return this.element }
  77  |                     setVolume() {}
  78  |                     getCurrentTime() { return 0 }
  79  |                     getDuration() { return 180 }
  80  |                     playVideo() { this.options.events.onStateChange({ data: 1 }) }
  81  |                     pauseVideo() { this.options.events.onStateChange({ data: 2 }) }
  82  |                     stopVideo() { this.options.events.onStateChange({ data: 0 }) }
  83  |                     seekTo() {}
  84  |                     destroy() {}
  85  |                 },
  86  |             }
  87  |         })
  88  | 
  89  |         await page.route('**://localhost/api/songs/1', async (route) => {
  90  |             await route.fulfill({
  91  |                 status: 200,
  92  |                 contentType: 'application/json',
  93  |                 body: JSON.stringify({
  94  |                     data: {
  95  |                         ...songs[0],
  96  |                         playback_provider: 'youtube',
  97  |                         playback_key: 'registered123',
  98  |                     },
  99  |                 }),
  100 |             })
  101 |         })
  102 | 
  103 |         await page.goto('/songs/1')
  104 |         await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()
  105 | 
  106 |         await expect(page.getByLabel('音楽プレーヤー')).toHaveCount(0)
  107 |         await expect(page.getByRole('button', { name: 'Pretenderの再生を開始' })).toHaveCount(0)
  108 |         await expect(page.getByText('再生・一時停止・音量・シークは動画内のYouTubeコントロールを操作してください。')).toBeVisible()
  109 |     })
  110 | 
  111 |     test('MV検索APIが失敗した場合に再試行導線を表示できる', async ({ page }) => {
  112 |         await authenticate(page)
  113 | 
  114 |         await page.route('**://localhost/api/songs/1', async (route) => {
  115 |             await route.fulfill({
  116 |                 status: 200,
  117 |                 contentType: 'application/json',
  118 |                 body: JSON.stringify({ data: songs[0] }),
  119 |             })
  120 |         })
  121 | 
  122 |         await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
  123 |             await route.fulfill({
  124 |                 status: 502,
  125 |                 contentType: 'application/json',
  126 |                 body: JSON.stringify({ message: 'YouTube APIへの接続に失敗しました。' }),
  127 |             })
  128 |         })
  129 | 
  130 |         await page.goto('/songs/1')
  131 |         await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()
  132 | 
  133 |         await expect(page.getByRole('alert')).toContainText('YouTube APIへの接続に失敗しました。')
  134 |         await expect(page.getByRole('button', { name: 'PretenderのMVを再生' })).toBeVisible()
  135 |         await expect(page.getByText('YouTubeで動画を検索')).toHaveCount(0)
  136 |     })
  137 | 
```