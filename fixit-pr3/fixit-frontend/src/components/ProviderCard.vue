<script setup>
import { computed } from 'vue'
import RatingStars from './RatingStars.vue'

const props = defineProps({
  provider: { type: Object, required: true },
  distance: { type: Number, default: null },
})
defineEmits(['select'])

const initials = computed(() =>
  (props.provider.name || '—').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase())
const roleLabel = computed(() => props.provider.category_names?.join(', ') || 'Provider')
</script>

<template>
  <div class="pc-card liquid-glass" role="button" @click="$emit('select', provider)">
    <div class="pc-avatar">
      <img v-if="provider.avatar_url" :src="provider.avatar_url" :alt="provider.name" />
      <span v-else>{{ initials }}</span>
    </div>
    <div style="flex:1;min-width:0">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:2px">
        <span style="font-size:14px;font-weight:700;color:var(--fx-text)">{{ provider.name }}</span>
        <span style="font-size:14px;font-weight:800;color:var(--fx-accent)">RM{{ provider.base_rate }}/hr</span>
      </div>
      <div style="font-size:12px;color:var(--fx-muted);margin-bottom:5px">{{ roleLabel }}</div>
      <div style="display:flex;align-items:center;gap:6px">
        <RatingStars :rating="provider.avg_rating" :size="12" />
        <span style="font-size:12px;font-weight:700;color:var(--fx-text)">{{ provider.avg_rating.toFixed(1) }}</span>
        <span style="font-size:11px;color:var(--fx-muted)">({{ provider.review_count }})</span>
        <span v-if="distance != null" style="font-size:11px;color:var(--fx-muted)">• {{ distance.toFixed(1) }}km</span>
      </div>
    </div>
    <span class="material-symbols-outlined" style="font-size:18px;color:rgba(142,112,104,0.45);flex-shrink:0">chevron_right</span>
  </div>
</template>

<style scoped>
.pc-card {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 16px; border-radius: 20px;
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.pc-card:hover { transform: translateY(-2px); }
.pc-card:active { transform: scale(0.97); }
.pc-avatar {
  width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px; font-weight: 800;
  background: linear-gradient(135deg, rgba(255,102,53,0.18), rgba(255,181,159,0.14));
  color: var(--fx-accent);
  border: 2px solid rgba(255,255,255,0.65);
  box-shadow: 0 2px 8px rgba(255,102,53,0.12);
  overflow: hidden;
}
.pc-avatar img { width: 100%; height: 100%; object-fit: cover; }
</style>
