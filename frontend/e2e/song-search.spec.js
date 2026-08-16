import { test, expect } from '@playwright/test'
import { authenticate, expectHeading, mockSongs, songs } from './helpers'

test.describe('曲検索とお気に入り追加', () => {
    test.beforeEach(async ({ page }) => {
        await authenticate(page)
        await mockSongs(page)
        await page.route('**://localhost/api/songs/1', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: songs[0] }),
            })
        })
    })

    test('キーワードで曲を絞り込み、お気に入りへ追加できる', async ({ page }) => {
        await page.route('**://localhost/api/my-songs**', async (route) => {
            if (route.request().method() === 'GET') {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ data: [] }),
                })
                return
            }

            if (route.request().method() === 'POST') {
                expect(route.request().postDataJSON()).toEqual({ song_id: 1 })
                await route.fulfill({
                    status: 201,
                    contentType: 'application/json',
                    body: JSON.stringify({ data: { id: 10, song: songs[0], tags: [] } }),
                })
                return
            }

            expect(route.request().method()).toBe('DELETE')
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ message: '削除しました。' }),
            })
        })

        await page.goto('/songs')
        await expectHeading(page, '曲検索')

        await page.getByPlaceholder('Pretender').fill('YOASOBI')
        await expect(page.getByRole('heading', { name: '夜に駆ける' })).toBeVisible()
        await expect(page.getByRole('heading', { name: 'Pretender' })).toBeHidden()
        await expect(page.getByText('詳細を開いて再生')).toHaveCount(0)

        await page.getByPlaceholder('Pretender').fill('Pretender')
        const songCard = page.locator('.song-card').filter({ hasText: 'Pretender' })
        await expect(songCard.locator('.audio-player')).toHaveCount(0)
        await songCard.getByRole('button', { name: 'お気に入りに追加', exact: true }).click()
        await expect(page.getByText('お気に入りに追加しました。')).toBeVisible()
        await expect(songCard.getByRole('button', { name: 'お気に入りを解除', exact: true })).toBeEnabled()
        await songCard.getByRole('button', { name: 'お気に入りを解除', exact: true }).click()
        await expect(page.getByText('お気に入りを解除しました。')).toBeVisible()
        await expect(songCard.getByRole('button', { name: 'お気に入りに追加', exact: true })).toBeEnabled()
    })

    test('既存のお気に入り状態を検索結果へ反映し、再タップで解除できる', async ({ page }) => {
        await page.route('**://localhost/api/my-songs**', async (route) => {
            if (route.request().method() === 'GET') {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ data: [{ id: 10, song: songs[0], tags: [] }] }),
                })
                return
            }

            expect(route.request().method()).toBe('DELETE')
            expect(route.request().url()).toContain('/my-songs/10')
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ message: '削除しました。' }),
            })
        })

        await page.goto('/songs')
        await page.getByPlaceholder('Pretender').fill('Pretender')
        const songCard = page.locator('.song-card').filter({ hasText: 'Pretender' })
        const favoriteButton = songCard.getByRole('button', { name: 'お気に入りを解除', exact: true })

        await expect(favoriteButton).toBeVisible()
        await favoriteButton.click()
        await expect(page.getByText('お気に入りを解除しました。')).toBeVisible()
        await expect(songCard.getByRole('button', { name: 'お気に入りに追加', exact: true })).toBeVisible()
    })

    test('曲詳細と同じ歌詞APIから検索結果の歌いだしを補完する', async ({ page }) => {
        await page.route('**://localhost/api/my-songs*', async (route) => {
            if (route.request().method() === 'GET') {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify({ data: [] }),
                })
                return
            }

            await route.continue()
        })
        await page.route('**://localhost/api/songs/1/lyrics', async (route) => {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: { lyrics: '検索結果の歌いだしです。\nサビです。', opening_line: '検索結果の歌いだしです。' } }),
            })
        })

        await page.goto('/songs')
        await page.getByPlaceholder('Pretender').fill('Pretender')
        await expect(page.getByText('検索結果の歌いだしです。')).toBeVisible()
    })

    test('検索前に人気アーティストのおすすめから検索できる', async ({ page }) => {
        await page.goto('/songs')

        await expect(page.getByRole('heading', { name: '人気アーティストから探す' })).toBeVisible()
        await page.screenshot({ path: 'test-results/issue-search-recommended-artists.png', fullPage: true })
        await page.getByRole('button', { name: 'YOASOBIでアーティスト検索' }).click()

        await expect(page.getByPlaceholder('Pretender')).toHaveValue('YOASOBI')
        await expect(page.getByLabel('検索対象')).toHaveValue('artist')
        await expect(page.getByRole('heading', { name: '夜に駆ける' })).toBeVisible()
        await expect(page.getByRole('heading', { name: '人気アーティストから探す' })).toHaveCount(0)
    })

    test('検索欄をクリアした後に遅れて届いた候補曲を表示しない', async ({ page }) => {
        await page.unroute('**://localhost/api/songs**')
        await page.route('**://localhost/api/songs/search**', async (route) => {
            await new Promise((resolve) => setTimeout(resolve, 500))
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: songs }),
            })
        })

        await page.goto('/songs')
        const requestPromise = page.waitForRequest('**://localhost/api/songs/search**')
        await page.getByPlaceholder('Pretender').fill('Pretender')
        await requestPromise
        await page.getByRole('button', { name: 'クリア' }).click()

        await expect(page.getByPlaceholder('Pretender')).toHaveValue('')
        await page.waitForTimeout(650)
        await expect(page.getByRole('heading', { name: 'Pretender' })).toHaveCount(0)
        await expect(page.getByRole('heading', { name: '人気アーティストから探す' })).toBeVisible()
    })

    test('1文字から曲を検索できる', async ({ page }) => {
        await page.goto('/songs')

        await page.getByPlaceholder('Pretender').fill('夜')

        await expect(page.getByRole('heading', { name: '夜に駆ける' })).toBeVisible()
        await expect(page.getByText('1文字以上入力すると検索できます。')).toHaveCount(0)
    })

    test('ひらがな入力でカタカナ表記のアーティストを検索できる', async ({ page }) => {
        await page.unroute('**://localhost/api/songs**')
        await mockSongs(page, [{ id: 3, title: 'ただ君に晴れ', artist: 'ヨルシカ', bpm: 120 }])
        await page.goto('/songs')

        await page.getByPlaceholder('Pretender').fill('よるしか')

        await expect(page.getByRole('heading', { name: 'ただ君に晴れ' })).toBeVisible()
    })

    test('検索結果がない場合は空状態を表示する', async ({ page }) => {
        await page.goto('/songs')
        await page.getByPlaceholder('Pretender').fill('存在しない曲')

        await expect(page.getByText('該当する曲がありません。')).toBeVisible()
    })

    test('検索結果から詳細へ進んでも検索条件を保持して戻れる', async ({ page }) => {
        await page.goto('/songs')
        await page.getByPlaceholder('Pretender').fill('Pretender')
        await page.getByRole('button', { name: 'Pretenderの詳細を開く' }).click()

        await expect(page.getByRole('link', { name: '曲検索へ戻る' })).toBeVisible()
        await page.getByRole('link', { name: '曲検索へ戻る' }).click()

        await expect(page).toHaveURL(/\/songs\?q=Pretender&field=all&sort=relevance$/)
        await expect(page.getByPlaceholder('Pretender')).toHaveValue('Pretender')
        await expect(page.getByRole('heading', { name: 'Pretender' })).toBeVisible()
    })
})
