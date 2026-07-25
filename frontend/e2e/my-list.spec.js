import { test, expect } from '@playwright/test'
import { authenticate, expectHeading, mySongs, tags } from './helpers'

test.describe('マイリストとリズム再生', () => {
    test.beforeEach(async ({ page }) => {
        await authenticate(page)
        await page.route('**://localhost/api/my-songs*', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: mySongs }),
            })
        })
        await page.route('**://localhost/api/tags', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: tags }),
            })
        })
    })

    test('マイリストの曲、メモ、タグを表示できる', async ({ page }) => {
        await page.goto('/')

        await expectHeading(page, 'マイリスト')
        await expect(page.getByRole('heading', { name: 'Pretender' })).toBeVisible()
        await expect(page.getByText('メモ: サビから練習')).toBeVisible()
        await expect(page.getByText('練習中', { exact: true })).toBeVisible()
    })

    test('空のマイリストから曲検索へ進める', async ({ page }) => {
        await page.unroute('**://localhost/api/my-songs*')
        await page.route('**://localhost/api/my-songs*', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: [] }),
            })
        })

        await page.goto('/')
        await expect(page.getByText('マイリストがありません。')).toBeVisible()
        await page.getByRole('main').getByRole('link', { name: '曲を探す' }).click()
        await expectHeading(page, '曲検索')
    })

    test('曲のリズムを再生・停止できる', async ({ page }) => {
        await page.goto('/')

        const player = page.getByRole('region', { name: 'Pretenderのリズム再生' })
        await expect(player.getByRole('button', { name: '再生' })).toBeVisible()
        await player.getByRole('button', { name: '再生' }).click()
        await expect(player.getByRole('button', { name: '停止' })).toBeVisible()
        await player.getByRole('button', { name: '停止' }).click()
        await expect(player.getByRole('button', { name: '再生' })).toBeVisible()
    })

    test('マイリスト取得失敗時にエラーを表示する', async ({ page }) => {
        await page.unroute('**://localhost/api/my-songs*')
        await page.route('**://localhost/api/my-songs*', async (route) => {
            await route.fulfill({ status: 500, contentType: 'application/json', body: '{}' })
        })

        await page.goto('/')

        await expect(page.getByText('マイリストの取得に失敗しました。')).toBeVisible()
    })

    test('メモとタグを編集できる', async ({ page }) => {
        await page.route('**://localhost/api/my-songs/10', async (route) => {
            if (route.request().method() === 'PUT') {
                expect(route.request().postDataJSON()).toEqual({
                    memo: '更新したメモ',
                    tag_ids: [1, 2],
                })
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ data: mySongs[0] }),
                })
                return
            }

            await route.continue()
        })

        await page.goto('/')
        const songCard = page.locator('.song-card').filter({ hasText: 'Pretender' }).first()
        await songCard.getByRole('button', { name: '編集' }).click()
        await songCard.getByLabel('メモ').fill('更新したメモ')
        await songCard.getByLabel('定番').check()
        await songCard.getByRole('button', { name: '更新' }).click()

        await expect(page.getByText('マイリストを更新しました。')).toBeVisible()
    })

    test('マイリストから曲を削除できる', async ({ page }) => {
        await page.route('**://localhost/api/my-songs/10', async (route) => {
            if (route.request().method() === 'DELETE') {
                await route.fulfill({ status: 204, body: '' })
                return
            }

            await route.continue()
        })

        await page.goto('/')
        const songCard = page.locator('.song-card').filter({ hasText: 'Pretender' }).first()
        page.once('dialog', (dialog) => dialog.accept())
        await songCard.getByRole('button', { name: '削除' }).click()

        await expect(page.getByText('マイリストから削除しました。')).toBeVisible()
    })
})
