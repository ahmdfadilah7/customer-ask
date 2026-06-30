<script setup>
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import { computed } from 'vue'

const props = defineProps({
  page: { type: Number, required: true },
  perPage: { type: Number, default: 15 },
  total: { type: Number, default: 0 },
})

const emit = defineEmits(['update:page'])

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)))
const from = computed(() => (props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1))
const to = computed(() => Math.min(props.page * props.perPage, props.total))

function goTo(page) {
  if (page >= 1 && page <= totalPages.value) {
    emit('update:page', page)
  }
}
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-4 pt-4">
    <p class="text-sm text-slate-500">
      Menampilkan <span class="font-medium text-slate-700">{{ from }}–{{ to }}</span>
      dari <span class="font-medium text-slate-700">{{ total }}</span> data
    </p>
    <div class="flex items-center gap-1">
      <button
        type="button"
        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="page <= 1"
        @click="goTo(page - 1)"
      >
        <ChevronLeft class="size-4" />
      </button>
      <span class="min-w-[80px] px-3 text-center text-sm font-medium text-slate-700">
        {{ page }} / {{ totalPages }}
      </span>
      <button
        type="button"
        class="inline-flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
        :disabled="page >= totalPages"
        @click="goTo(page + 1)"
      >
        <ChevronRight class="size-4" />
      </button>
    </div>
  </div>
</template>
