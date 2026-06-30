<script setup>
import { computed } from 'vue'
import { AlertTriangle, LogOut } from '@lucide/vue'
import { useConfirm } from '@/composables/useConfirm'

const { state, accept, cancel } = useConfirm()

const variants = {
  danger: {
    icon: AlertTriangle,
    iconBg: 'bg-red-100 text-red-600',
    button: 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
  },
  warning: {
    icon: LogOut,
    iconBg: 'bg-amber-100 text-amber-600',
    button: 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500',
  },
}

const style = computed(() => variants[state.variant] ?? variants.danger)
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="state.open"
        class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
        @click.self="cancel"
      >
        <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
          <div class="flex items-start gap-4">
            <div
              class="flex size-11 shrink-0 items-center justify-center rounded-xl"
              :class="style.iconBg"
            >
              <component :is="style.icon" class="size-5" />
            </div>
            <div class="min-w-0 flex-1">
              <h3 class="text-lg font-bold text-slate-900">{{ state.title }}</h3>
              <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ state.message }}</p>
            </div>
          </div>

          <div class="mt-6 flex justify-end gap-2">
            <button type="button" class="btn-secondary" @click="cancel">
              {{ state.cancelLabel }}
            </button>
            <button
              type="button"
              class="inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition focus:outline-none focus:ring-2 focus:ring-offset-2"
              :class="style.button"
              @click="accept"
            >
              {{ state.confirmLabel }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
