import { createApp } from 'vue'
import { createPinia } from 'pinia'
import './assets/styles.css'

import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { initCapacitor } from './capacitor'

// After a redeploy, an already-open tab may request a chunk whose hashed name no
// longer exists, so a lazy route import fails with "Failed to fetch dynamically
// imported module". Reload once to pull the fresh index.html + chunks instead of
// dead-ending. The one-shot flag prevents a reload loop if the chunk is truly gone.
function reloadForStaleChunk() {
  const key = 'fixit_chunk_reload'
  if (sessionStorage.getItem(key)) return
  sessionStorage.setItem(key, '1')
  window.location.reload()
}
router.onError((err) => {
  if (/dynamically imported module|Importing a module script failed|Failed to fetch/i.test(err?.message || '')) {
    reloadForStaleChunk()
  }
})
window.addEventListener('vite:preloadError', reloadForStaleChunk)
router.afterEach(() => sessionStorage.removeItem('fixit_chunk_reload'))

const app = createApp(App)
const pinia = createPinia()
app.use(pinia).use(router)
useAuthStore(pinia).init()
initCapacitor()
app.mount('#app')
