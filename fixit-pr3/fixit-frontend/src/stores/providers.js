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
    byId: (s) => (id) => s.providers.find(p => p.id === Number(id)),
  },
  actions: {
    async load() {
      if (this.loaded) return
      this.loading = true
      try {
        const user = api.getStoredUser()
        const categories = await api.getCategories()
        this.categories = categories

        if (user?.role === 'provider') {
          const profile = await api.getMyProviderProfile()
          this.providers = profile ? [profile] : []
        } else if (user?.role === 'admin') {
          // Admin lists use paginated getAdminProviders in each view — never bulk-load 15k.
          this.providers = []
        } else {
          this.providers = await api.searchProviders({ limit: 50, offset: 0 })
        }
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
    async reload() {
      this.loaded = false
      await this.load()
    },
    resetCache() {
      this.loaded = false
      this.providers = []
      this.categories = []
    },
  },
})