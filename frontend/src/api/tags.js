import apiClient from './client'

export const fetchTags = () => {
    return apiClient.get('/tags')
}
