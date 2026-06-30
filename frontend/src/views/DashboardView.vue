<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Archive,
  Building2,
  ChevronRight,
  Database,
  FileUp,
  Receipt,
  Shield,
  Sparkles,
  UserRound,
  Users,
} from '@lucide/vue'
import { fetchDashboardStats } from '@/api/dashboard'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import StatCard from '@/components/ui/StatCard.vue'
import PageInfoBanner from '@/components/ui/PageInfoBanner.vue'
import { P } from '@/constants/permissions'
import { TERMS } from '@/constants/terminology'
import { getApiErrorMessage } from '@/utils/apiError'

const auth = useAuthStore()
const toast = useToast()

const loading = ref(true)
const stats = ref(null)
const byBranch = ref([])

const roleLabel = computed(() => auth.user?.role_label || auth.roles[0] || 'User')

const accessSummary = computed(() => {
  if (auth.hasRole('superadmin')) {
    return 'Akses penuh ke data pelanggan corporate, pegawai, import, master data, dan role management.'
  }
  if (auth.hasRole('admin')) {
    return 'Kelola pelanggan corporate, pegawai, import, dan master data sesuai cabang.'
  }
  if (auth.hasRole('marketing')) {
    return 'Kelola dan import data pelanggan corporate, service fee, serta pegawai per cabang yang ditugaskan.'
  }
  if (auth.hasRole('tiketing')) {
    return 'Lihat data pelanggan corporate dan pegawai sesuai cabang yang diizinkan.'
  }
  return 'Selamat datang di portal Astrindo Travel Services.'
})

const branchNames = computed(() =>
  auth.user?.has_full_branch_access
    ? 'Semua cabang'
    : auth.user?.branches?.map((b) => b.name).join(', ') || '—',
)

const statCards = computed(() => {
  if (!stats.value) return []

  const cards = []

  if (auth.hasPermission(P.CORPORATE_VIEW)) {
    cards.push(
      { key: 'corporates', title: 'Pelanggan Corporate', value: stats.value.corporates, icon: Building2, accent: 'brand', to: '/corporate' },
      { key: 'employees', title: 'Pegawai', value: stats.value.employees, icon: Users, accent: 'emerald', to: '/customers' },
      { key: 'pics', title: 'Kontak PIC', value: stats.value.pics, icon: UserRound, accent: 'violet', to: '/corporate' },
      { key: 'pricing_rules', title: 'Service Fee', value: stats.value.pricing_rules, icon: Receipt, accent: 'amber', to: '/corporate' },
    )

    if (auth.hasPermission('corporate-delete') && stats.value.corporates_trashed > 0) {
      cards.push({
        key: 'trashed',
        title: 'Di Sampah',
        value: stats.value.corporates_trashed,
        icon: Archive,
        accent: 'brand',
        to: '/corporate?view=trashed',
      })
    }
  }

  return cards
})

const quickLinks = computed(() =>
  [
    { path: '/corporate', label: TERMS.corporate.label, desc: 'Profil perusahaan, PIC & service fee', icon: Building2, permission: P.CORPORATE_VIEW },
    { path: '/customers', label: TERMS.employee.label, desc: 'Tambah, edit & kirim WhatsApp', icon: UserRound, permission: P.PEGAWAI_VIEW },
    { path: '/import/corporate', label: 'Import Corporate', desc: 'Profil pelanggan & materai', icon: FileUp, permission: P.IMPORT_CORPORATE },
    { path: '/import/service', label: 'Import Service', desc: 'Service fee matrix', icon: Receipt, permission: P.IMPORT_SERVICE },
    { path: '/import/employee', label: 'Import Pegawai', desc: 'Data pegawai corporate', icon: Users, permission: P.IMPORT_PEGAWAI },
    { path: '/master-data/branches', label: 'Master Data', desc: 'Cabang, maskapai, template pesan', icon: Database, permission: P.CABANG_VIEW },
    { path: '/users', label: 'Users', desc: 'Manajemen akun internal', icon: Users, permission: P.USER_VIEW },
    { path: '/roles', label: 'Roles', desc: 'Permission role', icon: Shield, permission: P.ROLE_VIEW },
  ].filter((item) => auth.hasPermission(item.permission)),
)

