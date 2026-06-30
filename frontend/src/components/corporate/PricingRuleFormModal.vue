<script setup>
import { computed, ref, watch } from 'vue'
import { Check, Plane, Receipt, Search, X } from '@lucide/vue'
import AppModal from '@/components/ui/AppModal.vue'

const props = defineProps({
  reference: { type: Object, default: null },
  saving: { type: Boolean, default: false },
  rule: { type: Object, default: null },
  existingRules: { type: Array, default: () => [] },
})

const emit = defineEmits(['submit'])

const open = defineModel({ type: Boolean, default: false })

const activeSlotKey = ref('')
const activeRawValue = ref('')
const draftValues = ref({})
const activeCategory = ref('airline')
const search = ref('')
const showFilledOnly = ref(false)

const isEditing = computed(() => !!props.rule)
const modalTitle = computed(() => (isEditing.value ? 'Edit Service Fee' : 'Tambah Service Fee'))

const pricingSlots = computed(() => props.reference?.pricing_slots ?? [])

const existingBySlotKey = computed(() => {
  const map = new Map()
  for (const rule of props.existingRules) {
    const key = findSlotKeyForRule(rule)
    if (key) map.set(key, rule)
  }
  return map
})

const categoryCounts = computed(() => {
  let airline = 0
  let service = 0
  for (const slot of pricingSlots.value) {
    if (isAirlineSlot(slot)) airline += 1
    else service += 1
  }
  return { airline, service }
})

const filteredGroups = computed(() => {
  const term = search.value.trim().toLowerCase()
  const groups = new Map()

  for (const slot of pricingSlots.value) {
    const isAirline = isAirlineSlot(slot)
    if (activeCategory.value === 'airline' && !isAirline) continue
    if (activeCategory.value === 'service' && isAirline) continue

    if (showFilledOnly.value && !draftValues.value[slot.key]?.trim()) continue

    const haystack = `${slot.group} ${slot.label} ${slot.key}`.toLowerCase()
    if (term && !haystack.includes(term)) continue

    if (!groups.has(slot.group)) {
      groups.set(slot.group, { label: slot.group, slots: [] })
    }
    groups.get(slot.group).slots.push(slot)
  }

  return [...groups.values()].filter((group) => group.slots.length > 0)
})

const selectedSlot = computed(() =>
  pricingSlots.value.find((slot) => slot.key === activeSlotKey.value),
)

const draftEntries = computed(() =>
  pricingSlots.value
    .filter((slot) => (draftValues.value[slot.key] ?? '').trim())
    .map((slot) => ({
      slot,
      existing: existingBySlotKey.value.get(slot.key),
    })),
)

const draftCount = computed(() => draftEntries.value.length)

const canSubmit = computed(() => {
  if (isEditing.value) {
    return !!selectedSlot.value && activeRawValue.value.trim() !== ''
  }
  return draftCount.value > 0
})

function isAirlineSlot(slot) {
  return String(slot.key).startsWith('airline_')
}

function findSlotKeyForRule(rule) {
  if (!rule) return ''
  return pricingSlots.value.find(
    (slot) =>
      String(slot.service_category_id) === String(rule.service_category_id)
      && String(slot.region_scope_id) === String(rule.region_scope_id ?? '')
      && String(slot.airline_id ?? '') === String(rule.airline_id ?? ''),
  )?.key ?? ''
}

function normalizeValue(value) {
  return String(value ?? '').trim()
}

function persistActiveDraft() {
  if (!activeSlotKey.value) return
  const raw = normalizeValue(activeRawValue.value)
  const next = { ...draftValues.value }
  if (!raw) {
    delete next[activeSlotKey.value]
  } else {
    next[activeSlotKey.value] = raw
  }
  draftValues.value = next
}

function loadSlotIntoEditor(slotKey) {
  persistActiveDraft()

  activeSlotKey.value = slotKey
  const draft = draftValues.value[slotKey]
  if (draft) {
    activeRawValue.value = draft
    return
  }

  const existing = existingBySlotKey.value.get(slotKey)
  activeRawValue.value = existing?.raw_value ?? ''
}

function selectSlot(slot) {
  if (isEditing.value) return
  loadSlotIntoEditor(slot.key)
}

function removeDraft(slotKey) {
  const next = { ...draftValues.value }
  delete next[slotKey]
  draftValues.value = next
  if (activeSlotKey.value === slotKey) {
    activeRawValue.value = ''
  }
}

function slotDraftPreview(slotKey) {
  return draftValues.value[slotKey] ?? ''
}

function resetCreateState() {
  activeSlotKey.value = ''
  activeRawValue.value = ''
  draftValues.value = {}
  search.value = ''
  showFilledOnly.value = false
  activeCategory.value = 'airline'
}

