<script setup>
import { ref, watch } from 'vue'
import { formatRupiahValue } from '@/utils/currencyInput'

const model = defineModel({ type: String, default: '' })

const props = defineProps({
  placeholder: { type: String, default: '0' },
  disabled: { type: Boolean, default: false },
  maxlength: { type: [String, Number], default: 255 },
})

const display = ref('')

watch(
  () => model.value,
  (value) => {
    display.value = value ? formatRupiahValue(value) : ''
  },
  { immediate: true },
)

function onInput(event) {
  const formatted = formatRupiahValue(event.target.value)
  display.value = formatted
  model.value = formatted
  event.target.value = formatted
}
</script>

<template>
  <div class="relative">
    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">Rp</span>
    <input
      :value="display"
      type="text"
      inputmode="numeric"
      class="input-field pl-10"
      :placeholder="placeholder"
      :disabled="disabled"
      :maxlength="maxlength"
      @input="onInput"
    />
  </div>
</template>
