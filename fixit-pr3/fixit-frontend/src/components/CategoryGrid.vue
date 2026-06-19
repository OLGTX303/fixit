<script setup>
// Category tiles for the customer Home view.
defineProps({ categories: { type: Array, default: () => [] } })
defineEmits(['select'])

const tints = {
  Plumbing: { bg: '#EFF6FF', color: '#1D4ED8' },
  Electrical: { bg: '#FFFBEB', color: '#B45309' },
  Cleaning: { bg: '#F0FDF4', color: '#15803D' },
  Gardening: { bg: '#F0FDF4', color: '#166534' },
  'AC Service': { bg: '#EFF6FF', color: '#1E40AF' },
  Moving: { bg: '#FFF2EE', color: '#FF6635' },
}
const tintFor = (name) => tints[name] || { bg: '#FFF2EE', color: '#FF6635' }
</script>

<template>
  <div class="d-grid" style="grid-template-columns:repeat(3,1fr);gap:10px">
    <div v-for="c in categories" :key="c.id" role="button" @click="$emit('select', c)"
         class="d-flex flex-column align-items-center justify-content-center"
         :style="{ background: tintFor(c.name).bg, borderRadius: '14px', padding: '14px 10px', gap: '6px' }">
      <span style="font-size:24px">{{ c.icon_url }}</span>
      <span style="font-size:12px;font-weight:600;text-align:center" :style="{ color: tintFor(c.name).color }">{{ c.name }}</span>
    </div>
  </div>
</template>
