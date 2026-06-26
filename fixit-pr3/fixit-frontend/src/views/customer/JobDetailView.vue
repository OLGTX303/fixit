<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useAuthStore } from '../../stores/auth'
import { loadGoogleMaps } from '../../services/googleMaps'
import StatusTimeline from '../../components/StatusTimeline.vue'

const route  = useRoute()
const router = useRouter()
const bookingsStore = useBookingsStore()
const auth = useAuthStore()

const mapEl  = ref(null)
const mapErr = ref('')
let map = null, gmaps = null
let pickupMarker = null, destMarker = null, providerMarker = null
let routeLine = null
let animTimer = null

onMounted(async () => {
  await bookingsStore.load()
  initMap()
})

onBeforeUnmount(() => { if (animTimer) clearInterval(animTimer) })

const booking = computed(() => bookingsStore.byId(Number(route.params.id)))
const isMoving = computed(() => booking.value?.category?.name?.toLowerCase().includes('mov'))

// Simulated KL area coordinates
const PICKUP = { lat: 3.1370, lng: 101.6869 }
const DEST   = { lat: 3.1590, lng: 101.7120 }

// Provider starts ~40% along the route
const provPos = ref({ lat: PICKUP.lat + (DEST.lat - PICKUP.lat) * 0.4,
                      lng: PICKUP.lng + (DEST.lng - PICKUP.lng) * 0.4 })

const eta = ref(18) // minutes
const distLeft = ref('2.3')

async function initMap() {
  if (!mapEl.value) return
  try {
    gmaps = await loadGoogleMaps()
    const center = isMoving.value
      ? { lat: (PICKUP.lat + DEST.lat) / 2, lng: (PICKUP.lng + DEST.lng) / 2 }
      : { lat: PICKUP.lat, lng: PICKUP.lng }

    map = new gmaps.Map(mapEl.value, {
      center,
      zoom: isMoving.value ? 13 : 15,
      disableDefaultUI: true,
      zoomControl: true,
      styles: [
        { featureType: 'poi', stylers: [{ visibility: 'off' }] },
        { featureType: 'transit', stylers: [{ visibility: 'off' }] },
      ],
    })

    if (isMoving.value) {
      drawMovingRoute()
    } else {
      drawServiceLocation()
    }
  } catch (e) {
    mapErr.value = 'Map unavailable'
  }
}

function makeLabel(text, color) {
  return {
    text,
    color: '#fff',
    fontSize: '13px',
    fontWeight: 'bold',
  }
}

function drawMovingRoute() {
  // Pickup marker (green)
  pickupMarker = new gmaps.Marker({
    position: PICKUP, map,
    label: makeLabel('—'),
    icon: {
      path: gmaps.SymbolPath.CIRCLE,
      scale: 18,
      fillColor: '#22c55e',
      fillOpacity: 1,
      strokeColor: '#fff',
      strokeWeight: 3,
    },
    title: 'Pickup',
    zIndex: 2,
  })

  // Destination marker (orange)
  destMarker = new gmaps.Marker({
    position: DEST, map,
    label: makeLabel('—'),
    icon: {
      path: gmaps.SymbolPath.CIRCLE,
      scale: 18,
      fillColor: '#FF6635',
      fillOpacity: 1,
      strokeColor: '#fff',
      strokeWeight: 3,
    },
    title: 'Destination',
    zIndex: 2,
  })

  // Route polyline
  routeLine = new gmaps.Polyline({
    path: [PICKUP, { lat: PICKUP.lat + 0.008, lng: PICKUP.lng + 0.012 },
                   { lat: PICKUP.lat + 0.018, lng: PICKUP.lng + 0.018 }, DEST],
    geodesic: true,
    strokeColor: '#FF6635',
    strokeOpacity: 0.85,
    strokeWeight: 5,
    map,
  })

  // Provider truck marker
  providerMarker = new gmaps.Marker({
    position: provPos.value,
    map,
    icon: {
      path: 'M -14,-8 L 14,-8 L 14,8 L -14,8 Z',
      fillColor: '#3b82f6',
      fillOpacity: 1,
      strokeColor: '#fff',
      strokeWeight: 2,
      scale: 1.2,
    },
    title: 'Provider',
    zIndex: 10,
  })

  // Animate provider along route
  let progress = 0.4
  animTimer = setInterval(() => {
    progress = Math.min(progress + 0.003, 0.95)
    const lat = PICKUP.lat + (DEST.lat - PICKUP.lat) * progress
    const lng = PICKUP.lng + (DEST.lng - PICKUP.lng) * progress
    provPos.value = { lat, lng }
    providerMarker.setPosition(provPos.value)
    distLeft.value = ((1 - progress) * 3.8).toFixed(1)
    eta.value = Math.max(1, Math.round((1 - progress) * 35))
  }, 1500)
}

