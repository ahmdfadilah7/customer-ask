import axios from 'axios'

// Dev: kosong agar Vite proxy ke backend lokal. Production: kosong = same origin (satu domain).
const baseURL = import.meta.env.DEV
  ? ''
  : (import.meta.env.VITE_API_URL ?? '')

const apiClient = axios.create({
  baseURL,
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
