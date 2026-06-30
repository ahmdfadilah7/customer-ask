import apiClient from './client'

export function fetchCustomerContacts(customerId) {
  return apiClient.get(`/api/customers/${customerId}/contacts`)
}

export function createCustomerContact(customerId, payload) {
  return apiClient.post(`/api/customers/${customerId}/contacts`, payload)
}

export function updateCustomerContact(customerId, contactId, payload) {
  return apiClient.put(`/api/customers/${customerId}/contacts/${contactId}`, payload)
}

export function deleteCustomerContact(customerId, contactId) {
  return apiClient.delete(`/api/customers/${customerId}/contacts/${contactId}`)
}
