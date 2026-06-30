import apiClient from './client'

export function fetchRoles() {
  return apiClient.get('/api/roles')
}

export function fetchRolePermissionsMeta() {
  return apiClient.get('/api/roles/permissions')
}

export function createRole(payload) {
  return apiClient.post('/api/roles', payload)
}

export function updateRole(roleId, payload) {
  return apiClient.put(`/api/roles/${roleId}`, payload)
}

export function deleteRole(roleId) {
  return apiClient.delete(`/api/roles/${roleId}`)
}
