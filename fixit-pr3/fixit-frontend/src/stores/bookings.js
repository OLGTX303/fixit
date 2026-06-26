import { defineStore } from 'pinia'
import * as api from '../services/api'

export const STATUS_FLOW = ['requested', 'accepted', 'in_progress', 'completed', 'reviewed']

export const useBookingsStore = defineStore('bookings', {
  state: () => ({
    bookings: [],
    loaded: false,
    loading: false,
  }),
  getters: {
    byId: (s) => (id) => s.bookings.find(b => b.id === Number(id)),
    forCustomer: (s) => (customerId) => s.bookings.filter(b => b.customer_id === Number(customerId)),
    forProvider: (s) => (providerId) => s.bookings.filter(b => b.provider_id === Number(providerId)),
  },
  actions: {
    async load() {
      if (this.loaded) return
      this.loading = true
      try {
        this.bookings = await api.getBookings({ limit: 50, offset: 0 })
        this.loaded = true
      } finally {
        this.loading = false
      }
    },
    async create(payload) {
      const created = await api.createBooking(payload)
      this.bookings.unshift(created)
      return created
    },
    async advanceStatus(bookingId, status) {
      await api.updateBookingStatus(bookingId, status)
      const b = this.byId(bookingId)
      if (b) b.status = status
    },
    async remove(bookingId) {
      await api.deleteBooking(bookingId)
      this.bookings = this.bookings.filter(b => b.id !== Number(bookingId))
    },
    async reload() {
      this.loaded = false
      this.bookings = []
      await this.load()
    },
    /** Keep nested customer/provider avatars in sync after profile photo upload. */
    syncUserAvatar(userId, avatarUrl) {
      const id = Number(userId)
      for (const b of this.bookings) {
        if (b.customer?.id === id) {
          b.customer = { ...b.customer, avatar_url: avatarUrl ?? null }
        }
        if (b.provider?.user_id === id) {
          b.provider = { ...b.provider, avatar_url: avatarUrl ?? null }
        }
      }
    },
    resetCache() {
      this.loaded = false
      this.bookings = []
    },
  },
})
