import { defineConfig } from 'jsrepo'

export default defineConfig({
  registries: ['https://vue-bits.dev/r'],
  paths: {
    component: 'src/components/vue-bits',
    block: 'src/components/vue-bits',
    lib: 'src/lib/vue-bits',
  },
})