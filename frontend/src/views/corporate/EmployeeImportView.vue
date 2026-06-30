<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Download, Upload, FileSpreadsheet, Info, Users } from '@lucide/vue'
import { fetchBranches } from '@/api/branches'
import { fetchCustomers } from '@/api/customers'
import {
  downloadEmployeeTemplate,
  fetchEmployeeImportReference,
  importEmployeeData,
} from '@/api/employeeImport'
import { useAuthStore } from '@/stores/auth'
import { useBranchScope } from '@/composables/useBranchScope'
import { useToast } from '@/composables/useToast'
import FileDropzone from '@/components/ui/FileDropzone.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'

const auth = useAuthStore()
const {
  isBranchScoped,
  resolveBranchOptions,
  shouldShowBranchSelector,
  findBranchLabel,
  initBranchValue,
} = useBranchScope()
const toast = useToast()

const loading = ref(true)
const importing = ref(false)
const branches = ref([])
const customers = ref([])
const reference = ref(null)
const file = ref(null)
const branchId = ref('')
const customerName = ref('')
const lastResult = ref(null)

const canImport = computed(() => auth.canImport('pegawai'))

const branchOptions = computed(() => resolveBranchOptions(branches.value))
const showBranchPicker = computed(() => shouldShowBranchSelector(branchOptions.value))
const lockedBranchLabel = computed(() => findBranchLabel(branchOptions.value, branchId.value))

async function loadCustomers() {
  if (!branchId.value) {
    customers.value = []
    return
  }

  try {
    const { data } = await fetchCustomers({
      per_page: 500,
      'filter[branch_id]': branchId.value,
    })
    customers.value = data.data ?? []
  } catch {
    customers.value = []
  }
}

async function loadReference() {
  loading.value = true
  try {
    const requests = [fetchEmployeeImportReference()]
    if (!isBranchScoped.value) {
      requests.unshift(fetchBranches())
    }

    const results = await Promise.all(requests)
    if (!isBranchScoped.value) {
      branches.value = results[0].data.data ?? []
      reference.value = results[1].data
    } else {
      reference.value = results[0].data
    }

    initBranchValue(branchId, branchOptions.value)
    if (branchId.value) {
      await loadCustomers()
    }
  } catch {
    toast.error('Gagal memuat data referensi import.')
  } finally {
    loading.value = false
  }
}

watch(branchId, async () => {
  customerName.value = ''
  await loadCustomers()
})

onMounted(loadReference)

async function handleDownloadTemplate() {
  try {
    await downloadEmployeeTemplate()
    toast.success('Template berhasil diunduh.')
  } catch {
    toast.error('Gagal mengunduh template.')
  }
}

async function handleImport() {
  if (!file.value) {
    toast.warning('Pilih file Excel terlebih dahulu.')
    return
  }
  if (!branchId.value) {
    toast.warning('Pilih cabang terlebih dahulu.')
    return
  }

  importing.value = true
  lastResult.value = null

  try {
    const formData = new FormData()
    formData.append('file', file.value)
    formData.append('branch_id', branchId.value)
    if (customerName.value.trim()) {
      formData.append('customer_name', customerName.value.trim())
    }

    const { data } = await importEmployeeData(formData)
    lastResult.value = data.data
    toast.success(data.message)

    if (data.data?.errors?.length) {
      toast.warning(`${data.data.errors.length} baris memiliki peringatan/error.`)
    }
  } catch (err) {
    toast.error(err.response?.data?.message || 'Import gagal.')
    if (err.response?.data?.data) {
      lastResult.value = err.response.data.data
    }
  } finally {
    importing.value = false
  }
}
</script>

