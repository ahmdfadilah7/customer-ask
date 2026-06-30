<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Eye, MessageCircle, Pencil, Plus, Trash2, UserRound } from '@lucide/vue'
import { fetchCustomers } from '@/api/customers'
import {
  createEmployee,
  deleteEmployee,
  fetchEmployee,
  fetchEmployees,
  updateEmployee,
} from '@/api/employees'
import { fetchBranches } from '@/api/branches'
import { fetchNationalities } from '@/api/nationalities'
import { fetchTitles } from '@/api/titles'
import { fetchMessageTemplates } from '@/api/messageTemplates'
import { fetchWhatsAppStatus, previewWhatsAppMessage, sendWhatsAppMessage } from '@/api/whatsapp'
import { useAuthStore } from '@/stores/auth'
import { useBranchScope } from '@/composables/useBranchScope'
import { useReloadGuard } from '@/composables/useReloadGuard'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PageInfoBanner from '@/components/ui/PageInfoBanner.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppDataTable from '@/components/ui/AppDataTable.vue'
import AppTableColumn from '@/components/ui/AppTableColumn.vue'
import EmployeeFormModal from '@/components/employees/EmployeeFormModal.vue'
import { getApiErrorMessage } from '@/utils/apiError'
import { TERMS } from '@/constants/terminology'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const {
  isBranchScoped,
  resolveBranchOptions,
  shouldShowBranchSelector,
  findBranchLabel,
  initBranchValue,
} = useBranchScope()
const { nextToken, isStale } = useReloadGuard()
const confirm = useConfirm()
const toast = useToast()

const loading = ref(true)
const tableLoading = ref(false)
const employees = ref([])
const customers = ref([])
const branches = ref([])
const branchFilter = ref('')
const customerFilter = ref('')
const selectedEmployees = ref([])
const templates = ref([])
const whatsappConfigured = ref(false)
const whatsappDataLoaded = ref(false)
const customersLoaded = ref(false)
const loadingCustomerOptions = ref(false)
const showWhatsAppModal = ref(false)
const modalEmployees = ref([])
const sendingWhatsApp = ref(false)
const loadingPreview = ref(false)
const whatsappForm = ref({
  template_id: '',
  message: '',
  delay: '1-3',
})
const previewMessage = ref('')
const titles = ref([])
const nationalities = ref([])
const showEmployeeForm = ref(false)
const savingEmployee = ref(false)
const editingEmployee = ref(null)
const initializing = ref(false)

const canManage = computed(() => auth.canManage('pegawai'))
const canImportPegawai = computed(() => auth.canImport('pegawai'))
const canSendWhatsApp = computed(() => auth.hasPermission('whatsapp-kirim'))

const modalEmployeesWithMobile = computed(() =>
  modalEmployees.value.filter((row) => row.mobile?.trim()),
)

const isSingleWhatsAppTarget = computed(() => modalEmployees.value.length === 1)

const activeTemplates = computed(() => templates.value.filter((t) => t.is_active))

const branchOptions = computed(() => resolveBranchOptions(branches.value))
const showBranchFilter = computed(() => shouldShowBranchSelector(branchOptions.value))
const lockedBranchLabel = computed(() => findBranchLabel(branchOptions.value, branchFilter.value))

const filteredCustomers = computed(() => {
  if (!branchFilter.value) return customers.value
  return customers.value.filter((c) => String(c.branch_id) === String(branchFilter.value))
})

const emptyTableText = computed(() =>
  canImportPegawai.value
    ? 'Belum ada data pegawai. Tambah manual atau import lewat menu Import → Data Pegawai.'
    : 'Belum ada data pegawai.',
)

onMounted(loadData)

watch(
  () => route.query.edit,
  async (editId) => {
    if (!editId || !canManage.value) return
    await ensureFormOptions()
    await openEditEmployee(Number(editId))
    const query = { ...route.query }
    delete query.edit
    router.replace({ name: 'customer-list', query })
  },
)

watch(branchFilter, (newVal, oldVal) => {
  if (initializing.value) return
  if (newVal !== oldVal) {
    customerFilter.value = ''
    customersLoaded.value = false
    customers.value = []
  }
  refreshEmployees()
})

watch(customerFilter, () => {
  if (initializing.value) return
  refreshEmployees()
})

