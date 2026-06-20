<script setup>
defineProps({ categories: { type: Array, default: () => [] } })
defineEmits(['select'])

const cats = {
  Plumbing:   { icon: 'plumbing',      color: '#1D4ED8' },
  Electrical: { icon: 'electrical_services', color: '#B45309' },
  Cleaning:   { icon: 'cleaning_services',   color: '#15803D' },
  Gardening:  { icon: 'yard',          color: '#166534' },
  'AC Service':{ icon: 'ac_unit',      color: '#1E40AF' },
  Moving:     { icon: 'local_shipping',color: '#FF6635' },
}
const catFor = (name) => cats[name] || { icon: 'build', color: '#FF6635' }
</script>

<template>
  <div class="cg-grid">
    <div v-for="c in categories" :key="c.id"
         class="cg-tile liquid-glass" role="button" @click="$emit('select', c)">
      <div class="cg-icon-wrap" :style="{ color: catFor(c.name).color }">
        <span class="material-symbols-outlined"
              style="font-size:26px;font-variation-settings:'FILL' 1">
          {{ catFor(c.name).icon }}
        </span>
        <span v-if="!cats[c.name]" style="font-size:24px">{{ c.icon_url }}</span>
      </div>
      <span class="cg-label" :style="{ color: catFor(c.name).color }">{{ c.name }}</span>
    </div>
  </div>
</template>

<style scoped>
.cg-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}
.cg-tile {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 8px;
  padding: 16px 8px; border-radius: 20px;
  cursor: pointer;
  transition: transform 0.18s ease;
  min-height: 90px;
}
.cg-tile:hover  { transform: translateY(-3px); }
.cg-tile:active { transform: scale(0.94); }
.cg-icon-wrap {
  width: 44px; height: 44px; border-radius: 14px;
  background: rgba(255,255,255,0.45);
  display: flex; align-items: center; justify-content: center;
  border: 1px solid rgba(255,255,255,0.55);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.60);
}
.cg-label {
  font-size: 11px; font-weight: 700; text-align: center;
  letter-spacing: 0.02em; line-height: 1.3;
}
</style>
