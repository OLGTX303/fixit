import { defineStore } from 'pinia'
import * as api from '../services/api'

// Holds the provider directory + categories. Shared by the customer Search view
// and the admin Verification view — approving a provider in admin instantly
// makes them visible to customers because both read this same state.
export const useProvidersStore = defineStore('providers', {
  state: () => ({
    providers: [],
    categories: [],
    loaded: false,
    loading: false,
  }),
  getters: {
    // Only verified providers are ever shown to customers.
    verified: (s) => s.providers.filter(p => p.is_verified),
    pending: (s) => s.providers.filter(p => !p.is_verified),
    byId: (s) => (id) => s.providers.find(p => p.id === Number(id)),
  },
  actions: {
    async load() {
      if (this.loaded) return
      this.loading = true
      try {
        const [providers, categories] = await Promise.all([
          api.getProviders(), api.getCategories(),
        ])
        this.providers = providers
        this.categories = categories
        this.loaded = true
      } finally {
        this.loading = false
      }
    },
    // Called by the admin store after an approve/reject so customer views update.
    setVerified(providerId, isVerified) {
      const p = this.providers.find(x => x.id === Number(providerId))
      if (p) p.is_verified = isVerified
    },
  },
})
