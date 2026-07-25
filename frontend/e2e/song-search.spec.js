import { test, expect } from '@playwright/test'
import { authenticate, expectHeading, mockSongs } from './helpers'

test.describe('曲検索とマイリスト追加', () => {
    test.beforeEach(async ({ page }) => {
        await authenticate(page)
        await mockSongs(page)
    })

    test('キーワードで曲を絞り込み、マイリストへ追加できる', async ({ page }) => {
        await page.route('**://localhost/api/my-songs', async (route) => {
            expect(route.request().method()).toBe('POST')
            expect(route.request().postDataJSON()).toEqual({ song_id: 1 })
            await route.fulfill({ status: 201, contentType: 'application/json', body: '{}' })
        })

        await page.goto('/songs')
        await expectHeading(page, '曲検索')

        await page.getByPlaceholder('Pretender').fill('YOASOBI')
        await expect(page.getByRole('heading', { name: '夜に駆ける' })).toBeVisible()
        await expect(page.getByRole('heading', { name: 'Pretender' })).toBeHidden()

        await page.getByPlaceholder('Pretender').fill('Pretender')
        const songCard = page.locator('.song-card').filter({ hasText: 'Pretender' })
        await songCard.getByRole('button', { name: '追加' }).click()
        await expect(page.getByText('マイリストに追加しました。')).toBeVisible()
        await expect(songCard.getByRole('button', { name: '追加済み' })).toBeDisabled()
    })

    test('検索結果がない場合は空状態を表示する', async ({ page }) => {
        await page.goto('/songs')
        await page.getByPlaceholder('Pretender').fill('存在しない曲')

        await expect(page.getByText('該当する曲がありません。')).toBeVisible()
    })
})
