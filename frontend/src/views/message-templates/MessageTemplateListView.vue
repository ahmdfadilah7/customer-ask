<script setup>
import { onMounted, ref } from 'vue'
import { MessageSquare, Plus, Pencil, Trash2 } from '@lucide/vue'
import {
  createMessageTemplate,
  deleteMessageTemplate,
  fetchMessagePlaceholders,
  fetchMessageTemplates,
  updateMessageTemplate,
} from '@/api/messageTemplates'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import AppModal from '@/components/ui/AppModal.vue'
import AppDataTable from '@/components/ui/AppDataTable.vue'
import AppTableColumn from '@/components/ui/AppTableColumn.vue'

const auth = useAuthStore()
const toast = useToast()
const confirm = useConfirm()

const items = ref([])
const placeholders = ref([])
const loading = ref(true)
const showModal = ref(false)
const saving = ref(false)
const editingItem = ref(null)

const emptyForm = () => ({ name: '', body: '', description: '', is_active: true })
const form = ref(emptyForm())

const canManage = () => auth.canManage('template-pesan')

onMounted(loadData)

async function loadData() {
  loading.value = true
  try {
    const [templatesRes, placeholdersRes] = await Promise.all([
      fetchMessageTemplates(),
      fetchMessagePlaceholders(),
    ])
    items.value = templatesRes.data.data ?? []
    placeholders.value = placeholdersRes.data.data ?? []
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
    name: item.name,
    body: item.body,
    description: item.description ?? '',
    is_active: item.is_active,
  }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

function insertPlaceholder(key) {
  form.value.body = `${form.value.body}${key}`
}

async function handleSubmit() {
  saving.value = true
  try {
    if (editingItem.value) {
      await updateMessageTemplate(editingItem.value.id, form.value)
      toast.success('Template pesan berhasil diperbarui.')
    } else {
      await createMessageTemplate(form.value)
      toast.success('Template pesan berhasil dibuat.')
    }
    closeModal()
    await loadData()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal menyimpan template.')
  } finally {
    saving.value = false
  }
}

async function handleDelete(item) {
  const confirmed = await confirm.confirm({
    title: 'Hapus Template',
    message: `Hapus template "${item.name}"? Tindakan ini tidak dapat dibatalkan.`,
    confirmLabel: 'Ya, Hapus',
    variant: 'danger',
  })

  if (!confirmed) return

  try {
    await deleteMessageTemplate(item.id)
    toast.success('Template berhasil dihapus.')
    await loadData()
  } catch (err) {
    toast.error(err.response?.data?.message || 'Gagal menghapus template.')
  }
}
</script>

<template>
  <div class="page-shell">
    <PageHeader
      :icon="MessageSquare"
      title="Template Pesan WhatsApp"
      description="Buat template pesan dengan placeholder dinamis untuk dikirim ke pegawai corporate via Fonnte."
    />

    <LoadingSpinner v-if="loading" label="Memuat template..." />

    <AppDataTable
      v-else
      :data="items"
      search-placeholder="Cari nama template..."
      empty-text="Belum ada template pesan."
    >
      <template #toolbar>
        <button v-if="canManage()" type="button" class="btn-primary" @click="openCreate">
          <Plus class="size-4" />
          Tambah Template
        </button>
      </template>

      <AppTableColumn prop="name" label="Nama Template" min-width="180" fixed="left">
        <template #default="{ row }">
          <span class="font-medium text-slate-900">{{ row.name }}</span>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="body" label="Isi Pesan" min-width="280" :sortable="false">
        <template #default="{ row }">
          <p class="line-clamp-2 whitespace-pre-wrap text-sm text-slate-600">{{ row.body }}</p>
        </template>
      </AppTableColumn>
      <AppTableColumn prop="is_active" label="Status" width="110">
        <template #default="{ row }">
          <span :class="row.is_active ? 'badge-success' : 'badge-neutral'">
            {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
          </span>
        </template>
      </AppTableColumn>
      <AppTableColumn
        v-if="canManage()"
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

    <div v-if="!loading && placeholders.length" class="glass-panel p-5 sm:p-6">
      <h4 class="section-title mb-3">
        <MessageSquare class="section-icon" />
        Placeholder yang Tersedia
      </h4>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="item in placeholders"
          :key="item.key"
          type="button"
          class="badge-brand cursor-default"
          :title="item.label"
        >
          {{ item.key }}
        </button>
      </div>
      <p class="form-hint mt-3">
        Contoh: <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">Halo {name}, corporate Anda {corporate} ({cabang}).</code>
      </p>
    </div>

    <AppModal v-model="showModal" :title="editingItem ? 'Edit Template' : 'Tambah Template'" max-width="max-w-2xl">
      <form id="message-template-form" class="space-y-4" @submit.prevent="handleSubmit">
        <div>
          <label class="form-label">Nama Template</label>
          <input v-model="form.name" type="text" required maxlength="255" class="input-field" placeholder="Reminder Passport" />
        </div>
        <div>
          <label class="form-label">Deskripsi (opsional)</label>
          <input v-model="form.description" type="text" maxlength="500" class="input-field" placeholder="Pengingat masa berlaku passport" />
        </div>
        <div>
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <label class="form-label !mb-0">Isi Pesan</label>
            <div class="flex flex-wrap gap-1">
              <button
                v-for="item in placeholders"
                :key="item.key"
                type="button"
                class="rounded-lg bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 hover:bg-brand-100"
                :title="item.label"
                @click="insertPlaceholder(item.key)"
              >
                {{ item.key }}
              </button>
            </div>
          </div>
          <textarea
            v-model="form.body"
            required
            rows="8"
            maxlength="5000"
            class="input-field min-h-[160px] resize-y"
            placeholder="Halo {name},&#10;&#10;Kami dari {corporate} ingin menginformasikan..."
          />
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
          Template aktif (bisa dipilih saat kirim pesan)
        </label>
      </form>

      <template #footer>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" @click="closeModal">Batal</button>
          <button type="submit" form="message-template-form" class="btn-primary" :disabled="saving">
            {{ saving ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </template>
    </AppModal>
  </div>
</template>
