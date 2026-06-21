<script setup>
import { computed } from 'vue'
// 3D / illustrated category icons — Vite bundles + fingerprints them so they
// work under Capacitor's file:// origin on Android.
import plumbing    from '../assets/category-icons/plumbing.png'
import electrical  from '../assets/category-icons/electrical.png'
import cleaning    from '../assets/category-icons/cleaning.png'
import gardening   from '../assets/category-icons/gardening.png'
import acService   from '../assets/category-icons/ac-service.png'
import moving      from '../assets/category-icons/moving.png'
// New promotional categories
import painting    from '../assets/category-icons/painting.svg'
import pestControl from '../assets/category-icons/pest-control.svg'
import security    from '../assets/category-icons/security.svg'
import roofing     from '../assets/category-icons/roofing.svg'
import carpentry   from '../assets/category-icons/carpentry.svg'
import poolService from '../assets/category-icons/pool-service.svg'
import handyman    from '../assets/category-icons/handyman.svg'

const props = defineProps({ categories: { type: Array, default: () => [] } })
defineEmits(['select'])

// Metadata for API-driven categories (keyed by name)
const META = {
  Plumbing:     { icon: plumbing,    tint: 'rgba(59,130,246,0.14)' },
  Electrical:   { icon: electrical,  tint: 'rgba(245,200,40,0.18)' },
  Cleaning:     { icon: cleaning,    tint: 'rgba(34,197,94,0.14)' },
  Gardening:    { icon: gardening,   tint: 'rgba(16,185,129,0.14)' },
  'AC Service': { icon: acService,   tint: 'rgba(56,189,248,0.16)' },
  Moving:       { icon: moving,      tint: 'rgba(168,85,247,0.14)' },
  // New entries — also used if backend ever returns these names
  Painting:         { icon: painting,    tint: 'rgba(255,102,53,0.13)' },
  'Pest Control':   { icon: pestControl, tint: 'rgba(34,197,94,0.13)' },
  Security:         { icon: security,    tint: 'rgba(59,130,246,0.14)' },
  Roofing:          { icon: roofing,     tint: 'rgba(245,158,11,0.16)' },
  Carpentry:        { icon: carpentry,   tint: 'rgba(146,100,50,0.14)' },
  'Pool Service':   { icon: poolService, tint: 'rgba(14,165,233,0.16)' },
  Handyman:         { icon: handyman,    tint: 'rgba(249,115,22,0.14)' },
}

// Extra tiles always shown after the API-driven list (so the grid always
// has at least 12 entries and showcases all service types for promo).
const EXTRA = [
  { id: 'painting',      name: 'Painting',      icon_url: '' },
  { id: 'pest-control',  name: 'Pest Control',  icon_url: '' },
  { id: 'security',      name: 'Security',      icon_url: '' },
  { id: 'roofing',       name: 'Roofing',       icon_url: '' },
  { id: 'carpentry',     name: 'Carpentry',     icon_url: '' },
  { id: 'pool-service',  name: 'Pool Service',  icon_url: '' },
  { id: 'handyman',      name: 'Handyman',      icon_url: '' },
]

// Merge API categories with extras, de-duplicating by name
const allCategories = computed(() => {
  const names = new Set(props.categories.map(c => c.name))
  const filtered = EXTRA.filter(e => !names.has(e.name))
  return [...props.categories, ...filtered]
})

const metaFor = (name) => META[name] || { icon: null, tint: 'rgba(255,102,53,0.12)' }
</script>

<template>
  <div class="cg-grid">
    <div v-for="c in allCategories" :key="c.id"
         class="cg-tile liquid-glass lg-interactive" role="button" @click="$emit('select', c)">
      <div class="cg-icon-circle" :style="{ background: metaFor(c.name).tint }">
        <img v-if="metaFor(c.name).icon" :src="metaFor(c.name).icon" :alt="c.name" class="cg-icon-img" />
        <span v-else style="font-size:24px">{{ c.icon_url }}</span>
      </div>
      <span class="cg-label">{{ c.name }}</span>
    </div>
  </div>
</template>

<style scoped>
.cg-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}
.cg-tile {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 8px;
  padding: 16px 8px; border-radius: 20px;
  cursor: pointer;
  transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.22s ease;
}
.cg-tile:hover  { transform: translateY(-4px) scale(1.03); }
.cg-tile:active { transform: scale(0.95); }
.cg-icon-circle {
  width: 60px; height: 60px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  box-shadow:
    inset 0 2px 6px rgba(0,0,0,0.06),
    inset 0 1px 2px rgba(255,255,255,0.60),
    0 2px 8px rgba(0,0,0,0.06);
  transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1);
}
.cg-tile:hover .cg-icon-circle { transform: scale(1.10) translateY(-2px); }
.cg-icon-img {
  width: 48px; height: 48px; object-fit: contain;
  filter: drop-shadow(0 4px 8px rgba(0,0,0,0.14));
  transition: filter 0.2s ease;
}
.cg-tile:hover .cg-icon-img {
  filter: drop-shadow(0 6px 12px rgba(0,0,0,0.20));
}
.cg-label {
  font-size: 12px; font-weight: 700; text-align: center;
  color: var(--fx-text); letter-spacing: -0.01em; line-height: 1.2;
}
/* Desktop: 6-column layout */
@media (min-width: 768px) {
  .cg-grid { grid-template-columns: repeat(6, 1fr); }
}
</style>
