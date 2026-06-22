// Single source of truth for desktop-vs-mobile across ALL pages.
// One matchMedia listener (module-level singleton): when the mobile UI is active
// the desktop UI is disabled, and vice-versa. CSS reads body.fx-desktop /
// body.fx-mobile; JS reads the exported `isDesktop` ref. They can never disagree.
import { ref, watch } from 'vue'

const mq = window.matchMedia('(min-width: 992px)')
export const isDesktop = ref(mq.matches)

mq.addEventListener('change', (e) => { isDesktop.value = e.matches })

function syncBody(desktop) {
  document.body.classList.toggle('fx-desktop', desktop)
  document.body.classList.toggle('fx-mobile', !desktop)
}
syncBody(isDesktop.value)
watch(isDesktop, syncBody)
