<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProvidersStore } from '../../stores/providers'
import { useBookingsStore } from '../../stores/bookings'
import { useModalGuard } from '../../composables/useModalGuard'

import * as api from '../../services/api'
import RatingStars from '../../components/RatingStars.vue'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const providersStore = useProvidersStore()
const bookingsStore = useBookingsStore()
const provider = ref(null)

// Booking form state (all v-model bound) — workflow #1 (Customer Booking).
const form = ref({ date: '', time: '', address: '14 Maple Street, Apt 3', notes: '' })
const times = ['9:00 AM', '10:00 AM', '11:00 AM', '2:00 PM', '3:00 PM', '4:00 PM']
const submitting = ref(false)
const couponCode = ref('')
const couponMsg = ref('')
const couponDiscount = ref(0)
const couponApplied = ref(null)
const availableCoupons = ref([])
const showCouponPicker = ref(false)
const applyingCoupon = ref(false)

useModalGuard(showCouponPicker)

// Recurring booking (stretch goal)
const recurring = ref(false)
const recurrenceType = ref('weekly')
const recurrenceEndDate = ref('')
const RECURRENCE_OPTIONS = [
  { value: 'weekly',   label: 'Weekly' },
  { value: 'biweekly', label: 'Every 2 weeks' },
  { value: 'monthly',  label: 'Monthly' },
]

// Next 5 days as selectable date chips.
const dates = computed(() => Array.from({ length: 5 }, (_, i) => {
  const d = new Date(); d.setDate(d.getDate() + i)
  return { iso: d.toISOString().slice(0, 10), day: d.toLocaleDateString('en', { weekday: 'short' }), num: d.getDate() }
}))

onMounted(async () => {
  await Promise.all([
    providersStore.load(),
    api.getProvider(route.params.id).then((p) => { provider.value = p }),
  ])
  form.value.date = dates.value[0].iso
  form.value.time = times[0]
  try {
    availableCoupons.value = await api.getAvailableCoupons(Number(route.params.id))
  } catch { availableCoupons.value = [] }
})

// A specific service can be selected from the provider's catalog (Book button).
const selectedService = route.query.service ? String(route.query.service) : null
const servicePrice    = route.query.price ? parseFloat(route.query.price) : null
const providerServiceId = route.query.service_id ? Number(route.query.service_id) : null

const ESTIMATED_HOURS = 2
const platformFee = 5
// Selected service = flat price; otherwise fall back to hourly base rate.
const subtotal = computed(() => {
  if (!provider.value) return 0
  return servicePrice != null ? servicePrice : provider.value.base_rate * ESTIMATED_HOURS
})
const preDiscountTotal = computed(() => (provider.value ? subtotal.value + platformFee : 0))
const total = computed(() => Math.max(0, preDiscountTotal.value - couponDiscount.value))
const canSubmit = computed(() => form.value.date && form.value.time && form.value.address)

async function applyCoupon(code = couponCode.value) {
  if (!provider.value || !code?.trim()) return
  applyingCoupon.value = true
  couponMsg.value = ''
  try {
    const res = await api.validateCoupon({
      code: code.trim(),
      provider_id: provider.value.id,
      subtotal: preDiscountTotal.value,
    })
    if (!res.valid) {
      couponApplied.value = null
      couponDiscount.value = 0
      couponMsg.value = res.message || 'Invalid coupon'
      return
    }
    couponCode.value = code.trim().toUpperCase()
    couponApplied.value = res.coupon || { code: couponCode.value }
    couponDiscount.value = res.discount_amount || 0
    couponMsg.value = res.message || 'Coupon applied'
    showCouponPicker.value = false
  } catch (e) {
    couponApplied.value = null
    couponDiscount.value = 0
    couponMsg.value = e.message || 'Could not apply coupon'
  } finally {
    applyingCoupon.value = false
  }
}

function clearCoupon() {
  couponCode.value = ''
  couponApplied.value = null
  couponDiscount.value = 0
  couponMsg.value = ''
}

function pickCoupon(c) {
  couponCode.value = c.code
  applyCoupon(c.code)
}

