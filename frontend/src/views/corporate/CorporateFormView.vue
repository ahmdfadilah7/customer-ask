<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft,
  Building2,
  Plus,
  Receipt,
  Save,
  Search,
  Trash2,
  Users,
} from '@lucide/vue'
import { createCustomer, fetchCustomer, updateCustomer } from '@/api/customers'
import {
  createPricingRule,
  deletePricingRule,
  fetchPricingReference,
  updatePricingRule,
} from '@/api/customerPricing'
import { createEmployee, updateEmployee } from '@/api/employees'
import { fetchBranches } from '@/api/branches'
import { fetchNationalities } from '@/api/nationalities'
import { fetchTitles } from '@/api/titles'
import { useAuthStore } from '@/stores/auth'
import { useBranchScope } from '@/composables/useBranchScope'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PageInfoBanner from '@/components/ui/PageInfoBanner.vue'
import RupiahInput from '@/components/ui/RupiahInput.vue'
import { TERMS } from '@/constants/terminology'
import {
  CORPORATE_IMPORT_GROUPS,
  CORPORATE_INVOICE_METHODS,
  EMPLOYEE_IMPORT_HINT,
  YES_NO_OPTIONS,
  formatCustomerNameForImport,
  parseYesNo,
  yesNoFromBool,
} from '@/constants/importFormats'
import { formatRupiahValue, normalizeRupiahForSave } from '@/utils/currencyInput'
import { getApiErrorMessage } from '@/utils/apiError'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const toast = useToast()
const {
  isBranchScoped,
  resolveBranchOptions,
  initBranchValue,
} = useBranchScope()

const loading = ref(true)
const saving = ref(false)
const activeSection = ref('profile')
const pricingSearch = ref('')
const showFilledPricingOnly = ref(false)
const expandedPricingGroups = ref(new Set())

const branches = ref([])
const pricingReference = ref(null)
const titles = ref([])
const nationalities = ref([])
const customer = ref(null)

const isEditing = computed(() => route.name === 'corporate-edit')
const customerId = computed(() => (isEditing.value ? route.params.id : null))

const canCreate = computed(() => auth.hasPermission('corporate-create'))
const canUpdate = computed(() => auth.hasPermission('corporate-update'))
const canSave = computed(() => (isEditing.value ? canUpdate.value : canCreate.value))
const canManageEmployees = computed(() => auth.canManage('pegawai'))

const pageTitle = computed(() =>
  isEditing.value ? `Edit ${TERMS.corporate.label}` : `Tambah ${TERMS.corporate.label}`,
)

const branchOptions = computed(() => resolveBranchOptions(branches.value))
const lockBranch = computed(() => isBranchScoped.value && branchOptions.value.length === 1)

const pricingSlots = computed(() => pricingReference.value?.pricing_slots ?? [])

const pricingSlotGroups = computed(() => {
  const term = pricingSearch.value.trim().toLowerCase()
  const groups = new Map()

  for (const slot of pricingSlots.value) {
    const value = (pricingValues.value[slot.key] ?? '').trim()
    if (showFilledPricingOnly.value && !value) continue

    const haystack = `${slot.group} ${slot.label} ${slot.key}`.toLowerCase()
    if (term && !haystack.includes(term)) continue

    if (!groups.has(slot.group)) {
      groups.set(slot.group, { label: slot.group, slots: [] })
    }
    groups.get(slot.group).slots.push(slot)
  }

  return [...groups.values()].filter((group) => group.slots.length > 0)
})

const filledPricingCount = computed(() =>
  pricingSlots.value.filter((slot) => (pricingValues.value[slot.key] ?? '').trim()).length,
)

const filledEmployeeCount = computed(() =>
  employeeRows.value.filter((row) => row.full_name.trim()).length,
)

const sections = computed(() => [
  { id: 'profile', label: 'Profil Corporate', icon: Building2, badge: null },
  {
    id: 'service-fee',
    label: 'Service Fee',
    icon: Receipt,
    badge: filledPricingCount.value || null,
  },
  {
    id: 'employees',
    label: TERMS.employee.label,
    icon: Users,
    badge: filledEmployeeCount.value || null,
    hidden: !canManageEmployees.value,
  },
].filter((section) => !section.hidden))

