import apiClient from './client'

export function fetchRegionScopes() {
  return apiClient.get('/api/region-scopes')
}

export function createRegionScope(payload) {
  return apiClient.post('/api/region-scopes', payload)
}

export function updateRegionScope(id, payload) {
  return apiClient.put(`/api/region-scopes/${id}`, payload)
}

export function deleteRegionScope(id) {
  return apiClient.delete(`/api/region-scopes/${id}`)
}
