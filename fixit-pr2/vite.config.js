import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Base stays '/' for dev; api.js reads import.meta.env.BASE_URL so a
// sub-path deploy keeps working without code changes.
export default defineConfig({
  plugins: [vue()],
  server: { port: 5173, open: true },
})
