<script setup>
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const bookingsStore = useBookingsStore()

const booking = computed(() => bookingsStore.byId(route.params.id))

onMounted(() => bookingsStore.load())

// Provider-side status controls — drives the same Job.status the customer sees.
const stages = [
  { key: 'accepted', label: 'On My Way', icon: '🚗' },
  { key: 'in_progress', label: 'Working', icon: '🔧' },
]
function setStatus(key) {
  bookingsStore.advanceStatus(booking.value.id, key)
}
function complete() {
  bookingsStore.advanceStatus(booking.value.id, 'completed')
  router.push({ name: 'pro-requests' })
}
</script>

<template>
  <div v-if="booking" class="fx-page" style="max-width:520px">
    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-light rounded-circle" style="width:34px;height:34px;padding:0" @click="router.back()">
        <AppIcon name="back" :size="16" />
      </button>
      <div>
        <h1 class="fw-bold m-0" style="font-size:20px">Active Job</h1>
        <div style="font-size:12px;color:var(--fx-muted)">#{{ booking.id }}</div>
      </div>
    </div>

    <!-- Customer card -->
    <div class="fx-card d-flex align-items-center gap-3 mb-3">
      <div class="fx-avatar" style="width:46px;height:46px;background:var(--fx-blue-soft);color:var(--fx-blue)">
        {{ (booking.customer?.name || '?').split(' ').map(w => w[0]).join('') }}
      </div>
      <div class="flex-grow-1">
        <div class="fw-semibold">{{ booking.customer?.name }}</div>
        <div style="font-size:12px;color:var(--fx-muted)">{{ booking.category?.name }} · {{ booking.address }}</div>
      </div>
      <button class="btn btn-light rounded-circle" style="width:38px;height:38px;padding:0"
              @click="router.push({ name: 'pro-chat', params: { id: booking.id } })">
        <AppIcon name="chat" :size="18" style="color:var(--fx-blue)" />
      </button>
    </div>

    <!-- Status hero -->
    <div class="fx-card text-center mb-3" style="background:var(--fx-accent);color:#fff;padding:18px">
      <div style="font-size:12px;opacity:.8;font-weight:500">CURRENT STATUS</div>
      <div style="font-size:28px;font-weight:800;letter-spacing:-1px;text-transform:capitalize">
        {{ booking.status.replace('_', ' ') }}
      </div>
    </div>

    <div class="fw-semibold mb-2" style="font-size:13px;color:var(--fx-muted)">UPDATE STATUS</div>
    <div class="d-flex gap-2 mb-4">
      <div v-for="s in stages" :key="s.key" role="button" class="flex-fill text-center"
           style="padding:12px 6px;border-radius:12px;border:1.5px solid"
           :style="{ background: booking.status === s.key ? 'var(--fx-accent-soft)' : '#fff', borderColor: booking.status === s.key ? 'var(--fx-accent)' : 'var(--fx-border)' }"
           @click="setStatus(s.key)">
        <div style="font-size:20px">{{ s.icon }}</div>
        <div style="font-size:12px;font-weight:600" :style="{ color: booking.status === s.key ? 'var(--fx-accent)' : 'var(--fx-muted)' }">{{ s.label }}</div>
      </div>
    </div>

    <div class="fw-semibold mb-2" style="font-size:13px;color:var(--fx-muted)">JOB NOTES</div>
    <div class="fx-card mb-4" style="background:var(--fx-border-soft);box-shadow:none;font-size:13px;color:var(--fx-muted);line-height:1.5">
      {{ booking.notes || 'No notes provided.' }}
    </div>

    <button class="btn btn-success w-100" style="border-radius:14px;font-weight:600;padding:13px"
            @click="complete">✓ Mark as Complete</button>
  </div>

  <div v-else class="fx-page text-center py-5" style="color:var(--fx-muted)">Loading…</div>
</template>
