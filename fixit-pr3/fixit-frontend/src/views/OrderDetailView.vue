<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as api from '../services/api'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const booking = ref(null)
const error = ref('')
const loading = ref(true)

onMounted(async () => {
  try {
    booking.value = await api.getBooking(route.params.id)
  } catch (e) {
    error.value = e.message || 'Order not found'
  } finally {
    loading.value = false
  }
})

const STATUS_COLOR = {
  requested: '#f59e0b', accepted: '#3b82f6', in_progress: '#8b5cf6',
  completed: '#22c55e', reviewed: '#94a3b8', cancelled: '#ef4444', inquiry: '#94a3b8',
}
const statusLabel = (s) => (s || '').replace('_', ' ')

function fmt(iso) {
  if (!iso) return null
  const d = new Date(iso)
  return Number.isNaN(d.getTime())
    ? iso
    : d.toLocaleString('en', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
const fmtRM = (n) => `RM ${Number(n || 0).toFixed(2)}`

// Avatars (synced from the freshly-fetched booking: customer + provider both
// carry avatar_url). Fall back to initials if absent or the image 404s.
const broken = ref({})
const initials = (n) => (n || '—').split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase()
const avatarOk = (url, key) => url && !broken.value[key]

// Order-history timeline. "reached" comes from the status rank so a completed
// order never shows an earlier step as pending, even if its timestamp is null;
// the timestamp is shown when present. Cancelled appears only when it happened.
const STATUS_RANK = { requested: 0, accepted: 1, in_progress: 2, completed: 3, reviewed: 4 }
const timeline = computed(() => {
  const b = booking.value
  if (!b) return []
  const rank = STATUS_RANK[b.status] ?? 0
  const cancelled = b.status === 'cancelled' || !!b.cancelled_at
  const steps = [
    { key: 'submitted', label: 'Order submitted',  icon: 'receipt_long', at: b.created_at,     reached: true },
    { key: 'paid',      label: 'Payment received',  icon: 'payments',     at: b.paid_at,        reached: !!b.paid_at || rank >= 3 },
    { key: 'accepted',  label: 'Provider accepted', icon: 'handshake',    at: b.accepted_at,    reached: rank >= 1 },
    { key: 'progress',  label: 'Work in progress',  icon: 'build',        at: b.in_progress_at, reached: rank >= 2 },
    { key: 'completed', label: 'Job completed',     icon: 'check_circle', at: b.completed_at,   reached: rank >= 3 },
  ]
  if (cancelled) {
    steps.push({ key: 'cancelled', label: 'Order cancelled', icon: 'cancel', at: b.cancelled_at, reached: true })
  }
  return steps
})

const subtotal = computed(() => {
  const b = booking.value
  if (!b) return null
  const total = Number(b.total || 0)
  const disc = Number(b.discount_amount || 0)
  return disc > 0 ? total + disc : total
})
</script>

<template>
  <div class="od-root fx-page" style="max-width:640px">
    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-light rounded-circle" style="width:34px;height:34px;padding:0" @click="router.back()">
        <span class="material-symbols-outlined" style="font-size:18px">arrow_back</span>
      </button>
      <div>
        <h1 class="fw-bold m-0" style="font-size:20px">Order Details</h1>
        <div style="font-size:12px;color:var(--fx-muted)">#{{ route.params.id }}</div>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5" style="color:var(--fx-muted)">Loading order…</div>
    <div v-else-if="error" class="text-center py-5" style="color:var(--fx-muted)">
      <span class="material-symbols-outlined" style="font-size:42px;opacity:.3">search_off</span>
      <p class="mt-2">{{ error }}</p>
    </div>

    <template v-else-if="booking">
      <!-- Status banner -->
      <div class="fx-card d-flex align-items-center justify-content-between mb-3" style="padding:14px 16px">
        <div>
          <div style="font-size:11px;color:var(--fx-muted);font-weight:600;letter-spacing:.04em">CURRENT STATUS</div>
          <div class="fw-bold" style="font-size:18px;text-transform:capitalize">{{ statusLabel(booking.status) }}</div>
        </div>
        <span class="od-badge" :style="{ background: (STATUS_COLOR[booking.status] || '#94a3b8') + '22',
                                          color: STATUS_COLOR[booking.status] || '#94a3b8' }">
          {{ statusLabel(booking.status) }}
        </span>
      </div>

      <!-- Order snapshot -->
      <div class="fx-card mb-3" style="padding:16px">
        <div class="fw-semibold mb-3" style="font-size:13px;color:var(--fx-muted)">ORDER SNAPSHOT</div>

        <div class="d-flex align-items-center gap-3 mb-3">
          <img v-if="avatarOk(booking.provider?.avatar_url, 'prov')" :src="booking.provider.avatar_url"
               :alt="booking.provider?.name" class="od-avatar" @error="broken['prov'] = true" />
          <div v-else class="od-avatar od-avatar-fallback">{{ initials(booking.provider?.name) }}</div>
          <div class="flex-grow-1">
            <div class="fw-semibold">{{ booking.provider?.name || '—' }}</div>
            <div style="font-size:12px;color:var(--fx-muted)">{{ booking.category?.name }}</div>
          </div>
        </div>

        <div class="od-row">
          <span>Customer</span>
          <b class="d-flex align-items-center gap-2">
            <img v-if="avatarOk(booking.customer?.avatar_url, 'cust')" :src="booking.customer.avatar_url"
                 :alt="booking.customer?.name" class="od-avatar od-avatar-sm" @error="broken['cust'] = true" />
            <span v-else class="od-avatar od-avatar-sm od-avatar-fallback">{{ initials(booking.customer?.name) }}</span>
            {{ booking.customer?.name || '—' }}
          </b>
        </div>
        <div class="od-row"><span>Service</span><b>{{ booking.category?.name || '—' }}</b></div>
        <div class="od-row"><span>Scheduled</span><b>{{ fmt(booking.scheduled_at) || '—' }}</b></div>
        <div class="od-row"><span>Address</span><b class="text-end">{{ booking.address || '—' }}</b></div>
        <div v-if="booking.notes" class="od-row"><span>Notes</span><b class="text-end">{{ booking.notes }}</b></div>

        <div class="od-divider"></div>
        <div class="od-row"><span>Subtotal</span><b>{{ fmtRM(subtotal) }}</b></div>
        <div v-if="booking.discount_amount" class="od-row" style="color:#22c55e">
          <span>Discount</span><b>− {{ fmtRM(booking.discount_amount) }}</b>
        </div>
        <div class="od-row" style="font-size:15px">
          <span class="fw-semibold" style="color:var(--fx-text)">Total</span>
          <b style="color:var(--fx-accent)">{{ fmtRM(booking.total) }}</b>
        </div>
      </div>

      <!-- Timestamp timeline -->
      <div class="fx-card" style="padding:16px">
        <div class="fw-semibold mb-3" style="font-size:13px;color:var(--fx-muted)">ORDER HISTORY</div>
        <div class="od-steps">
          <div v-for="(s, i) in timeline" :key="s.key" class="od-step">
            <div class="od-step-left">
              <div class="od-step-icon" :class="{ done: s.reached }">
                <span class="material-symbols-outlined" style="font-size:16px;font-variation-settings:'FILL' 1">{{ s.icon }}</span>
              </div>
              <div v-if="i < timeline.length - 1" class="od-step-line" :class="{ done: s.reached }"></div>
            </div>
            <div class="od-step-body">
              <span class="od-step-label" :class="{ done: s.reached }">{{ s.label }}</span>
              <span class="od-step-time">{{ fmt(s.at) || (s.reached ? 'Done' : 'Pending') }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.od-root { min-height: 100vh; padding-bottom: 100px; }
.od-badge { font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 999px; text-transform: capitalize; }

.od-avatar { width: 46px; height: 46px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid rgba(255,255,255,0.65); }
.od-avatar-sm { width: 22px; height: 22px; border: 1.5px solid rgba(255,255,255,0.6); }
.od-avatar-fallback { display: inline-flex; align-items: center; justify-content: center;
  font-weight: 800; color: #fff; font-size: 14px; background: linear-gradient(160deg, #FF8056, #FF6635); }
.od-avatar-sm.od-avatar-fallback { font-size: 9px; }

.od-row { display: flex; justify-content: space-between; gap: 16px; padding: 7px 0; font-size: 13px; color: var(--fx-muted); }
.od-row b { color: var(--fx-text); font-weight: 600; }
.od-divider { height: 1px; background: var(--fx-border); margin: 8px 0; }

.od-steps { display: flex; flex-direction: column; }
.od-step { display: flex; gap: 14px; }
.od-step-left { display: flex; flex-direction: column; align-items: center; width: 32px; flex-shrink: 0; }
.od-step-icon {
  width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: rgba(0,0,0,0.06); color: var(--fx-muted);
}
.od-step-icon.done { background: var(--fx-accent); color: #fff; }
.od-step-line { width: 2px; flex: 1; min-height: 20px; margin: 3px 0; background: rgba(0,0,0,0.08); }
.od-step-line.done { background: var(--fx-accent); }
.od-step-body { padding: 4px 0 18px; flex: 1; min-width: 0; }
.od-step-label { display: block; font-size: 14px; font-weight: 600; color: var(--fx-muted); }
.od-step-label.done { color: var(--fx-text); }
.od-step-time { font-size: 12px; color: var(--fx-muted); }
</style>