function drawServiceLocation() {
  // Provider at job site
  providerMarker = new gmaps.Marker({
    position: PICKUP,
    map,
    icon: {
      path: gmaps.SymbolPath.CIRCLE,
      scale: 14,
      fillColor: '#FF6635',
      fillOpacity: 1,
      strokeColor: '#fff',
      strokeWeight: 3,
    },
    title: 'Provider location',
    zIndex: 10,
  })

  // Pulse animation circle
  new gmaps.Circle({
    center: PICKUP,
    radius: 120,
    fillColor: '#FF6635',
    fillOpacity: 0.12,
    strokeColor: '#FF6635',
    strokeOpacity: 0.35,
    strokeWeight: 1,
    map,
  })
}

const STATUS_STEPS = [
  { key: 'requested',   label: 'Booking Requested', icon: 'calendar_month' },
  { key: 'accepted',    label: 'Provider Accepted',  icon: 'check_circle' },
  { key: 'in_progress', label: 'Work In Progress',   icon: 'construction' },
  { key: 'completed',   label: 'Job Completed',       icon: 'verified' },
]

function stepDone(key) {
  const flow = ['requested','accepted','in_progress','completed','reviewed']
  const cur  = flow.indexOf(booking.value?.status)
  return flow.indexOf(key) < cur
}
function stepActive(key) { return booking.value?.status === key }
</script>

