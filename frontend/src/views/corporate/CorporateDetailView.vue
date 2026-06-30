<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Building2,
  FileUp,
  MapPin,
  Plane,
  Plus,
  Pencil,
  Receipt,
  Settings2,
  Trash2,
  UserRound,
  Users,
} from '@lucide/vue'
import { deleteCustomer, fetchCustomer } from '@/api/customers'
import {
  createPricingRule,
  deletePricingRule,
  fetchPricingReference,
  updatePricingRule,
} from '@/api/customerPricing'
import {
  createCustomerContact,
  deleteCustomerContact,
  updateCustomerContact,
} from '@/api/customerContacts'
import {
  createEmployee,
  fetchEmployee,
  updateEmployee,
} from '@/api/employees'
import { fetchNationalities } from '@/api/nationalities'
import { fetchTitles } from '@/api/titles'
import { useAuthStore } from '@/stores/auth'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import DetailHero from '@/components/ui/DetailHero.vue'
import AppModal from '@/components/ui/AppModal.vue'
import PricingRuleFormModal from '@/components/corporate/PricingRuleFormModal.vue'
import EmployeeFormModal from '@/components/employees/EmployeeFormModal.vue'
import { TERMS } from '@/constants/terminology'
import { getApiErrorMessage } from '@/utils/apiError'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const confirm = useConfirm()
const toast = useToast()

const loading = ref(true)
const customer = ref(null)
const activeTab = ref('profile')
const showPicModal = ref(false)
const savingPic = ref(false)
const editingPic = ref(null)
const showPricingModal = ref(false)
const savingPricing = ref(false)
const editingPricingRule = ref(null)
const pricingReference = ref(null)
const showEmployeeModal = ref(false)
const savingEmployee = ref(false)
const editingEmployee = ref(null)
const titles = ref([])
const nationalities = ref([])

const emptyPicForm = () => ({ employee_id: '', name: '', phone: '', email: '', is_primary: false })
const picForm = ref(emptyPicForm())
const picSource = ref('manual')

const canManage = computed(() => auth.canManage('corporate'))
const canUpdateCorporate = computed(() => auth.hasPermission('corporate-update'))
const canManageEmployee = computed(() => auth.canManage('pegawai'))
const canImportCorporate = computed(() => auth.canImport('corporate'))
const canImportService = computed(() => auth.canImport('service'))
const canImportPegawai = computed(() => auth.canImport('pegawai'))
const existingPricingRules = computed(() => {
  if (!customer.value?.pricing) return []
  return [
    ...(customer.value.pricing.airlines ?? []),
    ...(customer.value.pricing.services ?? []),
  ]
})
const canManagePic = computed(() => canManage.value)

const tabs = [
  { id: 'profile', label: 'Profil', icon: Building2 },
  { id: 'pic', label: 'Kontak PIC', icon: UserRound },
  { id: 'employees', label: TERMS.employee.label, icon: Users },
  { id: 'service-fee', label: 'Service Fee', icon: Receipt },
]

const summaryStats = computed(() => {
  if (!customer.value) return []
  const c = customer.value
  return [
    { key: 'employees', label: TERMS.employee.plural, value: c.employees?.length ?? 0, tab: 'employees', accent: 'emerald' },
    { key: 'pic', label: 'Kontak PIC', value: c.contacts?.length ?? 0, tab: 'pic', accent: 'violet' },
    { key: 'airlines', label: 'Maskapai', value: c.pricing?.airlines?.length ?? 0, tab: 'service-fee', accent: 'brand' },
    { key: 'services', label: 'Layanan Lain', value: c.pricing?.services?.length ?? 0, tab: 'service-fee', accent: 'amber' },
  ]
})

