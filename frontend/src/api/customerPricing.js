import apiClient from './client'

export function fetchPricingReference() {
  return apiClient.get('/api/pricing-reference')
}

export function createPricingRule(customerId, payload) {
  return apiClient.post(`/api/customers/${customerId}/pricing-rules`, payload)
}

export function updatePricingRule(customerId, ruleId, payload) {
  return apiClient.put(`/api/customers/${customerId}/pricing-rules/${ruleId}`, payload)
}

export function deletePricingRule(customerId, ruleId) {
  return apiClient.delete(`/api/customers/${customerId}/pricing-rules/${ruleId}`)
}
