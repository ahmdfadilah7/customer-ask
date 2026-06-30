<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { KeyRound, Save, UserRound } from '@lucide/vue'
import { updatePassword, updateProfile } from '@/api/profile'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import { getApiErrorMessage } from '@/utils/apiError'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

const loading = ref(true)
const savingProfile = ref(false)
const savingPassword = ref(false)
const activeTab = ref(route.query.tab === 'password' ? 'password' : 'profile')

const profileForm = ref({ name: '', email: '' })
const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const roleLabel = computed(() => {
  const role = auth.user?.roles?.[0]
  const labels = {
    superadmin: 'Super Admin',
    admin: 'Admin',
    marketing: 'Marketing',
    tiketing: 'Ticketing',
  }
  return labels[role] ?? role ?? '—'
})

const branchLabel = computed(() => {
  if (auth.user?.has_full_branch_access) return 'Semua cabang'
  return auth.user?.branches?.map((b) => `${b.name} (${b.code})`).join(', ') || '—'
})

watch(activeTab, (tab) => {
  router.replace({ query: tab === 'password' ? { tab: 'password' } : {} })
})

watch(
  () => route.query.tab,
  (tab) => {
    activeTab.value = tab === 'password' ? 'password' : 'profile'
  },
)

onMounted(async () => {
  if (!auth.user) {
    await auth.fetchUser()
  }
  syncProfileForm()
  loading.value = false
})

function syncProfileForm() {
  profileForm.value = {
    name: auth.user?.name ?? '',
    email: auth.user?.email ?? '',
  }
}

async function handleProfileSubmit() {
  savingProfile.value = true
  try {
    const { data } = await updateProfile(profileForm.value)
    auth.user = data.data
    toast.success('Profil berhasil diperbarui.')
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memperbarui profil.'))
  } finally {
    savingProfile.value = false
  }
}

async function handlePasswordSubmit() {
  savingPassword.value = true
  try {
    const { data } = await updatePassword(passwordForm.value)
    toast.success(data.message)
    passwordForm.value = {
      current_password: '',
      password: '',
      password_confirmation: '',
    }
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memperbarui password.'))
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <div class="page-shell mx-auto max-w-3xl">
    <PageHeader
      :icon="UserRound"
      title="Profil Saya"
      description="Kelola informasi akun dan keamanan password."
    >
      <template v-if="auth.user" #actions>
        <div class="flex items-center gap-3 rounded-xl bg-gradient-to-br from-brand-50 to-white px-4 py-3 ring-1 ring-brand-100">
          <div class="flex size-10 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white shadow-md shadow-brand-600/30">
            {{ auth.user.name?.charAt(0) || 'A' }}
          </div>
          <div class="min-w-0">
            <p class="truncate font-semibold text-slate-900">{{ auth.user.name }}</p>
            <p class="truncate text-xs text-slate-500">{{ auth.user.email }}</p>
          </div>
        </div>
      </template>
    </PageHeader>

    <LoadingSpinner v-if="loading" label="Memuat profil..." />

    <div v-else class="glass-panel overflow-hidden p-4 sm:p-6">
      <el-tabs v-model="activeTab" class="profile-tabs">
        <el-tab-pane label="Data Profil" name="profile">
          <div class="pt-4">
            <dl class="info-card mb-6 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Role</dt>
                <dd class="mt-1">
                  <span class="badge-brand">{{ roleLabel }}</span>
                </dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Cabang</dt>
                <dd class="mt-1 font-medium text-slate-800">{{ branchLabel }}</dd>
              </div>
            </dl>

            <form class="space-y-4" @submit.prevent="handleProfileSubmit">
              <div>
                <label class="form-label">Nama</label>
                <input v-model="profileForm.name" type="text" required maxlength="255" class="input-field" />
              </div>
              <div>
                <label class="form-label">Email</label>
                <input v-model="profileForm.email" type="email" required maxlength="255" class="input-field" />
              </div>
              <div class="flex justify-end pt-2">
                <button type="submit" class="btn-primary" :disabled="savingProfile">
                  <Save class="size-4" />
                  {{ savingProfile ? 'Menyimpan...' : 'Simpan Profil' }}
                </button>
              </div>
            </form>
          </div>
        </el-tab-pane>

        <el-tab-pane label="Password" name="password">
          <div class="pt-4">
            <p class="mb-4 text-sm text-slate-500">
              Minimal 8 karakter. Masukkan password lama untuk verifikasi.
            </p>

            <form class="space-y-4" @submit.prevent="handlePasswordSubmit">
              <div>
                <label class="form-label">Password Saat Ini</label>
                <input v-model="passwordForm.current_password" type="password" required autocomplete="current-password" class="input-field" />
              </div>
              <div>
                <label class="form-label">Password Baru</label>
                <input v-model="passwordForm.password" type="password" required minlength="8" autocomplete="new-password" class="input-field" />
              </div>
              <div>
                <label class="form-label">Konfirmasi Password Baru</label>
                <input v-model="passwordForm.password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="input-field" />
              </div>
              <div class="flex justify-end pt-2">
                <button type="submit" class="btn-primary" :disabled="savingPassword">
                  <KeyRound class="size-4" />
                  {{ savingPassword ? 'Menyimpan...' : 'Ubah Password' }}
                </button>
              </div>
            </form>
          </div>
        </el-tab-pane>
      </el-tabs>
    </div>
  </div>
</template>

<style scoped>
.profile-tabs :deep(.el-tabs__header) {
  margin-bottom: 0;
}

.profile-tabs :deep(.el-tabs__nav-wrap::after) {
  height: 1px;
  background-color: #e2e8f0;
}

.profile-tabs :deep(.el-tabs__item) {
  font-weight: 500;
  color: #64748b;
}

.profile-tabs :deep(.el-tabs__item.is-active) {
  font-weight: 600;
  color: #4f46e5;
}

.profile-tabs :deep(.el-tabs__active-bar) {
  background-color: #4f46e5;
}
</style>
