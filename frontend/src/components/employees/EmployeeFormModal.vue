<script setup>
import { computed, ref, watch } from 'vue'
import AppModal from '@/components/ui/AppModal.vue'
import { TERMS } from '@/constants/terminology'

const props = defineProps({
  customers: { type: Array, default: () => [] },
  titles: { type: Array, default: () => [] },
  nationalities: { type: Array, default: () => [] },
  saving: { type: Boolean, default: false },
  employee: { type: Object, default: null },
  defaultCustomerId: { type: [String, Number], default: '' },
})

const emit = defineEmits(['submit'])

const open = defineModel({ type: Boolean, default: false })

const emptyForm = () => ({
  customer_id: '',
  title_id: '',
  nationality_id: '',
  full_name: '',
  passport_number: '',
  passport_expiry: '',
  ktp_number: '',
  birthdate: '',
  mobile: '',
  email: '',
  ticket_name_format: '',
  status: 'active',
})

const form = ref(emptyForm())

const isEditing = computed(() => !!props.employee)
const modalTitle = computed(() => (isEditing.value ? 'Edit Pegawai' : 'Tambah Pegawai'))

watch(open, (visible) => {
  if (!visible) return

  if (props.employee) {
    form.value = {
      customer_id: props.employee.customer_id ?? props.employee.customer?.id ?? '',
      title_id: props.employee.title_id ?? '',
      nationality_id: props.employee.nationality_id ?? '',
      full_name: props.employee.full_name ?? '',
      passport_number: props.employee.passport_number ?? '',
      passport_expiry: props.employee.passport_expiry ?? '',
      ktp_number: props.employee.ktp_number ?? '',
      birthdate: props.employee.birthdate ?? '',
      mobile: props.employee.mobile ?? '',
      email: props.employee.email ?? '',
      ticket_name_format: props.employee.ticket_name_format ?? '',
      status: props.employee.status ?? 'active',
    }
  } else {
    form.value = {
      ...emptyForm(),
      customer_id: props.defaultCustomerId || '',
    }
  }
})

function handleSubmit() {
  const payload = { ...form.value }
  if (!payload.title_id) payload.title_id = null
  if (!payload.nationality_id) payload.nationality_id = null
  emit('submit', payload)
}
</script>

<template>
  <AppModal v-model="open" :title="modalTitle" max-width="max-w-2xl">
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
          <label class="form-label">{{ TERMS.corporate.label }} (Pelanggan) *</label>
          <select v-model="form.customer_id" class="input-field" required>
            <option value="">— Pilih pelanggan —</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">
              {{ c.name }} ({{ c.branch?.code ?? '—' }})
            </option>
          </select>
        </div>

        <div class="sm:col-span-2">
          <label class="form-label">Nama Lengkap *</label>
          <input v-model="form.full_name" type="text" required maxlength="255" class="input-field" />
        </div>

        <div>
          <label class="form-label">Title</label>
          <select v-model="form.title_id" class="input-field">
            <option value="">— Pilih title —</option>
            <option v-for="t in titles" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>

        <div>
          <label class="form-label">Nationality</label>
          <select v-model="form.nationality_id" class="input-field">
            <option value="">— Pilih nationality —</option>
            <option v-for="n in nationalities" :key="n.id" :value="n.id">{{ n.name }}</option>
          </select>
        </div>

        <div>
          <label class="form-label">Nomor Passport</label>
          <input v-model="form.passport_number" type="text" maxlength="50" class="input-field" />
        </div>

        <div>
          <label class="form-label">Exp. Passport</label>
          <input v-model="form.passport_expiry" type="date" class="input-field" />
        </div>

        <div>
          <label class="form-label">Nomor KTP</label>
          <input v-model="form.ktp_number" type="text" maxlength="20" class="input-field" />
        </div>

        <div>
          <label class="form-label">Tanggal Lahir</label>
          <input v-model="form.birthdate" type="date" class="input-field" />
        </div>

        <div>
          <label class="form-label">Mobile</label>
          <input v-model="form.mobile" type="text" maxlength="30" class="input-field" placeholder="08xxxxxxxxxx" />
        </div>

        <div>
          <label class="form-label">Email</label>
          <input v-model="form.email" type="email" maxlength="150" class="input-field" />
        </div>

        <div class="sm:col-span-2">
          <label class="form-label">Format Nama Tiket</label>
          <textarea
            v-model="form.ticket_name_format"
            rows="2"
            maxlength="255"
            class="input-field resize-y"
            placeholder="SURNAME/GIVEN NAME"
          />
        </div>

        <div>
          <label class="form-label">Status</label>
          <select v-model="form.status" class="input-field">
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
          </select>
        </div>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button type="button" class="btn-secondary" @click="open = false">Batal</button>
        <button type="submit" class="btn-primary" :disabled="saving">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </button>
      </div>
    </form>
  </AppModal>
</template>
