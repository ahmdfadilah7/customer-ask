import apiClient from './client'

export function fetchMessageTemplates(params = {}) {
  return apiClient.get('/api/message-templates', { params })
}

export function fetchMessageTemplate(id) {
  return apiClient.get(`/api/message-templates/${id}`)
}

export function fetchMessagePlaceholders() {
  return apiClient.get('/api/message-templates/placeholders')
}

export function createMessageTemplate(payload) {
  return apiClient.post('/api/message-templates', payload)
}

export function updateMessageTemplate(id, payload) {
  return apiClient.put(`/api/message-templates/${id}`, payload)
}

export function deleteMessageTemplate(id) {
  return apiClient.delete(`/api/message-templates/${id}`)
}