const profileSections = computed(() => {
  if (!customer.value) return []
  const c = customer.value
  return [
    {
      title: 'Informasi Umum',
      icon: MapPin,
      fields: [
        { label: 'Cabang', value: c.branch ? `${c.branch.name} (${c.branch.code})` : '—' },
        { label: 'Periode Kontrak', value: c.contract_period ?? '—' },
        { label: 'Versi Service Fee', value: c.active_pricing_version?.name ?? 'Belum diimport' },
      ],
    },
    {
      title: 'Pengaturan Corporate',
      icon: Settings2,
      fields: [
        { label: 'Mode Corporate', value: formatBool(c.corp_mode), type: 'bool', raw: c.corp_mode },
        { label: 'Faktur Pajak', value: formatBool(c.faktur_pajak), type: 'bool', raw: c.faktur_pajak },
        { label: 'Tampil Service Fee', value: formatBool(c.show_service_fee), type: 'bool', raw: c.show_service_fee },
        { label: 'Metode Invoice', value: formatInvoice(c.invoice_method) },
        { label: 'CN %', value: c.cn_percentage != null ? `${c.cn_percentage}%` : '—' },
      ],
    },
    {
      title: 'Keuangan & Catatan',
      icon: Receipt,
      fields: [
        { label: 'Materai', value: c.pricing?.materai ?? '—' },
        { label: 'Catatan Umum', value: c.general_note ?? '—', wide: true },
      ],
    },
  ]
})

const heroSubtitle = computed(() => {
  if (!customer.value) return ''
  const parts = []
  if (customer.value.branch?.name) {
    parts.push(`${customer.value.branch.name} (${customer.value.branch.code})`)
  }
  if (customer.value.active_pricing_version?.name) {
    parts.push(`Service Fee: ${customer.value.active_pricing_version.name}`)
  }
  return parts.join(' · ')
})

const primaryPic = computed(() =>
  customer.value?.contacts?.find((c) => c.is_primary) ?? null,
)

const customerOptions = computed(() => (customer.value ? [customer.value] : []))

onMounted(async () => {
  await loadDetail()
  if (canUpdateCorporate.value) {
    loadPricingReference()
  }
})

async function loadPricingReference() {
  try {
    const { data } = await fetchPricingReference()
    pricingReference.value = data
  } catch {
    pricingReference.value = null
  }
}

async function loadDetail() {
  loading.value = true
  try {
    const { data } = await fetchCustomer(route.params.id)
    customer.value = data.data
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat detail corporate.'))
    router.push({ name: 'corporate-list' })
  } finally {
    loading.value = false
  }
}

function formatBool(value) {
  if (value === true) return 'Ya'
  if (value === false) return 'Tidak'
  return '—'
}

function formatInvoice(method) {
  if (!method) return '—'
  return method.replace(/_/g, ' & ')
}

function applyEmployeeToPicForm(employeeId) {
  if (!employeeId) return
  const employee = customer.value?.employees?.find((e) => e.id === Number(employeeId))
  if (!employee) return
  picForm.value.name = employee.full_name
  picForm.value.phone = employee.mobile ?? ''
  picForm.value.email = employee.email ?? ''
}

watch(
  () => picForm.value.employee_id,
  (employeeId) => {
    if (picSource.value === 'employee' && employeeId) {
      applyEmployeeToPicForm(employeeId)
    }
  },
)

function goBack() {
  router.push({ name: 'corporate-list' })
}

function openPegawaiList() {
  if (!customer.value?.id) return
  router.push({ name: 'customer-list', query: { customer_id: customer.value.id } })
}

function openEmployeeDetail(employeeId) {
  router.push({ name: 'employee-detail', params: { id: employeeId } })
}

function switchTab(tabId) {
  activeTab.value = tabId
}

function statAccentClass(accent) {
  const map = {
    brand: 'from-brand-50 to-white ring-brand-100/80 text-brand-700',
    emerald: 'from-emerald-50 to-white ring-emerald-100/80 text-emerald-700',
    violet: 'from-violet-50 to-white ring-violet-100/80 text-violet-700',
    amber: 'from-amber-50 to-white ring-amber-100/80 text-amber-700',
  }
  return map[accent] ?? map.brand
}

async function handleDeleteCorporate() {
  if (!customer.value) return

  const confirmed = await confirm.confirm({
    title: 'Hapus Corporate',
    message: `Hapus corporate "${customer.value.name}"? Data dipindahkan ke sampah dan masih bisa dipulihkan.`,
    confirmLabel: 'Ya, Hapus',
    variant: 'danger',
  })

  if (!confirmed) return

  try {
    await deleteCustomer(customer.value.id)
    toast.success(`Corporate "${customer.value.name}" berhasil dihapus.`)
    router.push({ name: 'corporate-list' })
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus corporate.'))
  }
}

