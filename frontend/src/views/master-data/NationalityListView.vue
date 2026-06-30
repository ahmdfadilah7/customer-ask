<script setup>
import { onMounted, ref } from 'vue'
import { Plus, Pencil, Trash2, X } from '@lucide/vue'
import { fetchNationalities, createNationality, updateNationality, deleteNationality } from '@/api/nationalities'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppDataTable from '@/components/ui/AppDataTable.vue'
import AppTableColumn from '@/components/ui/AppTableColumn.vue'

const auth = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const items = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const editingItem = ref(null)

const emptyForm = () => ({ code: '', name: '' })
const form = ref(emptyForm())

onMounted(loadData)

async function loadData() {
  loading.value = true
  try {
    const { data } = await fetchNationalities()
    items.value = data.data
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
  form.value = { code: item.code, name: item.name }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  saving.value = true
  try {
    if (editingItem.value) {
      await updateNationality(editingItem.value.id, form.value)
      toast.success('Nationality berhasil diperbarui.')
    } else {
      await createNationality(form.value)
      toast.success('Nationality berhasil dibuat.')
    }
    closeModal()
    await loadData()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan nationality.')
  } finally {
    saving.value = false
  }
}

async function handleDelete(item) {
  const confirmed = await confirm.confirm({
    title: 'Hapus Nationality',
    message: `Apakah Anda yakin ingin menghapus nationality "${item.name}"? Tindakan ini tidak dapat dibatalkan.`,
    confirmLabel: 'Ya, Hapus',
  })

  if (!confirmed) return

  try {
    await deleteNationality(item.id)
    toast.success('Nationality berhasil dihapus.')
    await loadData()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal menghapus nationality.')
  }
}
</script>

<template>
  <div class="page-shell">
    <LoadingSpinner v-if="loading" label="Memuat nationality..." />

    <AppDataTable
      v-else
      :data="items"
      search-placeholder="Cari negara atau kode..."
      empty-text="Belum ada nationality."
      :page-size="25"
      :page-sizes="[15, 25, 50, 100]"
      :default-sort="{ prop: 'name', order: 'ascending' }"
    >
      <template #toolbar>
        <button v-if="auth.canManage('kebangsaan')" type="button" class="btn-primary" @click="openCreate">
          <Plus class="size-4" />
          Tambah Nationality
        </button>
      </template>

      <AppTableColumn prop="code" label="Kode" width="100" fixed="left">
        <template #default="{ row }">
          <span class="font-mono text-sm font-medium text-slate-900">{{ row.code }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="name" label="Nama" min-width="220" show-overflow-tooltip />
      <AppTableColumn
        v-if="auth.canManage('kebangsaan')"
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

    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal-overlay__align">
        <div class="modal-panel max-w-md">
          <div class="modal-header">
            <h3 class="modal-title">
              {{ editingItem ? 'Edit Nationality' : 'Tambah Nationality' }}
            </h3>
            <button type="button" class="btn-icon-neutral" @click="closeModal">
              <X class="size-5" />
            </button>
          </div>

          <div class="modal-body">
            <form class="space-y-4" @submit.prevent="handleSubmit">
          <div>
            <label class="form-label">Kode</label>
            <input v-model="form.code" type="text" required maxlength="10" class="input-field uppercase" placeholder="ID, ES, US" />
          </div>
          <div>
            <label class="form-label">Nama</label>
            <input v-model="form.name" type="text" required class="input-field" placeholder="Indonesia" />
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
    </div>
  </div>
</template>
