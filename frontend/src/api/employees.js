import apiClient from './client'

export function fetchEmployees(params = {}) {
  return apiClient.get('/api/employees', { params })
}

export function fetchEmployee(id) {
  return apiClient.get(`/api/employees/${id}`)
}

export function createEmployee(payload) {
  return apiClient.post('/api/employees', payload)
}

export function updateEmployee(id, payload) {
  return apiClient.put(`/api/employees/${id}`, payload)
}

export function deleteEmployee(id) {
  return apiClient.delete(`/api/employees/${id}`)
}

export function fetchCustomerEmployees(customerId) {
  return apiClient.get(`/api/customers/${customerId}/employees`)
}
