import { expect } from '@playwright/test'

export const user = {
    id: 1,
    name: 'テストユーザー',
    email: 'user@example.com',
    role: 'user',
}

export const admin = {
    id: 2,
    name: '管理者',
    email: 'admin@example.com',
    role: 'admin',
}

export const songs = [
    { id: 1, title: 'Pretender', artist: 'Official髭男dism', bpm: 92 },
    { id: 2, title: '夜に駆ける', artist: 'YOASOBI', bpm: 130 },
]

export const mySongs = [
    {
        id: 10,
        memo: 'サビから練習',
        tags: [{ id: 1, name: '練習中' }],
        song: songs[0],
    },
]

export const tags = [
    { id: 1, name: '練習中' },
    { id: 2, name: '定番' },
]

export async function authenticate(page, currentUser = user) {
    await page.addInitScript(({ authenticatedUser }) => {
        localStorage.setItem('token', 'e2e-token')
        localStorage.setItem('user', JSON.stringify(authenticatedUser))
    }, { authenticatedUser: currentUser })

    // 詳細画面の歌詞APIは各テストが必要な応答を明示できるよう、既定では未登録にする。
    await page.route('**://localhost/api/songs/*/lyrics', async (route) => {
        await route.fulfill({
            status: 404,
            contentType: 'application/json',
            body: JSON.stringify({ data: null }),
        })
    })
}

export async function mockSongs(page, responseSongs = songs) {
    await page.route('**://localhost/api/songs**', async (route) => {
        if (route.request().method() === 'GET') {
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: responseSongs }),
            })
            return
        }

        await route.continue()
    })
}

export async function expectHeading(page, heading) {
    await expect(page.getByRole('heading', { name: heading })).toBeVisible()
}
