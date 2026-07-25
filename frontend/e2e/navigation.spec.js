import { test, expect } from '@playwright/test'
import { admin, authenticate, expectHeading, mockSongs, user } from './helpers'

test.describe('画面遷移と認証ガード', () => {
    test('未認証ユーザーは保護画面からログイン画面へ転送される', async ({ page }) => {
        await page.goto('/')

        await expect(page).toHaveURL(/\/login$/)
        await expectHeading(page, 'ログイン')
    })

    test('ログイン成功後にマイリストへ遷移する', async ({ page }) => {
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
        await expectHeading(page, 'マイリスト')
    })

    test('一般ユーザーは管理画面からマイリストへ戻される', async ({ page }) => {
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
        await expectHeading(page, 'マイリスト')
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
        await expect(page.getByRole('link', { name: 'マイリスト' })).toBeVisible()
        await page.getByLabel('メインメニュー').getByRole('link', { name: '曲を探す' }).click()
        await expectHeading(page, '曲検索')

        await page.getByRole('button', { name: 'ログアウト' }).click()
        await expect(page).toHaveURL(/\/login$/)
    })
})
