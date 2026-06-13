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
    requestsForProvider: (s) => (providerId) =>
      s.bookings.filter(b => b.provider_id === Number(providerId) && b.status === 'requested'),
  },
  actions: {
    async load() {
      if (this.loaded) return
      this.loading = true
      try {
        this.bookings = await api.getBookings()
        this.loaded = true
      } finally {
        this.loading = false
      }
    },
    async create(payload) {
      const created = await api.createBooking(payload)
      // Re-join the new booking against loaded reference data for display.
      const enriched = {
        ...created,
        customer: null,
        provider: this.bookings.find(b => b.provider_id === created.provider_id)?.provider || null,
        category: this.bookings.find(b => b.category_id === created.category_id)?.category || null,
      }
      this.bookings.unshift(enriched)
      return enriched
    },
    async advanceStatus(bookingId, status) {
      await api.updateBookingStatus(bookingId, status)
      const b = this.byId(bookingId)
      if (b) b.status = status
    },
  },
})
