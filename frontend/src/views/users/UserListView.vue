<script setup>
import { onMounted, ref, computed } from 'vue'
import { Plus, Pencil, Trash2 } from '@lucide/vue'
import { fetchUsers, createUser, updateUser, deleteUser } from '@/api/users'
import { fetchBranches } from '@/api/branches'
import { fetchRolePermissionsMeta } from '@/api/roles'
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

const users = ref([])
const branches = ref([])
const roleMeta = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const editingUser = ref(null)

const emptyForm = () => ({
  name: '',
  email: '',
  password: '',
  role: 'marketing',
  branch_ids: [],
  is_active: true,
})

const form = ref(emptyForm())

const requiresBranch = computed(() =>
  roleMeta.value.find((r) => r.name === form.value.role)?.requires_branch ?? false,
)

const availableRoles = computed(() => {
  const roles = roleMeta.value
  if (auth.hasRole('superadmin')) return roles
  return roles.filter((r) => r.name !== 'superadmin')
})

onMounted(loadData)

async function loadData() {
  loading.value = true
  try {
    const [usersRes, branchesRes, metaRes] = await Promise.all([
      fetchUsers(),
      fetchBranches(),
      fetchRolePermissionsMeta(),
    ])
    users.value = usersRes.data.data
    branches.value = branchesRes.data.data
    roleMeta.value = metaRes.data.roles
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingUser.value = null
  form.value = emptyForm()
  showModal.value = true
}

function openEdit(user) {
  editingUser.value = user
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    role: user.roles[0] ?? 'marketing',
    branch_ids: user.branches?.map((b) => b.id) ?? [],
    is_active: user.is_active,
  }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editingUser.value = null
}

async function handleSubmit() {
  saving.value = true
  try {
    const payload = { ...form.value }
    if (editingUser.value && !payload.password) {
      delete payload.password
    }

    if (editingUser.value) {
      await updateUser(editingUser.value.id, payload)
      toast.success('User berhasil diperbarui.')
    } else {
      await createUser(payload)
      toast.success('User berhasil dibuat.')
    }

    closeModal()
    await loadData()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan user.')
  } finally {
    saving.value = false
  }
}

async function handleDelete(user) {
  const confirmed = await confirm.confirm({
    title: 'Hapus User',
    message: `Apakah Anda yakin ingin menghapus user "${user.name}"? Tindakan ini tidak dapat dibatalkan.`,
    confirmLabel: 'Ya, Hapus',
  })

  if (!confirmed) return

  try {
    await deleteUser(user.id)
    toast.success('User berhasil dihapus.')
    await loadData()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal menghapus user.')
  }
}

function roleLabel(name) {
  return roleMeta.value.find((r) => r.name === name)?.label ?? name
}
</script>

<template>
  <div class="page-shell">
    <LoadingSpinner v-if="loading" label="Memuat users..." />

    <AppDataTable
      v-else
      :data="users"
      search-placeholder="Cari nama, email, role..."
      empty-text="Belum ada user."
    >
      <template #toolbar>
        <button v-if="auth.hasPermission('user-create')" type="button" class="btn-primary" @click="openCreate">
          <Plus class="size-4" />
          Tambah User
        </button>
      </template>

      <AppTableColumn prop="name" label="Nama" min-width="160" fixed="left">
        <template #default="{ row }">
          <span class="font-medium text-slate-900">{{ row.name }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="email" label="Email" min-width="200" show-overflow-tooltip />
      <AppTableColumn prop="roles" label="Role" min-width="130" :sortable="false">
        <template #default="{ row }">
          <span class="badge-brand">
            {{ roleLabel(row.roles[0]) }}
          </span>
        </template>
      </AppTableColumn>
      <AppTableColumn label="Cabang" min-width="180" :sortable="false">
        <template #default="{ row }">
          <span v-if="row.has_full_branch_access" class="text-sm text-slate-600">Semua cabang</span>
          <span v-else class="text-sm text-slate-600">{{ row.branches?.map((b) => b.name).join(', ') || '—' }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="is_active" label="Status" width="110">
        <template #default="{ row }">
          <span
            :class="row.is_active ? 'badge-success' : 'badge-danger'"
          >
            {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
          </span>
        </template>
      </AppTableColumn>
      <AppTableColumn
        v-if="auth.canManage('user')"
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
            <button
              v-if="row.id !== auth.user?.id"
              type="button"
              class="btn-icon-danger"
              @click="handleDelete(row)"
            >
              <Trash2 class="size-4" />
            </button>
          </div>
        </template>
      </AppTableColumn>
    </AppDataTable>

    <AppModal v-model="showModal" :title="editingUser ? 'Edit User' : 'Tambah User'">
        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div>
            <label class="form-label">Nama</label>
            <input v-model="form.name" type="text" required class="input-field" />
          </div>
          <div>
            <label class="form-label">Email</label>
            <input v-model="form.email" type="email" required class="input-field" />
          </div>
          <div>
            <label class="form-label">
              Password {{ editingUser ? '(kosongkan jika tidak diubah)' : '' }}
            </label>
            <input v-model="form.password" type="password" :required="!editingUser" class="input-field" />
          </div>
          <div>
            <label class="form-label">Role</label>
            <select v-model="form.role" class="input-field">
              <option v-for="role in availableRoles" :key="role.name" :value="role.name">
                {{ role.label }}
              </option>
            </select>
          </div>
          <div v-if="requiresBranch">
            <label class="form-label">Cabang</label>
            <div class="space-y-2 rounded-xl border border-slate-200 p-3">
              <label v-for="branch in branches" :key="branch.id" class="flex items-center gap-2 text-sm">
                <input v-model="form.branch_ids" type="checkbox" :value="branch.id" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                {{ branch.name }} ({{ branch.code }})
              </label>
            </div>
          </div>
          <label class="flex items-center gap-2 text-sm text-slate-700">
            <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
            Akun aktif
          </label>
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
