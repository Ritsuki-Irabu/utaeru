import { test, expect } from '@playwright/test'
import { admin, authenticate, expectHeading, mockSongs, songs, user } from './helpers'

test.describe('画面遷移と認証ガード', () => {
    test('未認証ユーザーは保護画面からログイン画面へ転送される', async ({ page }) => {
        await page.goto('/')

        await expect(page).toHaveURL(/\/login$/)
        await expectHeading(page, 'ログイン')
    })

    test('ログイン成功後にお気に入りへ遷移する', async ({ page }) => {
        await page.route('**://localhost/api/auth/login', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ token: 'login-token', user }),
            })
        })
        await page.route('**://localhost/api/my-songs*', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: [] }),
            })
        })

        await page.goto('/login')
        await page.getByLabel('メールアドレス').fill(user.email)
        await page.getByLabel('パスワード').fill('password')
        await page.getByRole('button', { name: 'ログイン' }).click()

        await expect(page).toHaveURL(/\/$/)
        await expectHeading(page, 'お気に入り')
    })

    test('一般ユーザーは管理画面からお気に入りへ戻される', async ({ page }) => {
        await authenticate(page, user)
        await page.route('**://localhost/api/my-songs*', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: [] }),
            })
        })

        await page.goto('/admin/songs')

        await expect(page).toHaveURL(/\/$/)
        await expectHeading(page, 'お気に入り')
    })

    test('管理者は管理画面を表示できる', async ({ page }) => {
        await authenticate(page, admin)
        await mockSongs(page, [])

        await page.goto('/admin/songs')

        await expect(page).toHaveURL(/\/admin\/songs$/)
        await expectHeading(page, '曲マスタ管理')
        await expect(page.getByText('曲が登録されていません。')).toBeVisible()
        await expect(page.getByRole('link', { name: '管理画面' })).toBeVisible()
    })

    test('ログイン中は画面間ナビゲーションとログアウトが使える', async ({ page }) => {
        await authenticate(page, user)
        await page.route('**://localhost/api/my-songs*', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: [] }),
            })
        })
        await page.route('**://localhost/api/auth/logout', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'ログアウトしました。' }),
            })
        })
        await mockSongs(page, [])

        await page.goto('/')
        await expect(page.getByRole('link', { name: 'お気に入り' })).toBeVisible()
        await page.getByRole('button', { name: '設定' }).click()
        await expect(page.getByRole('menu').getByRole('button', { name: 'お気に入りをCSV出力' })).toBeVisible()
        await page.getByRole('button', { name: '設定' }).click()
        await page.getByLabel('メインメニュー').getByRole('link', { name: '曲を探す' }).click()
        await expectHeading(page, '曲検索')

        await page.getByRole('button', { name: '設定' }).click()
        await page.getByRole('menu').getByRole('button', { name: 'ログアウト' }).click()
        await expect(page).toHaveURL(/\/login$/)
    })

    test('曲詳細から検索画面へ戻ると検索条件を保持する', async ({ page }) => {
        await authenticate(page, user)
        await page.route('**://localhost/api/playlists', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: [] }),
            })
        })
        await page.route('**://localhost/api/songs/search**', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: [{ ...songs[1], source: 'catalog', is_imported: true }] }),
            })
        })
        await page.route('**://localhost/api/songs/2', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: songs[1] }),
            })
        })

        await page.goto('/songs')
        await page.getByPlaceholder('Pretender').fill('YOASOBI')
        await expect(page.getByRole('heading', { name: '夜に駆ける' })).toBeVisible()
        await page.locator('.song-result-button').click()
        await expect(page).toHaveURL(/\/songs\/2\?from=search&q=YOASOBI&field=all&sort=relevance$/)
        await page.getByRole('link', { name: '曲検索へ戻る' }).click()
        await expect(page).toHaveURL(/\/songs\?q=YOASOBI&field=all&sort=relevance$/)
        await expect(page.getByPlaceholder('Pretender')).toHaveValue('YOASOBI')
        await expect(page.getByRole('heading', { name: '夜に駆ける' })).toBeVisible()
    })
})
