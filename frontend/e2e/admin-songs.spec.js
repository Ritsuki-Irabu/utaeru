import { test, expect } from '@playwright/test'
import { admin, authenticate, mockSongs, songs } from './helpers'

test.describe('管理者の曲マスタ管理', () => {
    test.beforeEach(async ({ page }) => {
        await authenticate(page, admin)
        await mockSongs(page, songs)
    })

    test('曲を登録できる', async ({ page }) => {
        await page.route('**://localhost/api/songs', async (route) => {
            if (route.request().method() === 'POST') {
                expect(route.request().postDataJSON()).toEqual({
                    title: '新曲',
                    artist: '新アーティスト',
                    lyrics: '歌い出しの確認用テキスト',
                    bpm: 120,
                })
                await route.fulfill({ status: 201, contentType: 'application/json', body: '{}' })
                return
            }
            await route.continue()
        })

        await page.goto('/admin/songs')
        await expect(page.getByLabel('YouTube動画ID（任意）')).toHaveCount(0)
        await page.getByLabel('曲名').first().fill('新曲')
        await page.getByLabel('アーティスト').first().fill('新アーティスト')
        await page.getByLabel('歌詞（任意・許諾済み）').first().fill('歌い出しの確認用テキスト')
        await page.getByLabel('BPM').first().fill('120')
        await page.getByRole('button', { name: '登録' }).click()

        await expect(page.getByText('曲を登録しました。')).toBeVisible()
    })

    test('BPM未取得でも曲を登録できる', async ({ page }) => {
        await page.route('**://localhost/api/songs', async (route) => {
            if (route.request().method() === 'POST') {
                expect(route.request().postDataJSON()).toEqual({
                    title: 'BPM未取得曲',
                    artist: '検証アーティスト',
                    lyrics: null,
                    bpm: null,
                })
                await route.fulfill({ status: 201, contentType: 'application/json', body: '{}' })
                return
            }
            await route.continue()
        })

        await page.goto('/admin/songs')
        await page.getByLabel('曲名').first().fill('BPM未取得曲')
        await page.getByLabel('アーティスト').first().fill('検証アーティスト')
        await page.getByRole('button', { name: '登録' }).click()

        await expect(page.getByText('曲を登録しました。')).toBeVisible()
    })

    test('曲を編集・削除できる', async ({ page }) => {
        await page.route('**://localhost/api/songs/1/bpm/refresh', async (route) => {
            expect(route.request().method()).toBe('POST')
            await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: { ...songs[0], bpm: 93 } }) })
        })

        await page.route('**://localhost/api/songs/1', async (route) => {
            if (route.request().method() === 'PUT') {
                expect(route.request().postDataJSON()).toEqual({
                    title: 'Pretender 改',
                    artist: 'Official髭男dism',
                    lyrics: null,
                    bpm: 100,
                })
                await route.fulfill({ status: 200, contentType: 'application/json', body: '{}' })
                return
            }
            if (route.request().method() === 'DELETE') {
                await route.fulfill({ status: 204, body: '' })
                return
            }
            await route.continue()
        })

        await page.goto('/admin/songs')
        const songCard = page.locator('.song-card').filter({ hasText: 'Pretender' }).first()
        await songCard.getByRole('button', { name: '編集' }).click()
        const editForm = page.locator('.admin-edit-form')
        await editForm.getByLabel('曲名').fill('Pretender 改')
        await editForm.getByLabel('BPM').fill('100')
        await editForm.getByRole('button', { name: '更新' }).click()
        await expect(page.getByText('曲を更新しました。')).toBeVisible()

        await songCard.getByRole('button', { name: 'BPMを再取得' }).click()
        await expect(page.getByText('BPMを再取得しました。')).toBeVisible()

        page.once('dialog', (dialog) => dialog.accept())
        await songCard.getByRole('button', { name: '削除' }).click()
        await expect(page.getByText('曲を削除しました。')).toBeVisible()
    })

    test('管理画面から曲詳細を開いてMV再生を確認できる', async ({ page }) => {
        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    data: {
                        ...songs[0],
                        lyrics: '管理者向けテスト歌詞',
                    },
                }),
            })
        })
        await page.route('**://localhost/api/songs/1/lyrics', async (route) => {
            await route.fulfill({ status: 404, contentType: 'application/json', body: JSON.stringify({ data: null }) })
        })
        await page.route('**://localhost/api/songs/youtube/search**', async (route) => {
            expect(route.request().url()).toContain('song_id=1')
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({
                    data: [{ provider: 'youtube', video_id: 'admin-youtube123', title: 'Pretender Official MV', is_embeddable: true }],
                }),
            })
        })

        await page.goto('/admin/songs')
        const songCard = page.locator('.song-card').filter({ hasText: 'Pretender' }).first()
        await songCard.getByRole('link', { name: '詳細・MV' }).click()

        await expect(page).toHaveURL(/\/songs\/1\?from=admin$/)
        await expect(page.getByRole('link', { name: '管理画面へ戻る' })).toBeVisible()
        await page.locator('#mv-section summary').click()
        await page.getByRole('button', { name: 'PretenderのMVを再生' }).click()
        await expect(page.locator('iframe.youtube-embed')).toHaveAttribute('src', /admin-youtube123/)
    })
})
