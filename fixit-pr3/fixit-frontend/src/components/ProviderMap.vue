<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import L from 'leaflet'

// Leaflet + OpenStreetMap map of providers. Markers re-render whenever the
// filtered `providers` prop changes (reactive watch).
const props = defineProps({
  providers: { type: Array, default: () => [] },
  center: { type: Array, default: () => [51.5074, -0.1278] },
})
const emit = defineEmits(['select'])

const mapEl = ref(null)
let map = null
let markerLayer = null

// Custom orange teardrop marker matching the brand.
function pinIcon() {
  return L.divIcon({
    className: 'fx-pin',
    html: `<div style="width:24px;height:24px;border-radius:50% 50% 50% 0;background:#FF6635;
            border:2px solid #E04F20;transform:rotate(-45deg);box-shadow:0 2px 5px rgba(0,0,0,.3)"></div>`,
    iconSize: [24, 24],
    iconAnchor: [12, 24],
  })
}

function renderMarkers() {
  if (!map) return
  if (markerLayer) markerLayer.remove()
  markerLayer = L.layerGroup().addTo(map)
  props.providers.forEach(p => {
    if (p.latitude == null || p.longitude == null) return
    const m = L.marker([p.latitude, p.longitude], { icon: pinIcon() })
      .bindPopup(`<b>${p.name}</b><br>$${p.base_rate}/hr · ★ ${p.avg_rating.toFixed(1)}`)
    m.on('click', () => emit('select', p))
    m.addTo(markerLayer)
  })
}

onMounted(() => {
  map = L.map(mapEl.value, { zoomControl: true }).setView(props.center, 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map)
  // User location dot at the city centre.
  L.circleMarker(props.center, { radius: 7, color: '#3B82F6', fillColor: '#3B82F6', fillOpacity: 1, weight: 3 })
    .addTo(map)
  renderMarkers()
})

watch(() => props.providers, renderMarkers, { deep: true })
onBeforeUnmount(() => map && map.remove())
</script>

<template>
  <div ref="mapEl" class="fx-map"></div>
</template>
