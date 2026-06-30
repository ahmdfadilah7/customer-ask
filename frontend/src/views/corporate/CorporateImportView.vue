<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Download, Upload, FileSpreadsheet, Info, Building2, Receipt } from '@lucide/vue'
import { fetchBranches } from '@/api/branches'
import {
  downloadCorporateTemplate,
  fetchCorporateImportReference,
  importCorporateData,
} from '@/api/corporateImport'
import { useAuthStore } from '@/stores/auth'
import { useBranchScope } from '@/composables/useBranchScope'
import { useToast } from '@/composables/useToast'
import FileDropzone from '@/components/ui/FileDropzone.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'

const route = useRoute()
const auth = useAuthStore()
const {
  isBranchScoped,
  resolveBranchOptions,
  shouldShowBranchSelector,
  findBranchLabel,
  initBranchValue,
} = useBranchScope()
const toast = useToast()

const importType = computed(() => route.meta.importType ?? 'corporate')
const isService = computed(() => importType.value === 'service')

const loading = ref(true)
const importing = ref(false)
const branches = ref([])
const reference = ref(null)
const file = ref(null)
const branchId = ref('')
const versionName = ref('')
const lastResult = ref(null)

const canImport = computed(() => auth.canImport(isService.value ? 'service' : 'corporate'))

const branchOptions = computed(() => resolveBranchOptions(branches.value))
const showBranchPicker = computed(() => shouldShowBranchSelector(branchOptions.value))
const lockedBranchLabel = computed(() => findBranchLabel(branchOptions.value, branchId.value))

async function loadReference() {
  loading.value = true
  try {
    const requests = [fetchCorporateImportReference(importType.value)]
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
  } catch {
    toast.error('Gagal memuat data referensi import.')
  } finally {
    loading.value = false
  }
}

watch(importType, () => {
  file.value = null
  lastResult.value = null
  versionName.value = ''
  loadReference()
})

onMounted(loadReference)

async function handleDownloadTemplate() {
  try {
    await downloadCorporateTemplate(importType.value)
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
  if (isService.value && !versionName.value.trim()) {
    toast.warning('Isi nama versi pricing.')
    return
  }

  importing.value = true
  lastResult.value = null

  try {
    const formData = new FormData()
    formData.append('file', file.value)
    formData.append('branch_id', branchId.value)
    formData.append('import_type', importType.value)
    if (isService.value) {
      formData.append('version_name', versionName.value.trim())
    }

    const { data } = await importCorporateData(formData)
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
      :icon="isService ? Receipt : Building2"
      :title="isService ? 'Import Data Service' : 'Import Data Corporate'"
      :description="isService
        ? 'Import service fee per maskapai dan layanan. Materai diimport lewat menu Data Corporate.'
        : 'Import profil perusahaan pelanggan Astrindo: identitas, materai, periode kontrak, dan catatan.'"
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

          <div v-if="isService">
            <label class="form-label">Nama Versi Pricing</label>
            <input
              v-model="versionName"
              type="text"
              class="input-field"
              placeholder="SF CORP UPDATE JUNI 2026"
              :disabled="!canImport"
            />
            <p class="form-hint">Versi aktif cabang akan diganti setelah import berhasil.</p>
          </div>

          <FileDropzone
            v-model="file"
            accept=".xlsx,.xls"
            label="Seret file Excel (.xlsx) ke sini"
          />

          <button
            v-if="canImport"
            type="button"
            class="btn-primary w-full"
            :disabled="importing"
            @click="handleImport"
          >
            <FileSpreadsheet class="size-4" />
            {{ importing ? 'Mengimport...' : (isService ? 'Import Data Service' : 'Import Data Corporate') }}
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

            <div v-if="isService">
              <p class="mb-2 font-medium text-slate-700">Header Maskapai</p>
              <ul class="max-h-40 space-y-1 overflow-y-auto text-xs text-slate-600">
                <li v-for="airline in reference.airline_headers" :key="airline.code">
                  <span class="font-semibold text-brand-700">{{ airline.label }}</span>
                  — {{ airline.scopes?.join(' / ') }}
                </li>
              </ul>
            </div>

            <div v-else>
              <p class="mb-1 font-medium text-slate-700">Kolom Corporate</p>
              <p class="text-xs text-slate-500">
                Identitas → Profil Operasional → Materai → Periode Kontrak → Catatan
              </p>
              <p class="mt-2 text-xs text-slate-400">
                Materai disimpan ke versi pricing aktif cabang.
              </p>
            </div>
          </div>
        </div>
      </div>

      <div v-if="lastResult" class="glass-panel p-6">
        <h4 class="mb-3 font-semibold text-slate-900">Hasil Import Terakhir</h4>
        <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <div class="rounded-xl bg-slate-50 px-3 py-2 text-center">
            <p class="text-lg font-bold text-slate-900">{{ lastResult.stats?.customers ?? 0 }}</p>
            <p class="text-xs text-slate-500">Customer</p>
          </div>
          <div v-if="isService" class="rounded-xl bg-slate-50 px-3 py-2 text-center">
            <p class="text-lg font-bold text-slate-900">{{ lastResult.stats?.pricing_rules ?? 0 }}</p>
            <p class="text-xs text-slate-500">Pricing Rule</p>
          </div>
          <template v-else>
            <div class="rounded-xl bg-slate-50 px-3 py-2 text-center">
              <p class="text-lg font-bold text-slate-900">{{ lastResult.stats?.pricing_rules ?? 0 }}</p>
              <p class="text-xs text-slate-500">Materai</p>
            </div>
          </template>
        </div>

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