<template>
  <div class="jd-root fx-view-root">
    <!-- Top bar -->
    <header class="jd-topbar liquid-glass">
      <button class="jd-back" @click="router.back()">
        <span class="material-symbols-outlined">arrow_back_ios</span>
      </button>
      <span class="jd-topbar-title">Job Tracker</span>
      <button class="jd-chat-btn" @click="router.push({ name: 'chat', params: { id: route.params.id } })">
        <span class="material-symbols-outlined">chat</span>
      </button>
    </header>

    <div v-if="!booking" class="jd-loading">
      <span class="material-symbols-outlined" style="font-size:48px;opacity:.25">search_off</span>
      <p>Booking not found.</p>
    </div>

    <template v-else>
      <!-- ── Moving: delivery map ── -->
      <template v-if="isMoving">
        <!-- ETA banner -->
        <div class="jd-eta-bar liquid-glass">
          <span class="material-symbols-outlined" style="color:var(--fx-accent)">schedule</span>
          <span class="jd-eta-text">Arriving in <strong>{{ eta }} min</strong></span>
          <span class="jd-eta-dist">{{ distLeft }}km away</span>
          <span class="jd-eta-price accent">RM {{ booking.total_price || booking.provider?.base_rate || '—' }}</span>
        </div>

        <!-- Map -->
        <div class="jd-map-wrap">
          <div v-if="mapErr" class="jd-map-err">{{ mapErr }}</div>
          <div ref="mapEl" class="jd-map"></div>
          <!-- Legend -->
          <div class="jd-map-legend">
            <span class="jd-leg-dot green"></span><span class="jd-leg-label">Pickup</span>
            <span class="jd-leg-dot orange"></span><span class="jd-leg-label">Destination</span>
            <span class="jd-leg-dot blue"></span><span class="jd-leg-label">Provider</span>
          </div>
        </div>

        <!-- Route summary -->
        <div class="jd-route liquid-glass">
          <div class="jd-route-row pickup">
            <span class="jd-route-dot green"></span>
            <div class="jd-route-info">
              <span class="jd-route-lbl">Pickup</span>
              <span class="jd-route-addr">{{ booking.address || booking.notes || 'Service address, Johor' }}</span>
            </div>
          </div>
          <div class="jd-route-line"></div>
          <div class="jd-route-row">
            <span class="jd-route-dot orange"></span>
            <div class="jd-route-info">
              <span class="jd-route-lbl">Destination</span>
              <span class="jd-route-addr">Delivery destination, KL</span>
            </div>
          </div>
        </div>
      </template>

      <!-- ── Other services: progress + location map ── -->
      <template v-else>
        <!-- Progress steps (Meituan style) -->
        <div class="jd-progress liquid-glass">
          <h3 class="jd-section-title">Job Progress</h3>
          <div class="jd-steps">
            <div v-for="(step, i) in STATUS_STEPS" :key="step.key" class="jd-step">
              <div class="jd-step-left">
                <div class="jd-step-icon"
                     :class="{ done: stepDone(step.key), active: stepActive(step.key) }">
                  <span class="material-symbols-outlined" style="font-size:16px;font-variation-settings:'FILL' 1">
                    {{ stepDone(step.key) ? 'check' : step.icon }}
                  </span>
                </div>
                <div v-if="i < STATUS_STEPS.length - 1" class="jd-step-line"
                     :class="{ done: stepDone(step.key) }"></div>
              </div>
              <div class="jd-step-body">
                <span class="jd-step-label"
                      :class="{ done: stepDone(step.key), active: stepActive(step.key) }">
                  {{ step.label }}
                </span>
                <span class="jd-step-sub">
                  {{ stepDone(step.key) ? 'Completed' : stepActive(step.key) ? 'In progress…' : 'Pending' }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Provider location map -->
        <div class="jd-section-title-row">
          <h3 class="jd-section-title">Provider Location</h3>
        </div>
        <div class="jd-map-wrap small">
          <div v-if="mapErr" class="jd-map-err">{{ mapErr }}</div>
          <div ref="mapEl" class="jd-map"></div>
        </div>
      </template>

      <!-- Provider info strip -->
      <div class="jd-provider-strip liquid-glass">
        <div class="jd-prov-avatar">
          {{ (booking.provider?.name||'?').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase() }}
        </div>
        <div class="jd-prov-info">
          <span class="jd-prov-name">{{ booking.provider?.name }}</span>
          <span class="jd-prov-cat">{{ booking.category?.name }}</span>
        </div>
        <div class="jd-prov-actions">
          <button class="jd-action-btn"
                  @click="router.push({ name: 'chat', params: { id: route.params.id } })">
            <span class="material-symbols-outlined">chat</span>
          </button>
          <a class="jd-action-btn" href="tel:+60123456789">
            <span class="material-symbols-outlined">call</span>
          </a>
        </div>
      </div>

      <!-- Rate & Review button (when done) -->
      <div v-if="booking.status === 'completed'" style="padding:12px 16px 24px">
        <button class="jd-rate-btn"
                @click="router.push({ name: 'rate-review', params: { id: route.params.id } })">
          <span class="material-symbols-outlined" style="font-size:18px">star</span>
          Rate & Review
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
.jd-root { min-height: 100vh; padding-bottom: 100px; }

/* Top bar */
.jd-topbar {
  position: sticky; top: 0; z-index: 30;
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px; border-radius: 0;
  border-bottom: 1px solid rgba(255,255,255,0.40);
}
.jd-back {
  background: none; border: none; cursor: pointer; padding: 4px;
  display: flex; align-items: center; color: var(--fx-accent);
}
.jd-back .material-symbols-outlined { font-size: 22px; }
.jd-topbar-title { flex: 1; font-size: 17px; font-weight: 700; color: var(--fx-text); }
.jd-chat-btn {
  background: rgba(255,102,53,0.10); border: none; cursor: pointer;
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; color: var(--fx-accent);
}
.jd-chat-btn .material-symbols-outlined { font-size: 20px; }

.jd-loading {
  display: flex; flex-direction: column; align-items: center;
  gap: 10px; padding: 64px; color: var(--fx-muted);
}

/* ETA bar */
.jd-eta-bar {
  display: flex; align-items: center; gap: 10px;
  margin: 12px; padding: 12px 16px; border-radius: 14px;
}
.jd-eta-text { flex: 1; font-size: 14px; color: var(--fx-text); }
.jd-eta-dist { font-size: 12px; color: var(--fx-muted); }
.jd-eta-price { font-size: 16px; font-weight: 800; color: var(--fx-accent); }

/* Map */
.jd-map-wrap { position: relative; margin: 0 12px 12px; border-radius: 18px; overflow: hidden; height: 260px; }
.jd-map-wrap.small { height: 180px; }
.jd-map { width: 100%; height: 100%; }
.jd-map-err {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  background: rgba(0,0,0,0.04); color: var(--fx-muted); font-size: 14px;
}
.jd-map-legend {
  position: absolute; bottom: 10px; left: 10px;
  display: flex; align-items: center; gap: 8px;
  background: rgba(255,255,255,0.85); backdrop-filter: blur(8px);
  border-radius: 8px; padding: 6px 10px; font-size: 11px; font-weight: 600; color: var(--fx-text);
}
.jd-leg-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.jd-leg-dot.green  { background: #22c55e; }
.jd-leg-dot.orange { background: #FF6635; }
.jd-leg-dot.blue   { background: #3b82f6; }
.jd-leg-label { margin-right: 4px; }

/* Route summary */
.jd-route { margin: 0 12px 12px; border-radius: 16px; padding: 16px; }
.jd-route-row { display: flex; align-items: flex-start; gap: 12px; }
.jd-route-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.jd-route-dot.green  { background: #22c55e; }
.jd-route-dot.orange { background: #FF6635; }
.jd-route-info { flex: 1; min-width: 0; }
.jd-route-lbl  { display: block; font-size: 11px; color: var(--fx-muted); margin-bottom: 2px; }
.jd-route-addr { font-size: 13px; font-weight: 600; color: var(--fx-text); }
.jd-route-line { width: 2px; height: 20px; background: rgba(0,0,0,0.12); margin: 4px 0 4px 5px; }

/* Progress steps */
.jd-progress { margin: 12px; border-radius: 18px; padding: 18px; }
.jd-section-title { font-size: 15px; font-weight: 700; color: var(--fx-text); margin: 0 0 16px; }
.jd-section-title-row { padding: 0 12px 4px; }

.jd-steps { display: flex; flex-direction: column; gap: 0; }
.jd-step { display: flex; gap: 14px; }
.jd-step-left { display: flex; flex-direction: column; align-items: center; width: 36px; flex-shrink: 0; }
.jd-step-icon {
  width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: rgba(0,0,0,0.06); color: var(--fx-muted);
  transition: background 0.3s, color 0.3s;
}
.jd-step-icon.done   { background: #22c55e; color: #fff; }
.jd-step-icon.active { background: var(--fx-accent); color: #fff; box-shadow: 0 0 0 4px rgba(255,102,53,0.18); }
.jd-step-line { width: 2px; flex: 1; min-height: 24px; margin: 4px 0; background: rgba(0,0,0,0.08); }
.jd-step-line.done { background: #22c55e; }
.jd-step-body { padding: 6px 0 20px; flex: 1; min-width: 0; }
.jd-step-label { display: block; font-size: 14px; font-weight: 600; color: var(--fx-muted); }
.jd-step-label.done   { color: var(--fx-text); }
.jd-step-label.active { color: var(--fx-text); font-weight: 700; }
.jd-step-sub { font-size: 12px; color: var(--fx-muted); }

/* Provider strip */
.jd-provider-strip {
  display: flex; align-items: center; gap: 12px;
  margin: 0 12px 12px; border-radius: 16px; padding: 14px 16px;
}
.jd-prov-avatar {
  width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; font-weight: 800; color: var(--fx-accent);
  background: linear-gradient(135deg, rgba(255,102,53,0.15), rgba(255,181,159,0.12));
  border: 2px solid rgba(255,255,255,0.65);
}
.jd-prov-info { flex: 1; min-width: 0; }
.jd-prov-name { display: block; font-size: 14px; font-weight: 700; color: var(--fx-text); }
.jd-prov-cat  { font-size: 12px; color: var(--fx-muted); }
.jd-prov-actions { display: flex; gap: 8px; }
.jd-action-btn {
  width: 38px; height: 38px; border-radius: 50%; border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  background: rgba(255,102,53,0.10); color: var(--fx-accent);
  text-decoration: none;
}
.jd-action-btn .material-symbols-outlined { font-size: 20px; }

/* Rate button */
.jd-rate-btn {
  width: 100%; padding: 14px; border-radius: 14px; border: none; cursor: pointer;
  background: linear-gradient(180deg, #FF7D54, #FF6635); color: #fff;
  font-size: 16px; font-weight: 700; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 4px 16px rgba(255,102,53,0.32);
}
.jd-rate-btn:active { transform: scale(0.98); }
</style>
