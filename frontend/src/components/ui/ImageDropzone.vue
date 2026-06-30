<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Upload, X } from '@lucide/vue'

const props = defineProps({
  accept: { type: String, default: '.png,.jpg,.jpeg,.gif,.webp,.svg' },
  label: { type: String, default: 'Seret gambar ke sini atau klik untuk memilih' },
  hint: { type: String, default: 'PNG, JPG, WEBP, SVG' },
  disabled: { type: Boolean, default: false },
  currentUrl: { type: String, default: '' },
})

const model = defineModel({ type: Object, default: null })
const emit = defineEmits(['remove-current'])

const isDragging = ref(false)
const inputRef = ref(null)
const removedCurrent = ref(false)
const objectUrl = ref('')

const previewUrl = computed(() => {
  if (objectUrl.value) return objectUrl.value
  if (!removedCurrent.value && props.currentUrl) return props.currentUrl
  return ''
})

const hasPreview = computed(() => Boolean(previewUrl.value))

watch(
  () => model.value,
  (file, _, onCleanup) => {
    if (objectUrl.value) {
      URL.revokeObjectURL(objectUrl.value)
      objectUrl.value = ''
    }
    if (file) {
      objectUrl.value = URL.createObjectURL(file)
    }
    onCleanup?.(() => {
      if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value)
        objectUrl.value = ''
      }
    })
  },
)

watch(
  () => props.currentUrl,
  () => {
    removedCurrent.value = false
  },
)

onBeforeUnmount(() => {
  if (objectUrl.value) {
    URL.revokeObjectURL(objectUrl.value)
  }
})

function handleFiles(files) {
  if (props.disabled) return
  const file = files?.[0]
  if (file) {
    model.value = file
    removedCurrent.value = false
    emit('remove-current', false)
  }
}

function onDrop(e) {
  isDragging.value = false
  handleFiles(e.dataTransfer?.files)
}

function onChange(e) {
  handleFiles(e.target.files)
}

function clear() {
  if (model.value) {
    model.value = null
    if (inputRef.value) {
      inputRef.value.value = ''
    }
    return
  }

  if (props.currentUrl && !removedCurrent.value) {
    removedCurrent.value = true
    emit('remove-current', true)
  }
}

function openPicker() {
  if (!props.disabled) {
    inputRef.value?.click()
  }
}
</script>

<template>
  <div
    class="relative overflow-hidden rounded-xl border-2 border-dashed transition"
    :class="[
      hasPreview ? 'border-brand-400 bg-brand-50/50' : isDragging ? 'border-brand-500 bg-brand-50' : 'border-slate-200 bg-slate-50/50 hover:border-brand-300 hover:bg-brand-50/30',
      disabled ? 'cursor-not-allowed opacity-60' : '',
    ]"
    @dragover.prevent="!disabled && (isDragging = true)"
    @dragleave="isDragging = false"
    @drop.prevent="onDrop"
  >
    <input
      ref="inputRef"
      type="file"
      class="hidden"
      :accept="accept"
      :disabled="disabled"
      @change="onChange"
    />

    <div v-if="hasPreview" class="flex items-center gap-3 p-4">
      <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white ring-1 ring-slate-200">
        <img :src="previewUrl" alt="Preview" class="max-h-12 max-w-12 object-contain" />
      </div>
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-slate-900">
          {{ model?.name || 'Gambar tersimpan' }}
        </p>
        <p class="text-xs text-slate-500">
          <template v-if="model">{{ (model.size / 1024).toFixed(1) }} KB — akan diunggah saat simpan</template>
          <template v-else>Klik area ini untuk mengganti file</template>
        </p>
      </div>
      <button
        v-if="!disabled"
        type="button"
        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-200 hover:text-slate-600"
        @click.stop="clear"
      >
        <X class="size-4" />
      </button>
    </div>

    <button
      v-else
      type="button"
      class="flex w-full flex-col items-center gap-2 px-4 py-8 text-center"
      :disabled="disabled"
      @click="openPicker"
    >
      <div class="flex size-12 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <Upload class="size-5 text-brand-500" />
      </div>
      <p class="text-sm font-medium text-slate-700">{{ label }}</p>
      <p class="text-xs text-slate-400">{{ hint }}</p>
    </button>

    <button
      v-if="hasPreview && !disabled"
      type="button"
      class="absolute inset-0 z-10 cursor-pointer opacity-0"
      aria-label="Ganti gambar"
      @click="openPicker"
    />
  </div>
</template>
