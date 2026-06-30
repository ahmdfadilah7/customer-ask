import apiClient from './client'

const TEXT_FIELDS = [
  'site_name',
  'tagline',
  'meta_title',
  'meta_description',
  'meta_keywords',
  'meta_author',
  'footer_text',
  'contact_email',
  'contact_phone',
]

export function fetchWebsiteSettings() {
  return apiClient.get('/api/website-settings')
}

export function updateWebsiteSettings(payload, files = {}) {
  const hasFiles = files.logo instanceof File || files.favicon instanceof File
  const hasRemovals = files.removeLogo || files.removeFavicon

  if (!hasFiles && !hasRemovals) {
    return apiClient.put('/api/website-settings', payload)
  }

  const formData = new FormData()

  TEXT_FIELDS.forEach((key) => {
    const value = payload[key]
    if (value !== null && value !== undefined) {
      formData.append(key, value)
    }
  })

  if (files.logo instanceof File) {
    formData.append('logo', files.logo)
  }

  if (files.favicon instanceof File) {
    formData.append('favicon', files.favicon)
  }

  if (files.removeLogo) {
    formData.append('remove_logo', '1')
  }

  if (files.removeFavicon) {
    formData.append('remove_favicon', '1')
  }

  return apiClient.post('/api/website-settings', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}
