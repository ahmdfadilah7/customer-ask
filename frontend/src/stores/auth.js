import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import apiClient, { initCsrf } from '@/api/client'
import { managePermissions } from '@/constants/permissions'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const loading = ref(false)

  const permissions = computed(() => user.value?.permissions ?? [])
  const roles = computed(() => user.value?.roles ?? [])

  function hasRole(role) {
    return roles.value.includes(role)
  }

  function isSuperadmin() {
    return hasRole('superadmin')
  }

  function hasPermission(permission) {
    if (!permission) return true
    if (isSuperadmin()) return true
    return permissions.value.includes(permission)
  }

  function hasAnyPermission(list) {
    if (isSuperadmin()) return true
    return list.some((p) => hasPermission(p))
  }

  function canManage(feature) {
    return hasAnyPermission(managePermissions(feature))
  }

  function canImport(type) {
    const map = {
      corporate: 'import-corporate',
      service: 'import-service',
      employee: 'import-pegawai',
      pegawai: 'import-pegawai',
    }
    return hasPermission(map[type] ?? type)
  }

  async function fetchUser() {
    loading.value = true

    try {
      const { data } = await apiClient.get('/api/user')
      user.value = data.user
      return data.user
    } catch {
      user.value = null
      return null
    } finally {
      loading.value = false
    }
  }

  async function login(credentials) {
    await initCsrf()
    const { data } = await apiClient.post('/api/login', credentials)
    user.value = data.user
    return data.user
  }

  async function logout() {
    await apiClient.post('/api/logout')
    user.value = null
  }

  return {
    user,
    loading,
    permissions,
    roles,
    hasPermission,
    hasAnyPermission,
    canManage,
    canImport,
    hasRole,
    isSuperadmin,
    fetchUser,
    login,
    logout,
  }
})
