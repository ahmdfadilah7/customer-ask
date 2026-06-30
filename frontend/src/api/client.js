import axios from 'axios'

const apiClient = axios.create({
  baseURL: import.meta.env.DEV ? '' : (import.meta.env.VITE_API_URL || 'http://localhost:8000'),
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

export async function initCsrf() {
  await apiClient.get('/sanctum/csrf-cookie')
}

export default apiClient
