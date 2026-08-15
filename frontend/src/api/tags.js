import apiClient from './client'

export const fetchTags = () => {
    return apiClient.get('/tags')
}

export const createTag = (name) => {
    return apiClient.post('/tags', { name })
}
