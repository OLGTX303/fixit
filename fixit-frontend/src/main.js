import { createApp } from 'vue'
import { createPinia } from 'pinia'
import 'bootstrap/dist/css/bootstrap.min.css'
import 'leaflet/dist/leaflet.css'
import './assets/styles.css'

import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { initCapacitor } from './capacitor'

const app = createApp(App)
const pinia = createPinia()
app.use(pinia).use(router)
useAuthStore(pinia).init()
initCapacitor()
app.mount('#app')
