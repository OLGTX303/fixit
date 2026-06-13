import { defineStore } from 'pinia'
import * as api from '../services/api'
import { useProvidersStore } from './providers'

// Admin actions. Verification writes through to the shared providers store so a
// freshly approved provider appears in the customer Search view immediately.
export const useAdminStore = defineStore('admin', {
  state: () => ({
    decisions: {}, // providerId -> 'approved' | 'rejected'
  }),
  getters: {
    stats() {
      const providersStore = useProvidersStore()
      const approved = providersStore.providers.filter(p => p.is_verified).length
      const rejected = Object.values(this.decisions).filter(d => d === 'rejected').length
      const pending = providersStore.providers.filter(p => !p.is_verified).length - rejected
      return { pending: Math.max(0, pending), approved, rejected }
    },
  },
  actions: {
    async approve(providerId) {
      await api.setProviderVerification(providerId, true)
      useProvidersStore().setVerified(providerId, true)
      this.decisions[providerId] = 'approved'
    },
    async reject(providerId) {
      await api.setProviderVerification(providerId, false)
      useProvidersStore().setVerified(providerId, false)
      this.decisions[providerId] = 'rejected'
    },
  },
})
