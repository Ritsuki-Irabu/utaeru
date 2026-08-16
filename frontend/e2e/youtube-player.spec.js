import { test, expect } from '@playwright/test'
import { authenticate, songs } from './helpers'

test.describe('曲詳細のYouTube MV再生', () => {
    test('MVを再生ボタンから公式プレイヤーを表示できる', async ({ page }) => {
        await authenticate(page)

        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    data: {
                        ...songs[0],
                        lyrics: 'これはテスト用の歌詞です。\nリズムを確認します。',
                    },
                }),
            })
        })

        await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
            expect(route.request().url()).toContain('song_id=1')
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    data: [{
                        provider: 'youtube',
                        video_id: 'youtube123',
                        title: 'Pretender - Official MV',
                        bpm: 128,
                        is_embeddable: true,
                    }],
                }),
            })
        })

        await page.goto('/songs/1')

        await expect(page.getByRole('heading', { name: 'リズムを確認する' })).toBeVisible()
        await expect(page.getByRole('heading', { name: 'MVを再生する' })).toBeVisible()
        await expect(page.getByText('歌詞を表示する')).toBeVisible()
        await expect(page.locator('#rhythm-section summary .song-detail-panel-icon')).toContainText('♩')
        await expect(page.locator('#mv-section summary .song-detail-panel-icon')).toContainText('▶')
        await expect(page.locator('.lyrics-card summary .song-detail-panel-icon svg')).toBeVisible()
        await expect(page.getByText('MVは再生時に検索')).toHaveCount(0)
        await expect(page.getByText('MV再生可能')).toHaveCount(0)
        await expect(page.locator('#rhythm-section[open]')).toHaveCount(0)
        await expect(page.locator('#mv-section[open]')).toHaveCount(0)
        await expect(page.locator('.lyrics-card[open]')).toHaveCount(0)
        await expect(page.getByRole('img', { name: '歌いだし' })).toBeVisible()
        await expect(page.locator('.lyrics-card-preview')).toContainText('これはテスト用の歌詞です。')
        await page.locator('.lyrics-card summary').click()
        await expect(page.locator('.lyrics-text')).toContainText('これはテスト用の歌詞です。')
        await page.locator('.lyrics-card summary').click()
        await expect(page.getByText('YouTubeで動画を検索')).toHaveCount(0)
        await page.locator('#mv-section summary').click()
        await expect(page.getByRole('button', { name: 'PretenderのMVを再生' })).toBeVisible()
        await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()

        const youtubeIframe = page.locator('iframe[title="PretenderのYouTube再生"]')
        await expect(youtubeIframe).toBeVisible()
        await expect(youtubeIframe).toHaveAttribute('referrerpolicy', 'strict-origin-when-cross-origin')
        await expect(youtubeIframe).toHaveAttribute('src', /autoplay=0/)
        await expect(youtubeIframe).not.toHaveAttribute('src', /autoplay=1/)
        const iframeBox = await youtubeIframe.boundingBox()
        expect(iframeBox?.height).toBeGreaterThan(200)
        await expect(page.locator('script[src="https://www.youtube.com/iframe_api"]')).toHaveCount(0)
        await expect(page.getByText('再生・一時停止・音量・シークは動画内のYouTubeコントロールを操作してください。')).toHaveCount(0)
        await expect(page.getByRole('button', { name: 'Pretenderの再生を開始' })).toHaveCount(0)
        await expect(page.getByRole('button', { name: /別のMV/ }).first()).toBeVisible()
        await expect(page.getByRole('complementary', { name: 'バックグラウンドMV再生中' })).toHaveCount(0)
        await page.locator('#rhythm-section summary').click()
        const linkedRhythm = page.getByRole('region', { name: 'Pretenderのリズム再生' })
        await expect(linkedRhythm.getByRole('button', { name: '停止' })).toBeVisible()
        await expect(linkedRhythm.getByRole('button', { name: /タップでBPM計測/ })).toHaveCount(0)
        await page.screenshot({ path: 'test-results/issue-20-youtube-mv.png', fullPage: true })
    })

    test('MV再生中にお気に入りへ移動しても同じiframeを保持する', async ({ page }) => {
        await authenticate(page)
        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: songs[0] }),
            })
        })
        await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: [{ provider: 'youtube', video_id: 'background123', is_embeddable: true }] }),
            })
        })
        await page.route('**://localhost/api/my-songs*', async (route) => {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) })
        })
        await page.route('**://localhost/api/tags', async (route) => {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) })
        })

        await page.goto('/songs/1')
        await page.locator('#mv-section summary').click()
        await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()
        const iframe = page.locator('iframe[title="PretenderのYouTube再生"]')
        await expect(iframe).toHaveAttribute('src', /background123/)

        await page.getByRole('link', { name: 'お気に入り', exact: true }).click()
        await expect(page).toHaveURL(/\/$/)
        const miniPlayer = page.getByRole('complementary', { name: 'バックグラウンドMV再生中' })
        await expect(miniPlayer).toBeVisible()
        await expect(miniPlayer.getByRole('link', { name: '詳細でMVを表示' })).toBeVisible()
        await expect(miniPlayer.locator('iframe[title="PretenderのYouTube再生"]')).toBeHidden()
        await expect(page.locator('iframe[title="PretenderのYouTube再生"]')).toHaveAttribute('src', /background123/)
        await miniPlayer.getByRole('link', { name: '詳細でMVを表示' }).click()
        await expect(page).toHaveURL(/\/songs\/1$/)
        await expect(page.getByRole('complementary', { name: 'バックグラウンドMV再生中' })).toHaveCount(0)
        await expect(page.locator('iframe[title="PretenderのYouTube再生"]')).toBeVisible()
    })

    test('登録済みMVは動画内の標準コントロールを表示する', async ({ page }) => {
        await authenticate(page)
        await page.addInitScript(() => {
            window.YT = {
                PlayerState: { PLAYING: 1, PAUSED: 2, ENDED: 0 },
                Player: class {
                    constructor(element, options) {
                        this.element = element
                        this.options = options
                        setTimeout(() => options.events.onReady({ target: this }), 0)
                    }

                    getIframe() { return this.element }
                    setVolume() {}
                    getCurrentTime() { return 0 }
                    getDuration() { return 180 }
                    playVideo() { this.options.events.onStateChange({ data: 1 }) }
                    pauseVideo() { this.options.events.onStateChange({ data: 2 }) }
                    stopVideo() { this.options.events.onStateChange({ data: 0 }) }
                    seekTo() {}
                    destroy() {}
                },
            }
        })

        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    data: {
                        ...songs[0],
                        playback_provider: 'youtube',
                        playback_key: 'registered123',
                    },
                }),
            })
        })

        await page.goto('/songs/1')
        await page.locator('#mv-section summary').click()
        await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()

        await expect(page.getByLabel('音楽プレーヤー')).toHaveCount(0)
        await expect(page.getByRole('button', { name: 'Pretenderの再生を開始' })).toHaveCount(0)
        await expect(page.getByText('再生・一時停止・音量・シークは動画内のYouTubeコントロールを操作してください。')).toHaveCount(0)
    })

    test('MV検索APIが失敗した場合に再試行導線を表示できる', async ({ page }) => {
        await authenticate(page)

        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: songs[0] }),
            })
        })

        await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
            await route.fulfill({
                status: 502,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'YouTube APIへの接続に失敗しました。' }),
            })
        })

        await page.goto('/songs/1')
        await page.locator('#mv-section summary').click()
        await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()

        await expect(page.getByRole('alert')).toContainText('YouTube APIへの接続に失敗しました。')
        await expect(page.getByRole('button', { name: 'PretenderのMVを再生' })).toBeVisible()
        await expect(page.getByText('YouTubeで動画を検索')).toHaveCount(0)
    })

    test('埋め込み再生できないMVは次の候補へ切り替えられる', async ({ page }) => {
        await authenticate(page)

        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: songs[0] }),
            })
        })

        await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    data: [
                        { provider: 'youtube', video_id: 'blocked123', title: '埋め込み不可MV', is_embeddable: true },
                        { provider: 'youtube', video_id: 'fallback123', title: '代替MV', is_embeddable: true },
                    ],
                }),
            })
        })

        await page.goto('/songs/1')
        await page.locator('#mv-section summary').click()
        await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()
        await expect(page.locator('iframe.youtube-embed')).toHaveAttribute('src', /blocked123/)

        await page.evaluate(() => {
            window.dispatchEvent(new MessageEvent('message', {
                origin: 'https://www.youtube.com',
                data: JSON.stringify({ event: 'onError', info: 150 }),
            }))
        })

        await expect(page.locator('iframe.youtube-embed')).toHaveAttribute('src', /fallback123/)
    })

    test('MV検索の通信が切断された場合に再試行導線を表示できる', async ({ page }) => {
        await authenticate(page)

        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: songs[0] }),
            })
        })

        await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
            await route.abort('internetdisconnected')
        })

        await page.goto('/songs/1')
        await page.locator('#mv-section summary').click()
        await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()

        await expect(page.getByRole('alert')).toContainText('この曲のMVを見つけられませんでした')
        await expect(page.getByRole('button', { name: 'PretenderのMVを再生' })).toBeVisible()
    })

    test('登録歌詞がない場合も利用許諾済み歌詞APIの結果を歌詞カードへ表示できる', async ({ page }) => {
        await authenticate(page)

        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: { ...songs[0], lyrics: null } }),
            })
        })
        await page.route('**://localhost/api/songs/1/lyrics', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    data: {
                        lyrics: '提供元の歌い出しです。\nサビの歌詞です。',
                        source_url: 'https://licensed.example.test/songs/pretender',
                    },
                }),
            })
        })

        await page.goto('/songs/1')

        await page.locator('.lyrics-card summary').click()
        await expect(page.getByText('提供元の歌い出しです。')).toBeVisible()
        await expect(page.getByRole('link', { name: '歌詞提供元を開く' })).toHaveAttribute(
            'href',
            'https://licensed.example.test/songs/pretender',
        )
    })

    test('BPM未登録曲はタップテンポで暫定BPMを計測して再生できる', async ({ page }) => {
        await authenticate(page)

        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: { ...songs[0], bpm: null } }),
            })
        })

        await page.goto('/songs/1')
        await page.locator('#rhythm-section summary').click()

        const player = page.getByRole('region', { name: 'Pretenderのリズム再生' })
        const tapButton = player.getByRole('button', { name: /タップでBPM計測/ })
        await tapButton.click()
        await page.waitForTimeout(500)
        await tapButton.click()
        await page.waitForTimeout(500)
        await tapButton.click()
        await page.waitForTimeout(500)
        await tapButton.click()

        await expect(player.locator('.rhythm-interval')).toContainText('BPM')
        await expect(player.locator('.rhythm-interval')).toContainText('タップ計測')
        await player.getByRole('button', { name: '再生' }).click()
        await expect(player.getByRole('button', { name: '停止' })).toBeVisible()
    })

    test('曲詳細カテゴリをドラッグで並べ替え、再訪時も順番を保持できる', async ({ page }) => {
        await authenticate(page)

        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: { ...songs[0], lyrics: '歌詞カードの確認用です。' } }),
            })
        })

        await page.goto('/songs/1')

        const categoryList = page.locator('.song-detail-category-list > .song-detail-category')
        await expect(categoryList).toHaveCount(4)
        await expect(page.getByText('ドラッグして移動')).toHaveCount(0)
        await expect(page.getByText('移動', { exact: true })).toHaveCount(0)
        await expect(page.locator('.song-detail-drag-handle')).toHaveCount(0)
        await expect(page.locator('[data-category="rhythm"]')).toBeVisible()

        const sourceCategory = page.locator('[data-category="lyrics"]')
        const targetCategory = page.locator('[data-category="rhythm"]')
        await sourceCategory.scrollIntoViewIfNeeded()
        await sourceCategory.dragTo(targetCategory)

        await expect.poll(async () => page.locator('.song-detail-category-list > .song-detail-category').evaluateAll(
            (elements) => elements
                .sort((left, right) => Number(getComputedStyle(left).order) - Number(getComputedStyle(right).order))
                .map((element) => element.dataset.category),
        )).toEqual(['lyrics', 'rhythm', 'mv', 'info'])

        await page.reload()
        await expect.poll(async () => page.locator('.song-detail-category-list > .song-detail-category').evaluateAll(
            (elements) => elements
                .sort((left, right) => Number(getComputedStyle(left).order) - Number(getComputedStyle(right).order))
                .map((element) => element.dataset.category),
        )).toEqual(['lyrics', 'rhythm', 'mv', 'info'])
    })
})
