import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { fetchWebsiteSettings } from '@/api/websiteSettings'

const FALLBACK = {
  site_name: import.meta.env.VITE_APP_NAME || 'Astrindo Travel Services',
  tagline: 'Portal Corporate & Pegawai',
  meta_title: import.meta.env.VITE_APP_NAME || 'Astrindo Travel Services',
  meta_description: 'Portal internal Astrindo Travel Services.',
  meta_keywords: '',
  meta_author: '',
  logo_url: '',
  favicon_url: '',
  footer_text: '',
  contact_email: '',
  contact_phone: '',
}

function applyDocumentMeta(settings) {
  const title = settings.meta_title || settings.site_name || FALLBACK.site_name
  document.title = title

  const setMeta = (name, content) => {
    if (!content) return
    let el = document.querySelector(`meta[name="${name}"]`)
    if (!el) {
      el = document.createElement('meta')
      el.setAttribute('name', name)
      document.head.appendChild(el)
    }
    el.setAttribute('content', content)
  }

  setMeta('description', settings.meta_description)
  setMeta('keywords', settings.meta_keywords)
  setMeta('author', settings.meta_author)

  const favicon = settings.favicon_url?.trim()
  if (favicon) {
    let link = document.querySelector('link[rel="icon"]')
    if (!link) {
      link = document.createElement('link')
      link.setAttribute('rel', 'icon')
      document.head.appendChild(link)
    }
    link.setAttribute('href', favicon)
  }
}

export const useSiteSettingsStore = defineStore('siteSettings', () => {
  const settings = ref({ ...FALLBACK })
  const loaded = ref(false)
  const loading = ref(false)

  const siteName = computed(() => settings.value.site_name || FALLBACK.site_name)
  const tagline = computed(() => settings.value.tagline || FALLBACK.tagline)

  async function load(force = false) {
    if (loaded.value && !force) return settings.value

    loading.value = true
    try {
      const { data } = await fetchWebsiteSettings()
      settings.value = { ...FALLBACK, ...data.data }
      applyDocumentMeta(settings.value)
      loaded.value = true
      return settings.value
    } catch {
      applyDocumentMeta(FALLBACK)
      return settings.value
    } finally {
      loading.value = false
    }
  }

  function applySettings(data) {
    settings.value = { ...FALLBACK, ...data }
    applyDocumentMeta(settings.value)
    loaded.value = true
  }

  return {
    settings,
    loaded,
    loading,
    siteName,
    tagline,
    load,
    applySettings,
  }
})
