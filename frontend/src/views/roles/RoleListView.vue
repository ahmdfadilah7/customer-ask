<script setup>
import { computed, onMounted, ref } from 'vue'
import { Pencil, Plus, Shield, Trash2 } from '@lucide/vue'
import {
  createRole,
  deleteRole,
  fetchRoles,
  fetchRolePermissionsMeta,
  updateRole,
} from '@/api/roles'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { getApiErrorMessage } from '@/utils/apiError'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PageInfoBanner from '@/components/ui/PageInfoBanner.vue'
import RoleFormModal from '@/components/roles/RoleFormModal.vue'

const auth = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const roles = ref([])
const groups = ref([])
const loading = ref(true)
const saving = ref(false)
const showModal = ref(false)
const editingRole = ref(null)

const canCreate = computed(() => auth.hasPermission('role-create'))
const canUpdate = computed(() => auth.hasPermission('role-update'))
const canDelete = computed(() => auth.hasPermission('role-delete'))

onMounted(loadData)

async function loadData() {
  loading.value = true
  try {
    const [rolesRes, metaRes] = await Promise.all([
      fetchRoles(),
      fetchRolePermissionsMeta(),
    ])
    roles.value = rolesRes.data?.data ?? []
    groups.value = metaRes.data?.groups ?? []

    if (!groups.value.length) {
      toast.error('Daftar permission tidak tersedia. Jalankan ulang PermissionSeeder di backend.')
    }
  } catch (err) {
    roles.value = []
    groups.value = []
    toast.error(getApiErrorMessage(err, 'Gagal memuat data role. Pastikan permission role-view sudah di-seed.'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editingRole.value = null
  showModal.value = true
  if (!groups.value.length) {
    loadData()
  }
}

function openEdit(role) {
  editingRole.value = role
  showModal.value = true
}

function permissionCount(role) {
  return role.permissions?.length ?? 0
}

function groupSummary(role) {
  const names = new Set(role.permissions ?? [])
  return groups.value
    .filter((group) => group.permissions.some((p) => names.has(p.name)))
    .map((group) => group.label)
}

async function handleSubmit(payload) {
  saving.value = true
  try {
    if (editingRole.value) {
      const { data } = await updateRole(editingRole.value.id, payload)
      toast.success('Role berhasil diperbarui.')
      const index = roles.value.findIndex((r) => r.id === editingRole.value.id)
      if (index >= 0) roles.value[index] = data.data
    } else {
      const { data } = await createRole(payload)
      toast.success('Role berhasil dibuat.')
      roles.value.push(data.data)
    }
    showModal.value = false
    editingRole.value = null
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menyimpan role.'))
  } finally {
    saving.value = false
  }
}

async function handleDelete(role) {
  const confirmed = await confirm.confirm({
    title: 'Hapus Role',
    message: `Hapus role "${role.label}"? Role yang masih dipakai user tidak dapat dihapus.`,
    confirmLabel: 'Ya, Hapus',
  })
  if (!confirmed) return

  try {
    await deleteRole(role.id)
    roles.value = roles.value.filter((r) => r.id !== role.id)
    toast.success('Role berhasil dihapus.')
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus role.'))
  }
}
</script>

<template>
  <div class="page-shell">
    <PageHeader
      :icon="Shield"
      title="Role & Permission"
      description="Buat role kustom dan tentukan fitur yang dapat diakses, misalnya marketing dengan cabang-view dan cabang-create."
    >
      <template v-if="canCreate" #actions>
        <button type="button" class="btn-primary !py-2" @click="openCreate">
          <Plus class="size-4" />
          Tambah Role
        </button>
      </template>
    </PageHeader>

    <PageInfoBanner>
      Permission mengikuti format <code class="text-xs">fitur-aksi</code>, contoh:
      <code class="text-xs">dashboard-view</code>,
      <code class="text-xs">cabang-view</code>,
      <code class="text-xs">cabang-create</code>.
      Role superadmin tidak dapat dihapus. Role lain (admin, marketing, kustom, dll.) dapat dihapus jika tidak dipakai user.
    </PageInfoBanner>

    <LoadingSpinner v-if="loading" label="Memuat roles..." />

    <div v-else-if="!roles.length" class="glass-panel p-8 text-center">
      <p class="text-sm text-slate-600">Belum ada role atau gagal memuat data dari server.</p>
      <button type="button" class="btn-secondary mt-4" @click="loadData">Muat ulang</button>
    </div>

    <div v-else class="grid gap-4 lg:grid-cols-2">
      <div
        v-for="role in roles"
        :key="role.id"
        class="glass-panel p-5 sm:p-6"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="font-bold text-slate-900">{{ role.label }}</h3>
              <span class="badge-neutral font-mono text-[11px]">{{ role.name }}</span>
              <span v-if="role.is_system" class="badge-brand text-[11px]">Sistem</span>
            </div>
            <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ role.description || '—' }}</p>
          </div>
          <span class="badge-neutral shrink-0">{{ role.users_count ?? 0 }} user</span>
        </div>

        <div v-if="role.requires_branch" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700 ring-1 ring-amber-100">
          Akses dibatasi sesuai cabang yang ditugaskan
        </div>

        <div class="mt-4">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">
            {{ permissionCount(role) }} permission aktif
          </p>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <span
              v-for="label in groupSummary(role)"
              :key="label"
              class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600"
            >
              {{ label }}
            </span>
          </div>
        </div>

        <div v-if="canUpdate || canDelete" class="mt-4 flex justify-end gap-2 border-t border-slate-100 pt-4">
          <button
            v-if="canUpdate"
            type="button"
            class="btn-secondary !py-1.5 text-sm"
            @click="openEdit(role)"
          >
            <Pencil class="size-4" />
            Edit
          </button>
          <button
            v-if="canDelete && role.name !== 'superadmin'"
            type="button"
            class="btn-danger !py-1.5 text-sm"
            @click="handleDelete(role)"
          >
            <Trash2 class="size-4" />
            Hapus
          </button>
        </div>
      </div>
    </div>

    <RoleFormModal
      v-model="showModal"
      :groups="groups"
      :saving="saving"
      :editing-role="editingRole"
      @submit="handleSubmit"
    />
  </div>
</template>
