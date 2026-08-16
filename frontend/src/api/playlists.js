import apiClient from './client'

export const fetchPlaylists = () => apiClient.get('/playlists')
export const fetchPlaylist = (playlistId) => apiClient.get(`/playlists/${playlistId}`)
export const createPlaylist = (payload) => apiClient.post('/playlists', payload)
export const updatePlaylist = (playlistId, payload) => apiClient.put(`/playlists/${playlistId}`, payload)
export const deletePlaylist = (playlistId) => apiClient.delete(`/playlists/${playlistId}`)
export const addPlaylistSong = (playlistId, payload) => apiClient.post(`/playlists/${playlistId}/songs`, payload)
export const updatePlaylistSong = (playlistId, playlistSongId, payload) => apiClient.put(`/playlists/${playlistId}/songs/${playlistSongId}`, payload)
export const deletePlaylistSong = (playlistId, playlistSongId) => apiClient.delete(`/playlists/${playlistId}/songs/${playlistSongId}`)
export const sharePlaylist = (playlistId) => apiClient.post(`/playlists/${playlistId}/share`)
export const revokePlaylistShare = (playlistId) => apiClient.delete(`/playlists/${playlistId}/share`)
export const fetchSharedPlaylist = (token) => apiClient.get(`/shared/playlists/${token}`)
export const copySharedPlaylist = (token) => apiClient.post(`/shared/playlists/${token}/copy`)
