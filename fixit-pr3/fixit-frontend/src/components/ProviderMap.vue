<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { loadGoogleMaps } from '../services/googleMaps'

// Google Maps map of providers. Markers re-render whenever the filtered
// `providers` prop changes. The Maps API key is served by the backend.
const props = defineProps({
  providers: { type: Array, default: () => [] },
  center: { type: Array, default: () => [1.4927, 103.7414] },
})
const emit = defineEmits(['select'])

const mapEl = ref(null)
const error = ref('')
let gmaps = null
let map = null
let markers = []
let userMarker = null
let infoWindow = null

function clearMarkers() {
  markers.forEach((m) => m.setMap(null))
  markers = []
}

function renderMarkers() {
  if (!map || !gmaps) return
  clearMarkers()
  props.providers.forEach((p) => {
    if (p.latitude == null || p.longitude == null) return
    const marker = new gmaps.Marker({
      position: { lat: Number(p.latitude), lng: Number(p.longitude) },
      map,
      title: p.name,
      icon: {
        path: gmaps.SymbolPath.CIRCLE,
        scale: 9,
        fillColor: '#FF6635',
        fillOpacity: 1,
        strokeColor: '#E04F20',
        strokeWeight: 2,
      },
    })
    marker.addListener('click', () => {
      infoWindow.setContent(
        `<div style="font-weight:700">${p.name}</div>` +
        `<div style="font-size:12px">RM${p.base_rate}/hr · ★ ${Number(p.avg_rating).toFixed(1)}</div>`,
      )
      infoWindow.open(map, marker)
      emit('select', p)
    })
    markers.push(marker)
  })
}

function placeUser() {
  if (!map || !gmaps) return
  const pos = { lat: props.center[0], lng: props.center[1] }
  if (userMarker) {
    userMarker.setPosition(pos)
  } else {
    userMarker = new gmaps.Marker({
      position: pos,
      map,
      title: 'Your location',
      zIndex: 999,
      icon: {
        path: gmaps.SymbolPath.CIRCLE,
        scale: 7,
        fillColor: '#3B82F6',
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 3,
      },
    })
  }
}

onMounted(async () => {
  try {
    gmaps = await loadGoogleMaps()
    map = new gmaps.Map(mapEl.value, {
      center: { lat: props.center[0], lng: props.center[1] },
      zoom: 13,
      disableDefaultUI: false,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false,
    })
    infoWindow = new gmaps.InfoWindow()
    placeUser()
    renderMarkers()
  } catch (e) {
    error.value = e.message
  }
})

watch(() => props.providers, renderMarkers, { deep: true })
watch(() => props.center, () => {
  if (map) map.setCenter({ lat: props.center[0], lng: props.center[1] })
  placeUser()
})

onBeforeUnmount(() => {
  clearMarkers()
  if (userMarker) userMarker.setMap(null)
})
</script>

<template>
  <div class="fx-map-wrap">
    <div ref="mapEl" class="fx-map"></div>
    <div v-if="error" class="fx-map-error">{{ error }}</div>
  </div>
</template>

<style scoped>
/* Isolate the map's stacking context. Google Maps renders tiles/controls with
   very high z-index values; without isolation they paint over the fixed bottom
   nav when the page scrolls. `isolation: isolate` + z-index:0 confines them. */
.fx-map-wrap { position: relative; z-index: 0; isolation: isolate; }
.fx-map-error {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  background: var(--fx-border-soft); border-radius: 16px; color: var(--fx-muted);
  font-size: 13px; text-align: center; padding: 16px;
}
</style>