function openCreatePic() {
  editingPic.value = null
  picSource.value = customer.value?.employees?.length ? 'employee' : 'manual'
  picForm.value = {
    ...emptyPicForm(),
    is_primary: !customer.value?.contacts?.length,
  }
  showPicModal.value = true
}

function openEditPic(contact) {
  editingPic.value = contact
  picSource.value = contact.employee_id ? 'employee' : 'manual'
  picForm.value = {
    employee_id: contact.employee_id ?? '',
    name: contact.name,
    phone: contact.phone ?? '',
    email: contact.email ?? '',
    is_primary: contact.is_primary,
  }
  showPicModal.value = true
}

function onPicSourceChange() {
  if (picSource.value === 'manual') {
    picForm.value.employee_id = ''
    return
  }
  if (picForm.value.employee_id) {
    applyEmployeeToPicForm(picForm.value.employee_id)
  }
}

function closePicModal() {
  showPicModal.value = false
}

async function handlePicSubmit() {
  savingPic.value = true
  try {
    const payload = {
      name: picForm.value.name.trim(),
      phone: picForm.value.phone.trim() || null,
      email: picForm.value.email.trim() || null,
      is_primary: picForm.value.is_primary,
      employee_id: picForm.value.employee_id ? Number(picForm.value.employee_id) : null,
    }

    if (editingPic.value) {
      await updateCustomerContact(customer.value.id, editingPic.value.id, payload)
      toast.success('PIC berhasil diperbarui.')
    } else {
      await createCustomerContact(customer.value.id, payload)
      toast.success('PIC berhasil ditambahkan.')
    }

    closePicModal()
    await loadDetail()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menyimpan PIC.'))
  } finally {
    savingPic.value = false
  }
}

async function handleDeletePic(contact) {
  const confirmed = await confirm.confirm({
    title: 'Hapus PIC',
    message: `Hapus PIC "${contact.name}"? Tindakan ini tidak dapat dibatalkan.`,
    confirmLabel: 'Ya, Hapus',
    variant: 'danger',
  })

  if (!confirmed) return

  try {
    await deleteCustomerContact(customer.value.id, contact.id)
    toast.success('PIC berhasil dihapus.')
    await loadDetail()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus PIC.'))
  }
}

function openEditCorporate() {
  if (!customer.value) return
  router.push({ name: 'corporate-edit', params: { id: customer.value.id } })
}

function openCreatePricingRule() {
  editingPricingRule.value = null
  showPricingModal.value = true
}

function openEditPricingRule(rule) {
  editingPricingRule.value = rule
  showPricingModal.value = true
}

async function handlePricingSubmit(payload) {
  if (!customer.value) return
  const items = payload.items ?? [payload]
  if (!items.length) return

  savingPricing.value = true
  try {
    let created = 0
    let updated = 0

    for (const item of items) {
      const ruleId = editingPricingRule.value?.id ?? item.existing_rule_id
      const apiPayload = {
        service_category_id: item.service_category_id,
        region_scope_id: item.region_scope_id,
        airline_id: item.airline_id,
        raw_value: item.raw_value,
      }

      if (ruleId) {
        await updatePricingRule(customer.value.id, ruleId, apiPayload)
        updated += 1
      } else {
        await createPricingRule(customer.value.id, apiPayload)
        created += 1
      }
    }

    if (items.length === 1) {
      toast.success(updated ? 'Service fee berhasil diperbarui.' : 'Service fee berhasil ditambahkan.')
    } else {
      toast.success(`${items.length} tarif service fee berhasil disimpan (${created} baru, ${updated} diperbarui).`)
    }

    showPricingModal.value = false
    await loadDetail()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menyimpan service fee.'))
  } finally {
    savingPricing.value = false
  }
}

async function handleDeletePricingRule(rule) {
  if (!customer.value) return
  const confirmed = await confirm.confirm({
    title: 'Hapus Service Fee',
    message: `Hapus tarif "${rule.label || rule.raw_value}"?`,
    confirmLabel: 'Ya, Hapus',
    variant: 'danger',
  })
  if (!confirmed) return

  try {
    await deletePricingRule(customer.value.id, rule.id)
    toast.success('Service fee berhasil dihapus.')
    await loadDetail()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus service fee.'))
  }
}