const emptyProfile = () => ({
  branch_id: '',
  name: '',
  corp_mode: 'no',
  faktur_pajak: '',
  show_service_fee: '',
  invoice_method: '',
  cn_percentage: '',
  materai: '',
  contract_period: '',
  general_note: '',
})

const profile = ref(emptyProfile())
const pricingValues = ref({})
const pricingRuleIds = ref({})

let employeeKey = 0

function createEmployeeRow(data = {}) {
  return {
    _key: ++employeeKey,
    _id: data.id ?? null,
    title_id: data.title_id ?? data.title?.id ?? '',
    nationality_id: data.nationality_id ?? data.nationality?.id ?? '',
    full_name: data.full_name ?? '',
    passport_number: data.passport_number ?? '',
    passport_expiry: data.passport_expiry ?? '',
    ktp_number: data.ktp_number ?? '',
    birthdate: data.birthdate ?? '',
    mobile: data.mobile ?? '',
    email: data.email ?? '',
    ticket_name_format: data.ticket_name_format ?? '',
    status: data.status ?? 'active',
  }
}

const employeeRows = ref([])

onMounted(async () => {
  if (isEditing.value && !canUpdate.value) {
    toast.error('Anda tidak memiliki akses edit corporate.')
    router.replace({ name: 'corporate-list' })
    return
  }
  if (!isEditing.value && !canCreate.value) {
    toast.error('Anda tidak memiliki akses tambah corporate.')
    router.replace({ name: 'corporate-list' })
    return
  }

  await loadPage()
})

async function loadPage() {
  loading.value = true
  try {
    const requests = [
      fetchPricingReference(),
      fetchTitles(),
      fetchNationalities(),
    ]

    if (!isBranchScoped.value) {
      requests.push(fetchBranches())
    }

    if (isEditing.value) {
      requests.push(fetchCustomer(customerId.value))
    }

    const results = await Promise.all(requests)
    let index = 0

    pricingReference.value = results[index++].data
    titles.value = results[index++].data.data ?? []
    nationalities.value = results[index++].data.data ?? []

    if (!isBranchScoped.value) {
      branches.value = results[index++].data.data ?? []
    }

    if (isEditing.value) {
      customer.value = results[index++].data.data
      hydrateFromCustomer(customer.value)
    } else {
      const branchFilter = ref('')
      initBranchValue(branchFilter, branchOptions.value)
      profile.value.branch_id = branchFilter.value
      employeeRows.value = [createEmployeeRow()]
      initPricingGroups()
    }
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat form corporate.'))
    router.push({ name: 'corporate-list' })
  } finally {
    loading.value = false
  }
}

function hydrateFromCustomer(data) {
  profile.value = {
    branch_id: data.branch_id ?? data.branch?.id ?? '',
    name: formatCustomerNameForImport(data),
    corp_mode: yesNoFromBool(data.corp_mode) || 'no',
    faktur_pajak: yesNoFromBool(data.faktur_pajak),
    show_service_fee: yesNoFromBool(data.show_service_fee),
    invoice_method: data.invoice_method ?? '',
    cn_percentage: data.cn_percentage ?? '',
    materai: data.pricing?.materai ? formatRupiahValue(data.pricing.materai) : '',
    contract_period: data.contract_period ?? '',
    general_note: data.general_note ?? '',
  }

  pricingValues.value = {}
  pricingRuleIds.value = {}

  const rules = [
    ...(data.pricing?.airlines ?? []),
    ...(data.pricing?.services ?? []),
  ]

  for (const rule of rules) {
    const slot = findSlotForRule(rule)
    if (!slot) continue
    pricingValues.value[slot.key] = rule.raw_value ?? ''
    pricingRuleIds.value[slot.key] = rule.id
  }

  const employees = (data.employees ?? []).map((employee) => createEmployeeRow(employee))
  employeeRows.value = employees.length ? employees : [createEmployeeRow()]
  initPricingGroups()
}

function initPricingGroups() {
  expandedPricingGroups.value = new Set(pricingSlots.value.map((slot) => slot.group))
}

