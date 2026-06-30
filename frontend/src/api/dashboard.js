import apiClient from './client'

export function fetchDashboardStats() {
  return apiClient.get('/api/dashboard/stats')
}
