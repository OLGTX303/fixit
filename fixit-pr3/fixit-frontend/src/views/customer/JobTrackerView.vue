<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useAuthStore } from '../../stores/auth'
import StatusTimeline from '../../components/StatusTimeline.vue'

const route = useRoute()
const router = useRouter()
const bookingsStore = useBookingsStore()
const auth = useAuthStore()

const selectedId = ref(route.query.id ? Number(route.query.id) : null)

onMounted(async () => {
  await bookingsStore.load()
  const mine = bookingsStore.forCustomer(auth.user.id)
  if (!selectedId.value && mine.length) selectedId.value = mine[0].id
})

const myBookings = computed(() => bookingsStore.forCustomer(auth.user.id))
const selected = computed(() => bookingsStore.byId(selectedId.value))

const STATUS_BADGE = {
  requested: { c: 'var(--fx-warn)', bg: 'var(--fx-warn-soft)', label: 'Requested' },
  accepted: { c: 'var(--fx-blue)', bg: 'var(--fx-blue-soft)', label: 'Accepted' },
  in_progress: { c: 'var(--fx-blue)', bg: 'var(--fx-blue-soft)', label: 'In Progress' },
  completed: { c: 'var(--fx-success)', bg: 'var(--fx-success-soft)', label: 'Completed' },
  reviewed: { c: 'var(--fx-muted)', bg: 'var(--fx-border-soft)', label: 'Reviewed' },
}
</script>

<template>
  <div class="fx-page">
    <h1 class="fw-bold mb-3" style="font-size:20px">My Bookings</h1>

    <!-- Booking selector list -->
    <div class="d-flex flex-column gap-2 mb-4">
      <div v-for="b in myBookings" :key="b.id" role="button" class="fx-card d-flex align-items-center gap-3"
           :style="{ outline: b.id === selectedId ? '2px solid var(--fx-accent)' : 'none' }"
           @click="selectedId = b.id">
        <div class="fx-avatar" style="width:42px;height:42px">
          {{ (b.provider?.name || '?').split(' ').map(w => w[0]).join('') }}
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold" style="font-size:14px">{{ b.provider?.name || 'Provider' }}</div>
          <div style="font-size:12px;color:var(--fx-muted)">
            {{ b.category?.name }} · #{{ b.id }}
          </div>
        </div>
        <span class="fx-badge" :style="{ color: STATUS_BADGE[b.status].c, background: STATUS_BADGE[b.status].bg }">
          {{ STATUS_BADGE[b.status].label }}
        </span>
      </div>
      <div v-if="!myBookings.length" class="text-center py-4" style="color:var(--fx-muted)">
        No bookings yet — find a provider to get started.
      </div>
    </div>

    <!-- Timeline for selected booking -->
    <template v-if="selected">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold m-0" style="font-size:16px">Job Tracker — #{{ selected.id }}</h2>
      </div>
      <div class="fx-card">
        <StatusTimeline :status="selected.status" />
      </div>

      <button class="btn btn-outline-primary w-100 mt-3"
              @click="router.push({ name: 'chat', params: { id: selected.id } })">
        Message {{ selected.provider?.name || 'provider' }}
      </button>

      <button v-if="selected.status === 'completed'" class="btn btn-primary w-100 mt-3"
              @click="router.push({ name: 'rate-review', params: { id: selected.id } })">
        Rate &amp; Review
      </button>
    </template>
  </div>
</template>
