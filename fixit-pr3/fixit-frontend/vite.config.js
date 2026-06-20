import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  // Absolute base so nested SPA routes (e.g. /account/email) resolve assets
  // correctly when served from the domain root; also works for Capacitor.
  base: '/',
  plugins: [vue()],
  server: {
    port: 5173,
    strictPort: true,
  },
  build: {
    sourcemap: false,
    rollupOptions: {
      output: {
        manualChunks: undefined,
      },
    },
  },
})