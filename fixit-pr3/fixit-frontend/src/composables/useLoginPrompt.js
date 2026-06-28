import { ref } from 'vue'
import { isDesktop } from './useViewport.js'

// Guest gating: a gated action opens the login. Desktop → bottom-sheet popup;
// mobile → the full /login page (per design).
export const loginSheetOpen = ref(false)

export function promptLogin() {
  if (isDesktop.value) loginSheetOpen.value = true
  else window.location.assign('/login')
}

export function closeLoginSheet() {
  loginSheetOpen.value = false
}
