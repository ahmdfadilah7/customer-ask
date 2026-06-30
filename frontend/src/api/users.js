import apiClient from './client'

export function fetchUsers() {
  return apiClient.get('/api/users')
}

export function createUser(payload) {
  return apiClient.post('/api/users', payload)
}

export function updateUser(id, payload) {
  return apiClient.put(`/api/users/${id}`, payload)
}

export function deleteUser(id) {
  return apiClient.delete(`/api/users/${id}`)
}
