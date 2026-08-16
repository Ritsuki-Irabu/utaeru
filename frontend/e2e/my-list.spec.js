import { test, expect } from '@playwright/test'
import { authenticate, expectHeading, mySongs, songs, tags } from './helpers'

test.describe('お気に入りと曲詳細再生', () => {
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
            if (route.request().method() === 'POST') {
                const { name } = route.request().postDataJSON()
                await route.fulfill({
                    status: 201,
                    contentType: 'application/json',
                    body: JSON.stringify({ data: { id: 3, name } }),
                })
                return
            }

            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: tags }),
            })
        })
        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: songs[0] }),
            })
        })
    })

    test('お気に入りの曲、メモ、タグを表示できる', async ({ page }) => {
        await page.goto('/')

        await expectHeading(page, 'お気に入り')
        await page.getByRole('button', { name: '設定' }).click()
        await expect(page.getByRole('menu').getByRole('button', { name: 'お気に入りをCSV出力' })).toBeVisible()
        await page.getByRole('button', { name: '設定' }).click()
        await expect(page.getByPlaceholder('曲名・アーティスト・メモ・タグ')).toBeVisible()
        await expect(page.getByRole('heading', { name: 'Pretender' })).toBeVisible()
        await expect(page.getByText('メモ: サビから練習')).toBeVisible()
        await expect(page.getByText('練習中', { exact: true })).toBeVisible()
        await page.screenshot({ path: 'test-results/issue-21-compact-song-list.png', fullPage: true })
    })

    test('お気に入り一覧の歌いだしを歌詞APIから補完する', async ({ page }) => {
        await page.route('**://localhost/api/songs/1/lyrics', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: { lyrics: 'お気に入りの歌いだしです。\nサビです。', opening_line: 'お気に入りの歌いだしです。' } }),
            })
        })

        await page.goto('/')
        await expect(page.getByText('お気に入りの歌いだしです。')).toBeVisible()
    })

    test('お気に入りを曲名・アーティスト・メモ・タグで検索できる', async ({ page }) => {
        await page.goto('/')

        await page.getByPlaceholder('曲名・アーティスト・メモ・タグ').fill('Official髭男dism')
        await expect(page.getByRole('heading', { name: 'Pretender' })).toBeVisible()

        await page.getByPlaceholder('曲名・アーティスト・メモ・タグ').fill('存在しない曲')
        await expect(page.getByText('検索条件に一致するお気に入りがありません。')).toBeVisible()
    })

    test('空のお気に入りから曲検索へ進める', async ({ page }) => {
        await page.unroute('**://localhost/api/my-songs*')
        await page.route('**://localhost/api/my-songs*', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: [] }),
            })
        })

        await page.goto('/')
        await expect(page.getByText('お気に入りがありません。')).toBeVisible()
        await page.getByRole('main').getByRole('link', { name: '曲を探す' }).click()
        await expectHeading(page, '曲検索')
    })

    test('お気に入り一覧から詳細を開いて曲のリズムを再生・停止できる', async ({ page }) => {
        await page.goto('/')

        const songCard = page.locator('.song-card').first()
        await expect(songCard.locator('.audio-player')).toHaveCount(0)
        await expect(songCard.getByRole('region')).toHaveCount(0)
        await expect(songCard.getByRole('button', { name: 'Pretenderの詳細を開く' })).toBeVisible()
        await expect(songCard.getByText('詳細を開いて再生')).toHaveCount(0)
        await songCard.getByRole('button', { name: 'Pretenderの詳細を開く' }).click()
        await expect(page).toHaveURL(/\/songs\/1$/)

        await expect(page.getByText('歌詞を表示する')).toBeVisible()
        await expect(page.getByRole('img', { name: '歌いだし' })).toBeVisible()
        const summaryHeights = await page.locator('#rhythm-section summary, #mv-section summary, .lyrics-card summary').evaluateAll(
            (summaries) => summaries.map((summary) => summary.getBoundingClientRect().height),
        )
        expect(Math.max(...summaryHeights) - Math.min(...summaryHeights)).toBeLessThanOrEqual(2)
        await page.locator('#rhythm-section summary').click()
        const player = page.getByRole('region', { name: 'Pretenderのリズム再生' })
        await expect(player.getByRole('button', { name: '再生' })).toBeVisible()
        await player.getByRole('button', { name: '再生' }).click()
        await expect(player.getByRole('button', { name: '停止' })).toBeVisible()
        await player.getByRole('button', { name: '停止' }).click()
        await expect(player.getByRole('button', { name: '再生' })).toBeVisible()
        await page.screenshot({ path: 'test-results/issue-18-song-detail.png', fullPage: true })
    })

    test('リズム再生は詳細画面内で確認し、画面を切り替えると停止する', async ({ page }) => {
        await page.goto('/')
        await page.locator('.song-card').first().getByRole('button', { name: 'Pretenderの詳細を開く' }).click()
        await page.locator('#rhythm-section summary').click()

        const player = page.getByRole('region', { name: 'Pretenderのリズム再生' })
        await player.getByRole('button', { name: '再生' }).click()
        await expect(player.getByRole('button', { name: '停止' })).toBeVisible()

        await page.getByLabel('メインメニュー').getByRole('link', { name: 'お気に入り' }).click()
        await expect(page).toHaveURL(/\/$/)
        await expect(page.getByRole('complementary', { name: 'バックグラウンド再生中' })).toHaveCount(0)
    })

    test('曲詳細からお気に入りを解除し、プレイリストへ追加できる', async ({ page }) => {
        await page.route('**://localhost/api/my-songs/10', async (route) => {
            if (route.request().method() === 'DELETE') {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ message: '削除しました。' }),
                })
                return
            }

            await route.continue()
        })
        await page.route('**://localhost/api/playlists', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: [{ id: 5, name: '練習曲', song_count: 0 }] }),
            })
        })
        await page.route('**://localhost/api/playlists/5/songs', async (route) => {
            expect(route.request().method()).toBe('POST')
            expect(route.request().postDataJSON()).toEqual({ song_id: 1 })
            await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({}) })
        })

        await page.goto('/songs/1')
        await expect(page.getByRole('button', { name: 'お気に入りを解除' })).toBeVisible()
        await page.getByRole('button', { name: 'お気に入りを解除' }).click()
        await expect(page.getByText('お気に入りを解除しました。')).toBeVisible()

        await page.getByRole('button', { name: 'プレイリストに追加' }).click()
        await page.getByRole('button', { name: '練習曲' }).click()
        await expect(page.getByText('「練習曲」に追加しました。')).toBeVisible()
    })

    test('お気に入り取得失敗時にエラーを表示する', async ({ page }) => {
        await page.unroute('**://localhost/api/my-songs*')
        await page.route('**://localhost/api/my-songs*', async (route) => {
            await route.fulfill({ status: 500, contentType: 'application/json', body: '{}' })
        })

        await page.goto('/')

        await expect(page.getByText('お気に入りの取得に失敗しました。')).toBeVisible()
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
        const songCard = page.locator('.song-card').first()
        await songCard.getByRole('button', { name: '編集' }).click()
        await songCard.getByLabel('メモ').fill('更新したメモ')
        await songCard.getByLabel('定番').check()
        await songCard.getByRole('button', { name: '更新' }).click()

        await expect(page.getByText('お気に入りを更新しました。')).toBeVisible()
    })

    test('お気に入り編集中に新しいタグを追加して選択できる', async ({ page }) => {
        await page.goto('/')
        const songCard = page.locator('.song-card').first()
        await songCard.getByRole('button', { name: '編集' }).click()
        await songCard.getByLabel('新しいタグ').fill('今週練習')
        await songCard.getByRole('button', { name: 'タグを追加' }).click()

        await expect(songCard.getByLabel('今週練習')).toBeChecked()
    })

    test('お気に入りから曲を削除できる', async ({ page }) => {
        await page.route('**://localhost/api/my-songs/10', async (route) => {
            if (route.request().method() === 'DELETE') {
                await route.fulfill({ status: 204, body: '' })
                return
            }

            await route.continue()
        })

        await page.goto('/')
        const songCard = page.locator('.song-card').filter({ hasText: 'Pretender' }).first()
        await songCard.getByRole('button', { name: '削除' }).click()

        await expect(page.getByText('お気に入りを解除しました。')).toBeVisible()
    })
})
