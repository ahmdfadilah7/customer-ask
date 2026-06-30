<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Building2, Mail, Lock, ArrowRight, Sparkles } from '@lucide/vue'
import { useAuthStore } from '@/stores/auth'
import { useSiteSettingsStore } from '@/stores/siteSettings'

const router = useRouter()
const auth = useAuthStore()
const siteSettings = useSiteSettingsStore()

const appName = computed(() => siteSettings.siteName)
const appTagline = computed(() => siteSettings.tagline)
const footerText = computed(() => siteSettings.settings.footer_text)

onMounted(() => {
  if (!siteSettings.loaded) {
    siteSettings.load()
  }
})

const form = ref({ email: '', password: '' })
const loading = ref(false)
const error = ref('')

async function handleLogin() {
  loading.value = true
  error.value = ''

  try {
    await auth.login(form.value)
    router.push('/')
  } catch (err) {
    error.value = err.response?.data?.message || 'Login gagal. Periksa email dan password.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen">
    <!-- Left panel - branding -->
    <div class="relative hidden w-1/2 overflow-hidden bg-gradient-to-br from-brand-950 via-brand-800 to-brand-600 lg:flex lg:flex-col lg:justify-between lg:p-12">
      <div class="absolute inset-0 opacity-30">
        <div class="absolute -left-20 -top-20 size-80 rounded-full bg-white/10 blur-3xl" />
        <div class="absolute -bottom-20 -right-20 size-96 rounded-full bg-brand-400/20 blur-3xl" />
      </div>

      <div class="relative">
        <div class="flex items-center gap-3">
          <div v-if="siteSettings.settings.logo_url" class="flex size-11 items-center justify-center overflow-hidden rounded-2xl bg-white/10 ring-1 ring-white/20">
            <img :src="siteSettings.settings.logo_url" :alt="appName" class="max-h-8 max-w-8 object-contain" />
          </div>
          <div v-else class="flex size-11 items-center justify-center rounded-2xl bg-white/10 ring-1 ring-white/20">
            <Building2 class="size-6 text-white" />
          </div>
          <span class="text-lg font-bold text-white">{{ appName }}</span>
        </div>
      </div>

      <div class="relative space-y-6">
        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm text-brand-100 ring-1 ring-white/20">
          <Sparkles class="size-4" />
          Platform Terintegrasi
        </div>
        <h1 class="text-4xl font-extrabold leading-tight tracking-tight text-white xl:text-5xl">
          Kelola pelanggan corporate,<br>pegawai & service fee<br>dalam satu tempat.
        </h1>
        <p class="max-w-md text-lg text-brand-100">
          Portal internal Astrindo Travel Services untuk mengelola data perusahaan pelanggan, pegawai, dan service fee matrix.
        </p>
      </div>

      <p class="relative text-sm text-brand-200">
        {{ footerText || `© ${new Date().getFullYear()} ${appName}` }}
      </p>
    </div>

    <!-- Right panel - login form -->
    <div class="flex w-full flex-col items-center justify-center px-6 py-12 lg:w-1/2">
      <div class="w-full max-w-md">
        <div class="mb-8 lg:hidden">
          <div class="flex items-center gap-2">
            <div class="flex size-10 items-center justify-center rounded-xl bg-brand-600 text-white">
              <Building2 class="size-5" />
            </div>
            <span class="text-lg font-bold text-slate-900">{{ appName }}</span>
          </div>
        </div>

        <div class="glass-panel overflow-hidden p-0">
          <div class="h-1 bg-gradient-to-r from-brand-500 to-violet-500" />
          <div class="p-8">
          <div class="mb-8 text-center lg:text-left">
            <span class="badge-brand">
              Welcome Back
            </span>
            <h2 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">Masuk ke akun Anda</h2>
            <p class="mt-1 text-sm text-slate-500">Silakan login untuk melanjutkan</p>
          </div>

          <form class="space-y-5" autocomplete="off" @submit.prevent="handleLogin">
            <div>
              <label class="form-label">Email</label>
              <div class="relative">
                <Mail class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="form.email"
                  type="email"
                  name="login-email"
                  required
                  autocomplete="off"
                  class="input-field !pl-10"
                  placeholder="nama@perusahaan.com"
                />
              </div>
            </div>

            <div>
              <label class="form-label">Password</label>
              <div class="relative">
                <Lock class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="form.password"
                  type="password"
                  name="login-password"
                  required
                  autocomplete="new-password"
                  class="input-field !pl-10"
                  placeholder="••••••••"
                />
              </div>
            </div>

            <div
              v-if="error"
              class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
              {{ error }}
            </div>

            <button type="submit" class="btn-primary w-full !py-3" :disabled="loading">
              <template v-if="loading">Memproses...</template>
              <template v-else>
                Login
                <ArrowRight class="size-4" />
              </template>
            </button>
          </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
