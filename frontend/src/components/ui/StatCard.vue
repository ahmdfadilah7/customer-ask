<script setup>
defineProps({
  title: { type: String, required: true },
  value: { type: [String, Number], default: '—' },
  subtitle: { type: String, default: '' },
  icon: { type: Object, default: null },
  accent: { type: String, default: 'brand' },
})

const accents = {
  brand: 'from-brand-500 to-brand-700 shadow-brand-500/25',
  emerald: 'from-emerald-500 to-emerald-700 shadow-emerald-500/25',
  amber: 'from-amber-500 to-orange-600 shadow-amber-500/25',
  violet: 'from-violet-500 to-purple-700 shadow-violet-500/25',
}
</script>

<template>
  <div class="glass-panel group p-5 transition hover:-translate-y-0.5 hover:shadow-2xl">
    <div class="flex items-start justify-between gap-4">
      <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-slate-500">{{ title }}</p>
        <p class="mt-2 truncate text-3xl font-bold tracking-tight text-slate-900">{{ value }}</p>
        <p v-if="subtitle" class="mt-1 text-xs text-slate-400">{{ subtitle }}</p>
      </div>
      <div
        v-if="icon"
        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-white shadow-lg transition group-hover:scale-105"
        :class="accents[accent]"
      >
        <component :is="icon" class="size-6" />
      </div>
    </div>
  </div>
</template>
