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
                    bpm: 120,
                })
                await route.fulfill({ status: 201, contentType: 'application/json', body: '{}' })
                return
            }
            await route.continue()
        })

        await page.goto('/admin/songs')
        await page.getByLabel('曲名').first().fill('新曲')
        await page.getByLabel('アーティスト').first().fill('新アーティスト')
        await page.getByLabel('BPM').first().fill('120')
        await page.getByRole('button', { name: '登録' }).click()

        await expect(page.getByText('曲を登録しました。')).toBeVisible()
    })

    test('曲を編集・削除できる', async ({ page }) => {
        await page.route('**://localhost/api/songs/1', async (route) => {
            if (route.request().method() === 'PUT') {
                expect(route.request().postDataJSON()).toEqual({
                    title: 'Pretender 改',
                    artist: 'Official髭男dism',
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

        page.once('dialog', (dialog) => dialog.accept())
        await songCard.getByRole('button', { name: '削除' }).click()
        await expect(page.getByText('曲を削除しました。')).toBeVisible()
    })
})
