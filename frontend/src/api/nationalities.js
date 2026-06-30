import apiClient from './client'

export function fetchNationalities() {
  return apiClient.get('/api/nationalities')
}

export function createNationality(payload) {
  return apiClient.post('/api/nationalities', payload)
}

export function updateNationality(id, payload) {
  return apiClient.put(`/api/nationalities/${id}`, payload)
}

export function deleteNationality(id) {
  return apiClient.delete(`/api/nationalities/${id}`)
}
