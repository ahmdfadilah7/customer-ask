<script setup>
import { computed, ref, watch } from 'vue'
import AppModal from '@/components/ui/AppModal.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  groups: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  editingRole: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'submit'])

const form = ref(emptyForm())

const isEditing = computed(() => Boolean(props.editingRole))
const isSuperadminRole = computed(() => props.editingRole?.name === 'superadmin')

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return

    if (props.editingRole) {
      form.value = {
        name: props.editingRole.name,
        label: props.editingRole.label,
        description: props.editingRole.description ?? '',
        requires_branch: Boolean(props.editingRole.requires_branch),
        permissions: [...(props.editingRole.permissions ?? [])],
      }
    } else {
      form.value = emptyForm()
    }
  },
)

function emptyForm() {
  return {
    name: '',
    label: '',
    description: '',
    requires_branch: false,
    permissions: [],
  }
}

function isGroupChecked(group) {
  const names = group.permissions.map((p) => p.name)
  return names.length > 0 && names.every((name) => form.value.permissions.includes(name))
}

function isGroupIndeterminate(group) {
  const names = group.permissions.map((p) => p.name)
  const selected = names.filter((name) => form.value.permissions.includes(name))
  return selected.length > 0 && selected.length < names.length
}

function toggleGroup(group, checked) {
  const names = group.permissions.map((p) => p.name)
  const set = new Set(form.value.permissions)

  if (checked) {
    names.forEach((name) => set.add(name))
  } else {
    names.forEach((name) => set.delete(name))
  }

  form.value.permissions = [...set]
}

function togglePermission(name, checked) {
  const set = new Set(form.value.permissions)
  if (checked) set.add(name)
  else set.delete(name)
  form.value.permissions = [...set]
}

function close() {
  emit('update:modelValue', false)
}

function handleSubmit() {
  emit('submit', { ...form.value })
}
</script>

<template>
  <AppModal
    :model-value="modelValue"
    :title="isEditing ? 'Edit Role' : 'Tambah Role'"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <form class="space-y-5" @submit.prevent="handleSubmit">
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="form-label">Kode Role *</label>
          <input
            v-model="form.name"
            type="text"
            required
            class="input-field font-mono text-sm"
            :disabled="isSuperadminRole"
            placeholder="marketing"
            pattern="[a-z0-9_-]+"
          />
          <p class="form-hint">Huruf kecil, angka, strip, underscore. Contoh: marketing</p>
        </div>
        <div>
          <label class="form-label">Nama Tampilan *</label>
          <input v-model="form.label" type="text" required class="input-field" placeholder="Marketing" />
        </div>
      </div>

      <div>
        <label class="form-label">Deskripsi</label>
        <textarea v-model="form.description" rows="2" class="input-field resize-y" placeholder="Hak akses tim marketing..." />
      </div>

      <label class="flex items-center gap-2 text-sm text-slate-700">
        <input v-model="form.requires_branch" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
        Batasi akses per cabang (user wajib pilih cabang)
      </label>

      <div>
        <label class="form-label">Fitur & Permission *</label>
        <p class="form-hint mb-3">Pilih fitur yang dapat diakses role ini.</p>

        <div v-if="!groups.length" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
          Permission belum dimuat. Tutup modal lalu muat ulang halaman, atau jalankan
          <code class="text-xs">php artisan db:seed --class=PermissionSeeder</code>.
        </div>
        <div v-else class="max-h-[min(50vh,420px)] space-y-3 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50/50 p-3">
          <div
            v-for="group in groups"
            :key="group.key"
            class="rounded-xl bg-white p-4 ring-1 ring-slate-100"
          >
            <label class="flex cursor-pointer items-center gap-2 font-semibold text-slate-900">
              <input
                type="checkbox"
                class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                :checked="isGroupChecked(group)"
                :indeterminate="isGroupIndeterminate(group)"
                @change="toggleGroup(group, $event.target.checked)"
              />
              {{ group.label }}
            </label>

            <div class="mt-3 grid gap-2 sm:grid-cols-2">
              <label
                v-for="perm in group.permissions"
                :key="perm.name"
                class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-100 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
              >
                <input
                  type="checkbox"
                  class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                  :checked="form.permissions.includes(perm.name)"
                  @change="togglePermission(perm.name, $event.target.checked)"
                />
                <span class="min-w-0 flex-1">
                  <span class="block font-medium">{{ perm.label }}</span>
                  <span class="block font-mono text-[11px] text-slate-400">{{ perm.name }}</span>
                </span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end gap-2 border-t border-slate-100 pt-4">
        <button type="button" class="btn-secondary" @click="close">Batal</button>
        <button type="submit" class="btn-primary" :disabled="saving || form.permissions.length === 0">
          {{ saving ? 'Menyimpan...' : isEditing ? 'Simpan Perubahan' : 'Buat Role' }}
        </button>
      </div>
    </form>
  </AppModal>
</template>
