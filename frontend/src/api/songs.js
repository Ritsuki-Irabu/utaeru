import apiClient from './client'

// 管理者が曲情報との一致を再確認してBPMを再取得する
export const refreshSongBpm = (songId) => {
    return apiClient.post('/songs/' + songId + '/bpm/refresh')
}

// user画面・admin画面の両方で使う公開曲マスタの一覧を取得する
export const fetchSongs = () => {
    return apiClient.get('/songs')
}

export const searchSongs = (query, field = 'all') => {
    return apiClient.get('/songs/search', { params: { q: query, field } })
}

export const importSong = (payload) => {
    return apiClient.post('/songs/import', payload)
}

// MVボタン押下時に、曲IDをキーに公式YouTube動画の候補を取得する。
// タイトル・アーティストはサーバー側で曲マスタから再構成するため、別曲の混入を防げる。
export const searchYoutubeVideos = (songOrQuery) => {
    if (songOrQuery && typeof songOrQuery === 'object' && songOrQuery.id) {
        return apiClient.get('/songs/youtube/search', { params: { song_id: songOrQuery.id } })
    }

    // 既存の汎用検索呼び出しとの互換性を残す。
    return apiClient.get('/songs/youtube/search', { params: { q: songOrQuery } })
}

// 一覧から再生専用の曲詳細画面へ遷移するときに曲情報を取得する
export const fetchSong = (songId) => {
    return apiClient.get(`/songs/${songId}`)
}

// DBに歌詞がない曲だけ、利用許諾済み歌詞APIの結果を取得する
export const fetchSongLyrics = (songId) => {
    return apiClient.get(`/songs/${songId}/lyrics`)
}

// 管理者画面の登録フォームから送られた曲情報を保存する
export const createSong = (song) => {
    return apiClient.post('/songs', song)
}

// 管理者画面の編集フォームから送られた曲情報で更新する
export const updateSong = (songId, song) => {
    return apiClient.put(`/songs/${songId}`, song)
}

// 管理者画面で指定した曲を曲マスタから削除する
export const deleteSong = (songId) => {
    return apiClient.delete(`/songs/${songId}`)
}
