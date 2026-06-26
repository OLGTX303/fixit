<script setup>
import { computed } from 'vue'
import plumbing   from '../assets/category-icons/plumbing.png'
import electrical from '../assets/category-icons/electrical.png'
import cleaning   from '../assets/category-icons/cleaning.png'
import gardening  from '../assets/category-icons/gardening.png'
import acService  from '../assets/category-icons/ac-service.png'
import moving     from '../assets/category-icons/moving.png'

// Horizontal "Top Rated Nearby" card matching the home widget's snap cards.
const props = defineProps({ provider: { type: Object, required: true } })
defineEmits(['select'])

const ICONS = {
  Plumbing: plumbing, Electrical: electrical, Cleaning: cleaning,
  Gardening: gardening, 'AC Service': acService, Moving: moving,
}
const TINTS = {
  Plumbing: 'linear-gradient(135deg,#dbeafe,#eff6ff)',
  Electrical: 'linear-gradient(135deg,#fef3c7,#fffbeb)',
  Cleaning: 'linear-gradient(135deg,#dcfce7,#f0fdf4)',
  Gardening: 'linear-gradient(135deg,#d1fae5,#ecfdf5)',
  'AC Service': 'linear-gradient(135deg,#e0f2fe,#f0f9ff)',
  Moving: 'linear-gradient(135deg,#f3e8ff,#faf5ff)',
}
const primaryCat = computed(() => props.provider.category_names?.[0] || '')
const headerImg   = computed(() => ICONS[primaryCat.value] || null)
const headerTint  = computed(() => TINTS[primaryCat.value] || 'linear-gradient(135deg,#ffe6dd,#fff2ee)')
const roleLabel   = computed(() => props.provider.category_names?.join(', ') || 'Provider')
const initials    = computed(() =>
  (props.provider.name || '—').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase())
</script>

<template>
  <div class="pcw liquid-glass" role="button" @click="$emit('select', provider)">
    <!-- Service header with the category 3D icon -->
    <div class="pcw-header" :style="{ background: headerTint }">
      <img v-if="headerImg" :src="headerImg" :alt="primaryCat" class="pcw-header-img" />
      <div class="pcw-rating">
        <span class="material-symbols-outlined" style="font-size:14px;color:#f59e0b;font-variation-settings:'FILL' 1">star</span>
        <span style="font-size:12px;font-weight:700">{{ provider.avg_rating.toFixed(1) }}</span>
      </div>
      <!-- Priority listing badge (stretch goal) -->
      <div v-if="provider.is_priority" class="pcw-priority">
        <span class="material-symbols-outlined" style="font-size:12px;font-variation-settings:'FILL' 1">bolt</span>
        Priority
      </div>
    </div>

    <!-- Provider identity -->
    <div class="pcw-body">
      <div class="pcw-avatar">
        <img v-if="provider.avatar_url" :src="provider.avatar_url" alt="" />
        <span v-else>{{ initials }}</span>
      </div>
      <div style="min-width:0;flex:1">
        <div class="pcw-name">{{ provider.name }}</div>
        <div class="pcw-role">{{ roleLabel }}</div>
      </div>
    </div>

    <div class="pcw-foot">
      <div class="pcw-price">
        RM {{ provider.rate_type === 'per_job' ? provider.per_job_rate : provider.base_rate }}
        <span class="pcw-price-unit">{{ provider.rate_type === 'per_job' ? '/job' : '/hr' }}</span>
      </div>
      <button class="btn btn-primary btn-sm pcw-book" type="button" @click.stop="$emit('select', provider)">Book</button>
    </div>
  </div>
</template>

<style scoped>
.pcw {
  flex: none;
  width: 260px;
  border-radius: 24px;
  padding: 12px;
  scroll-snap-align: start;
  transition: transform 0.18s ease;
}
.pcw:active { transform: scale(0.98); }
.pcw-header {
  position: relative;
  height: 130px; width: 100%;
  border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  overflow: hidden;
  margin-bottom: 28px;
}
.pcw-header-img {
  width: 96px; height: 96px; object-fit: contain;
  filter: drop-shadow(0 8px 14px rgba(0,0,0,0.12));
}
.pcw-rating {
  position: absolute; top: 10px; right: 10px;
  display: flex; align-items: center; gap: 3px;
  padding: 4px 9px; border-radius: 999px;
  background: rgba(255,255,255,0.70);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255,255,255,0.60);
  box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.pcw-body {
  display: flex; align-items: center; gap: 12px;
  margin-top: -52px; margin-bottom: 14px; padding: 0 2px;
  position: relative; z-index: 1;
}
.pcw-avatar {
  width: 48px; height: 48px; border-radius: 50%; flex-shrink: 0;
  border: 2px solid rgba(255,255,255,0.90);
  box-shadow: 0 4px 10px rgba(0,0,0,0.12);
  overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, rgba(255,102,53,0.20), rgba(255,181,159,0.15));
  color: var(--fx-accent); font-weight: 800; font-size: 15px;
}
.pcw-avatar img { width: 100%; height: 100%; object-fit: cover; }
.pcw-name { font-size: 15px; font-weight: 700; color: var(--fx-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pcw-role { font-size: 12px; color: var(--fx-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pcw-foot { display: flex; align-items: center; justify-content: space-between; padding: 0 2px; }
.pcw-price { font-size: 18px; font-weight: 700; color: var(--fx-accent); }
.pcw-price-unit { font-size: 12px; font-weight: 400; color: var(--fx-muted); }
.pcw-book { padding: 7px 18px; font-size: 12px; }
.pcw-priority {
  position: absolute; bottom: 10px; left: 10px;
  display: flex; align-items: center; gap: 3px;
  padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700;
  background: rgba(255,190,0,0.85);
  backdrop-filter: blur(8px);
  color: #7a5200;
  border: 1px solid rgba(255,220,80,0.60);
  box-shadow: 0 2px 8px rgba(255,190,0,0.25);
}
</style>
