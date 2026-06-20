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

const ICONS = {
  Plumbing:     plumbing,
  Electrical:   electrical,
  Cleaning:     cleaning,
  Gardening:    gardening,
  'AC Service': acService,
  Moving:       moving,
}
const iconFor = (name) => ICONS[name] || null
</script>

<template>
  <div class="cg-grid">
    <div v-for="c in categories" :key="c.id"
         class="cg-tile liquid-glass" role="button" @click="$emit('select', c)">
      <div class="cg-icon-stage">
        <img v-if="iconFor(c.name)" :src="iconFor(c.name)" :alt="c.name" class="cg-icon-img" />
        <span v-else style="font-size:30px">{{ c.icon_url }}</span>
      </div>
      <span class="cg-label">{{ c.name }}</span>
    </div>
  </div>
</template>

<style scoped>
.cg-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}
.cg-tile {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 6px;
  padding: 20px 12px 16px; border-radius: 24px;
  cursor: pointer;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.cg-tile:hover  { transform: translateY(-3px); }
.cg-tile:active { transform: scale(0.96); }
.cg-icon-stage {
  width: 96px; height: 96px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 6px;
}
.cg-icon-img {
  width: 100%; height: 100%;
  object-fit: contain;
  filter: drop-shadow(0 6px 12px rgba(0,0,0,0.10));
}
.cg-label {
  font-size: 15px; font-weight: 600; text-align: center;
  color: var(--fx-text); letter-spacing: -0.01em;
}
</style>
