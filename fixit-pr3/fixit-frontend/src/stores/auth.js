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
    setUser(user) {
      const prevAvatar = this.user?.avatar_url
      const prevId = this.user?.id
      this.user = user
      api.persistSession(this.token, user)
      if (user?.id && user.avatar_url !== prevAvatar) {
        useBookingsStore().syncUserAvatar(prevId ?? user.id, user.avatar_url ?? null)
        useProvidersStore().resetCache()
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
      // Guests live on home now (not /login).
      if (window.location.pathname !== '/home') {
        window.location.href = '/home'
      }
    },
  },
})