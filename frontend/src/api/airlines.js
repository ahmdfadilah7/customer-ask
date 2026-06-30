import apiClient from './client'

export function fetchAirlines() {
  return apiClient.get('/api/airlines')
}

export function createAirline(payload) {
  return apiClient.post('/api/airlines', payload)
}

export function updateAirline(id, payload) {
  return apiClient.put(`/api/airlines/${id}`, payload)
}

export function deleteAirline(id) {
  return apiClient.delete(`/api/airlines/${id}`)
}
