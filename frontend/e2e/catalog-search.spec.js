import { test, expect } from '@playwright/test'
import { authenticate } from './helpers'

test.describe('ユーザー向け曲カタログ検索', () => {
    test('外部検索結果をお気に入りとプレイリストへ追加できる', async ({ page }) => {
        await authenticate(page)

        const externalSong = {
            id: null,
            provider: 'itunes',
            provider_key: '12345',
            source: 'external',
            is_imported: false,
            title: '新しい曲',
            artist: '新しいアーティスト',
            album: '新しいアルバム',
            artwork_url: null,
            bpm: null,
            playback_provider: 'itunes',
            playback_key: '12345',
            playback_url: 'https://music.apple.com/jp/song/12345',
        }

        await page.route('**://localhost/api/songs**', async (route) => {
            const request = route.request()

            if (request.method() === 'GET') {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [externalSong] }) })
                return
            }

            expect(request.postDataJSON()).toEqual({ provider: 'itunes', provider_key: '12345' })
            await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ ...externalSong, id: 20 }) })
        })
        await page.route('**://localhost/api/playlists', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: [{ id: 10, name: '練習曲', song_count: 0 }] }),
            })
        })
        await page.route('**://localhost/api/my-songs', async (route) => {
            if (route.request().method() === 'GET') {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ data: [] }),
                })
                return
            }

            expect(route.request().method()).toBe('POST')
            expect(route.request().postDataJSON()).toEqual({ song_id: 20 })
            await route.fulfill({
                status: 201,
                contentType: 'application/json',
                body: JSON.stringify({ data: { id: 20, song: { id: 20, title: '新しい曲', artist: '新しいアーティスト' }, tags: [] } }),
            })
        })
        await page.route('**://localhost/api/playlists/10/songs', async (route) => {
            expect(route.request().postDataJSON()).toEqual({ song_id: 20 })
            await route.fulfill({ status: 201, contentType: 'application/json', body: '{}' })
        })

        await page.goto('/songs')
        await page.getByPlaceholder('Pretender').fill('新しい曲')
        await expect(page.getByRole('heading', { name: '新しい曲' })).toBeVisible()

        const songCard = page.locator('.song-card').filter({ hasText: '新しい曲' })
        await songCard.getByRole('button', { name: 'お気に入りに追加', exact: true }).click()
        await expect(page.getByText('お気に入りに追加しました。')).toBeVisible()

        await songCard.getByRole('button', { name: '新しい曲をプレイリストに追加' }).click()
        await expect(page.getByText('プレイリストに追加しました。')).toBeVisible()
        await page.screenshot({ path: 'test-results/issue-17-catalog-search.png', fullPage: true })
    })

    test('プレイリスト未作成でも曲検索画面から作成して追加できる', async ({ page }) => {
        await authenticate(page)

        await page.route('**://localhost/api/songs**', async (route) => {
            const request = route.request()

            if (request.method() === 'GET') {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ data: [{
                        id: null,
                        provider: 'itunes',
                        provider_key: '67890',
                        source: 'external',
                        title: '最初の曲',
                        artist: '最初のアーティスト',
                        album: null,
                        artwork_url: null,
                        bpm: null,
                    }] }),
                })
                return
            }

            expect(request.postDataJSON()).toEqual({ provider: 'itunes', provider_key: '67890' })
            await route.fulfill({
                status: 201,
                contentType: 'application/json',
                body: JSON.stringify({ data: { id: 21, title: '最初の曲', artist: '最初のアーティスト' } }),
            })
        })

        await page.route('**://localhost/api/playlists', async (route) => {
            const request = route.request()

            if (request.method() === 'GET') {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [] }) })
                return
            }

            expect(request.postDataJSON()).toEqual({ name: 'まずは練習', description: '' })
            await route.fulfill({
                status: 201,
                contentType: 'application/json',
                body: JSON.stringify({ data: { id: 11, name: 'まずは練習', song_count: 0 } }),
            })
        })
        await page.route('**://localhost/api/playlists/11/songs', async (route) => {
            expect(route.request().postDataJSON()).toEqual({ song_id: 21 })
            await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({}) })
        })

        await page.goto('/songs')
        await page.getByPlaceholder('Pretender').fill('最初の曲')
        await expect(page.getByRole('heading', { name: '最初の曲' })).toBeVisible()
        await expect(page.getByText('プレイリストがまだありません')).toBeVisible()

        const songCard = page.locator('.song-card').filter({ hasText: '最初の曲' })
        await songCard.getByRole('button', { name: '最初の曲をプレイリストに追加' }).click()
        await songCard.getByRole('button', { name: 'プレイリストを作成' }).click()
        await songCard.getByLabel('新しいプレイリスト名').fill('まずは練習')
        await songCard.getByRole('button', { name: '作成して曲を追加' }).click()
        await expect(page.getByText('「まずは練習」を作成し、プレイリストに追加しました。')).toBeVisible()
    })
})
