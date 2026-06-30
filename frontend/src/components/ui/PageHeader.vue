<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
  icon: { type: Object, default: null },
})
</script>

<template>
  <div class="page-header">
    <div class="page-header__main">
      <div v-if="icon" class="page-header__icon">
        <component :is="icon" />
      </div>
      <div class="min-w-0">
        <h2 class="page-title">{{ title }}</h2>
        <p v-if="description" class="page-desc">{{ description }}</p>
      </div>
    </div>
    <div v-if="$slots.actions" class="page-header__actions">
      <slot name="actions" />
    </div>
    <div v-if="$slots.default" class="w-full">
      <slot />
    </div>
  </div>
</template>
