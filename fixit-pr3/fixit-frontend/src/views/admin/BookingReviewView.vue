<script setup>
import { ref, computed, onMounted } from 'vue'
import { useBookingsStore } from '../../stores/bookings'
import * as api from '../../services/api'
import RatingStars from '../../components/RatingStars.vue'

const bookingsStore = useBookingsStore()
const reviews = ref([])
const filter = ref('All')

onMounted(async () => {
  await bookingsStore.load()
  reviews.value = await api.getReviews()
})

const FILTERS = ['All', 'requested', 'accepted', 'in_progress', 'completed', 'reviewed']
const shown = computed(() =>
  filter.value === 'All' ? bookingsStore.bookings : bookingsStore.bookings.filter(b => b.status === filter.value))

const STATUS_STYLE = {
  requested: { c: 'var(--fx-warn)', bg: 'var(--fx-warn-soft)' },
  accepted: { c: 'var(--fx-blue)', bg: 'var(--fx-blue-soft)' },
  in_progress: { c: 'var(--fx-blue)', bg: 'var(--fx-blue-soft)' },
  completed: { c: 'var(--fx-success)', bg: 'var(--fx-success-soft)' },
  reviewed: { c: 'var(--fx-muted)', bg: 'var(--fx-border-soft)' },
}

const kpis = computed(() => ({
  bookings: bookingsStore.bookings.length,
  active: bookingsStore.bookings.filter(b => ['accepted', 'in_progress'].includes(b.status)).length,
  avgRating: reviews.value.length
    ? (reviews.value.reduce((s, r) => s + r.rating, 0) / reviews.value.length).toFixed(1)
    : '—',
}))
const label = (s) => s.replace('_', ' ')
</script>

<template>
  <div class="fx-page">
    <h1 class="fw-bold mb-1" style="font-size:20px">Monitoring</h1>
    <div class="mb-3" style="font-size:13px;color:var(--fx-muted)">Bookings &amp; Reviews</div>

    <!-- KPIs -->
    <div class="d-flex gap-2 mb-3">
      <div class="flex-fill text-center" style="background:var(--fx-blue-soft);border-radius:14px;padding:10px 8px">
        <div style="font-size:20px;font-weight:800;color:var(--fx-blue)">{{ kpis.bookings }}</div>
        <div style="font-size:10px;color:var(--fx-blue);font-weight:500">Bookings</div>
      </div>
      <div class="flex-fill text-center" style="background:var(--fx-warn-soft);border-radius:14px;padding:10px 8px">
        <div style="font-size:20px;font-weight:800;color:var(--fx-warn)">{{ kpis.active }}</div>
        <div style="font-size:10px;color:var(--fx-warn);font-weight:500">Active</div>
      </div>
      <div class="flex-fill text-center" style="background:var(--fx-success-soft);border-radius:14px;padding:10px 8px">
        <div style="font-size:20px;font-weight:800;color:var(--fx-success)">{{ kpis.avgRating }}</div>
        <div style="font-size:10px;color:var(--fx-success);font-weight:500">Avg Rating</div>
      </div>
    </div>

    <!-- Filter chips -->
    <div class="d-flex gap-2 mb-3" style="overflow-x:auto">
      <span v-for="f in FILTERS" :key="f" class="fx-chip sm" :class="{ active: filter === f }"
            style="text-transform:capitalize" @click="filter = f">{{ f === 'All' ? 'All' : label(f) }}</span>
    </div>

    <!-- Bookings -->
    <div class="d-flex flex-column gap-2 mb-4">
      <div v-for="b in shown" :key="b.id" class="fx-card" style="padding:12px">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <span class="fw-bold" style="font-size:13px">#{{ b.id }}</span>
          <span class="fw-bold" style="font-size:15px">RM{{ b.total }}</span>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <div style="font-size:12px;color:var(--fx-muted)">
              <span style="color:var(--fx-text);font-weight:500">{{ b.customer?.name }}</span>
              → <span style="color:var(--fx-text);font-weight:500">{{ b.provider?.name }}</span>
            </div>
            <div style="font-size:11px;color:var(--fx-muted);margin-top:2px">{{ b.category?.name }}</div>
          </div>
          <span class="fx-badge" style="text-transform:capitalize"
                :style="{ color: STATUS_STYLE[b.status].c, background: STATUS_STYLE[b.status].bg }">
            {{ label(b.status) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Reviews -->
    <div class="fw-bold mb-2" style="font-size:14px">Recent Reviews</div>
    <div class="d-flex flex-column gap-2">
      <div v-for="r in reviews" :key="r.id" class="fx-card" style="padding:12px">
        <div class="d-flex justify-content-between mb-1">
          <span style="font-size:12px;color:var(--fx-muted)">Job #{{ r.job_id }}</span>
          <RatingStars :rating="r.rating" :size="12" />
        </div>
        <div style="font-size:13px;color:var(--fx-muted);font-style:italic">"{{ r.comment }}"</div>
      </div>
    </div>
  </div>
</template>
