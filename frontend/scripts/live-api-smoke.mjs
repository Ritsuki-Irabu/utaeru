const apiBaseUrl = (process.env.E2E_API_URL ?? 'http://localhost/api').replace(/\/$/, '')
const email = process.env.E2E_SMOKE_EMAIL ?? 'user@example.com'
const password = process.env.E2E_SMOKE_PASSWORD ?? 'password'

let token = ''
let temporaryPlaylistId = null
let temporaryMySongId = null
let restoredMySong = null

const request = async (path, options = {}) => {
    const response = await fetch(`${apiBaseUrl}${path}`, {
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? { Authorization: `Bearer ${token}` } : {}),
            ...(options.headers ?? {}),
        },
    })
    const body = await response.json().catch(() => null)

    if (!response.ok) {
        throw new Error(`${options.method ?? 'GET'} ${path} -> ${response.status}: ${JSON.stringify(body)}`)
    }

    return body
}

const unwrap = (body) => body?.data ?? body

const json = (method, body) => ({
    method,
    body: JSON.stringify(body),
})

const restoreOrRemoveFavorite = async () => {
    if (temporaryMySongId) {
        await request(`/my-songs/${temporaryMySongId}`, { method: 'DELETE' })
        return
    }

    if (restoredMySong) {
        await request(`/my-songs/${restoredMySong.id}`, json('PUT', {
            memo: restoredMySong.memo ?? '',
            tag_ids: (restoredMySong.tags ?? []).map((tag) => tag.id),
        }))
    }
}

const cleanup = async () => {
    if (temporaryPlaylistId) {
        await request(`/playlists/${temporaryPlaylistId}`, { method: 'DELETE' })
        temporaryPlaylistId = null
    }

    await restoreOrRemoveFavorite()

    if (token) {
        await request('/auth/logout', { method: 'POST' })
        token = ''
    }
}

try {
    const login = unwrap(await request('/auth/login', json('POST', { email, password })))
    token = login.token

    if (!token || !login.user) {
        throw new Error('ログインレスポンスにtokenまたはuserがありません。')
    }

    const currentUser = await request('/user')
    if (currentUser.email !== email) {
        throw new Error(`ログインユーザーが一致しません: ${currentUser.email}`)
    }

    const songs = unwrap(await request('/songs'))
    if (!Array.isArray(songs) || songs.length === 0) {
        throw new Error('公開曲マスタに曲がありません。')
    }

    const selectedSong = songs[0]
    const currentMySongs = unwrap(await request('/my-songs'))
    const existingMySong = currentMySongs.find((mySong) => mySong.song?.id === selectedSong.id)

    if (existingMySong) {
        restoredMySong = existingMySong
        await request(`/my-songs/${existingMySong.id}`, json('PUT', {
            memo: `${existingMySong.memo ?? ''} [smoke]`,
            tag_ids: (existingMySong.tags ?? []).map((tag) => tag.id),
        }))
        console.log(`favorite_update=ok (restored id=${existingMySong.id})`)
    } else {
        const createdMySong = unwrap(await request('/my-songs', json('POST', {
            song_id: selectedSong.id,
            memo: 'live API smoke test',
        })))
        temporaryMySongId = createdMySong.id
        console.log(`favorite_create=ok id=${temporaryMySongId}`)
    }

    const playlist = unwrap(await request('/playlists', json('POST', {
        name: `API smoke ${Date.now()}`,
        description: '自動検証後に削除する一時プレイリスト',
    })))
    temporaryPlaylistId = playlist.id

    const playlistSong = unwrap(await request(`/playlists/${temporaryPlaylistId}/songs`, json('POST', {
        song_id: selectedSong.id,
        position: 1,
    })))

    await request(`/playlists/${temporaryPlaylistId}`, json('PUT', {
        name: playlist.name,
        description: '更新確認済み',
    }))

    await request(`/playlists/${temporaryPlaylistId}/share`, { method: 'POST' })
    await request(`/playlists/${temporaryPlaylistId}/share`, { method: 'DELETE' })
    await request(`/playlists/${temporaryPlaylistId}/songs/${playlistSong.id}`, { method: 'DELETE' })
    await request(`/playlists/${temporaryPlaylistId}`, { method: 'DELETE' })
    temporaryPlaylistId = null

    console.log('auth=ok')
    console.log(`songs_read=ok count=${songs.length}`)
    console.log('playlist_create_update_share_song_delete=ok')
} finally {
    await cleanup()
}
