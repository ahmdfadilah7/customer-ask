<script setup>
import { onUnmounted, watch } from 'vue'
import { X } from '@lucide/vue'

defineProps({
  title: { type: String, required: true },
  maxWidth: { type: String, default: 'max-w-lg' },
})

const open = defineModel({ type: Boolean, default: false })

function close() {
  open.value = false
}

function lockBodyScroll() {
  document.body.style.overflow = 'hidden'
}

function unlockBodyScroll() {
  document.body.style.overflow = ''
}

watch(open, (visible) => {
  if (visible) {
    lockBodyScroll()
  } else {
    unlockBodyScroll()
  }
})

onUnmounted(unlockBodyScroll)
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
      <div v-if="open" class="modal-overlay" @click.self="close">
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div v-if="open" class="modal-panel" :class="maxWidth" role="dialog" aria-modal="true">
            <div class="modal-header">
              <h3 class="modal-title">{{ title }}</h3>
              <button type="button" class="btn-icon-neutral" @click="close">
                <X class="size-5" />
              </button>
            </div>
            <div class="modal-body">
              <slot />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
