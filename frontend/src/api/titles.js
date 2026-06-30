import apiClient from './client'

export function fetchTitles() {
  return apiClient.get('/api/titles')
}

export function createTitle(payload) {
  return apiClient.post('/api/titles', payload)
}

export function updateTitle(id, payload) {
  return apiClient.put(`/api/titles/${id}`, payload)
}

export function deleteTitle(id) {
  return apiClient.delete(`/api/titles/${id}`)
}
