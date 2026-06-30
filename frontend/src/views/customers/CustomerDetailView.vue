<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Building2, Trash2, UserRound, Users } from '@lucide/vue'
import { deleteCustomer, fetchCustomer } from '@/api/customers'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import DetailHero from '@/components/ui/DetailHero.vue'
import { TERMS } from '@/constants/terminology'
import AppDataTable from '@/components/ui/AppDataTable.vue'
import AppTableColumn from '@/components/ui/AppTableColumn.vue'
import { getApiErrorMessage } from '@/utils/apiError'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const confirm = useConfirm()
const toast = useToast()

const canManage = computed(() => auth.canManage('corporate'))
const canImportPegawai = computed(() => auth.canImport('pegawai'))

const loading = ref(true)
const customer = ref(null)

const picCount = computed(() => customer.value?.employees?.filter((e) => e.is_pic).length ?? 0)
const primaryPicCount = computed(() => customer.value?.employees?.filter((e) => e.is_primary_pic).length ?? 0)

onMounted(loadDetail)

async function loadDetail() {
  loading.value = true
  try {
    const { data } = await fetchCustomer(route.params.id)
    customer.value = data.data
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat detail customer.'))
    router.push({ name: 'customer-list' })
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push({ name: 'customer-list' })
}

function openCorporate() {
  router.push({ name: 'corporate-detail', params: { id: customer.value.id } })
}

async function handleDeleteCustomer() {
  if (!customer.value) return

  const confirmed = await confirm.confirm({
    title: 'Hapus Customer',
    message: `Hapus customer/corporate "${customer.value.name}" beserta data terkait? Tindakan ini tidak dapat dibatalkan.`,
    confirmLabel: 'Ya, Hapus',
    variant: 'danger',
  })

  if (!confirmed) return

  try {
    await deleteCustomer(customer.value.id)
    toast.success(`Customer "${customer.value.name}" berhasil dihapus.`)
    router.push({ name: 'customer-list' })
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus customer.'))
  }
}

function formatDate(value) {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    })
  } catch {
    return value
  }
}

function picLabel(employee) {
  if (!employee.is_pic) return 'Bukan PIC'
  return employee.is_primary_pic ? 'PIC Utama' : 'PIC'
}
</script>

<template>
  <div class="page-shell">
    <DetailHero
      :title="customer?.name"
      :subtitle="customer ? `${customer.branch?.name} (${customer.branch?.code})` : ''"
    >
      <template #leading>
        <button type="button" class="btn-secondary !py-2" @click="goBack">
          <ArrowLeft class="size-4" />
          Kembali
        </button>
      </template>
      <template #actions>
        <button v-if="customer" type="button" class="btn-secondary !py-2" @click="openCorporate">
          <Building2 class="size-4" />
          Profil {{ TERMS.corporate.label }}
        </button>
        <button v-if="canManage && customer" type="button" class="btn-danger-outline !py-2" @click="handleDeleteCustomer">
          <Trash2 class="size-4" />
          Hapus
        </button>
      </template>
    </DetailHero>

    <LoadingSpinner v-if="loading" label="Memuat detail..." />

    <template v-else-if="customer">
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="stat-mini">
          <p class="stat-mini__value">{{ customer.employees?.length ?? 0 }}</p>
          <p class="stat-mini__label">Total Pegawai</p>
        </div>
        <div class="stat-mini">
          <p class="stat-mini__value text-emerald-700">{{ picCount }}</p>
          <p class="stat-mini__label">Sebagai PIC</p>
        </div>
        <div class="stat-mini">
          <p class="stat-mini__value text-brand-700">{{ primaryPicCount }}</p>
          <p class="stat-mini__label">PIC Utama</p>
        </div>
      </div>

      <div class="glass-panel p-6">
        <div class="section-header">
          <h4 class="section-title">
            <Users class="section-icon" />
            Data Pegawai
          </h4>
        </div>

        <AppDataTable
          v-if="customer.employees?.length"
          bare
          :data="customer.employees"
          search-placeholder="Cari employee..."
          :page-size="25"
          empty-text="Belum ada pegawai."
        >
          <AppTableColumn prop="full_name" label="Nama" sortable min-width="180" fixed="left" />
          <AppTableColumn prop="title" label="Title" width="80" />
          <AppTableColumn prop="nationality" label="Nationality" width="110" />
          <AppTableColumn prop="passport_number" label="Passport" width="110" />
          <AppTableColumn label="Exp. Passport" width="110">
            <template #default="{ row }">{{ formatDate(row.passport_expiry) }}</template>
          </AppTableColumn>
          <AppTableColumn prop="ktp_number" label="KTP" width="150" />
          <AppTableColumn prop="mobile" label="Mobile" width="130" />
          <AppTableColumn prop="email" label="Email" min-width="160" />
          <AppTableColumn prop="ticket_name_format" label="Ticket Name" min-width="180" />
          <AppTableColumn label="Status PIC" width="120" sortable>
            <template #default="{ row }">
              <span
                :class="row.is_pic
                  ? (row.is_primary_pic ? 'badge-brand' : 'badge-success')
                  : 'badge-neutral'"
              >
                {{ picLabel(row) }}
              </span>
            </template>
          </AppTableColumn>
        </AppDataTable>

        <p v-else class="empty-state !py-6">
          Belum ada data pegawai.
          <template v-if="canImportPegawai"> Import lewat menu Import → Data Pegawai.</template>
        </p>
      </div>

      <div v-if="customer.contacts?.length" class="glass-panel p-6">
        <div class="section-header">
          <h4 class="section-title">
            <UserRound class="section-icon" />
            Daftar PIC Terdaftar
          </h4>
        </div>
        <ul class="space-y-2 text-sm">
          <li
            v-for="contact in customer.contacts"
            :key="contact.id"
            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200/80 bg-white px-4 py-3 shadow-sm"
          >
            <div>
              <span class="font-medium text-slate-900">{{ contact.name }}</span>
              <span v-if="contact.email" class="ml-2 text-slate-500">{{ contact.email }}</span>
            </div>
            <div class="flex items-center gap-2">
              <span v-if="contact.employee_id" class="badge-success">Dari Employee</span>
              <span v-if="contact.is_primary" class="badge-brand">Utama</span>
            </div>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>
