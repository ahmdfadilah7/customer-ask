<script setup>
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
})
</script>

<template>
  <div class="detail-hero">
    <div class="detail-hero__accent" />
    <div class="detail-hero__body">
      <slot name="leading" />
      <div v-if="title || subtitle" class="min-w-0 flex-1">
        <h2 v-if="title" class="page-title">{{ title }}</h2>
        <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
        <slot name="meta" />
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 sm:ml-auto">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