async function loadData() {
  loading.value = true
  initializing.value = true
  try {
    if (!isBranchScoped.value) {
      const { data } = await fetchBranches()
      branches.value = data.data ?? []
    }

    initBranchValue(branchFilter, branchOptions.value)

    if (route.query.customer_id) {
      customerFilter.value = String(route.query.customer_id)
      await ensureCustomerOptions()
    }

    await reloadEmployees()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat data pegawai.'))
  } finally {
    initializing.value = false
    loading.value = false
  }
}

async function refreshEmployees() {
  tableLoading.value = true
  try {
    await reloadEmployees()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat data pegawai.'))
  } finally {
    tableLoading.value = false
  }
}

async function reloadEmployees() {
  const token = nextToken()
  const params = { per_page: 200 }
  if (customerFilter.value) {
    params['filter[customer_id]'] = customerFilter.value
  } else if (branchFilter.value) {
    params['filter[customer.branch_id]'] = branchFilter.value
  }

  const { data } = await fetchEmployees(params)
  if (isStale(token)) return
  employees.value = data.data ?? []
}

async function ensureCustomerOptions() {
  if (customersLoaded.value || loadingCustomerOptions.value) return

  loadingCustomerOptions.value = true
  try {
    const params = { per_page: 200 }
    if (branchFilter.value) {
      params['filter[branch_id]'] = branchFilter.value
    }
    const { data } = await fetchCustomers(params)
    customers.value = data.data ?? []
    customersLoaded.value = true
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat daftar corporate.'))
  } finally {
    loadingCustomerOptions.value = false
  }
}

async function ensureFormOptions() {
  await Promise.all([
    ensureCustomerOptions(),
    loadTitlesIfNeeded(),
    loadNationalitiesIfNeeded(),
  ])
}

async function loadTitlesIfNeeded() {
  if (titles.value.length) return
  try {
    const { data } = await fetchTitles()
    titles.value = data.data ?? []
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat daftar gelar.'))
  }
}

async function loadNationalitiesIfNeeded() {
  if (nationalities.value.length) return
  try {
    const { data } = await fetchNationalities()
    nationalities.value = data.data ?? []
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat daftar kebangsaan.'))
  }
}

async function ensureWhatsAppData() {
  if (whatsappDataLoaded.value) return

  const requests = []
  if (auth.hasPermission('template-pesan-view')) {
    requests.push(fetchMessageTemplates({ active_only: true }))
  }
  if (canSendWhatsApp.value) {
    requests.push(fetchWhatsAppStatus())
  }

  if (!requests.length) {
    whatsappDataLoaded.value = true
    return
  }

  try {
    const results = await Promise.all(requests)
    let index = 0
    if (auth.hasPermission('template-pesan-view')) {
      templates.value = results[index]?.data.data ?? []
      index += 1
    }
    if (canSendWhatsApp.value) {
      whatsappConfigured.value = results[index]?.data.configured ?? false
    }
    whatsappDataLoaded.value = true
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat data WhatsApp.'))
  }
}

async function onCustomerFilterFocus() {
  await ensureCustomerOptions()
}

async function onCustomerChange() {
  await refreshEmployees()
}

function openCorporateDetail(customerId) {
  if (!customerId) return
  router.push({ name: 'corporate-detail', params: { id: customerId } })
}

function openEmployeeDetail(row) {
  router.push({ name: 'employee-detail', params: { id: row.id } })
}

async function openAddEmployee() {
  await ensureFormOptions()
  editingEmployee.value = null
  showEmployeeForm.value = true
}

async function openEditEmployee(idOrRow) {
  const id = typeof idOrRow === 'object' ? idOrRow.id : idOrRow
  try {
    await ensureFormOptions()
    const { data } = await fetchEmployee(id)
    editingEmployee.value = data.data
    showEmployeeForm.value = true
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
    showEmployeeForm.value = false
    editingEmployee.value = null
    await reloadEmployees()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menyimpan pegawai.'))
  } finally {
    savingEmployee.value = false
  }
}

async function handleDeleteEmployee(row) {
  const confirmed = await confirm.confirm({
    title: 'Hapus Pegawai',
    message: `Hapus pegawai "${row.full_name}"? Tindakan ini tidak dapat dibatalkan.`,
    confirmLabel: 'Ya, Hapus',
    variant: 'danger',
  })

  if (!confirmed) return

  try {
    await deleteEmployee(row.id)
    toast.success('Pegawai berhasil dihapus.')
    await reloadEmployees()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus pegawai.'))
  }
}

function onEmployeeSelectionChange(rows) {
  selectedEmployees.value = rows
}

