<script setup>
import { onMounted, ref } from 'vue'
import { Plus, Pencil, Trash2, X } from '@lucide/vue'
import { fetchAirlines, createAirline, updateAirline, deleteAirline } from '@/api/airlines'
import { fetchRegionScopes } from '@/api/regionScopes'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppDataTable from '@/components/ui/AppDataTable.vue'
import AppTableColumn from '@/components/ui/AppTableColumn.vue'
import { getApiErrorMessage } from '@/utils/apiError'

const auth = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const items = ref([])
const regionScopes = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const editingItem = ref(null)

const emptyForm = () => ({
  code: '',
  name: '',
  region_scope_ids: [],
  description: '',
  sort_order: 0,
  status: 'active',
})
const form = ref(emptyForm())

onMounted(loadData)

async function loadData() {
  loading.value = true
  try {
    const [airlinesRes, scopesRes] = await Promise.all([
      fetchAirlines(),
      fetchRegionScopes(),
    ])
    items.value = airlinesRes.data.data ?? []
    regionScopes.value = scopesRes.data.data ?? []
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat airlines.'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingItem.value = null
  form.value = emptyForm()
  showModal.value = true
}

function openEdit(item) {
  editingItem.value = item
  form.value = {
    code: item.code,
    name: item.name,
    region_scope_ids: item.region_scope_ids ?? item.region_scopes?.map((s) => s.id) ?? [],
    description: item.description ?? '',
    sort_order: item.sort_order ?? 0,
    status: item.status,
  }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

function scopeBadgeClass(code) {
  const isInternational = ['INTR', 'INTERNATIONAL'].includes(code?.toUpperCase())
  return isInternational
    ? 'bg-violet-50 text-violet-700 ring-violet-600/20'
    : 'bg-blue-50 text-blue-700 ring-blue-600/20'
}

async function handleSubmit() {
  if (!form.value.region_scope_ids.length) {
    toast.warning('Pilih minimal satu scope wilayah.')
    return
  }

  saving.value = true
  try {
    const payload = {
      ...form.value,
      code: form.value.code.trim().toUpperCase(),
    }

    if (editingItem.value) {
      await updateAirline(editingItem.value.id, payload)
      toast.success('Airline berhasil diperbarui.')
    } else {
      await createAirline(payload)
      toast.success('Airline berhasil dibuat.')
    }
    closeModal()
    await loadData()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menyimpan airline.'))
  } finally {
    saving.value = false
  }
}

async function handleDelete(item) {
  const confirmed = await confirm.confirm({
    title: 'Hapus Airline',
    message: `Apakah Anda yakin ingin menghapus "${item.name}"? Tindakan ini tidak dapat dibatalkan.`,
    confirmLabel: 'Ya, Hapus',
  })

  if (!confirmed) return

  try {
    await deleteAirline(item.id)
    toast.success('Airline berhasil dihapus.')
    await loadData()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus airline.'))
  }
}
</script>

<template>
  <div class="page-shell">
    <LoadingSpinner v-if="loading" label="Memuat airlines..." />

    <AppDataTable
      v-else
      :data="items"
      search-placeholder="Cari kode, nama, atau scope..."
      empty-text="Belum ada airline."
      :default-sort="{ prop: 'sort_order', order: 'ascending' }"
    >
      <template #toolbar>
        <button v-if="auth.canManage('maskapai')" type="button" class="btn-primary" @click="openCreate">
          <Plus class="size-4" />
          Tambah Airline
        </button>
      </template>

      <AppTableColumn prop="code" label="Kode" width="100" fixed="left">
        <template #default="{ row }">
          <span class="font-mono text-xs font-medium text-slate-900">{{ row.code }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="name" label="Nama" min-width="140">
        <template #default="{ row }">
          <span class="font-medium text-slate-900">{{ row.name }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn label="Scope" min-width="180" :sortable="false">
        <template #default="{ row }">
          <div v-if="row.region_scopes?.length" class="flex flex-wrap gap-1">
            <span
              v-for="scope in row.region_scopes"
              :key="scope.id"
              class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
              :class="scopeBadgeClass(scope.code)"
            >
              {{ scope.name }}
            </span>
          </div>
          <span v-else>—</span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="description" label="Deskripsi" min-width="180" show-overflow-tooltip>
        <template #default="{ row }">
          <span class="text-sm text-slate-600">{{ row.description ?? '—' }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="sort_order" label="Urutan" width="90" align="right">
        <template #default="{ row }">
          <span class="tabular-nums">{{ row.sort_order }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="status" label="Status" width="110">
        <template #default="{ row }">
          <span
            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
            :class="row.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'"
          >
            {{ row.status }}
          </span>
        </template>
      </AppTableColumn>
      <AppTableColumn
        v-if="auth.canManage('maskapai')"
        label="Aksi"
        width="110"
        align="right"
        :sortable="false"
      >
        <template #default="{ row }">
          <div class="flex justify-end gap-1">
            <button type="button" class="btn-icon-neutral" @click="openEdit(row)">
              <Pencil class="size-4" />
            </button>
            <button type="button" class="btn-icon-danger" @click="handleDelete(row)">
              <Trash2 class="size-4" />
            </button>
          </div>
        </template>
      </AppTableColumn>
    </AppDataTable>

    <div v-if="showModal" class="modal-overlay">
      <div class="modal-panel max-w-lg">
        <div class="modal-header">
          <h3 class="modal-title">
            {{ editingItem ? 'Edit Airline' : 'Tambah Airline' }}
          </h3>
          <button type="button" class="btn-icon-neutral" @click="closeModal">
            <X class="size-5" />
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div>
            <label class="form-label">Kode</label>
            <input v-model="form.code" type="text" required maxlength="50" class="input-field uppercase" />
          </div>
          <div>
            <label class="form-label">Nama</label>
            <input v-model="form.name" type="text" required class="input-field" placeholder="Garuda" />
          </div>
          <div>
            <label class="form-label">Scope Wilayah</label>
            <p class="mb-2 text-xs text-slate-400">Bisa pilih lebih dari satu, misal International dan Domestic</p>
            <div class="space-y-2 rounded-xl border border-slate-200 p-3">
              <label v-for="scope in regionScopes" :key="scope.id" class="flex items-center gap-2 text-sm">
                <input
                  v-model="form.region_scope_ids"
                  type="checkbox"
                  :value="scope.id"
                  class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                />
                {{ scope.name }} ({{ scope.code }})
              </label>
            </div>
          </div>
          <div>
            <label class="form-label">Deskripsi</label>
            <input v-model="form.description" type="text" class="input-field" placeholder="Contoh: Garuda Indonesia" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label">Urutan</label>
              <input v-model.number="form.sort_order" type="number" min="0" class="input-field" />
            </div>
            <div>
              <label class="form-label">Status</label>
              <select v-model="form.status" class="input-field">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" class="btn-secondary" @click="closeModal">Batal</button>
            <button type="submit" class="btn-primary" :disabled="saving">
              {{ saving ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
