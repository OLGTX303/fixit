<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProvidersStore } from '../../stores/providers'
import { useBookingsStore } from '../../stores/bookings'
import { useAuthStore } from '../../stores/auth'
import RatingStars from '../../components/RatingStars.vue'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const providersStore = useProvidersStore()
const bookingsStore = useBookingsStore()
const auth = useAuthStore()

const provider = computed(() => providersStore.byId(route.params.id))

// Booking form state (all v-model bound) — workflow #1 (Customer Booking).
const form = ref({ date: '', time: '', address: '14 Maple Street, Apt 3', notes: '' })
const times = ['9:00 AM', '10:00 AM', '11:00 AM', '2:00 PM', '3:00 PM', '4:00 PM']
const submitting = ref(false)

// Next 5 days as selectable date chips.
const dates = computed(() => Array.from({ length: 5 }, (_, i) => {
  const d = new Date(); d.setDate(d.getDate() + i)
  return { iso: d.toISOString().slice(0, 10), day: d.toLocaleDateString('en', { weekday: 'short' }), num: d.getDate() }
}))

onMounted(async () => {
  await providersStore.load()
  form.value.date = dates.value[0].iso
  form.value.time = times[0]
})

const ESTIMATED_HOURS = 2
const platformFee = 5
const total = computed(() => (provider.value ? provider.value.base_rate * ESTIMATED_HOURS + platformFee : 0))
const canSubmit = computed(() => form.value.date && form.value.time && form.value.address)

async function confirm() {
  submitting.value = true
  const booking = await bookingsStore.create({
    customer_id: auth.user.id,
    provider_id: provider.value.id,
    category_id: provider.value.category_ids[0],
    scheduled_at: `${form.value.date}T${form.value.time}`,
    address: form.value.address,
    notes: form.value.notes,
    total: total.value,
  })
  submitting.value = false
  router.push({
    name: 'payment',
    query: { booking_id: booking.id, amount: total.value.toFixed(2) },
  })
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
        <div class="fw-bold text-accent">${{ provider.base_rate }}/hr</div>
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
    <input class="fx-input mb-4" v-model="form.notes" placeholder="Add special instructions…" />

    <!-- Price estimate -->
    <div class="fx-card bg-accent-soft mb-3">
      <div class="d-flex justify-content-between mb-2" style="font-size:13px">
        <span style="color:var(--fx-muted)">Service fee (est. {{ ESTIMATED_HOURS }} hrs)</span>
        <span class="fw-semibold">${{ (provider.base_rate * ESTIMATED_HOURS).toFixed(2) }}</span>
      </div>
      <div class="d-flex justify-content-between mb-2" style="font-size:13px">
        <span style="color:var(--fx-muted)">Platform fee</span>
        <span class="fw-semibold">${{ platformFee.toFixed(2) }}</span>
      </div>
      <hr style="border-color:rgba(255,102,53,.2)" />
      <div class="d-flex justify-content-between align-items-center">
        <span class="fw-bold" style="font-size:14px">Estimated Total</span>
        <span class="fw-bold text-accent" style="font-size:16px">${{ total.toFixed(2) }}</span>
      </div>
    </div>

    <button class="btn btn-primary w-100" :disabled="!canSubmit || submitting" @click="confirm">
      {{ submitting ? 'Confirming…' : 'Confirm & Pay' }}
    </button>
  </div>

  <div v-else class="fx-page text-center py-5" style="color:var(--fx-muted)">Loading…</div>
</template>
