<script setup>
// 3D glass category icons (imported so Vite bundles + fingerprints them,
// which keeps them working under Capacitor's file:// origin on Android).
import plumbing   from '../assets/category-icons/plumbing.png'
import electrical from '../assets/category-icons/electrical.png'
import cleaning   from '../assets/category-icons/cleaning.png'
import gardening  from '../assets/category-icons/gardening.png'
import acService  from '../assets/category-icons/ac-service.png'
import moving     from '../assets/category-icons/moving.png'

defineProps({ categories: { type: Array, default: () => [] } })
defineEmits(['select'])

const META = {
  Plumbing:     { icon: plumbing,   tint: 'rgba(59,130,246,0.14)' },
  Electrical:   { icon: electrical, tint: 'rgba(245,200,40,0.18)' },
  Cleaning:     { icon: cleaning,   tint: 'rgba(34,197,94,0.14)' },
  Gardening:    { icon: gardening,  tint: 'rgba(16,185,129,0.14)' },
  'AC Service': { icon: acService,  tint: 'rgba(56,189,248,0.16)' },
  Moving:       { icon: moving,     tint: 'rgba(168,85,247,0.14)' },
}
const metaFor = (name) => META[name] || { icon: null, tint: 'rgba(255,102,53,0.12)' }
</script>

<template>
  <div class="cg-grid">
    <div v-for="c in categories" :key="c.id"
         class="cg-tile liquid-glass" role="button" @click="$emit('select', c)">
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
  padding: 14px 8px; border-radius: 18px;
  cursor: pointer;
  transition: transform 0.18s ease, background 0.18s ease;
}
.cg-tile:hover  { transform: translateY(-3px); }
.cg-tile:active { transform: scale(0.95); }
.cg-icon-circle {
  width: 58px; height: 58px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  box-shadow: inset 0 2px 6px rgba(0,0,0,0.06), inset 0 1px 1px rgba(255,255,255,0.50);
}
.cg-icon-img {
  width: 48px; height: 48px; object-fit: contain;
  filter: drop-shadow(0 3px 6px rgba(0,0,0,0.12));
}
.cg-label {
  font-size: 13px; font-weight: 600; text-align: center;
  color: var(--fx-text); letter-spacing: -0.01em; line-height: 1.2;
}
/* Tablet/desktop: fan out to 6 across like the widget's lg layout */
@media (min-width: 768px) {
  .cg-grid { grid-template-columns: repeat(6, 1fr); }
}
</style>
