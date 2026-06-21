<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const bookingsStore = useBookingsStore()
const auth = useAuthStore()

onMounted(() => bookingsStore.load())

const myBookings = computed(() => bookingsStore.forCustomer(auth.user?.id))

const STATUS = {
  requested:   { c: 'var(--fx-warn)',    bg: 'var(--fx-warn-soft)',    label: 'Requested' },
  accepted:    { c: 'var(--fx-blue)',    bg: 'var(--fx-blue-soft)',    label: 'Accepted' },
  in_progress: { c: 'var(--fx-blue)',    bg: 'var(--fx-blue-soft)',    label: 'In Progress' },
  completed:   { c: 'var(--fx-success)', bg: 'var(--fx-success-soft)', label: 'Completed' },
  reviewed:    { c: 'var(--fx-muted)',   bg: 'rgba(255,255,255,0.18)', label: 'Reviewed' },
}

function initials(name) {
  return (name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
}
</script>

<template>
  <div class="fx-page">
    <div class="mb-4">
      <h1 class="fw-bold mb-1" style="font-size:24px;letter-spacing:-0.02em">Messages</h1>
      <p style="font-size:14px;color:var(--fx-muted);margin:0">Your conversations with service providers</p>
    </div>

    <div class="d-flex flex-column gap-2">
      <button
        v-for="b in myBookings"
        :key="b.id"
        class="conv-row fx-card d-flex align-items-center gap-3"
        @click="router.push({ name: 'chat', params: { id: b.id } })"
      >
        <div class="fx-avatar" style="width:48px;height:48px;font-size:16px;font-weight:800;flex-shrink:0">
          {{ initials(b.provider?.name) }}
        </div>
        <div class="flex-grow-1" style="min-width:0;text-align:left">
          <div class="fw-semibold" style="font-size:15px;margin-bottom:3px">
            {{ b.provider?.name || 'Provider' }}
          </div>
          <div style="font-size:12px;color:var(--fx-muted)">
            {{ b.category?.name || 'Service' }} · Job #{{ b.id }}
          </div>
        </div>
        <div class="d-flex flex-column align-items-end gap-2" style="flex-shrink:0">
          <span class="fx-badge"
                :style="{ color: STATUS[b.status]?.c, background: STATUS[b.status]?.bg }">
            {{ STATUS[b.status]?.label || b.status }}
          </span>
          <span class="material-symbols-outlined" style="font-size:18px;color:var(--fx-muted-soft)">chevron_right</span>
        </div>
      </button>

      <div v-if="!myBookings.length" class="fx-card text-center py-5">
        <span class="material-symbols-outlined" style="font-size:44px;color:var(--fx-muted-soft);display:block;margin-bottom:12px">chat</span>
        <div class="fw-semibold" style="font-size:15px;margin-bottom:4px">No conversations yet</div>
        <div style="font-size:13px;color:var(--fx-muted)">Book a service to start chatting with providers</div>
        <button class="btn btn-primary mt-4" style="border-radius:999px;padding:11px 28px"
                @click="router.push({ name: 'search' })">
          Find a provider
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.conv-row {
  width: 100%;
  border: none;
  cursor: pointer;
  padding: 14px 16px;
  text-align: left;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.conv-row:hover {
  transform: translateY(-1px);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.75), 0 12px 36px rgba(0,0,0,0.09);
}
.conv-row:active { transform: scale(0.98); }
</style>
