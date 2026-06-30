<script setup>
import { Info } from '@lucide/vue'

defineProps({
  title: { type: String, default: '' },
})
</script>

<template>
  <div class="info-card flex gap-3 text-sm text-slate-600">
    <Info class="mt-0.5 size-5 shrink-0 text-brand-500" />
    <div class="min-w-0">
      <p v-if="title" class="font-semibold text-slate-800">{{ title }}</p>
      <p class="leading-relaxed" :class="title ? 'mt-1' : ''">
        <slot />
      </p>
    </div>
  </div>
</template>
