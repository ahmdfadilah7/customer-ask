import apiClient from './client'

export function fetchCorporateImportReference(importType) {
  return apiClient.get('/api/corporate-import/reference', { params: { type: importType } })
}

export async function downloadCorporateTemplate(importType) {
  const { data } = await apiClient.get('/api/corporate-import/template', {
    params: { type: importType },
    responseType: 'blob',
  })

  const filename = importType === 'service'
    ? 'import-service-template.xlsx'
    : 'import-corporate-template.xlsx'

  const url = window.URL.createObjectURL(data)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

export function importCorporateData(formData) {
  return apiClient.post('/api/corporate-import', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}
