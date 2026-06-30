<script setup>
import { computed, onMounted, ref } from 'vue'
import { Globe, Image, Mail, Save, Search } from '@lucide/vue'
import { fetchWebsiteSettings, updateWebsiteSettings } from '@/api/websiteSettings'
import { useAuthStore } from '@/stores/auth'
import { useSiteSettingsStore } from '@/stores/siteSettings'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PageInfoBanner from '@/components/ui/PageInfoBanner.vue'
import ImageDropzone from '@/components/ui/ImageDropzone.vue'
import { getApiErrorMessage } from '@/utils/apiError'

const auth = useAuthStore()
const siteSettings = useSiteSettingsStore()
const toast = useToast()

const loading = ref(true)
const saving = ref(false)
const activeSection = ref('branding')

const canManage = computed(() => auth.hasPermission('setting-website-update'))

const form = ref({
  site_name: '',
  tagline: '',
  meta_title: '',
  meta_description: '',
  meta_keywords: '',
  meta_author: '',
  logo_url: '',
  favicon_url: '',
  footer_text: '',
  contact_email: '',
  contact_phone: '',
})

const logoFile = ref(null)
const faviconFile = ref(null)
const removeLogo = ref(false)
const removeFavicon = ref(false)

const sections = [
  { id: 'branding', label: 'Branding', icon: Image },
  { id: 'seo', label: 'SEO & Meta', icon: Search },
  { id: 'contact', label: 'Kontak & Footer', icon: Mail },
]

onMounted(loadSettings)

async function loadSettings() {
  loading.value = true
  try {
    const { data } = await fetchWebsiteSettings()
    form.value = { ...form.value, ...data.data }
    resetUploadState()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat pengaturan website.'))
  } finally {
    loading.value = false
  }
}

function resetUploadState() {
  logoFile.value = null
  faviconFile.value = null
  removeLogo.value = false
  removeFavicon.value = false
}

async function handleSubmit() {
  if (!canManage.value) return

  saving.value = true
  try {
    const { data } = await updateWebsiteSettings(form.value, {
      logo: logoFile.value,
      favicon: faviconFile.value,
      removeLogo: removeLogo.value,
      removeFavicon: removeFavicon.value,
    })
    form.value = { ...form.value, ...data.data }
    resetUploadState()
    siteSettings.applySettings(data.data)
    toast.success(data.message)
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menyimpan pengaturan.'))
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="page-shell">
    <PageHeader
      :icon="Globe"
      title="Setting Website"
      description="Kelola nama website, tagline, meta tag SEO, dan informasi tampilan portal."
    >
      <template v-if="canManage" #actions>
        <button type="button" class="btn-primary !py-2" :disabled="saving || loading" @click="handleSubmit">
          <Save class="size-4" />
          {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
        </button>
      </template>
    </PageHeader>

    <PageInfoBanner>
      Perubahan diterapkan ke sidebar, halaman login, dan meta tag browser.
      Logo & favicon diunggah ke server dan disajikan melalui symlink <code class="text-xs">/storage</code>.
    </PageInfoBanner>

    <LoadingSpinner v-if="loading" label="Memuat pengaturan..." />

    <div v-else class="glass-panel overflow-hidden p-0">
      <div class="border-b border-slate-100 bg-slate-50/50 px-4 py-3 sm:px-6">
        <div class="segment-control w-full sm:w-auto">
          <button
            v-for="section in sections"
            :key="section.id"
            type="button"
            class="segment-control__btn flex items-center gap-1.5"
            :class="activeSection === section.id ? 'segment-control__btn--active' : 'segment-control__btn--inactive'"
            @click="activeSection = section.id"
          >
            <component :is="section.icon" class="size-4 shrink-0" />
            {{ section.label }}
          </button>
        </div>
      </div>

      <form class="space-y-6 p-5 sm:p-6" @submit.prevent="handleSubmit">
        <div v-show="activeSection === 'branding'" class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="form-label">Nama Website *</label>
            <input v-model="form.site_name" type="text" required maxlength="255" class="input-field" :disabled="!canManage" />
            <p class="form-hint">Ditampilkan di sidebar dan halaman login.</p>
          </div>
          <div class="sm:col-span-2">
            <label class="form-label">Tagline</label>
            <input v-model="form.tagline" type="text" maxlength="255" class="input-field" :disabled="!canManage" placeholder="Portal Corporate & Pegawai" />
            <p class="form-hint">Teks pendek di bawah nama website pada sidebar.</p>
          </div>
          <div>
            <label class="form-label">Logo</label>
            <ImageDropzone
              v-model="logoFile"
              accept=".png,.jpg,.jpeg,.gif,.webp,.svg"
              label="Unggah logo website"
              hint="PNG, JPG, WEBP, SVG — maks. 2 MB"
              :current-url="removeLogo ? '' : form.logo_url"
              :disabled="!canManage"
              @remove-current="removeLogo = $event"
            />
          </div>
          <div>
            <label class="form-label">Favicon</label>
            <ImageDropzone
              v-model="faviconFile"
              accept=".png,.jpg,.jpeg,.gif,.webp,.svg,.ico"
              label="Unggah favicon"
              hint="ICO, PNG, JPG — maks. 512 KB"
              :current-url="removeFavicon ? '' : form.favicon_url"
              :disabled="!canManage"
              @remove-current="removeFavicon = $event"
            />
          </div>
        </div>

        <div v-show="activeSection === 'seo'" class="grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="form-label">Meta Title</label>
            <input v-model="form.meta_title" type="text" maxlength="255" class="input-field" :disabled="!canManage" />
            <p class="form-hint">Judul tab browser. Kosongkan untuk memakai nama website.</p>
          </div>
          <div class="sm:col-span-2">
            <label class="form-label">Meta Description</label>
            <textarea v-model="form.meta_description" rows="3" maxlength="1000" class="input-field resize-y" :disabled="!canManage" />
          </div>
          <div>
            <label class="form-label">Meta Keywords</label>
            <input v-model="form.meta_keywords" type="text" maxlength="500" class="input-field" :disabled="!canManage" placeholder="astrindo, corporate, pegawai" />
          </div>
          <div>
            <label class="form-label">Meta Author</label>
            <input v-model="form.meta_author" type="text" maxlength="255" class="input-field" :disabled="!canManage" />
          </div>
        </div>

        <div v-show="activeSection === 'contact'" class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="form-label">Email Kontak</label>
            <input v-model="form.contact_email" type="email" maxlength="150" class="input-field" :disabled="!canManage" />
          </div>
          <div>
            <label class="form-label">Telepon Kontak</label>
            <input v-model="form.contact_phone" type="text" maxlength="30" class="input-field" :disabled="!canManage" />
          </div>
          <div class="sm:col-span-2">
            <label class="form-label">Teks Footer</label>
            <textarea v-model="form.footer_text" rows="3" maxlength="1000" class="input-field resize-y" :disabled="!canManage" placeholder="© 2026 Astrindo Travel Services" />
          </div>
        </div>

        <div v-if="canManage" class="flex justify-end border-t border-slate-100 pt-4">
          <button type="submit" class="btn-primary" :disabled="saving">
            <Save class="size-4" />
            {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
          </button>
        </div>

        <p v-else class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500 ring-1 ring-slate-100">
          Anda hanya dapat melihat pengaturan. Hubungi admin untuk mengubah data.
        </p>
      </form>
    </div>
  </div>
</template>
