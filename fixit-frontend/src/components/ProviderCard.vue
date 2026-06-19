<script setup>
import { computed } from 'vue'
import RatingStars from './RatingStars.vue'

// Reusable provider list card. Emits 'select' so parents control navigation.
const props = defineProps({
  provider: { type: Object, required: true },
  distance: { type: Number, default: null },
})
defineEmits(['select'])

const initials = computed(() =>
  (props.provider.name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase())
const roleLabel = computed(() => props.provider.category_names?.join(', ') || 'Provider')
</script>

<template>
  <div class="fx-card d-flex align-items-center gap-3" role="button" @click="$emit('select', provider)">
    <div class="fx-avatar" style="width:46px;height:46px;font-size:16px">{{ initials }}</div>
    <div class="flex-grow-1" style="min-width:0">
      <div class="d-flex justify-content-between align-items-start">
        <div class="fw-semibold" style="font-size:14px">{{ provider.name }}</div>
        <div class="fw-bold text-accent" style="font-size:14px">${{ provider.base_rate }}/hr</div>
      </div>
      <div style="font-size:12px;color:var(--fx-muted)">{{ roleLabel }}</div>
      <div class="d-flex align-items-center gap-2 mt-1">
        <RatingStars :rating="provider.avg_rating" :size="12" />
        <span style="font-size:12px;font-weight:600">{{ provider.avg_rating.toFixed(1) }}</span>
        <span style="font-size:11px;color:var(--fx-muted)">({{ provider.review_count }})</span>
        <span v-if="distance != null" style="font-size:11px;color:var(--fx-muted)">• {{ distance.toFixed(1) }}km</span>
      </div>
    </div>
  </div>
</template>