watch(open, (visible) => {
  if (!visible) return

  if (props.rule) {
    const slotKey = findSlotKeyForRule(props.rule)
    resetCreateState()
    activeSlotKey.value = slotKey
    activeRawValue.value = props.rule.raw_value ?? ''
    activeCategory.value = slotKey.startsWith('airline_') ? 'airline' : 'service'
  } else {
    resetCreateState()
  }
})

watch(pricingSlots, () => {
  if (props.rule && open.value && !activeSlotKey.value) {
    activeSlotKey.value = findSlotKeyForRule(props.rule)
  }
})

watch(activeRawValue, () => {
  if (!isEditing.value) persistActiveDraft()
})

function buildSubmitItems() {
  if (isEditing.value) {
    const slot = selectedSlot.value
    if (!slot) return []
    const rawValue = normalizeValue(activeRawValue.value)
    if (!rawValue) return []

    return [{
      service_category_id: slot.service_category_id,
      region_scope_id: slot.region_scope_id,
      airline_id: slot.airline_id,
      raw_value: rawValue,
      existing_rule_id: props.rule?.id ?? null,
    }]
  }

  persistActiveDraft()

  return draftEntries.value.map(({ slot, existing }) => ({
    service_category_id: slot.service_category_id,
    region_scope_id: slot.region_scope_id,
    airline_id: slot.airline_id,
    raw_value: draftValues.value[slot.key],
    existing_rule_id: existing?.id ?? null,
  }))
}

function handleSubmit() {
  const items = buildSubmitItems()
  if (!items.length) return
  emit('submit', { items })
}
</script>

