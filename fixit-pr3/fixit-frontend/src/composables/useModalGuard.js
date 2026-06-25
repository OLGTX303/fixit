import { onMounted, onUnmounted, watch, isRef, unref } from 'vue'

let openCount = 0

function sync() {
  if (openCount > 0) document.body.classList.add('fx-modal-open')
  else document.body.classList.remove('fx-modal-open')
}

/**
 * Hide mobile bottom nav while overlays are open. Pass a ref for conditional
 * modals, or omit for always-on overlays (full-page sheets).
 */
export function useModalGuard(active = true) {
  if (isRef(active)) {
    watch(active, (open, wasOpen) => {
      if (open && !wasOpen) { openCount++; sync() }
      else if (!open && wasOpen) { openCount = Math.max(0, openCount - 1); sync() }
    }, { immediate: true })
    onUnmounted(() => {
      if (unref(active)) {
        openCount = Math.max(0, openCount - 1)
        sync()
      }
    })
    return
  }

  onMounted(() => { openCount++; sync() })
  onUnmounted(() => {
    openCount = Math.max(0, openCount - 1)
    sync()
  })
}