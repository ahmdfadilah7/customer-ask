<script setup>
import { ref } from 'vue'
import { Upload, FileSpreadsheet, X } from '@lucide/vue'

const props = defineProps({
  accept: { type: String, default: '.csv,.xlsx,.xls' },
  label: { type: String, default: 'Seret file ke sini atau klik untuk memilih' },
})

const model = defineModel({ type: Object, default: null })
const isDragging = ref(false)
const inputRef = ref(null)

function handleFiles(files) {
  const file = files?.[0]
  if (file) {
    model.value = file
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
  model.value = null
  if (inputRef.value) {
    inputRef.value.value = ''
  }
}
</script>

<template>
  <div
    class="relative rounded-xl border-2 border-dashed transition"
    :class="model ? 'border-brand-400 bg-brand-50/50' : isDragging ? 'border-brand-500 bg-brand-50' : 'border-slate-200 bg-slate-50/50 hover:border-brand-300 hover:bg-brand-50/30'"
    @dragover.prevent="isDragging = true"
    @dragleave="isDragging = false"
    @drop.prevent="onDrop"
  >
    <input
      ref="inputRef"
      type="file"
      class="absolute inset-0 z-10 cursor-pointer opacity-0"
      :accept="accept"
      @change="onChange"
    />

    <div v-if="model" class="flex items-center gap-3 p-4">
      <div class="flex size-10 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
        <FileSpreadsheet class="size-5" />
      </div>
      <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-medium text-slate-900">{{ model.name }}</p>
        <p class="text-xs text-slate-500">{{ (model.size / 1024).toFixed(1) }} KB</p>
      </div>
      <button
        type="button"
        class="relative z-20 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-200 hover:text-slate-600"
        @click.stop="clear"
      >
        <X class="size-4" />
      </button>
    </div>

    <div v-else class="flex flex-col items-center gap-2 px-4 py-8 text-center">
      <div class="flex size-12 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <Upload class="size-5 text-brand-500" />
      </div>
      <p class="text-sm font-medium text-slate-700">{{ label }}</p>
      <p class="text-xs text-slate-400">CSV, XLS, XLSX</p>
    </div>
  </div>
</template>
