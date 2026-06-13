import { defineStore } from 'pinia'
import * as api from '../services/api'

// Minimal auth/session store. PR2 has no real auth — login just looks up a
// known mock user by email. Role drives which navigation + routes are shown.
export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: null,
    loading: false,
    error: null,
  }),
  getters: {
    isAuthenticated: (s) => !!s.token,
    role: (s) => s.user?.role || null,
  },
  actions: {
    async login(email, password) {
      this.loading = true
      this.error = null
      try {
        const { token, user } = await api.login(email, password)
        this.token = token
        this.user = user
        return user
      } catch (e) {
        this.error = e.message
        throw e
      } finally {
        this.loading = false
      }
    },
    // PR2 convenience: jump straight in as a demo role for grading.
    async loginAs(role) {
      const emails = { customer: 'alex@email.com', provider: 'marcus@email.com', admin: 'admin@fixit.com' }
      return this.login(emails[role], 'demo')
    },
    logout() {
      this.user = null
      this.token = null
    },
  },
})
