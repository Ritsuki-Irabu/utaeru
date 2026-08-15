import { test, expect } from '@playwright/test'
import { authenticate, expectHeading, songs } from './helpers'

const playlistSong = (id = 10, position = 1) => ({
    id,
    position,
    memo: '',
    added_by_user_id: 1,
    song: {
        ...songs[0],
        playback_provider: 'youtube',
        playback_key: 'youtube123',
        playback_url: null,
    },
})

test.describe('プレイリスト作成・共有・再生元リンク', () => {
    test('プレイリストを作成し、曲の追加・編集・共有ができる', async ({ page }) => {
        await authenticate(page)

        let currentPlaylist = {
            id: 1,
            name: '朝の練習',
            description: '',
            visibility: 'private',
            is_shared: false,
            songs: [],
        }

        await page.route('**://localhost/api/playlists**', async (route) => {
            const request = route.request()
            const url = new URL(request.url())

            if (request.method() === 'GET' && url.pathname === '/api/playlists') {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: [currentPlaylist] }) })
                return
            }

            if (request.method() === 'POST' && url.pathname === '/api/playlists') {
                expect(request.postDataJSON()).toEqual({ name: '朝の練習', description: '毎朝の練習曲' })
                currentPlaylist = { ...currentPlaylist, description: '毎朝の練習曲' }
                await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify(currentPlaylist) })
                return
            }

            if (url.pathname === '/api/playlists/1' && request.method() === 'GET') {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(currentPlaylist) })
                return
            }

            if (url.pathname === '/api/playlists/1/songs' && request.method() === 'POST') {
                currentPlaylist = { ...currentPlaylist, songs: [playlistSong()] }
                await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: currentPlaylist.songs[0] }) })
                return
            }

            if (url.pathname === '/api/playlists/1/share' && request.method() === 'POST') {
                currentPlaylist = { ...currentPlaylist, visibility: 'shared', is_shared: true }
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ share_url: 'http://localhost:5173/shared/playlists/test-token', playlist: currentPlaylist }) })
                return
            }

            await route.continue()
        })
        await page.route('**://localhost/api/songs**', async (route) => {
            const url = new URL(route.request().url())

            if (url.pathname === '/api/songs/1') {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(songs[0]) })
                return
            }

            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: songs }) })
        })

        await page.goto('/playlists')
        await expectHeading(page, 'プレイリスト一覧')
        await expect(page.getByRole('link', { name: /朝の練習を開く/ })).toBeVisible()
        await page.screenshot({ path: 'test-results/issue-19-playlist-list.png', fullPage: true })
        await page.getByRole('button', { name: '新規プレイリスト' }).click()
        await page.getByLabel('プレイリスト名').fill('朝の練習')
        await page.getByLabel('説明（任意）').fill('毎朝の練習曲')
        await page.getByRole('button', { name: 'プレイリストを作成' }).click()
        await expect(page).toHaveURL(/\/playlists\/1$/)
        await expectHeading(page, '朝の練習')

        await expect(page.getByRole('button', { name: 'プレイリストを管理' })).toBeVisible()
        await expect(page.getByRole('button', { name: '連続再生を開始' })).toBeVisible()
        await expect(page.getByLabel('追加する曲')).toHaveCount(0)
        await page.getByRole('button', { name: 'プレイリストを管理' }).click()
        await page.getByLabel('追加する曲').selectOption('1')
        await page.getByRole('button', { name: '追加' }).click()
        await expect(page.getByText('Pretender', { exact: true })).toBeVisible()
        await expect(page.getByRole('button', { name: 'Pretenderを再生' })).toHaveCount(0)
        await page.getByRole('button', { name: '共有リンクを発行・更新' }).click()
        await expect(page.locator('input[aria-label="共有リンク"]')).toHaveValue('http://localhost:5173/shared/playlists/test-token')
        await page.getByRole('button', { name: '管理を閉じる' }).click()
        await page.screenshot({ path: 'test-results/issue-19-playlist-detail.png', fullPage: true })

        await page.getByRole('link', { name: /Pretender/ }).click()
        await expect(page).toHaveURL(/\/songs\/1\?from=playlist&playlistId=1$/)
        await page.locator('#rhythm-section summary').click()
        await expect(page.getByRole('region', { name: 'Pretenderのリズム再生' })).toBeVisible()
    })

    test('共有プレイリストを閲覧し、自分の一覧へコピーできる', async ({ page }) => {
        await authenticate(page)
        const shared = { id: 3, name: '共有曲', description: 'みんなで練習', is_shared: true, songs: [playlistSong()] }

        await page.route('**://localhost/api/shared/playlists/share-token**', async (route) => {
            if (route.request().method() === 'GET') {
                await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(shared) })
                return
            }

            await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ id: 4 }) })
        })
        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(playlistSong().song) })
        })

        await page.goto('/shared/playlists/share-token')
        await expectHeading(page, '共有曲')
        await expect(page.getByText('みんなで練習')).toBeVisible()
        await expect(page.getByRole('link', { name: /Pretender/ })).toBeVisible()
        await page.getByRole('link', { name: /Pretender/ }).click()
        await expect(page).toHaveURL(/\/songs\/1\?from=shared&token=share-token$/)
        await page.locator('#mv-section summary').click()
        await expect(page.getByRole('button', { name: 'PretenderのMVを再生' })).toBeVisible()
        await expect(page.getByText('公式サービスで再生')).toHaveCount(0)
        await page.goBack()
        await page.getByRole('button', { name: '自分のプレイリストへコピー' }).click()
        await expect(page).toHaveURL(/\/playlists\/4$/)
        await page.screenshot({ path: 'test-results/issue-16-shared-playlist.png', fullPage: true })
    })
})