function findSlotForRule(rule) {
  return pricingSlots.value.find(
    (slot) =>
      String(slot.service_category_id) === String(rule.service_category_id)
      && String(slot.region_scope_id) === String(rule.region_scope_id ?? '')
      && String(slot.airline_id ?? '') === String(rule.airline_id ?? ''),
  )
}

function togglePricingGroup(label) {
  const next = new Set(expandedPricingGroups.value)
  if (next.has(label)) next.delete(label)
  else next.add(label)
  expandedPricingGroups.value = next
}

function isPricingGroupExpanded(label) {
  return expandedPricingGroups.value.has(label)
}

function addEmployeeRow() {
  employeeRows.value.push(createEmployeeRow())
}

function removeEmployeeRow(index) {
  if (employeeRows.value.length === 1) {
    employeeRows.value[0] = createEmployeeRow()
    return
  }
  employeeRows.value.splice(index, 1)
}

function emptyToNull(value) {
  const trimmed = String(value ?? '').trim()
  if (trimmed === '' || trimmed === '-') return null
  return trimmed
}

function buildProfilePayload() {
  const payload = {
    name: profile.value.name.trim(),
    corp_mode: parseYesNo(profile.value.corp_mode) ?? false,
    faktur_pajak: parseYesNo(profile.value.faktur_pajak),
    show_service_fee: parseYesNo(profile.value.show_service_fee),
    invoice_method: profile.value.invoice_method || null,
    cn_percentage: profile.value.cn_percentage === '' ? null : Number(profile.value.cn_percentage),
    contract_period: profile.value.contract_period.trim() || null,
    general_note: profile.value.general_note.trim() || null,
    materai: normalizeRupiahForSave(profile.value.materai),
  }

  if (!isEditing.value) {
    payload.branch_id = profile.value.branch_id
  }

  return payload
}

function buildEmployeePayload(row, targetCustomerId) {
  return {
    customer_id: targetCustomerId,
    title_id: row.title_id || null,
    nationality_id: row.nationality_id || null,
    full_name: row.full_name.trim(),
    passport_number: emptyToNull(row.passport_number),
    passport_expiry: emptyToNull(row.passport_expiry),
    ktp_number: emptyToNull(row.ktp_number),
    birthdate: emptyToNull(row.birthdate),
    mobile: emptyToNull(row.mobile),
    email: emptyToNull(row.email),
    ticket_name_format: emptyToNull(row.ticket_name_format),
    status: row.status,
  }
}

async function savePricingRules(targetCustomerId) {
  const tasks = []

  for (const slot of pricingSlots.value) {
    const value = (pricingValues.value[slot.key] ?? '').trim()
    const existingId = pricingRuleIds.value[slot.key]

    if (!value) {
      if (existingId) {
        tasks.push(deletePricingRule(targetCustomerId, existingId))
      }
      continue
    }

    const payload = {
      service_category_id: slot.service_category_id,
      region_scope_id: slot.region_scope_id,
      airline_id: slot.airline_id,
      raw_value: value,
    }

    if (existingId) {
      tasks.push(updatePricingRule(targetCustomerId, existingId, payload))
    } else {
      tasks.push(createPricingRule(targetCustomerId, payload))
    }
  }

  if (tasks.length) {
    await Promise.all(tasks)
  }
}

async function saveEmployees(targetCustomerId) {
  const rows = employeeRows.value.filter((row) => row.full_name.trim())
  if (!rows.length) return

  await Promise.all(
    rows.map((row) => {
      const payload = buildEmployeePayload(row, targetCustomerId)
      return row._id ? updateEmployee(row._id, payload) : createEmployee(payload)
    }),
  )
}

