<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProvidersStore } from '../../stores/providers'
import * as api from '../../services/api'
import RatingStars from '../../components/RatingStars.vue'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const providersStore = useProvidersStore()

const reviews = ref([])
const provider = computed(() => providersStore.byId(route.params.id))

onMounted(async () => {
  await providersStore.load()
  reviews.value = await api.getReviewsForProvider(route.params.id)
})

const initials = computed(() =>
  (provider.value?.name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase())
</script>

<template>
  <div v-if="provider" class="fx-page">
    <button class="btn btn-light rounded-circle mb-2" style="width:34px;height:34px;padding:0" @click="router.back()">
      <AppIcon name="back" :size="16" />
    </button>

    <div class="d-flex justify-content-between align-items-start">
      <div class="d-flex gap-3">
        <div class="fx-avatar" style="width:64px;height:64px;font-size:24px">{{ initials }}</div>
        <div>
          <div class="d-flex align-items-center gap-2">
            <span style="font-size:20px;font-weight:800">{{ provider.name }}</span>
            <span v-if="provider.is_verified" class="d-inline-flex align-items-center justify-content-center"
                  style="width:18px;height:18px;border-radius:50%;background:var(--fx-success-soft);color:var(--fx-success)">
              <AppIcon name="check" :size="12" />
            </span>
          </div>
          <div style="font-size:13px;color:var(--fx-muted)">{{ provider.category_names.join(', ') }}</div>
          <div class="d-flex align-items-center gap-1 mt-1" style="font-size:13px;color:var(--fx-muted)">
            <AppIcon name="location" :size="14" /> {{ provider.location }}
          </div>
        </div>
      </div>
      <div class="text-end">
        <div style="font-size:22px;font-weight:800;color:var(--fx-accent)">${{ provider.base_rate }}</div>
        <div style="font-size:12px;color:var(--fx-muted)">/hour</div>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2 mt-3">
      <RatingStars :rating="provider.avg_rating" :size="15" />
      <span class="fw-semibold" style="font-size:13px">{{ provider.avg_rating.toFixed(1) }}</span>
      <span style="font-size:13px;color:var(--fx-muted)">({{ provider.review_count }} reviews)</span>
    </div>

    <p class="mt-3 mb-4" style="font-size:14px;color:var(--fx-muted);line-height:1.6">{{ provider.bio }}</p>

    <div class="fw-bold mb-2" style="font-size:14px">Services Offered</div>
    <div class="d-flex flex-wrap gap-2 mb-4">
      <span v-for="s in provider.services" :key="s" class="fx-chip sm">{{ s }}</span>
    </div>

    <div class="fw-bold mb-2" style="font-size:14px">Recent Reviews</div>
    <div v-for="r in reviews" :key="r.id" class="fx-card mb-2">
      <div class="d-flex justify-content-between align-items-center mb-1">
        <RatingStars :rating="r.rating" :size="12" />
        <span style="font-size:11px;color:var(--fx-muted)">{{ new Date(r.created_at).toLocaleDateString() }}</span>
      </div>
      <div style="font-size:13px;color:var(--fx-muted);line-height:1.5">{{ r.comment }}</div>
    </div>
    <div v-if="!reviews.length" class="text-muted mb-3" style="font-size:13px">No reviews yet.</div>

    <button class="btn btn-primary w-100 mt-2" @click="router.push({ name: 'booking-form', params: { id: provider.id } })">
      Book Now — ${{ provider.base_rate }}/hr
    </button>
  </div>

  <div v-else class="fx-page text-center py-5" style="color:var(--fx-muted)">Loading provider…</div>
</template>
