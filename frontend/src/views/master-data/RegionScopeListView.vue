<script setup>
import { onMounted, ref } from 'vue'
import { Plus, Pencil, Trash2, X } from '@lucide/vue'
import { fetchRegionScopes, createRegionScope, updateRegionScope, deleteRegionScope } from '@/api/regionScopes'
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
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const editingItem = ref(null)

const emptyForm = () => ({ code: '', name: '', status: 'active' })
const form = ref(emptyForm())

onMounted(loadData)

async function loadData() {
  loading.value = true
  try {
    const { data } = await fetchRegionScopes()
    items.value = data.data ?? []
  } catch (err) {
    items.value = []
    toast.error(getApiErrorMessage(err, 'Gagal memuat scope wilayah.'))
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
  form.value = { code: item.code, name: item.name, status: item.status }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  saving.value = true
  try {
    const payload = {
      ...form.value,
      code: form.value.code.trim().toUpperCase(),
    }

    if (editingItem.value) {
      await updateRegionScope(editingItem.value.id, payload)
      toast.success('Scope wilayah berhasil diperbarui.')
    } else {
      await createRegionScope(payload)
      toast.success('Scope wilayah berhasil dibuat.')
    }
    closeModal()
    await loadData()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menyimpan scope wilayah.'))
  } finally {
    saving.value = false
  }
}

async function handleDelete(item) {
  const confirmed = await confirm.confirm({
    title: 'Hapus Scope Wilayah',
    message: `Apakah Anda yakin ingin menghapus "${item.name}"? Tindakan ini tidak dapat dibatalkan.`,
    confirmLabel: 'Ya, Hapus',
  })

  if (!confirmed) return

  try {
    await deleteRegionScope(item.id)
    toast.success('Scope wilayah berhasil dihapus.')
    await loadData()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus scope wilayah.'))
  }
}
</script>

<template>
  <div class="page-shell">
    <LoadingSpinner v-if="loading" label="Memuat scope wilayah..." />

    <AppDataTable
      v-else
      :data="items"
      search-placeholder="Cari kode atau nama scope..."
      empty-text="Belum ada scope wilayah."
    >
      <template #toolbar>
        <button v-if="auth.canManage('scope-wilayah')" type="button" class="btn-primary" @click="openCreate">
          <Plus class="size-4" />
          Tambah Scope
        </button>
      </template>

      <AppTableColumn prop="code" label="Kode" width="110" fixed="left">
        <template #default="{ row }">
          <span class="font-mono text-sm font-medium text-slate-900">{{ row.code }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="name" label="Nama" min-width="180" />
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
      <AppTableColumn prop="airlines_count" label="Airlines" width="100" align="right">
        <template #default="{ row }">
          <span class="tabular-nums">{{ row.airlines_count ?? 0 }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn
        v-if="auth.canManage('scope-wilayah')"
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
      <div class="modal-panel max-w-md">
        <div class="modal-header">
          <h3 class="modal-title">
            {{ editingItem ? 'Edit Scope Wilayah' : 'Tambah Scope Wilayah' }}
          </h3>
          <button type="button" class="btn-icon-neutral" @click="closeModal">
            <X class="size-5" />
          </button>
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div>
            <label class="form-label">Kode</label>
            <input v-model="form.code" type="text" required maxlength="30" class="input-field uppercase" placeholder="INTR, DOM" />
          </div>
          <div>
            <label class="form-label">Nama</label>
            <input v-model="form.name" type="text" required class="input-field" placeholder="International" />
          </div>
          <div>
            <label class="form-label">Status</label>
            <select v-model="form.status" class="input-field">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
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
