import { reactive } from 'vue'

const state = reactive({
  open: false,
  title: 'Konfirmasi',
  message: 'Apakah Anda yakin?',
  confirmLabel: 'Ya, Lanjutkan',
  cancelLabel: 'Batal',
  variant: 'danger',
  resolve: null,
})

export function useConfirm() {
  function confirm(options = {}) {
    return new Promise((resolve) => {
      state.open = true
      state.title = options.title ?? 'Konfirmasi'
      state.message = options.message ?? 'Apakah Anda yakin?'
      state.confirmLabel = options.confirmLabel ?? 'Ya, Lanjutkan'
      state.cancelLabel = options.cancelLabel ?? 'Batal'
      state.variant = options.variant ?? 'danger'
      state.resolve = resolve
    })
  }

  function accept() {
    state.resolve?.(true)
    close()
  }

  function cancel() {
    state.resolve?.(false)
    close()
  }

  function close() {
    state.open = false
    state.resolve = null
  }

  return { state, confirm, accept, cancel }
}
