import apiClient from './client'

export function fetchBranches() {
  return apiClient.get('/api/branches')
}

export function createBranch(payload) {
  return apiClient.post('/api/branches', payload)
}

export function updateBranch(id, payload) {
  return apiClient.put(`/api/branches/${id}`, payload)
}

export function deleteBranch(id) {
  return apiClient.delete(`/api/branches/${id}`)
}
