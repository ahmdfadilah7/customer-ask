<script setup>
import { onMounted, ref } from 'vue'
import { Plus, Pencil, Trash2 } from '@lucide/vue'
import { fetchBranches, createBranch, updateBranch, deleteBranch } from '@/api/branches'
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

const branches = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const editingBranch = ref(null)

const emptyForm = () => ({ code: '', name: '', status: 'active' })
const form = ref(emptyForm())

onMounted(loadBranches)

async function loadBranches() {
  loading.value = true
  try {
    const { data } = await fetchBranches()
    branches.value = data.data
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingBranch.value = null
  form.value = emptyForm()
  showModal.value = true
}

function openEdit(branch) {
  editingBranch.value = branch
  form.value = { code: branch.code, name: branch.name, status: branch.status }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function handleSubmit() {
  saving.value = true
  try {
    if (editingBranch.value) {
      await updateBranch(editingBranch.value.id, form.value)
      toast.success('Cabang berhasil diperbarui.')
    } else {
      await createBranch(form.value)
      toast.success('Cabang berhasil dibuat.')
    }
    closeModal()
    await loadBranches()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan cabang.')
  } finally {
    saving.value = false
  }
}

async function handleDelete(branch) {
  const confirmed = await confirm.confirm({
    title: 'Hapus Cabang',
    message: `Apakah Anda yakin ingin menghapus cabang "${branch.name}"? Tindakan ini tidak dapat dibatalkan.`,
    confirmLabel: 'Ya, Hapus',
  })

  if (!confirmed) return

  try {
    await deleteBranch(branch.id)
    toast.success('Cabang berhasil dihapus.')
    await loadBranches()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal menghapus cabang.')
  }
}
</script>

<template>
  <div class="page-shell">
    <LoadingSpinner v-if="loading" label="Memuat cabang..." />

    <AppDataTable
      v-else
      :data="branches"
      search-placeholder="Cari kode atau nama cabang..."
      empty-text="Belum ada cabang."
    >
      <template #toolbar>
        <button v-if="auth.canManage('cabang')" type="button" class="btn-primary" @click="openCreate">
          <Plus class="size-4" />
          Tambah Cabang
        </button>
      </template>

      <AppTableColumn prop="code" label="Kode" width="100" fixed="left">
        <template #default="{ row }">
          <span class="font-mono text-sm font-medium text-slate-900">{{ row.code }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="name" label="Nama Cabang" min-width="180" />
      <AppTableColumn prop="status" label="Status" width="110">
        <template #default="{ row }">
          <span
            :class="row.status === 'active' ? 'badge-success' : 'badge-neutral'"
          >
            {{ row.status }}
          </span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="users_count" label="Users" width="90" align="right">
        <template #default="{ row }">
          <span class="tabular-nums">{{ row.users_count ?? 0 }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn
        v-if="auth.canManage('cabang')"
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

    <AppModal v-model="showModal" :title="editingBranch ? 'Edit Cabang' : 'Tambah Cabang'" max-width="max-w-md">
        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div>
            <label class="form-label">Kode</label>
            <input v-model="form.code" type="text" required maxlength="20" class="input-field uppercase" />
          </div>
          <div>
            <label class="form-label">Nama Cabang</label>
            <input v-model="form.name" type="text" required class="input-field" />
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
    </AppModal>
  </div>
</template>
