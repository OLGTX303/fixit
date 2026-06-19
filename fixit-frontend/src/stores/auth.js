import { defineStore } from 'pinia'
import * as api from '../services/api'
import { useBookingsStore } from './bookings'
import { useProvidersStore } from './providers'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: api.getStoredUser(),
    token: api.getStoredToken(),
    loading: false,
    error: null,
  }),
  getters: {
    isAuthenticated: (s) => !!s.token,
    role: (s) => s.user?.role || null,
  },
  actions: {
    init() {
      api.setUnauthorizedHandler(() => this.logoutAndRedirect())
      if (this.token && this.user) {
        api.persistSession(this.token, this.user)
      }
    },
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
    async register(payload) {
      this.loading = true
      this.error = null
      try {
        const { token, user } = await api.register(payload)
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
    logout() {
      api.logout()
      this.user = null
      this.token = null
      useProvidersStore().resetCache()
      useBookingsStore().resetCache()
    },
    logoutAndRedirect() {
      this.logout()
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    },
  },
})