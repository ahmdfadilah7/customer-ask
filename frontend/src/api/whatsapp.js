import apiClient from './client'

export function fetchWhatsAppStatus() {
  return apiClient.get('/api/whatsapp/status')
}

export function previewWhatsAppMessage(payload) {
  return apiClient.post('/api/whatsapp/preview', payload)
}

export function sendWhatsAppMessage(payload) {
  return apiClient.post('/api/whatsapp/send', payload)
}
