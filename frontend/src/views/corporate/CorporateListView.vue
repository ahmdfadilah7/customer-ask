<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Archive, ArchiveRestore, Building2, Eye, Pencil, Plus, Trash2 } from '@lucide/vue'
import {
  bulkDeleteCustomers,
  bulkForceDeleteCustomers,
  bulkRestoreCustomers,
  deleteCustomer,
  fetchCustomers,
  fetchTrashedCustomers,
  forceDeleteCustomer,
  restoreCustomer,
} from '@/api/customers'
import { fetchBranches } from '@/api/branches'
import { useAuthStore } from '@/stores/auth'
import { useBranchScope } from '@/composables/useBranchScope'
import { useReloadGuard } from '@/composables/useReloadGuard'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import PageInfoBanner from '@/components/ui/PageInfoBanner.vue'
import { TERMS } from '@/constants/terminology'
import AppDataTable from '@/components/ui/AppDataTable.vue'
import AppTableColumn from '@/components/ui/AppTableColumn.vue'
import { getApiErrorMessage } from '@/utils/apiError'

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
const customers = ref([])
const branches = ref([])
const branchFilter = ref('')
const selectedRows = ref([])
const viewMode = ref(route.query.view === 'trashed' ? 'trashed' : 'active')
const initializing = ref(false)
const dataTableRef = ref(null)

const canManage = computed(() => auth.canManage('corporate'))
const canCreate = computed(() => auth.hasPermission('corporate-create'))
const canUpdate = computed(() => auth.hasPermission('corporate-update'))
const canImportCorporate = computed(() => auth.canImport('corporate'))
const isTrashedView = computed(() => viewMode.value === 'trashed')

const branchOptions = computed(() => resolveBranchOptions(branches.value))
const showBranchFilter = computed(() => shouldShowBranchSelector(branchOptions.value))
const lockedBranchLabel = computed(() => findBranchLabel(branchOptions.value, branchFilter.value))

watch(viewMode, async () => {
  clearTableSelection()
  router.replace({ query: viewMode.value === 'trashed' ? { view: 'trashed' } : {} })
  await refreshCustomers()
})

function clearTableSelection() {
  selectedRows.value = []
  nextTick(() => dataTableRef.value?.clearSelection())
}

onMounted(loadData)

async function loadData() {
  loading.value = true
  initializing.value = true
  try {
    if (!isBranchScoped.value) {
      const { data } = await fetchBranches()
      branches.value = data.data ?? []
    }

    initBranchValue(branchFilter, branchOptions.value)

    await reloadCustomers()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat data corporate.'))
  } finally {
    initializing.value = false
    loading.value = false
  }
}

async function refreshCustomers() {
  tableLoading.value = true
  try {
    await reloadCustomers()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memuat data corporate.'))
  } finally {
    tableLoading.value = false
  }
}

async function reloadCustomers() {
  const token = nextToken()
  const params = { per_page: 200 }
  if (branchFilter.value) {
    params['filter[branch_id]'] = branchFilter.value
  }
  const fetcher = isTrashedView.value ? fetchTrashedCustomers : fetchCustomers
  const { data } = await fetcher(params)
  if (isStale(token)) return
  customers.value = data.data ?? []
}

async function onBranchChange() {
  clearTableSelection()
  await refreshCustomers()
}

function openDetail(row) {
  router.push({ name: 'corporate-detail', params: { id: row.id } })
}

function openCreateCorporate() {
  router.push({ name: 'corporate-create' })
}

function openEditCorporate(row) {
  router.push({ name: 'corporate-edit', params: { id: row.id } })
}

function openPegawaiList(row) {
  router.push({ name: 'customer-list', query: { customer_id: row.id } })
}

function onSelectionChange(rows) {
  selectedRows.value = rows
}

async function handleDelete(row) {
  const confirmed = await confirm.confirm({
    title: 'Hapus Corporate',
    message: `Hapus corporate "${row.name}"? Data dipindahkan ke sampah dan masih bisa dipulihkan.`,
    confirmLabel: 'Ya, Hapus',
    variant: 'danger',
  })

  if (!confirmed) return

  try {
    await deleteCustomer(row.id)
    toast.success(`Corporate "${row.name}" berhasil dihapus.`)
    clearTableSelection()
    await reloadCustomers()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus corporate.'))
  }
}

