import apiClient from './client'

export function fetchCustomers(params = {}) {
  return apiClient.get('/api/customers', { params })
}

export function fetchTrashedCustomers(params = {}) {
  return apiClient.get('/api/customers/trashed/list', { params })
}

export function fetchCustomer(id) {
  return apiClient.get(`/api/customers/${id}`)
}

export function deleteCustomer(id) {
  return apiClient.delete(`/api/customers/${id}`)
}

export function bulkDeleteCustomers(ids) {
  return apiClient.post('/api/customers/bulk-delete', { ids })
}

export function restoreCustomer(id) {
  return apiClient.post(`/api/customers/${id}/restore`)
}

export function bulkRestoreCustomers(ids) {
  return apiClient.post('/api/customers/bulk-restore', { ids })
}

export function forceDeleteCustomer(id) {
  return apiClient.delete(`/api/customers/${id}/force`)
}

export function bulkForceDeleteCustomers(ids) {
  return apiClient.post('/api/customers/bulk-force-delete', { ids })
}