<template>
  <div class="page-shell">
    <PageHeader
      :icon="Users"
      title="Import Data Pegawai"
      description="Import data pegawai per pelanggan corporate. Mendukung template baru maupun file export legacy Employee List."
    >
      <template #actions>
        <button type="button" class="btn-secondary shrink-0" @click="handleDownloadTemplate">
          <Download class="size-4" />
          Unduh Template
        </button>
      </template>
    </PageHeader>

    <LoadingSpinner v-if="loading" label="Memuat..." />

    <template v-else>
      <div class="grid gap-6 lg:grid-cols-2">
        <div class="glass-panel space-y-4 p-6">
          <h4 class="section-title">
            <Upload class="section-icon" />
            Upload File
          </h4>

          <div>
            <label class="form-label">Cabang</label>
            <select
              v-if="showBranchPicker"
              v-model="branchId"
              class="input-field"
              :disabled="!canImport"
            >
              <option v-if="!isBranchScoped" value="">— Pilih cabang —</option>
              <option v-for="b in branchOptions" :key="b.id" :value="b.id">
                {{ b.name }} ({{ b.code }})
              </option>
            </select>
            <p v-else class="input-field flex items-center bg-slate-50 text-sm text-slate-700">
              {{ lockedBranchLabel }}
            </p>
          </div>

          <div>
            <label class="form-label">Nama Pelanggan (Corporate)</label>
            <input
              v-model="customerName"
              type="text"
              class="input-field"
              list="employee-customer-options"
              placeholder="PT. Sol Melia Indonesia"
              :disabled="!canImport"
            />
            <datalist id="employee-customer-options">
              <option v-for="c in customers" :key="c.id" :value="c.name" />
            </datalist>
            <p class="form-hint">
              Opsional untuk template baru (kolom Nama Corporate sudah ada di file).
              Wajib untuk file export lama tanpa kolom identitas.
            </p>
          </div>

          <FileDropzone
            v-model="file"
            accept=".xlsx,.xls,.csv"
            label="Seret file Excel/CSV ke sini"
          />

          <button
            v-if="canImport"
            type="button"
            class="btn-primary w-full"
            :disabled="importing"
            @click="handleImport"
          >
            <FileSpreadsheet class="size-4" />
            {{ importing ? 'Mengimport...' : 'Import Data Employee' }}
          </button>
          <p v-else class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-700">
            Anda hanya dapat melihat referensi format. Hubungi admin untuk melakukan import.
          </p>
        </div>

        <div class="glass-panel p-6">
          <h4 class="mb-4 flex items-center gap-2 font-semibold text-slate-900">
            <Info class="size-4 text-brand-600" />
            Referensi Format
          </h4>

          <div v-if="reference" class="space-y-4 text-sm">
            <div>
              <p class="mb-2 font-medium text-slate-700">Grup Kolom</p>
              <p class="text-xs text-slate-500">
                {{ reference.column_groups?.join(' · ') }}
              </p>
            </div>

            <div>
              <p class="mb-2 font-medium text-slate-700">Kolom Template</p>
              <ul class="max-h-52 space-y-1 overflow-y-auto text-xs text-slate-600">
                <li v-for="col in reference.columns" :key="col.key">
                  <span class="font-medium text-slate-700">{{ col.label }}</span>
                  <span class="text-slate-400"> — {{ col.group }}</span>
                </li>
              </ul>
            </div>

            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs text-slate-600">
              <p class="font-medium text-slate-700">File export lama</p>
              <p class="mt-1">
                {{ reference.legacy_format?.description }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <div v-if="lastResult" class="glass-panel p-6">
        <h4 class="mb-3 font-semibold text-slate-900">Hasil Import Terakhir</h4>
        <div class="grid gap-3 sm:grid-cols-4">
          <div class="rounded-xl bg-slate-50 px-3 py-2 text-center">
            <p class="text-lg font-bold text-slate-900">{{ lastResult.stats?.employees ?? 0 }}</p>
            <p class="text-xs text-slate-500">Employee</p>
          </div>
          <div class="rounded-xl bg-slate-50 px-3 py-2 text-center">
            <p class="text-lg font-bold text-emerald-700">{{ lastResult.stats?.created ?? 0 }}</p>
            <p class="text-xs text-slate-500">Baru</p>
          </div>
          <div class="rounded-xl bg-slate-50 px-3 py-2 text-center">
            <p class="text-lg font-bold text-brand-700">{{ lastResult.stats?.updated ?? 0 }}</p>
            <p class="text-xs text-slate-500">Diperbarui</p>
          </div>
          <div class="rounded-xl bg-slate-50 px-3 py-2 text-center">
            <p class="text-lg font-bold text-slate-900">{{ lastResult.stats?.customers ?? 0 }}</p>
            <p class="text-xs text-slate-500">Corporate</p>
          </div>
        </div>

        <p v-if="lastResult.format" class="mt-3 text-xs text-slate-500">
          Format terdeteksi: <span class="font-medium">{{ lastResult.format }}</span>
          <span v-if="lastResult.branch_code"> · Cabang {{ lastResult.branch_code }}</span>
        </p>

        <div v-if="lastResult.errors?.length" class="mt-4">
          <p class="mb-2 text-sm font-medium text-amber-700">Peringatan / Error ({{ lastResult.errors.length }})</p>
          <ul class="max-h-48 space-y-1 overflow-y-auto rounded-xl border border-amber-100 bg-amber-50/50 p-3 text-xs text-amber-900">
            <li v-for="(err, idx) in lastResult.errors" :key="idx">{{ err }}</li>
          </ul>
        </div>
      </div>
    </template>
  </div>
</template>