async function ensureEmployeeFormOptions() {
  if (!titles.value.length) {
    const { data } = await fetchTitles()
    titles.value = data.data ?? []
  }
  if (!nationalities.value.length) {
    const { data } = await fetchNationalities()
    nationalities.value = data.data ?? []
  }
}

async function openAddEmployee() {
  await ensureEmployeeFormOptions()
  editingEmployee.value = null
  showEmployeeModal.value = true
}

async function openEditEmployee(employee) {
  await ensureEmployeeFormOptions()
  try {
    const { data } = await fetchEmployee(employee.id)
    editingEmployee.value = data.data
    showEmployeeModal.value = true
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat data pegawai.'))
  }
}

async function handleEmployeeSubmit(payload) {
  savingEmployee.value = true
  try {
    if (editingEmployee.value) {
      await updateEmployee(editingEmployee.value.id, payload)
      toast.success('Pegawai berhasil diperbarui.')
    } else {
      await createEmployee(payload)
      toast.success('Pegawai berhasil ditambahkan.')
    }
    showEmployeeModal.value = false
    await loadDetail()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menyimpan pegawai.'))
  } finally {
    savingEmployee.value = false
  }
}
</script>

<template>
  <div class="page-shell">
    <DetailHero :title="customer?.name" :subtitle="heroSubtitle">
      <template #leading>
        <button type="button" class="btn-secondary !py-2" @click="goBack">
          <ArrowLeft class="size-4" />
          Kembali
        </button>
      </template>
      <template v-if="customer" #meta>
        <div class="mt-2 flex flex-wrap gap-2">
          <span v-if="customer.corp_mode" class="badge-success">Corp Mode</span>
          <span v-if="primaryPic" class="badge-brand">PIC: {{ primaryPic.name }}</span>
          <span v-else class="badge-warning">Belum ada PIC utama</span>
          <span v-if="!customer.active_pricing_version" class="badge-warning">Service fee belum diimport</span>
        </div>
      </template>
      <template #actions>
        <button
          v-if="customer"
          type="button"
          class="btn-primary !py-2"
          @click="openPegawaiList"
        >
          <Users class="size-4" />
          {{ TERMS.employee.label }}
        </button>
        <button
          v-if="canUpdateCorporate && customer"
          type="button"
          class="btn-secondary !py-2"
          @click="openEditCorporate"
        >
          <Pencil class="size-4" />
          Edit Corporate
        </button>
        <router-link v-if="canImportService" to="/import/service" class="btn-secondary !py-2">
          <FileUp class="size-4" />
          Import Service
        </router-link>
        <button
          v-if="canManage && customer"
          type="button"
          class="btn-danger-outline !py-2"
          @click="handleDeleteCorporate"
        >
          <Trash2 class="size-4" />
          Hapus
        </button>
      </template>
    </DetailHero>

    <LoadingSpinner v-if="loading" label="Memuat detail corporate..." />

    <template v-else-if="customer">
      <!-- Ringkasan cepat -->
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <button
          v-for="stat in summaryStats"
          :key="stat.key"
          type="button"
          class="group rounded-2xl bg-gradient-to-br p-4 text-left ring-1 transition hover:-translate-y-0.5 hover:shadow-md"
          :class="statAccentClass(stat.accent)"
          @click="switchTab(stat.tab)"
        >
          <p class="text-2xl font-bold tracking-tight text-slate-900">{{ stat.value }}</p>
          <p class="mt-0.5 text-sm font-medium text-slate-600">{{ stat.label }}</p>
          <p class="mt-2 text-xs font-medium opacity-0 transition group-hover:opacity-100">
            Klik untuk lihat →
          </p>
        </button>
      </div>

      <!-- Tab navigasi -->
      <div class="glass-panel overflow-hidden p-0">
        <div class="border-b border-slate-100 bg-slate-50/50 px-4 py-3 sm:px-6">
          <div class="segment-control w-full sm:w-auto">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              type="button"
              class="segment-control__btn flex items-center gap-1.5"
              :class="activeTab === tab.id ? 'segment-control__btn--active' : 'segment-control__btn--inactive'"
              @click="switchTab(tab.id)"
            >
              <component :is="tab.icon" class="size-4 shrink-0" />
              <span class="hidden sm:inline">{{ tab.label }}</span>
              <span class="sm:hidden">{{ tab.label.split(' ')[0] }}</span>
            </button>
          </div>
        </div>

        <div class="p-5 sm:p-6">
          <!-- Tab: Profil -->
          <div v-show="activeTab === 'profile'" class="space-y-6">
            <p class="text-sm text-slate-500">
              Profil dan pengaturan {{ TERMS.corporate.singular.toLowerCase() }} pelanggan.
              <template v-if="canUpdateCorporate">
                Klik <button type="button" class="font-medium text-brand-600 hover:underline" @click="openEditCorporate">Edit Corporate</button>
                untuk mengubah data.
              </template>
              <template v-else-if="canImportCorporate">
                Data diimport lewat
                <router-link to="/import/corporate" class="font-medium text-brand-600 hover:underline">Import Corporate</router-link>.
              </template>
            </p>
            <div
              v-for="section in profileSections"
              :key="section.title"
              class="rounded-2xl border border-slate-100 bg-slate-50/30 p-5"
            >
              <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900">
                <component :is="section.icon" class="size-4 text-brand-600" />
                {{ section.title }}
              </h4>
              <dl class="grid gap-3 sm:grid-cols-2">
                <div
                  v-for="field in section.fields"
                  :key="field.label"
                  class="rounded-xl border border-slate-100 bg-white px-4 py-3"
                  :class="field.wide ? 'sm:col-span-2' : ''"
                >
                  <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ field.label }}</dt>
                  <dd class="mt-1">
                    <span
                      v-if="field.type === 'bool' && field.raw != null"
                      :class="field.raw ? 'badge-success' : 'badge-neutral'"
                    >
                      {{ field.value }}
                    </span>
                    <span v-else class="whitespace-pre-wrap font-medium text-slate-800">{{ field.value }}</span>
                  </dd>
                </div>
              </dl>
            </div>
          </div>

          <!-- Tab: PIC -->
          <div v-show="activeTab === 'pic'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <p class="text-sm text-slate-500">
                Kontak person in charge untuk komunikasi dengan pelanggan corporate ini.
              </p>
              <button
                v-if="canManagePic"
                type="button"
                class="btn-primary !py-2"
                @click="openCreatePic"
              >
                <Plus class="size-4" />
                Tambah PIC
              </button>
            </div>

            <div v-if="customer.contacts?.length" class="grid gap-3 sm:grid-cols-2">
              <div
                v-for="contact in customer.contacts"
                :key="contact.id"
                class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm transition hover:border-brand-200 hover:shadow-md"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <p class="truncate font-semibold text-slate-900">{{ contact.name }}</p>
                    <p v-if="contact.employee_id" class="mt-1 text-xs text-emerald-600">Terhubung ke pegawai</p>
                  </div>
                  <span v-if="contact.is_primary" class="badge-brand shrink-0">PIC Utama</span>
                </div>
                <dl class="mt-3 space-y-1.5 text-sm">
                  <div class="flex gap-2">
                    <dt class="w-14 shrink-0 text-slate-400">Telp</dt>
                    <dd class="font-medium text-slate-700">{{ contact.phone || '—' }}</dd>
                  </div>
                  <div class="flex gap-2">
                    <dt class="w-14 shrink-0 text-slate-400">Email</dt>
                    <dd class="truncate font-medium text-slate-700">{{ contact.email || '—' }}</dd>
                  </div>
                </dl>
                <div v-if="canManagePic" class="mt-4 flex justify-end gap-1 border-t border-slate-100 pt-3">
                  <button type="button" class="btn-icon-neutral" title="Edit PIC" @click="openEditPic(contact)">
                    <Pencil class="size-4" />
                  </button>
                  <button type="button" class="btn-icon-danger" title="Hapus PIC" @click="handleDeletePic(contact)">
                    <Trash2 class="size-4" />
                  </button>
                </div>
              </div>
            </div>
            <p v-else class="empty-state">
              Belum ada kontak PIC. Tambahkan PIC untuk memudahkan komunikasi dengan pelanggan ini.
            </p>
          </div>

          <!-- Tab: Pegawai -->
          <div v-show="activeTab === 'employees'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <p class="text-sm text-slate-500">
                {{ TERMS.employee.plural }} corporate ini.
              </p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-if="canManageEmployee"
                  type="button"
                  class="btn-primary !py-2 text-sm"
                  @click="openAddEmployee"
                >
                  <Plus class="size-4" />
                  Tambah Pegawai
                </button>
                <button type="button" class="btn-secondary !py-2 text-sm" @click="openPegawaiList">
                  <Users class="size-4" />
                  Buka Menu Pegawai
                </button>
              </div>
            </div>

            <div v-if="customer.employees?.length" class="table-shell overflow-x-auto">
              <table class="data-table min-w-[600px]">
                <thead>
                  <tr>
                    <th>Nama</th>
                    <th>Mobile</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th v-if="canManageEmployee" class="text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="employee in customer.employees" :key="employee.id">
                    <td>
                      <button
                        type="button"
                        class="font-medium text-brand-600 hover:underline"
                        @click="openEmployeeDetail(employee.id)"
                      >
                        {{ employee.full_name }}
                      </button>
                    </td>
                    <td>{{ employee.mobile || '—' }}</td>
                    <td>{{ employee.email || '—' }}</td>
                    <td>
                      <span :class="employee.status === 'active' ? 'badge-success' : 'badge-neutral'">
                        {{ employee.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                      </span>
                    </td>
                    <td v-if="canManageEmployee" class="text-right">
                      <button
                        type="button"
                        class="btn-icon-neutral"
                        title="Edit pegawai"
                        @click="openEditEmployee(employee)"
                      >
                        <Pencil class="size-4" />
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p v-else class="empty-state">
              Belum ada pegawai.
              <template v-if="canManageEmployee">
                <button type="button" class="text-brand-600 hover:underline" @click="openAddEmployee">Tambah pegawai</button>
                atau
              </template>
              <template v-else-if="canImportPegawai">
                <router-link to="/import/employee" class="text-brand-600 hover:underline">Import pegawai</router-link>
                atau
              </template>
              kelola lewat menu Pegawai.
            </p>
          </div>

          <!-- Tab: Service Fee -->
          <div v-show="activeTab === 'service-fee'" class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <p class="text-sm text-slate-500">
                Tarif service fee
                <span v-if="customer.active_pricing_version" class="font-medium text-slate-700">
                  — versi {{ customer.active_pricing_version.name }}
                </span>.
              </p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-if="canUpdateCorporate"
                  type="button"
                  class="btn-primary !py-2 text-sm"
                  @click="openCreatePricingRule"
                >
                  <Plus class="size-4" />
                  Tambah Tarif
                </button>
                <router-link
                  v-if="canImportService"
                  to="/import/service"
                  class="btn-secondary !py-2 text-sm"
                >
                  <FileUp class="size-4" />
                  Import Service
                </router-link>
              </div>
            </div>

            <p v-if="!customer.active_pricing_version" class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800 ring-1 ring-amber-100">
              Belum ada versi service fee aktif.
              <template v-if="canUpdateCorporate"> Tambah tarif manual atau import lewat menu Import → Data Service.</template>
              <template v-else-if="canImportService"> Import lewat menu Import → Data Service.</template>
            </p>

            <div class="rounded-2xl border border-slate-100 bg-slate-50/30 p-5">
              <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900">
                <Plane class="size-4 text-brand-600" />
                Maskapai
                <span class="font-normal text-slate-400">({{ customer.pricing?.airlines?.length ?? 0 }})</span>
              </h4>
              <div v-if="customer.pricing?.airlines?.length" class="table-shell overflow-x-auto">
                <table class="data-table min-w-[480px]">
                  <thead>
                    <tr>
                      <th>Kode</th>
                      <th>Scope</th>
                      <th>Nilai</th>
                      <th v-if="canUpdateCorporate" class="text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in customer.pricing.airlines" :key="row.id">
                      <td class="font-mono font-medium text-slate-800">{{ row.airline_code ?? '—' }}</td>
                      <td>{{ row.region_scope ?? '—' }}</td>
                      <td class="whitespace-pre-wrap text-sm">{{ row.raw_value }}</td>
                      <td v-if="canUpdateCorporate" class="text-right">
                        <div class="flex justify-end gap-1">
                          <button type="button" class="btn-icon-neutral" @click="openEditPricingRule(row)">
                            <Pencil class="size-4" />
                          </button>
                          <button type="button" class="btn-icon-danger" @click="handleDeletePricingRule(row)">
                            <Trash2 class="size-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="empty-state !py-4">Belum ada data maskapai.</p>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-slate-50/30 p-5">
              <h4 class="mb-4 flex items-center gap-2 text-sm font-semibold text-slate-900">
                <Receipt class="size-4 text-brand-600" />
                Layanan Lain
                <span class="font-normal text-slate-400">({{ customer.pricing?.services?.length ?? 0 }})</span>
              </h4>
              <div v-if="customer.pricing?.services?.length" class="table-shell overflow-x-auto">
                <table class="data-table min-w-[480px]">
                  <thead>
                    <tr>
                      <th>Layanan</th>
                      <th>Scope</th>
                      <th>Nilai</th>
                      <th v-if="canUpdateCorporate" class="text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in customer.pricing.services" :key="row.id">
                      <td class="font-medium text-slate-800">{{ row.label }}</td>
                      <td>{{ row.region_scope ?? '—' }}</td>
                      <td class="whitespace-pre-wrap text-sm">{{ row.raw_value }}</td>
                      <td v-if="canUpdateCorporate" class="text-right">
                        <div class="flex justify-end gap-1">
                          <button type="button" class="btn-icon-neutral" @click="openEditPricingRule(row)">
                            <Pencil class="size-4" />
                          </button>
                          <button type="button" class="btn-icon-danger" @click="handleDeletePricingRule(row)">
                            <Trash2 class="size-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p v-else class="empty-state !py-4">Belum ada data layanan lain.</p>
            </div>
          </div>
        </div>
      </div>
    </template>

    <AppModal v-model="showPicModal" :title="editingPic ? 'Edit PIC' : 'Tambah PIC'" max-width="max-w-md">
      <form class="space-y-4" @submit.prevent="handlePicSubmit">
        <div v-if="customer?.employees?.length">
          <label class="form-label">Sumber Data PIC</label>
          <div class="flex gap-4 text-sm">
            <label class="flex cursor-pointer items-center gap-2">
              <input v-model="picSource" type="radio" value="employee" class="size-4 border-slate-300 text-brand-600" @change="onPicSourceChange" />
              Dari pegawai
            </label>
            <label class="flex cursor-pointer items-center gap-2">
              <input v-model="picSource" type="radio" value="manual" class="size-4 border-slate-300 text-brand-600" @change="onPicSourceChange" />
              Input manual
            </label>
          </div>
        </div>

        <div v-if="picSource === 'employee' && customer?.employees?.length">
          <label class="form-label">Pegawai</label>
          <select v-model="picForm.employee_id" class="input-field" required>
            <option value="">— Pilih pegawai —</option>
            <option v-for="emp in customer.employees" :key="emp.id" :value="emp.id">
              {{ emp.full_name }}
              <template v-if="emp.email"> — {{ emp.email }}</template>
            </option>
          </select>
        </div>

        <div>
          <label class="form-label">Nama PIC</label>
          <input
            v-model="picForm.name"
            type="text"
            required
            maxlength="255"
            class="input-field"
            placeholder="Nama lengkap"
            :readonly="picSource === 'employee' && !!picForm.employee_id"
          />
        </div>
        <div>
          <label class="form-label">Telepon</label>
          <input v-model="picForm.phone" type="text" maxlength="30" class="input-field" placeholder="08xxxxxxxxxx" />
        </div>
        <div>
          <label class="form-label">Email</label>
          <input v-model="picForm.email" type="email" maxlength="150" class="input-field" placeholder="pic@perusahaan.com" />
        </div>
        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
          <input v-model="picForm.is_primary" type="checkbox" class="size-4 rounded border-slate-300 text-brand-600" />
          Jadikan PIC utama
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="closePicModal">Batal</button>
          <button type="submit" class="btn-primary" :disabled="savingPic">
            {{ savingPic ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </AppModal>

    <PricingRuleFormModal
      v-model="showPricingModal"
      :reference="pricingReference"
      :rule="editingPricingRule"
      :existing-rules="existingPricingRules"
      :saving="savingPricing"
      @submit="handlePricingSubmit"
    />

    <EmployeeFormModal
      v-model="showEmployeeModal"
      :customers="customerOptions"
      :titles="titles"
      :nationalities="nationalities"
      :employee="editingEmployee"
      :default-customer-id="customer?.id"
      lock-customer
      :saving="savingEmployee"
      @submit="handleEmployeeSubmit"
    />
  </div>
</template>