async function handleSubmit() {
  if (!canSave.value) return
  if (!profile.value.name.trim()) {
    toast.error('Nama corporate wajib diisi.')
    activeSection.value = 'profile'
    return
  }
  if (!isEditing.value && !profile.value.branch_id) {
    toast.error('Cabang wajib dipilih.')
    activeSection.value = 'profile'
    return
  }

  saving.value = true
  try {
    const payload = buildProfilePayload()
    let targetId = customerId.value

    if (isEditing.value) {
      await updateCustomer(targetId, payload)
    } else {
      const { data } = await createCustomer(payload)
      targetId = data.data?.id ?? data.id
    }

    await savePricingRules(targetId)

    if (canManageEmployees.value) {
      await saveEmployees(targetId)
    }

    toast.success(isEditing.value ? 'Corporate berhasil diperbarui.' : 'Corporate berhasil ditambahkan.')
    router.push({ name: 'corporate-detail', params: { id: targetId } })
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menyimpan corporate.'))
  } finally {
    saving.value = false
  }
}

function goBack() {
  if (isEditing.value && customerId.value) {
    router.push({ name: 'corporate-detail', params: { id: customerId.value } })
  } else {
    router.push({ name: 'corporate-list' })
  }
}
</script>

<template>
  <div class="page-shell">
    <PageHeader :icon="Building2" :title="pageTitle" description="Lengkapi profil corporate, service fee, dan pegawai dalam satu formulir.">
      <template #actions>
        <button type="button" class="btn-secondary !py-2" @click="goBack">
          <ArrowLeft class="size-4" />
          Kembali
        </button>
        <button
          v-if="canSave"
          type="button"
          class="btn-primary !py-2"
          :disabled="saving || loading"
          @click="handleSubmit"
        >
          <Save class="size-4" />
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </template>
    </PageHeader>

    <PageInfoBanner>
      Form mengikuti template import. Field kosong pada service fee dan pegawai tidak akan disimpan.
      Nama corporate dapat berisi alias dengan pemisah <span class="font-medium">/</span>.
    </PageInfoBanner>

    <LoadingSpinner v-if="loading" label="Memuat formulir..." />

    <div v-else class="glass-panel overflow-hidden p-0">
      <div class="border-b border-slate-100 bg-slate-50/50 px-4 py-3 sm:px-6">
        <div class="segment-control w-full overflow-x-auto sm:w-auto">
          <button
            v-for="section in sections"
            :key="section.id"
            type="button"
            class="segment-control__btn flex items-center gap-1.5 whitespace-nowrap"
            :class="activeSection === section.id ? 'segment-control__btn--active' : 'segment-control__btn--inactive'"
            @click="activeSection = section.id"
          >
            <component :is="section.icon" class="size-4 shrink-0" />
            {{ section.label }}
            <span
              v-if="section.badge"
              class="rounded-full bg-brand-100 px-1.5 py-0.5 text-[10px] font-semibold text-brand-700"
            >
              {{ section.badge }}
            </span>
          </button>
        </div>
      </div>

      <form class="space-y-6 p-5 sm:p-6" @submit.prevent="handleSubmit">
        <!-- Profil Corporate -->
        <div v-show="activeSection === 'profile'" class="space-y-6">
          <fieldset class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <legend class="px-1 text-sm font-semibold text-slate-800">
              {{ CORPORATE_IMPORT_GROUPS.find((g) => g.key === 'identity')?.label }}
            </legend>
            <div class="grid gap-4 sm:grid-cols-2">
              <div v-if="!isEditing" class="sm:col-span-2">
                <label class="form-label">Cabang *</label>
                <select
                  v-model="profile.branch_id"
                  class="input-field"
                  required
                  :disabled="lockBranch || !canSave"
                >
                  <option value="">— Pilih cabang —</option>
                  <option v-for="b in branchOptions" :key="b.id" :value="b.id">
                    {{ b.name }} ({{ b.code }})
                  </option>
                </select>
              </div>
              <div v-else class="sm:col-span-2">
                <label class="form-label">Cabang</label>
                <input
                  type="text"
                  class="input-field bg-slate-50"
                  disabled
                  :value="customer?.branch ? `${customer.branch.name} (${customer.branch.code})` : '—'"
                />
              </div>
              <div class="sm:col-span-2">
                <label class="form-label">Nama Corporate *</label>
                <input
                  v-model="profile.name"
                  type="text"
                  required
                  maxlength="255"
                  class="input-field"
                  placeholder="PT Contoh / Contoh Corp"
                  :disabled="!canSave"
                />
              </div>
            </div>
          </fieldset>

          <fieldset class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <legend class="px-1 text-sm font-semibold text-slate-800">
              {{ CORPORATE_IMPORT_GROUPS.find((g) => g.key === 'profile')?.label }}
            </legend>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <label class="form-label">Corp Mode</label>
                <select v-model="profile.corp_mode" class="input-field" :disabled="!canSave">
                  <option value="yes">Ya</option>
                  <option value="no">Tidak</option>
                </select>
              </div>
              <div>
                <label class="form-label">Faktur Pajak</label>
                <select v-model="profile.faktur_pajak" class="input-field" :disabled="!canSave">
                  <option v-for="opt in YES_NO_OPTIONS" :key="opt.value || 'empty'" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="form-label">Service Fee</label>
                <select v-model="profile.show_service_fee" class="input-field" :disabled="!canSave">
                  <option v-for="opt in YES_NO_OPTIONS" :key="`sf-${opt.value || 'empty'}`" :value="opt.value">
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="form-label">Invoice</label>
                <select v-model="profile.invoice_method" class="input-field" :disabled="!canSave">
                  <option
                    v-for="opt in CORPORATE_INVOICE_METHODS"
                    :key="opt.value || 'empty'"
                    :value="opt.value"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="form-label">CN %</label>
                <input
                  v-model="profile.cn_percentage"
                  type="number"
                  min="0"
                  max="100"
                  step="0.01"
                  class="input-field"
                  :disabled="!canSave"
                />
              </div>
            </div>
          </fieldset>

          <div class="grid gap-4 lg:grid-cols-2">
            <fieldset class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
              <legend class="px-1 text-sm font-semibold text-slate-800">
                {{ CORPORATE_IMPORT_GROUPS.find((g) => g.key === 'materai')?.label }}
              </legend>
              <div>
                <label class="form-label">Materai</label>
                <RupiahInput v-model="profile.materai" placeholder="10.000" :disabled="!canSave" />
                <p class="form-hint">Otomatis diformat ke notasi Rupiah (contoh: 10.000).</p>
              </div>
            </fieldset>

            <fieldset class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
              <legend class="px-1 text-sm font-semibold text-slate-800">
                {{ CORPORATE_IMPORT_GROUPS.find((g) => g.key === 'contract')?.label }}
              </legend>
              <div>
                <label class="form-label">Periode Kontrak</label>
                <textarea
                  v-model="profile.contract_period"
                  rows="3"
                  maxlength="500"
                  class="input-field resize-y"
                  placeholder="Contoh: 1 Jan 2025 s/d 31 Des 2025"
                  :disabled="!canSave"
                />
              </div>
            </fieldset>
          </div>

          <fieldset class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5">
            <legend class="px-1 text-sm font-semibold text-slate-800">
              {{ CORPORATE_IMPORT_GROUPS.find((g) => g.key === 'note')?.label }}
            </legend>
            <div>
              <label class="form-label">Catatan</label>
              <textarea
                v-model="profile.general_note"
                rows="4"
                class="input-field resize-y"
                :disabled="!canSave"
              />
            </div>
          </fieldset>
        </div>

        <!-- Service Fee -->
        <div v-show="activeSection === 'service-fee'" class="space-y-4">
          <div class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="relative flex-1 sm:max-w-md">
              <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
              <input
                v-model="pricingSearch"
                type="search"
                class="input-field pl-9"
                placeholder="Cari maskapai atau layanan..."
              />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
              <input v-model="showFilledPricingOnly" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
              Tampilkan yang terisi saja
            </label>
          </div>

          <div v-if="!pricingSlotGroups.length" class="rounded-xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-500">
            Tidak ada kolom service fee yang cocok dengan filter.
          </div>

          <div v-for="group in pricingSlotGroups" :key="group.label" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <button
              type="button"
              class="flex w-full items-center justify-between bg-slate-50 px-4 py-3 text-left text-sm font-semibold text-slate-800 hover:bg-slate-100"
              @click="togglePricingGroup(group.label)"
            >
              <span>{{ group.label }}</span>
              <span class="text-xs font-normal text-slate-500">
                {{ group.slots.filter((s) => (pricingValues[s.key] ?? '').trim()).length }} / {{ group.slots.length }} terisi
              </span>
            </button>

            <div v-show="isPricingGroupExpanded(group.label)" class="divide-y divide-slate-100">
              <div
                v-for="slot in group.slots"
                :key="slot.key"
                class="grid gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,220px)] sm:items-center"
              >
                <div>
                  <p class="text-sm font-medium text-slate-800">{{ slot.label }}</p>
                  <p class="text-xs text-slate-500">{{ slot.key }}</p>
                </div>
                <input
                  v-model="pricingValues[slot.key]"
                  type="text"
                  maxlength="255"
                  class="input-field"
                  placeholder="50.000 atau 5%"
                  :disabled="!canSave"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Pegawai -->
        <div v-show="activeSection === 'employees' && canManageEmployees" class="space-y-4">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-slate-600">{{ EMPLOYEE_IMPORT_HINT }}</p>
            <button type="button" class="btn-secondary !py-2" @click="addEmployeeRow">
              <Plus class="size-4" />
              Tambah Pegawai
            </button>
          </div>

          <div
            v-for="(row, index) in employeeRows"
            :key="row._key"
            class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 sm:p-5"
          >
            <div class="flex items-center justify-between gap-3">
              <h3 class="text-sm font-semibold text-slate-800">
                Pegawai {{ index + 1 }}
                <span v-if="row._id" class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-normal text-slate-500">Existing</span>
              </h3>
              <button type="button" class="btn-icon-danger" title="Hapus baris" @click="removeEmployeeRow(index)">
                <Trash2 class="size-4" />
              </button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              <div>
                <label class="form-label">Title</label>
                <select v-model="row.title_id" class="input-field" :disabled="!canSave">
                  <option value="">—</option>
                  <option v-for="t in titles" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
              </div>
              <div class="sm:col-span-2">
                <label class="form-label">Name *</label>
                <input v-model="row.full_name" type="text" maxlength="255" class="input-field" :disabled="!canSave" />
              </div>
              <div>
                <label class="form-label">Nationality</label>
                <select v-model="row.nationality_id" class="input-field" :disabled="!canSave">
                  <option value="">—</option>
                  <option v-for="n in nationalities" :key="n.id" :value="n.id">{{ n.name }}</option>
                </select>
              </div>
              <div>
                <label class="form-label">Passport No.</label>
                <input v-model="row.passport_number" type="text" maxlength="50" class="input-field" :disabled="!canSave" />
              </div>
              <div>
                <label class="form-label">Passport Exp Date</label>
                <input v-model="row.passport_expiry" type="date" class="input-field" :disabled="!canSave" />
              </div>
              <div>
                <label class="form-label">KTP No.</label>
                <input v-model="row.ktp_number" type="text" maxlength="20" class="input-field" :disabled="!canSave" />
              </div>
              <div>
                <label class="form-label">Birthdate</label>
                <input v-model="row.birthdate" type="date" class="input-field" :disabled="!canSave" />
              </div>
              <div>
                <label class="form-label">Mobile No.</label>
                <input v-model="row.mobile" type="text" maxlength="30" class="input-field" :disabled="!canSave" />
              </div>
              <div>
                <label class="form-label">Email</label>
                <input v-model="row.email" type="email" maxlength="150" class="input-field" :disabled="!canSave" />
              </div>
              <div class="sm:col-span-2 lg:col-span-3">
                <label class="form-label">Reservation/Ticket Name</label>
                <input
                  v-model="row.ticket_name_format"
                  type="text"
                  maxlength="255"
                  class="input-field"
                  placeholder="SURNAME/GIVEN NAME"
                  :disabled="!canSave"
                />
              </div>
            </div>
          </div>
        </div>
      </form>

      <div
        v-if="canSave"
        class="sticky bottom-0 flex justify-end gap-2 border-t border-slate-100 bg-white/95 px-5 py-4 backdrop-blur sm:px-6"
      >
        <button type="button" class="btn-secondary" @click="goBack">Batal</button>
        <button type="button" class="btn-primary" :disabled="saving" @click="handleSubmit">
          {{ saving ? 'Menyimpan...' : 'Simpan Corporate' }}
        </button>
      </div>
    </div>
  </div>
</template>