async function confirm() {
  submitting.value = true
  try {
  const notes = selectedService
    ? `[${selectedService}] ${form.value.notes}`.trim()
    : form.value.notes
  const booking = await bookingsStore.create({
    provider_id:          provider.value.id,
    category_id:          provider.value.category_ids[0],
    scheduled_at:         `${form.value.date}T${form.value.time}`,
    address:              form.value.address,
    notes:                notes,
    provider_service_id:  providerServiceId || undefined,
    recurrence_type:      recurring.value ? recurrenceType.value : 'none',
    recurrence_end_date:  recurring.value && recurrenceEndDate.value ? recurrenceEndDate.value : null,
    coupon_code:          couponApplied.value ? couponCode.value : undefined,
  })
  router.push({
    name: 'payment',
    query: { booking_id: booking.id, amount: booking.total?.toFixed(2) ?? total.value.toFixed(2) },
  })
  } catch (e) {
    alert(e.message || 'Booking failed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div v-if="provider" class="fx-page">
    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-light rounded-circle" style="width:34px;height:34px;padding:0" @click="router.back()">
        <AppIcon name="back" :size="16" />
      </button>
      <h1 class="fw-bold m-0" style="font-size:20px">Book Service</h1>
    </div>

    <!-- Provider mini-card -->
    <div class="fx-card d-flex align-items-center gap-3 mb-4">
      <div class="fx-avatar" style="width:46px;height:46px">
        {{ provider.name.split(' ').map(w => w[0]).join('') }}
      </div>
      <div class="flex-grow-1">
        <div class="fw-semibold" style="font-size:14px">{{ provider.name }}</div>
        <div style="font-size:12px;color:var(--fx-muted)">{{ provider.category_names.join(', ') }}</div>
      </div>
      <div class="text-end">
        <div class="fw-bold text-accent">RM{{ provider.base_rate }}/hr</div>
        <RatingStars :rating="provider.avg_rating" :size="11" />
      </div>
    </div>

    <!-- Date -->
    <div class="fw-bold mb-2" style="font-size:14px">Select Date</div>
    <div class="d-flex gap-2 mb-4">
      <div v-for="d in dates" :key="d.iso" role="button" class="flex-fill text-center"
           style="padding:10px 4px;border-radius:12px;border:1.5px solid"
           :style="{
             background: form.date === d.iso ? 'var(--fx-accent)' : '#fff',
             color: form.date === d.iso ? '#fff' : 'var(--fx-text)',
             borderColor: form.date === d.iso ? 'var(--fx-accent)' : 'var(--fx-border)',
           }"
           @click="form.date = d.iso">
        <div style="font-size:10px;opacity:.8">{{ d.day }}</div>
        <div style="font-size:17px;font-weight:700">{{ d.num }}</div>
      </div>
    </div>

    <!-- Time -->
    <div class="fw-bold mb-2" style="font-size:14px">Select Time</div>
    <div class="d-grid mb-4" style="grid-template-columns:repeat(3,1fr);gap:8px">
      <div v-for="t in times" :key="t" role="button" class="text-center"
           style="padding:10px 4px;border-radius:10px;font-size:13px;font-weight:500;border:1.5px solid"
           :style="{
             background: form.time === t ? 'var(--fx-accent)' : '#fff',
             color: form.time === t ? '#fff' : 'var(--fx-text)',
             borderColor: form.time === t ? 'var(--fx-accent)' : 'var(--fx-border)',
           }"
           @click="form.time = t">{{ t }}</div>
    </div>

    <!-- Address + notes -->
    <div class="fw-bold mb-2" style="font-size:14px">Service Address</div>
    <input class="fx-input mb-2" v-model="form.address" placeholder="Enter your address" />
    <input class="fx-input mb-3" v-model="form.notes" placeholder="Add special instructions…" />

    <!-- Recurring booking toggle (stretch goal) -->
    <div class="fx-card mb-4" style="padding:14px 16px">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
          <div class="fw-semibold" style="font-size:14px">Make it Recurring?</div>
          <div style="font-size:12px;color:var(--fx-muted)">Auto-schedule the same service periodically</div>
        </div>
        <div class="form-check form-switch m-0">
          <input class="form-check-input" type="checkbox" v-model="recurring" style="cursor:pointer;width:42px;height:22px" />
        </div>
      </div>
      <template v-if="recurring">
        <div class="d-flex gap-2 mb-2 flex-wrap">
          <button v-for="opt in RECURRENCE_OPTIONS" :key="opt.value"
            class="fx-chip sm" :class="{ active: recurrenceType === opt.value }"
            @click="recurrenceType = opt.value">{{ opt.label }}</button>
        </div>
        <div>
          <label style="font-size:12px;color:var(--fx-muted);margin-bottom:4px;display:block">End date (optional)</label>
          <input class="fx-input" type="date" v-model="recurrenceEndDate" :min="form.date" />
        </div>
      </template>
    </div>

    <!-- Coupon -->
    <div class="fx-card mb-3" style="padding:14px 16px">
      <div class="fw-bold mb-2" style="font-size:14px">Coupon Code</div>
      <div class="d-flex gap-2 mb-2">
        <input class="fx-input flex-grow-1" v-model="couponCode" placeholder="Enter code" style="margin:0" />
        <button class="btn btn-outline-primary" style="white-space:nowrap" :disabled="applyingCoupon" @click="applyCoupon()">
          {{ applyingCoupon ? '…' : 'Apply' }}
        </button>
      </div>
      <div v-if="couponMsg" style="font-size:12px" :style="{ color: couponDiscount > 0 ? 'var(--fx-success)' : '#ef4444' }">
        {{ couponMsg }}
      </div>
      <div v-if="couponApplied" class="d-flex justify-content-between align-items-center mt-2" style="font-size:13px">
        <span class="fw-semibold">{{ couponApplied.code }} applied</span>
        <button class="btn btn-link p-0" style="font-size:12px" @click="clearCoupon">Remove</button>
      </div>
      <button v-if="availableCoupons.length" class="btn btn-link p-0 mt-1" style="font-size:12px" @click="showCouponPicker = true">
        Browse {{ availableCoupons.length }} available coupon{{ availableCoupons.length > 1 ? 's' : '' }}
      </button>
    </div>

    <!-- Price estimate -->
    <div class="fx-card bg-accent-soft mb-3">
      <div class="d-flex justify-content-between mb-2" style="font-size:13px">
        <span style="color:var(--fx-muted)">{{ selectedService ? selectedService : `Service fee (est. ${ESTIMATED_HOURS} hrs)` }}</span>
        <span class="fw-semibold">RM{{ subtotal.toFixed(2) }}</span>
      </div>
      <div class="d-flex justify-content-between mb-2" style="font-size:13px">
        <span style="color:var(--fx-muted)">Platform fee</span>
        <span class="fw-semibold">RM{{ platformFee.toFixed(2) }}</span>
      </div>
      <div v-if="couponDiscount > 0" class="d-flex justify-content-between mb-2" style="font-size:13px">
        <span style="color:var(--fx-success)">Coupon discount</span>
        <span class="fw-semibold" style="color:var(--fx-success)">-RM{{ couponDiscount.toFixed(2) }}</span>
      </div>
      <hr style="border-color:rgba(255,102,53,.2)" />
      <div class="d-flex justify-content-between align-items-center">
        <span class="fw-bold" style="font-size:14px">Estimated Total</span>
        <span class="fw-bold text-accent" style="font-size:16px">RM{{ total.toFixed(2) }}</span>
      </div>
    </div>

    <button class="btn btn-primary w-100" :disabled="!canSubmit || submitting" @click="confirm">
      {{ submitting ? 'Confirming…' : 'Confirm & Pay' }}
    </button>
  </div>

  <div v-else class="fx-page text-center py-5" style="color:var(--fx-muted)">Loading…</div>

  <Teleport to="body">
    <div v-if="showCouponPicker" class="bf-cpn-backdrop" @click.self="showCouponPicker = false">
      <div class="bf-cpn-sheet">
        <div class="fw-bold mb-3" style="font-size:16px">Available Coupons</div>
        <button
          v-for="c in availableCoupons" :key="c.id"
          class="bf-cpn-item" @click="pickCoupon(c)"
        >
          <span class="fw-bold">{{ c.code }}</span>
          <span style="font-size:12px;color:var(--fx-muted)">
            {{ c.discount_type === 'percent' ? `${c.discount_value}% off` : `RM${c.discount_value} off` }}
            <span v-if="c.min_spend"> · min RM{{ c.min_spend }}</span>
          </span>
        </button>
        <button class="btn btn-light w-100 mt-2" @click="showCouponPicker = false">Close</button>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.bf-cpn-backdrop {
  position: fixed; inset: 0; z-index: 2000;
  background: rgba(0,0,0,0.45);
  display: flex; align-items: flex-end; justify-content: center;
  padding: 16px; padding-bottom: max(16px, env(safe-area-inset-bottom));
}
.bf-cpn-sheet {
  width: 100%; max-width: 480px; background: var(--fx-card);
  border-radius: 20px 20px 16px 16px; padding: 20px; max-height: 70vh; overflow-y: auto;
}
.bf-cpn-item {
  display: flex; flex-direction: column; align-items: flex-start; gap: 2px;
  width: 100%; text-align: left; padding: 12px 14px; margin-bottom: 8px;
  border-radius: 12px; border: 1px solid var(--fx-border); background: #fff; cursor: pointer;
}
</style>