async function openWhatsAppModalForEmployee(row) {
  if (!row.mobile?.trim()) {
    toast.warning('Pegawai ini tidak memiliki nomor mobile.')
    return
  }

  try {
    await ensureWhatsAppData()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat data WhatsApp.'))
    return
  }

  if (!whatsappConfigured.value) {
    toast.error('Token Fonnte belum dikonfigurasi di backend (.env FONNTE_TOKEN).')
    return
  }

  modalEmployees.value = [row]
  whatsappForm.value = {
    template_id: activeTemplates.value[0]?.id ?? '',
    message: '',
    delay: '1-3',
  }
  previewMessage.value = ''
  showWhatsAppModal.value = true
  loadPreview()
}

async function openWhatsAppModal() {
  if (!selectedEmployees.value.length) {
    toast.warning('Pilih minimal satu pegawai.')
    return
  }

  try {
    await ensureWhatsAppData()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat data WhatsApp.'))
    return
  }

  if (!whatsappConfigured.value) {
    toast.error('Token Fonnte belum dikonfigurasi di backend (.env FONNTE_TOKEN).')
    return
  }

  modalEmployees.value = [...selectedEmployees.value]
  whatsappForm.value = {
    template_id: activeTemplates.value[0]?.id ?? '',
    message: '',
    delay: '1-3',
  }
  previewMessage.value = ''
  showWhatsAppModal.value = true
  loadPreview()
}

watch(
  () => [whatsappForm.value.template_id, whatsappForm.value.message],
  () => {
    if (showWhatsAppModal.value) {
      loadPreview()
    }
  },
)

async function loadPreview() {
  const sample = modalEmployees.value.find((row) => row.mobile?.trim()) ?? modalEmployees.value[0]
  if (!sample) return

  if (!whatsappForm.value.template_id && !whatsappForm.value.message.trim()) {
    previewMessage.value = ''
    return
  }

  loadingPreview.value = true
  try {
    const payload = {
      employee_id: sample.id,
      template_id: whatsappForm.value.template_id || undefined,
      message: whatsappForm.value.message.trim() || undefined,
    }
    const { data } = await previewWhatsAppMessage(payload)
    previewMessage.value = data.data.message
  } catch {
    previewMessage.value = ''
  } finally {
    loadingPreview.value = false
  }
}

async function handleSendWhatsApp() {
  const targetIds = modalEmployees.value.map((row) => row.id)
  if (!targetIds.length) return

  if (!whatsappForm.value.template_id && !whatsappForm.value.message.trim()) {
    toast.warning('Pilih template atau isi pesan manual.')
    return
  }

  const confirmMessage = isSingleWhatsAppTarget.value
    ? `Kirim pesan WhatsApp ke ${modalEmployees.value[0].full_name} (${modalEmployees.value[0].mobile?.trim()})?`
    : `Kirim pesan ke ${modalEmployeesWithMobile.value.length} pegawai dengan nomor mobile valid? (${targetIds.length - modalEmployeesWithMobile.value.length} tanpa mobile akan dilewati)`

  const confirmed = await confirm.confirm({
    title: 'Kirim WhatsApp',
    message: confirmMessage,
    confirmLabel: 'Ya, Kirim',
    variant: 'warning',
  })

  if (!confirmed) return

  sendingWhatsApp.value = true
  try {
    const payload = {
      employee_ids: targetIds,
      template_id: whatsappForm.value.template_id || undefined,
      message: whatsappForm.value.message.trim() || undefined,
      delay: isSingleWhatsAppTarget.value ? undefined : (whatsappForm.value.delay || undefined),
    }
    const { data } = await sendWhatsAppMessage(payload)
    toast.success(data.message)

    if (data.skipped?.length) {
      toast.warning(`${data.skipped.length} pegawai dilewati (mobile kosong/tidak valid).`)
    }

    showWhatsAppModal.value = false
    modalEmployees.value = []
    selectedEmployees.value = []
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal mengirim pesan WhatsApp.'))
  } finally {
    sendingWhatsApp.value = false
  }
}
</script>