onMounted(loadStats)

async function loadStats() {
  if (!auth.hasPermission(P.DASHBOARD_VIEW)) return

  loading.value = true
  try {
    const { data } = await fetchDashboardStats()
    stats.value = data.data
    byBranch.value = data.by_branch ?? []
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat statistik dashboard.'))
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="page-shell">
    <div class="glass-panel overflow-hidden p-0">
      <div class="h-1 bg-gradient-to-r from-brand-500 via-brand-600 to-violet-500" />
      <div class="flex flex-wrap items-start justify-between gap-5 p-6 sm:p-8">
        <div class="min-w-0 flex-1">
          <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 ring-1 ring-brand-100">
            <Sparkles class="size-3.5" />
            {{ roleLabel }}
          </div>
          <p class="text-sm text-slate-500">Selamat datang kembali,</p>
          <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ auth.user?.name }}</h2>
          <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-500">{{ accessSummary }}</p>
        </div>
        <div class="info-card min-w-[200px]">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Cabang Akses</p>
          <p class="mt-1.5 text-sm font-semibold text-slate-800">{{ branchNames }}</p>
        </div>
      </div>
    </div>

    <PageInfoBanner v-if="auth.hasPermission(P.CORPORATE_VIEW)" title="Panduan Menu">
      <strong>Corporate</strong> = profil perusahaan pelanggan, kontak PIC, dan service fee.
      <strong>Pegawai</strong> = data pegawai perorangan (tambah, edit, WhatsApp).
      Gunakan menu <strong>Import</strong> untuk unggah data massal.
    </PageInfoBanner>

    <LoadingSpinner v-if="loading" label="Memuat statistik..." />

    <template v-else>
      <div v-if="statCards.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <RouterLink
          v-for="card in statCards"
          :key="card.key"
          :to="card.to"
          class="block transition hover:-translate-y-0.5"
        >
          <StatCard
            :title="card.title"
            :value="card.value ?? 0"
            :icon="card.icon"
            :accent="card.accent"
          />
        </RouterLink>
      </div>

      <div v-if="auth.hasPermission(P.CORPORATE_VIEW) && byBranch.length" class="glass-panel p-6">
        <div class="section-header !mb-5">
          <h3 class="section-title">
            <Building2 class="section-icon" />
            Ringkasan per Cabang
          </h3>
        </div>
        <div class="table-shell overflow-x-auto">
          <table class="data-table min-w-[520px]">
            <thead>
              <tr>
                <th>Cabang</th>
                <th>Pelanggan</th>
                <th>Pegawai</th>
                <th>PIC</th>
                <th>Service Fee</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in byBranch" :key="row.id">
                <td class="font-medium text-slate-800">
                  {{ row.name }}
                  <span class="ml-1 text-xs font-normal text-slate-400">({{ row.code }})</span>
                </td>
                <td>{{ row.corporates }}</td>
                <td>{{ row.employees }}</td>
                <td>{{ row.pics }}</td>
                <td>{{ row.pricing_rules }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="quickLinks.length">
        <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Akses Cepat</h3>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <RouterLink
            v-for="link in quickLinks"
            :key="link.path"
            :to="link.path"
            class="quick-link-card group"
          >
            <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600 ring-1 ring-brand-100">
              <component :is="link.icon" class="size-5" />
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-semibold text-slate-900 group-hover:text-brand-700">{{ link.label }}</p>
              <p class="mt-0.5 text-xs text-slate-500">{{ link.desc }}</p>
            </div>
            <ChevronRight class="size-4 shrink-0 text-slate-300 transition group-hover:text-brand-500" />
          </RouterLink>
        </div>
      </div>
    </template>
  </div>
</template>