async function handleBulkDelete() {
  if (!selectedRows.value.length) return

  const count = selectedRows.value.length
  const confirmed = await confirm.confirm({
    title: 'Hapus Corporate Terpilih',
    message: `Hapus ${count} corporate terpilih? Data dipindahkan ke sampah.`,
    confirmLabel: `Ya, Hapus ${count}`,
    variant: 'danger',
  })

  if (!confirmed) return

  try {
    const { data } = await bulkDeleteCustomers(selectedRows.value.map((row) => row.id))
    toast.success(data.message)
    if (data.failed?.length) {
      toast.warning(`${data.failed.length} corporate gagal dihapus.`)
    }
    clearTableSelection()
    await reloadCustomers()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus corporate terpilih.'))
  }
}

async function handleRestore(row) {
  const confirmed = await confirm.confirm({
    title: 'Pulihkan Corporate',
    message: `Pulihkan corporate "${row.name}" beserta employee terkait?`,
    confirmLabel: 'Ya, Pulihkan',
    variant: 'default',
  })

  if (!confirmed) return

  try {
    const { data } = await restoreCustomer(row.id)
    toast.success(data.message)
    clearTableSelection()
    await reloadCustomers()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memulihkan corporate.'))
  }
}

async function handleBulkRestore() {
  if (!selectedRows.value.length) return

  const count = selectedRows.value.length
  const confirmed = await confirm.confirm({
    title: 'Pulihkan Corporate Terpilih',
    message: `Pulihkan ${count} corporate terpilih?`,
    confirmLabel: `Ya, Pulihkan ${count}`,
  })

  if (!confirmed) return

  try {
    const { data } = await bulkRestoreCustomers(selectedRows.value.map((row) => row.id))
    toast.success(data.message)
    if (data.failed?.length) {
      toast.warning(`${data.failed.length} corporate gagal dipulihkan.`)
    }
    clearTableSelection()
    await reloadCustomers()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal memulihkan corporate terpilih.'))
  }
}

async function handleForceDelete(row) {
  const confirmed = await confirm.confirm({
    title: 'Hapus Permanen',
    message: `Hapus permanen corporate "${row.name}"? Semua data terkait akan dihapus dari database dan TIDAK dapat dipulihkan.`,
    confirmLabel: 'Ya, Hapus Permanen',
    variant: 'danger',
  })

  if (!confirmed) return

  try {
    const { data } = await forceDeleteCustomer(row.id)
    toast.success(data.message)
    clearTableSelection()
    await reloadCustomers()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus permanen corporate.'))
  }
}

async function handleBulkForceDelete() {
  if (!selectedRows.value.length) return

  const count = selectedRows.value.length
  const confirmed = await confirm.confirm({
    title: 'Hapus Permanen Terpilih',
    message: `Hapus permanen ${count} corporate? Tindakan ini TIDAK dapat dibatalkan.`,
    confirmLabel: `Ya, Hapus Permanen ${count}`,
    variant: 'danger',
  })

  if (!confirmed) return

  try {
    const { data } = await bulkForceDeleteCustomers(selectedRows.value.map((row) => row.id))
    toast.success(data.message)
    if (data.failed?.length) {
      toast.warning(`${data.failed.length} corporate gagal dihapus permanen.`)
    }
    clearTableSelection()
    await reloadCustomers()
  } catch (err) {
    toast.error(getApiErrorMessage(err, 'Gagal menghapus permanen corporate terpilih.'))
  }
}

function formatDate(value) {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return value
  }
}
</script>