<template>
  <AppModal v-model="open" :title="modalTitle" max-width="max-w-5xl">
    <div v-if="!isEditing" class="mb-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-slate-100">
      <p><span class="font-medium text-slate-800">Langkah 1:</span> Pilih kolom di kiri.</p>
      <p class="mt-1"><span class="font-medium text-slate-800">Langkah 2:</span> Isi nilai di kanan, lalu pilih kolom lain. Semua tarif terisi disimpan sekaligus.</p>
    </div>

    <form id="pricing-rule-form" @submit.prevent="handleSubmit">
      <template v-if="isEditing">
        <div
          v-if="selectedSlot"
          class="mb-4 rounded-xl border border-brand-100 bg-brand-50/50 p-4"
        >
          <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Kolom</p>
          <p class="mt-1 font-semibold text-slate-900">{{ selectedSlot.group }} — {{ selectedSlot.label }}</p>
        </div>

        <div class="space-y-2 rounded-xl border border-slate-200 bg-white p-4">
          <label class="form-label">Nilai Tarif *</label>
          <input
            v-model="activeRawValue"
            type="text"
            maxlength="255"
            class="input-field"
            placeholder="Contoh: 50.000 atau 5%"
            required
          />
          <p class="form-hint">Isi sesuai format template import (teks bebas).</p>
        </div>
      </template>

      <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
        <div class="flex min-h-[min(58vh,480px)] flex-col rounded-xl border border-slate-200 bg-slate-50/50">
          <div class="space-y-3 border-b border-slate-200 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">1 · Pilih kolom</p>
            <div class="segment-control w-full">
              <button
                type="button"
                class="segment-control__btn flex flex-1 items-center justify-center gap-1.5 text-xs"
                :class="activeCategory === 'airline' ? 'segment-control__btn--active' : 'segment-control__btn--inactive'"
                @click="activeCategory = 'airline'"
              >
                <Plane class="size-3.5" />
                Maskapai ({{ categoryCounts.airline }})
              </button>
              <button
                type="button"
                class="segment-control__btn flex flex-1 items-center justify-center gap-1.5 text-xs"
                :class="activeCategory === 'service' ? 'segment-control__btn--active' : 'segment-control__btn--inactive'"
                @click="activeCategory = 'service'"
              >
                <Receipt class="size-3.5" />
                Layanan ({{ categoryCounts.service }})
              </button>
            </div>
            <div class="relative">
              <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
              <input v-model="search" type="search" class="input-field pl-9 text-sm" placeholder="Cari..." />
            </div>
            <label class="flex items-center gap-2 text-xs text-slate-600">
              <input v-model="showFilledOnly" type="checkbox" class="rounded border-slate-300 text-brand-600" />
              Hanya kolom yang sudah diisi
            </label>
          </div>

          <div class="flex-1 overflow-y-auto p-2">
            <div v-if="!filteredGroups.length" class="p-6 text-center text-sm text-slate-500">
              Tidak ada kolom ditemukan.
            </div>
            <div v-for="group in filteredGroups" :key="group.label" class="mb-3">
              <p class="px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ group.label }}</p>
              <div class="space-y-0.5">
                <button
                  v-for="slot in group.slots"
                  :key="slot.key"
                  type="button"
                  class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm transition"
                  :class="activeSlotKey === slot.key
                    ? 'bg-brand-100 text-brand-900 ring-1 ring-brand-200'
                    : 'hover:bg-white'"
                  @click="selectSlot(slot)"
                >
                  <span
                    class="flex size-5 shrink-0 items-center justify-center rounded-full border"
                    :class="slotDraftPreview(slot.key)
                      ? 'border-emerald-400 bg-emerald-50 text-emerald-600'
                      : activeSlotKey === slot.key
                        ? 'border-brand-400 bg-brand-50 text-brand-600'
                        : 'border-slate-200 bg-white text-transparent'"
                  >
                    <Check class="size-3" />
                  </span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate font-medium">{{ slot.label }}</span>
                    <span v-if="slotDraftPreview(slot.key)" class="block truncate text-xs text-emerald-700">
                      {{ slotDraftPreview(slot.key) }}
                    </span>
                    <span v-else-if="existingBySlotKey.has(slot.key)" class="block truncate text-xs text-amber-600">
                      Ada: {{ existingBySlotKey.get(slot.key)?.raw_value }}
                    </span>
                  </span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="flex min-h-[min(58vh,480px)] flex-col gap-4">
          <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">2 · Kolom terpilih</p>
            <template v-if="selectedSlot">
              <p class="mt-2 font-semibold text-slate-900">{{ selectedSlot.group }}</p>
              <p class="text-sm text-slate-700">{{ selectedSlot.label }}</p>
              <p class="mt-1 font-mono text-[11px] text-slate-400">{{ selectedSlot.key }}</p>
            </template>
            <p v-else class="mt-2 text-sm text-slate-500">Belum ada kolom dipilih — klik daftar di kiri.</p>
          </div>

          <div
            class="flex flex-1 flex-col rounded-xl border border-slate-200 bg-white p-4"
            :class="!selectedSlot ? 'opacity-60' : ''"
          >
            <label class="form-label">Nilai tarif</label>
            <p v-if="!selectedSlot" class="text-sm text-slate-500">Pilih kolom terlebih dahulu.</p>
            <template v-else>
              <input
                v-model="activeRawValue"
                type="text"
                maxlength="255"
                class="input-field"
                placeholder="Contoh: 50.000 atau 5%"
              />
              <p class="form-hint mt-2">Nilai tersimpan otomatis saat Anda pindah ke kolom lain.</p>
            </template>
          </div>

          <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Siap disimpan</p>
              <span class="rounded-full bg-brand-100 px-2 py-0.5 text-xs font-semibold text-brand-700">
                {{ draftCount }} tarif
              </span>
            </div>

            <p v-if="!draftCount" class="text-sm text-slate-500">
              Belum ada tarif. Isi nilai minimal pada satu kolom.
            </p>

            <ul v-else class="max-h-36 space-y-1.5 overflow-y-auto">
              <li
                v-for="{ slot, existing } in draftEntries"
                :key="slot.key"
                class="flex items-center gap-2 rounded-lg bg-white px-2.5 py-1.5 text-sm ring-1 ring-slate-100"
              >
                <button
                  type="button"
                  class="min-w-0 flex-1 text-left"
                  @click="loadSlotIntoEditor(slot.key)"
                >
                  <span class="block truncate font-medium text-slate-800">{{ slot.group }} · {{ slot.label }}</span>
                  <span class="block truncate text-xs text-emerald-700">{{ slotDraftPreview(slot.key) }}</span>
                  <span v-if="existing" class="text-[10px] text-amber-600">Mengganti {{ existing.raw_value }}</span>
                </button>
                <button
                  type="button"
                  class="btn-icon-neutral !p-1"
                  title="Hapus dari daftar"
                  @click="removeDraft(slot.key)"
                >
                  <X class="size-3.5" />
                </button>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </form>

    <template #footer>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p v-if="!isEditing && draftCount" class="text-sm text-slate-600">
          {{ draftCount }} tarif akan disimpan sekaligus.
        </p>
        <p v-else class="hidden text-sm text-slate-500 sm:block" />

        <div class="flex flex-col-reverse gap-2 sm:flex-row">
          <button type="button" class="btn-secondary" @click="open = false">Batal</button>
          <button
            type="submit"
            form="pricing-rule-form"
            class="btn-primary"
            :disabled="saving || !canSubmit"
          >
            <template v-if="saving">Menyimpan...</template>
            <template v-else-if="isEditing">Simpan Perubahan</template>
            <template v-else-if="draftCount > 1">Simpan {{ draftCount }} Tarif</template>
            <template v-else>Simpan Tarif</template>
          </button>
        </div>
      </div>
    </template>
  </AppModal>
</template>
