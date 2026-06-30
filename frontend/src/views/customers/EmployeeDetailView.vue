<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Building2, Contact, IdCard, Pencil, Trash2, UserRound } from '@lucide/vue'
import { deleteEmployee, fetchEmployee } from '@/api/employees'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import DetailHero from '@/components/ui/DetailHero.vue'
import { TERMS } from '@/constants/terminology'
import { getApiErrorMessage } from '@/utils/apiError'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const confirm = useConfirm()
const toast = useToast()

const loading = ref(true)
const employee = ref(null)

const canManage = computed(() => auth.canManage('pegawai'))

const sections = computed(() => {
  if (!employee.value) return []
  const e = employee.value

  const result = [
    {
      title: 'Informasi Umum',
      icon: UserRound,
      fields: [
        { label: 'Nama Lengkap', value: e.full_name ?? '—' },
        { label: 'Status', value: e.status === 'active' ? 'Aktif' : e.status === 'inactive' ? 'Nonaktif' : (e.status ?? '—') },
        { label: 'Peran PIC', value: picLabel(e) },
      ],
    },
    {
      title: `Pelanggan & ${TERMS.corporate.label}`,
      icon: Building2,
      fields: [
        { label: TERMS.corporate.label, value: e.customer?.name ?? '—' },
        { label: 'Cabang', value: e.customer?.branch ? `${e.customer.branch.name} (${e.customer.branch.code})` : '—' },
      ],
    },
    {
      title: 'Identitas',
      icon: IdCard,
      fields: [
        { label: 'Title', value: e.title ?? '—' },
        { label: 'Nationality', value: e.nationality ?? '—' },
        { label: 'Nomor KTP', value: e.ktp_number ?? '—', mono: true },
        { label: 'Tanggal Lahir', value: formatDate(e.birthdate) },
      ],
    },
    {
      title: 'Passport & Tiket',
      icon: IdCard,
      fields: [
        { label: 'Nomor Passport', value: e.passport_number ?? '—', mono: true },
        { label: 'Exp. Passport', value: formatDate(e.passport_expiry) },
        { label: 'Format Nama Tiket', value: e.ticket_name_format ?? '—', wide: true },
      ],
    },
    {
      title: 'Kontak',
      icon: Contact,
      fields: [
        { label: 'Mobile', value: e.mobile ?? '—' },
        { label: 'Email', value: e.email ?? '—' },
      ],
    },
  ]

  if (e.is_pic && e.contact) {
    result.push({
      title: 'Data PIC Corporate',
      icon: Contact,
      fields: [
        { label: 'Nama PIC', value: e.contact.name ?? '—' },
        { label: 'Telepon PIC', value: e.contact.phone ?? '—' },
        { label: 'Email PIC', value: e.contact.email ?? '—' },
        { label: 'PIC Utama', value: e.contact.is_primary ? 'Ya' : 'Tidak' },
      ],
    })
  }

  result.push({
    title: 'Catatan Sistem',
    icon: UserRound,
    fields: [
      { label: 'Dibuat', value: formatDateTime(e.created_at) },
      { label: 'Diperbarui', value: formatDateTime(e.updated_at) },
    ],
  })

  return result
})

onMounted(loadDetail)

async function loadDetail() {
  loading.value = true
  try {
    const { data } = await fetchEmployee(route.params.id)
    employee.value = data.data
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat detail pegawai.'))
    router.push({ name: 'customer-list' })
  } finally {
    loading.value = false
  }
}

function goBack() {
  router.push({ name: 'customer-list' })
}

function openEdit() {
  router.push({ name: 'customer-list', query: { edit: employee.value.id } })
}

function openCorporate() {
  if (!employee.value?.customer?.id) return
  router.push({ name: 'corporate-detail', params: { id: employee.value.customer.id } })
}

async function handleDelete() {
  if (!employee.value) return

  const confirmed = await confirm.confirm({
    title: 'Hapus Pegawai',
    message: `Hapus pegawai "${employee.value.full_name}"? Tindakan ini tidak dapat dibatalkan.`,
    confirmLabel: 'Ya, Hapus',
    variant: 'danger',
  })

  if (!confirmed) return

  try {
    await deleteEmployee(employee.value.id)
    toast.success('Pegawai berhasil dihapus.')
    router.push({ name: 'customer-list' })
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus pegawai.'))
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

function formatDateTime(value) {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return value
  }
}

function picLabel(emp) {
  if (!emp?.is_pic) return 'Bukan PIC'
  return emp.is_primary_pic ? 'PIC Utama' : 'PIC'
}
</script>

<template>
  <div class="page-shell">
    <DetailHero
      :title="employee?.full_name"
      :subtitle="employee ? `${employee.customer?.name ?? '—'} · ${employee.customer?.branch?.code ?? '—'}` : ''"
    >
      <template #leading>
        <button type="button" class="btn-secondary !py-2" @click="goBack">
          <ArrowLeft class="size-4" />
          Kembali
        </button>
      </template>
      <template v-if="employee" #meta>
        <span
          class="mt-2 inline-flex"
          :class="employee.is_pic
            ? (employee.is_primary_pic ? 'badge-brand' : 'badge-success')
            : 'badge-neutral'"
        >
          {{ picLabel(employee) }}
        </span>
        <span
          class="mt-2 inline-flex"
          :class="employee.status === 'active' ? 'badge-success' : 'badge-neutral'"
        >
          {{ employee.status === 'active' ? 'Aktif' : 'Nonaktif' }}
        </span>
      </template>
      <template #actions>
        <button
          v-if="employee?.customer?.id"
          type="button"
          class="btn-secondary !py-2"
          @click="openCorporate"
        >
          <Building2 class="size-4" />
          Lihat {{ TERMS.corporate.label }}
        </button>
        <button v-if="canManage && employee" type="button" class="btn-primary !py-2" @click="openEdit">
          <Pencil class="size-4" />
          Edit
        </button>
        <button v-if="canManage && employee" type="button" class="btn-danger-outline !py-2" @click="handleDelete">
          <Trash2 class="size-4" />
          Hapus
        </button>
      </template>
    </DetailHero>

    <LoadingSpinner v-if="loading" label="Memuat detail pegawai..." />

    <div v-else-if="employee" class="space-y-6">
      <div
        v-for="section in sections"
        :key="section.title"
        class="glass-panel p-6"
      >
        <div class="section-header">
          <h4 class="section-title">
            <component :is="section.icon" class="section-icon" />
            {{ section.title }}
          </h4>
        </div>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
          <div
            v-for="field in section.fields"
            :key="field.label"
            class="rounded-xl border border-slate-100 bg-slate-50/50 px-4 py-3"
            :class="field.wide ? 'sm:col-span-2' : ''"
          >
            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ field.label }}</dt>
            <dd
              class="mt-1 whitespace-pre-wrap font-medium text-slate-800"
              :class="field.mono ? 'font-mono text-sm' : ''"
            >
              {{ field.value }}
            </dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</template>
