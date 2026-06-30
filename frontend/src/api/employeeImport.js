import apiClient from './client'

export function fetchEmployeeImportReference() {
  return apiClient.get('/api/employee-import/reference')
}

export async function downloadEmployeeTemplate() {
  const { data } = await apiClient.get('/api/employee-import/template', {
    responseType: 'blob',
  })

  const url = window.URL.createObjectURL(data)
  const link = document.createElement('a')
  link.href = url
  link.download = 'import-employee-template.xlsx'
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

export function importEmployeeData(formData) {
  return apiClient.post('/api/employee-import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}