<template>
  <div class="page-shell">
    <PageHeader
      :icon="Building2"
      :title="isTrashedView ? 'Sampah Pelanggan' : `Daftar ${TERMS.corporate.label}`"
      :description="isTrashedView
        ? 'Pelanggan corporate yang dihapus (soft delete). Pulihkan atau hapus permanen.'
        : TERMS.corporate.listDescription"
    >
      <template #actions>
        <button
          v-if="!isTrashedView && canCreate"
          type="button"
          class="btn-primary !py-2 text-sm"
          @click="openCreateCorporate"
        >
          <Plus class="size-4" />
          Tambah Corporate
        </button>
        <router-link
          v-if="!isTrashedView && canImportCorporate"
          to="/import/corporate"
          class="btn-secondary !py-2 text-sm"
        >
          Import Corporate
        </router-link>
        <div class="segment-control">
          <button
            type="button"
            class="segment-control__btn"
            :class="!isTrashedView ? 'segment-control__btn--active' : 'segment-control__btn--inactive'"
            @click="viewMode = 'active'"
          >
            Aktif
          </button>
          <button
            type="button"
            class="segment-control__btn"
            :class="isTrashedView ? 'segment-control__btn--active' : 'segment-control__btn--inactive'"
            @click="viewMode = 'trashed'"
          >
            Sampah
          </button>
        </div>

        <div v-if="showBranchFilter || isBranchScoped" class="min-w-[180px]">
          <label class="filter-label">Filter Cabang</label>
          <select
            v-if="showBranchFilter"
            v-model="branchFilter"
            class="input-field"
            @change="onBranchChange"
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
      </template>
    </PageHeader>

    <PageInfoBanner v-if="!isTrashedView">
      {{ TERMS.corporate.pageHelp }}
      <router-link to="/customers" class="font-medium text-brand-600 hover:underline">
        Buka menu Pegawai →
      </router-link>
    </PageInfoBanner>

    <LoadingSpinner v-if="loading" label="Memuat corporate..." />

    <AppDataTable
      v-else
      ref="dataTableRef"
      :data="customers"
      :loading="tableLoading"
      search-placeholder="Cari nama corporate..."
      :page-size="25"
      :selectable="canManage"
      :empty-text="isTrashedView ? 'Sampah kosong.' : 'Belum ada data corporate.'"
      @selection-change="onSelectionChange"
    >
        <template v-if="canManage" #toolbar>
          <template v-if="isTrashedView">
            <button
              v-if="selectedRows.length"
              type="button"
              class="btn-success-outline"
              @click="handleBulkRestore"
            >
              <ArchiveRestore class="size-4" />
              Pulihkan {{ selectedRows.length }} terpilih
            </button>
            <button
              v-if="selectedRows.length"
              type="button"
              class="btn-danger-outline"
              @click="handleBulkForceDelete"
            >
              <Trash2 class="size-4" />
              Hapus permanen {{ selectedRows.length }}
            </button>
          </template>
          <button
            v-else-if="selectedRows.length"
            type="button"
            class="btn-danger-outline"
            @click="handleBulkDelete"
          >
            <Trash2 class="size-4" />
            Hapus {{ selectedRows.length }} terpilih
          </button>
        </template>

        <AppTableColumn prop="name" label="Nama Pelanggan" sortable min-width="220" fixed="left" />
        <AppTableColumn prop="branch.code" label="Cabang" sortable width="100" />

        <template v-if="isTrashedView">
          <AppTableColumn prop="deleted_at" label="Dihapus Pada" sortable width="160">
            <template #default="{ row }">{{ formatDate(row.deleted_at) }}</template>
          </AppTableColumn>
          <AppTableColumn prop="employees_count" label="Pegawai" sortable width="90">
            <template #default="{ row }">{{ row.employees_count ?? 0 }}</template>
          </AppTableColumn>
        </template>

        <template v-else>
          <AppTableColumn prop="employees_count" label="Pegawai" sortable width="85">
            <template #default="{ row }">
              <button
                v-if="(row.employees_count ?? 0) > 0"
                type="button"
                class="font-medium text-brand-600 hover:underline"
                title="Lihat pegawai di menu Pegawai"
                @click="openPegawaiList(row)"
              >
                {{ row.employees_count ?? 0 }}
              </button>
              <span v-else class="text-slate-400">0</span>
            </template>
          </AppTableColumn>
        </template>

        <AppTableColumn label="Aksi" :width="isTrashedView ? 100 : (canManage || canUpdate ? 150 : 80)" :sortable="false">
          <template #default="{ row }">
            <div class="flex justify-end gap-1">
              <template v-if="isTrashedView">
                <button
                  v-if="canManage"
                  type="button"
                  class="btn-icon-success"
                  title="Pulihkan"
                  @click="handleRestore(row)"
                >
                  <ArchiveRestore class="size-4" />
                </button>
                <button
                  v-if="canManage"
                  type="button"
                  class="btn-icon-danger"
                  title="Hapus permanen"
                  @click="handleForceDelete(row)"
                >
                  <Trash2 class="size-4" />
                </button>
              </template>
              <template v-else>
                <button
                  type="button"
                  class="btn-icon-brand inline-flex items-center gap-1 px-2 py-1 text-sm"
                  title="Lihat detail"
                  @click="openDetail(row)"
                >
                  <Eye class="size-4" />
                </button>
                <button
                  v-if="canUpdate"
                  type="button"
                  class="btn-icon-neutral"
                  title="Edit corporate"
                  @click="openEditCorporate(row)"
                >
                  <Pencil class="size-4" />
                </button>
                <button
                  v-if="canManage"
                  type="button"
                  class="btn-icon-danger"
                  title="Hapus ke sampah"
                  @click="handleDelete(row)"
                >
                  <Archive class="size-4" />
                </button>
              </template>
            </div>
          </template>
        </AppTableColumn>
      </AppDataTable>
  </div>
</template>
