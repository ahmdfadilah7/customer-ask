import apiClient from './client'

export function updateProfile(payload) {
  return apiClient.put('/api/profile', payload)
}

export function updatePassword(payload) {
  return apiClient.put('/api/profile/password', payload)
}
