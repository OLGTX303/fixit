<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import * as api from '../../services/api'
import RatingStars from '../../components/RatingStars.vue'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const bookingsStore = useBookingsStore()

const booking = computed(() => bookingsStore.byId(route.params.id))

// Review form (v-model bound).
const rating = ref(5)
const tags = ['On Time', 'Professional', 'Quality Work', 'Great Value', 'Friendly', 'Clean']
const selectedTags = ref(['On Time', 'Professional', 'Quality Work'])
const comment = ref('')
const submitting = ref(false)

onMounted(() => bookingsStore.load())

function toggleTag(t) {
  const i = selectedTags.value.indexOf(t)
  i === -1 ? selectedTags.value.push(t) : selectedTags.value.splice(i, 1)
}

async function submit() {
  submitting.value = true
  await api.createReview({
    job_id: booking.value.id,
    rating: rating.value,
    comment: comment.value || selectedTags.value.join(', '),
  })
  await bookingsStore.advanceStatus(booking.value.id, 'reviewed')
  submitting.value = false
  router.push({ name: 'job-tracker', query: { id: booking.value.id } })
}
</script>

<template>
  <div v-if="booking" class="fx-page" style="max-width:480px">
    <div class="d-flex align-items-center gap-2 mb-4">
      <button class="btn btn-light rounded-circle" style="width:34px;height:34px;padding:0" @click="router.back()">
        <AppIcon name="back" :size="16" />
      </button>
      <h1 class="fw-bold m-0" style="font-size:20px">Rate Experience</h1>
    </div>

    <div class="text-center mb-4">
      <div class="fx-avatar mx-auto mb-2" style="width:64px;height:64px;font-size:24px">
        {{ (booking.provider?.name || '?').split(' ').map(w => w[0]).join('') }}
      </div>
      <div class="fw-bold" style="font-size:18px">{{ booking.provider?.name }}</div>
      <div style="font-size:13px;color:var(--fx-muted)">{{ booking.category?.name }} · #{{ booking.id }}</div>
    </div>

    <div class="text-center mb-4">
      <div class="mb-2" style="font-size:15px;font-weight:600;color:var(--fx-muted)">How was the service?</div>
      <div class="d-flex justify-content-center">
        <RatingStars interactive v-model="rating" :size="38" />
      </div>
    </div>

    <div class="fw-semibold mb-2" style="font-size:14px">What stood out?</div>
    <div class="d-flex flex-wrap gap-2 mb-4">
      <span v-for="t in tags" :key="t" class="fx-chip sm" :class="{ active: selectedTags.includes(t) }"
            @click="toggleTag(t)">{{ t }}</span>
    </div>

    <div class="fw-semibold mb-2" style="font-size:14px">Write a Review</div>
    <textarea class="fx-input mb-4" rows="3" v-model="comment"
              placeholder="Tell others about your experience…" style="resize:none"></textarea>

    <button class="btn btn-primary w-100" :disabled="submitting" @click="submit">
      {{ submitting ? 'Submitting…' : 'Submit Review' }}
    </button>
    <div class="text-center mt-3">
      <span role="button" style="font-size:13px;color:var(--fx-muted)" @click="router.back()">Skip for now</span>
    </div>
  </div>

  <div v-else class="fx-page text-center py-5" style="color:var(--fx-muted)">Loading…</div>
</template>