<template>
  <div class="page-shell">
    <PageHeader
      :icon="UserRound"
      :title="TERMS.employee.label"
      :description="TERMS.employee.listDescription"
    >
      <template #actions>
        <button
          v-if="canManage"
          type="button"
          class="btn-primary !py-2 text-sm"
          @click="openAddEmployee"
        >
          <Plus class="size-4" />
          Tambah Pegawai
        </button>
        <router-link v-if="canImportPegawai" to="/import/employee" class="btn-secondary !py-2 text-sm">
          Import Pegawai
        </router-link>
        <div v-if="showBranchFilter || isBranchScoped" class="min-w-[160px]">
          <label class="filter-label">Filter Cabang</label>
          <select
            v-if="showBranchFilter"
            v-model="branchFilter"
            class="input-field"
          >
            <option v-if="!isBranchScoped" value="">Semua cabang</option>
            <option v-for="b in branchOptions" :key="b.id" :value="b.id">
              {{ b.name }} ({{ b.code }})
            </option>
          </select>
          <p v-else class="input-field flex items-center bg-slate-50 text-sm text-slate-700">
            {{ lockedBranchLabel }}
          </p>
        </div>
        <div class="min-w-[200px]">
          <label class="filter-label">Filter {{ TERMS.corporate.label }}</label>
          <select
            v-model="customerFilter"
            class="input-field"
            @focus="onCustomerFilterFocus"
            @change="onCustomerChange"
          >
            <option value="">
              {{ loadingCustomerOptions ? 'Memuat...' : 'Semua pelanggan' }}
            </option>
            <option v-for="c in filteredCustomers" :key="c.id" :value="c.id">
              {{ c.name }}
            </option>
          </select>
        </div>
      </template>
    </PageHeader>

    <PageInfoBanner>
      {{ TERMS.employee.pageHelp }}
      <router-link to="/corporate" class="font-medium text-brand-600 hover:underline">
        Buka menu Corporate →
      </router-link>
    </PageInfoBanner>

    <LoadingSpinner v-if="loading" label="Memuat data pegawai..." />

    <AppDataTable
      v-else
      :data="employees"
      :loading="tableLoading"
      search-placeholder="Cari nama, email, atau corporate..."
      :page-size="25"
      :selectable="canSendWhatsApp"
      :empty-text="emptyTableText"
      @selection-change="onEmployeeSelectionChange"
    >
      <template v-if="canSendWhatsApp" #toolbar>
        <button
          v-if="selectedEmployees.length"
          type="button"
          class="btn-primary"
          @click="openWhatsAppModal"
        >
          <MessageCircle class="size-4" />
          Kirim WhatsApp ({{ selectedEmployees.length }})
        </button>
        <router-link
          v-if="auth.hasPermission('template-pesan-update')"
          to="/message-templates"
          class="btn-secondary"
        >
          Kelola Template
        </router-link>
      </template>
      <AppTableColumn prop="full_name" label="Nama Pegawai" sortable min-width="200" fixed="left" />
      <AppTableColumn :label="TERMS.corporate.label" sortable min-width="180">
        <template #default="{ row }">
          <button
            v-if="row.customer?.id"
            type="button"
            class="text-left font-medium text-brand-600 hover:underline"
            title="Lihat profil corporate"
            @click="openCorporateDetail(row.customer.id)"
          >
            {{ row.customer.name }}
          </button>
          <span v-else class="text-slate-400">Pelanggan dihapus</span>
        </template>
      </AppTableColumn>
      <AppTableColumn label="Cabang" width="90">
        <template #default="{ row }">
          {{ row.customer?.branch?.code ?? '—' }}
        </template>
      </AppTableColumn>
      <AppTableColumn prop="mobile" label="Mobile" width="130">
        <template #default="{ row }">
          <span :class="row.mobile?.trim() ? 'text-slate-700' : 'text-slate-400'">
            {{ row.mobile?.trim() || '—' }}
          </span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="email" label="Email" min-width="160" />
      <AppTableColumn label="" width="160" :sortable="false">
        <template #default="{ row }">
          <div class="flex items-center gap-1">
            <button
              v-if="canSendWhatsApp"
              type="button"
              class="btn-icon-brand inline-flex items-center gap-1 px-2 py-1 text-sm"
              :class="!row.mobile?.trim() ? 'opacity-40 cursor-not-allowed' : ''"
              :title="row.mobile?.trim() ? 'Kirim pesan WhatsApp' : 'Mobile kosong'"
              @click="openWhatsAppModalForEmployee(row)"
            >
              <MessageCircle class="size-4" />
            </button>
            <button
              type="button"
              class="btn-icon-brand inline-flex items-center gap-1 px-2 py-1 text-sm"
              title="Detail pegawai"
              @click="openEmployeeDetail(row)"
            >
              <Eye class="size-4" />
            </button>
            <button
              v-if="canManage"
              type="button"
              class="btn-icon-brand inline-flex items-center gap-1 px-2 py-1 text-sm"
              title="Edit pegawai"
              @click="openEditEmployee(row)"
            >
              <Pencil class="size-4" />
            </button>
            <button
              v-if="canManage"
              type="button"
              class="btn-icon-danger inline-flex items-center gap-1 px-2 py-1 text-sm"
              title="Hapus pegawai"
              @click="handleDeleteEmployee(row)"
            >
              <Trash2 class="size-4" />
            </button>
          </div>
        </template>
      </AppTableColumn>
    </AppDataTable>

    <EmployeeFormModal
      v-model="showEmployeeForm"
      :customers="filteredCustomers.length ? filteredCustomers : customers"
      :titles="titles"
      :nationalities="nationalities"
      :employee="editingEmployee"
      :default-customer-id="customerFilter"
      :saving="savingEmployee"
      @submit="handleEmployeeSubmit"
    />

    <AppModal
      v-model="showWhatsAppModal"
      :title="isSingleWhatsAppTarget ? 'Kirim Pesan WhatsApp' : 'Kirim Pesan WhatsApp (Bulk)'"
      max-width="max-w-2xl"
    >
      <div class="space-y-4">
        <div class="info-card text-sm text-slate-600">
          <template v-if="isSingleWhatsAppTarget">
            <p>
              Penerima:
              <span class="font-semibold text-slate-800">{{ modalEmployees[0]?.full_name }}</span>
            </p>
            <p class="mt-1">
              Mobile:
              <span class="font-mono text-emerald-700">{{ modalEmployees[0]?.mobile?.trim() }}</span>
            </p>
          </template>
          <template v-else>
            <p>
              <span class="font-semibold text-slate-800">{{ modalEmployees.length }}</span> pegawai terpilih ·
              <span class="font-semibold text-emerald-700">{{ modalEmployeesWithMobile.length }}</span> punya nomor mobile
            </p>
          </template>
          <p v-if="!whatsappConfigured" class="mt-2 text-amber-700">
            Token Fonnte belum dikonfigurasi. Hubungi admin untuk set <code class="text-xs">FONNTE_TOKEN</code> di backend.
          </p>
        </div>

        <div>
          <label class="form-label">Template Pesan</label>
          <select v-model="whatsappForm.template_id" class="input-field">
            <option value="">— Pesan manual —</option>
            <option v-for="template in activeTemplates" :key="template.id" :value="template.id">
              {{ template.name }}
            </option>
          </select>
          <p v-if="!activeTemplates.length" class="form-hint">
            Belum ada template aktif.
            <router-link to="/message-templates" class="text-brand-600 hover:underline">Buat template</router-link>
          </p>
        </div>

        <div v-if="!whatsappForm.template_id">
          <label class="form-label">Pesan Manual</label>
          <textarea
            v-model="whatsappForm.message"
            rows="5"
            maxlength="5000"
            class="input-field min-h-[120px] resize-y"
            placeholder="Halo {name}, pesan untuk {corporate}..."
          />
          <p class="form-hint">Placeholder: {name}, {corporate}, {cabang}, {mobile}, {email}, {title}</p>
        </div>

        <div v-if="!isSingleWhatsAppTarget">
          <label class="form-label">Delay antar pesan (detik)</label>
          <input v-model="whatsappForm.delay" type="text" class="input-field" placeholder="1-3" />
          <p class="form-hint">Rekomendasi Fonnte: gunakan delay untuk bulk message, mis. 1-3 atau 2.</p>
        </div>

        <div v-if="previewMessage || loadingPreview" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Preview (sample)</p>
          <LoadingSpinner v-if="loadingPreview" :panel="false" size="sm" label="Memuat preview..." />
          <p v-else class="whitespace-pre-wrap text-sm text-slate-700">{{ previewMessage }}</p>
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" class="btn-secondary" @click="showWhatsAppModal = false">Batal</button>
          <button
            type="button"
            class="btn-primary"
            :disabled="sendingWhatsApp || !whatsappConfigured || !modalEmployeesWithMobile.length"
            @click="handleSendWhatsApp"
          >
            <MessageCircle class="size-4" />
            {{ sendingWhatsApp ? 'Mengirim...' : 'Kirim via Fonnte' }}
          </button>
        </div>
      </div>
    </AppModal>
  </div>
</template>
