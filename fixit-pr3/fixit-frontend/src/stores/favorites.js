import { defineStore } from 'pinia'
import * as api from '../services/api'

export const useFavoritesStore = defineStore('favorites', {
  state: () => ({
    ids: [],
    loaded: false,
    loading: false,
  }),
  getters: {
    has: (s) => (providerId) => s.ids.includes(Number(providerId)),
  },
  actions: {
    async load() {
      if (this.loading) return
      this.loading = true
      try {
        const all = []
        const page = 50
        let offset = 0
        let batch
        do {
          batch = await api.getFavorites({ limit: page, offset })
          all.push(...batch)
          offset += batch.length
        } while (batch.length === page)
        this.ids = all.map(p => p.id)
        this.loaded = true
      } catch {
        this.ids = []
      } finally {
        this.loading = false
      }
    },
    async toggle(providerId) {
      const id = Number(providerId)
      if (this.has(id)) {
        await api.unfavoriteProvider(id)
        this.ids = this.ids.filter(x => x !== id)
        return false
      }
      await api.favoriteProvider(id)
      this.ids.push(id)
      return true
    },
    reset() {
      this.ids = []
      this.loaded = false
    },
  },
})