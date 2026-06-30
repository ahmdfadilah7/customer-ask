<script setup>
defineProps({
  size: { type: String, default: 'md' },
  label: { type: String, default: 'Memuat...' },
  panel: { type: Boolean, default: true },
})

const sizes = {
  sm: 'size-5 border-2',
  md: 'size-8 border-[3px]',
  lg: 'size-12 border-4',
}
</script>

<template>
  <div :class="panel ? 'glass-panel flex flex-col items-center justify-center gap-3 py-16' : 'flex flex-col items-center justify-center gap-3 py-12'">
    <div
      class="animate-spin rounded-full border-brand-200 border-t-brand-600"
      :class="sizes[size]"
    />
    <p v-if="label" class="text-sm font-medium text-slate-500">{{ label }}</p>
  </div>
</template>
