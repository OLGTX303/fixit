<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import plumbing    from '../assets/category-icons/plumbing.png'
import electrical  from '../assets/category-icons/electrical.png'
import cleaning    from '../assets/category-icons/cleaning.png'
import gardening   from '../assets/category-icons/gardening.png'
import acService   from '../assets/category-icons/ac-service.png'
import moving      from '../assets/category-icons/moving.png'
import painting    from '../assets/category-icons/painting.svg'
import pestControl from '../assets/category-icons/pest-control.svg'
import roofing     from '../assets/category-icons/roofing.svg'
import carpentry   from '../assets/category-icons/carpentry.svg'
import poolService from '../assets/category-icons/pool-service.svg'
import handyman    from '../assets/category-icons/handyman.svg'
import { categoryIcon, categoryTint } from '../services/categoryIcons'

const props = defineProps({ categories: { type: Array, default: () => [] } })
defineEmits(['select'])

const META = {
  Plumbing:       { icon: plumbing,    tint: 'rgba(59,130,246,0.14)' },
  Electrical:     { icon: electrical,  tint: 'rgba(245,200,40,0.18)' },
  Cleaning:       { icon: cleaning,    tint: 'rgba(34,197,94,0.14)' },
  Gardening:      { icon: gardening,   tint: 'rgba(16,185,129,0.14)' },
  'AC Service':   { icon: acService,   tint: 'rgba(56,189,248,0.16)' },
  Moving:         { icon: moving,      tint: 'rgba(168,85,247,0.14)' },
  Painting:       { icon: painting,    tint: 'rgba(255,102,53,0.13)' },
  'Pest Control': { icon: pestControl, tint: 'rgba(34,197,94,0.13)' },
  Roofing:        { icon: roofing,     tint: 'rgba(245,158,11,0.16)' },
  Carpentry:      { icon: carpentry,   tint: 'rgba(146,100,50,0.14)' },
  'Pool Service': { icon: poolService, tint: 'rgba(14,165,233,0.16)' },
  Handyman:       { icon: handyman,    tint: 'rgba(249,115,22,0.14)' },
}

const EXTRA = [
  { id: 'painting',     name: 'Painting',     icon_url: '' },
  { id: 'pest-control', name: 'Pest Control', icon_url: '' },
  { id: 'roofing',      name: 'Roofing',      icon_url: '' },
  { id: 'carpentry',    name: 'Carpentry',    icon_url: '' },
  { id: 'pool-service', name: 'Pool Service', icon_url: '' },
  { id: 'handyman',     name: 'Handyman',     icon_url: '' },
]

const allCategories = computed(() => {
  const names = new Set(props.categories.map(c => c.name))
  const filtered = EXTRA.filter(e => !names.has(e.name))
  return [...props.categories, ...filtered]
})

const PAGE_SIZE = 6
const pages = computed(() => {
  const all = allCategories.value
  const out = []
  for (let i = 0; i < all.length; i += PAGE_SIZE) out.push(all.slice(i, i + PAGE_SIZE))
  return out
})

const PAGE_LABELS = ['Core Services', 'More Services']
const activePage  = ref(0)
const viewportRef = ref(null)

// Exact-match META first (curated icon + tint); otherwise resolve an icon by
// keyword so granular categories (Pipe Repair, Garden Trim…) still get an image.
const metaFor = (name) => META[name] || { icon: categoryIcon(name), tint: categoryTint(name) }

// ── Touch swipe (non-passive touchmove so we can preventDefault) ──────────
let tx0 = 0, ty0 = 0, dragging = false

function onTouchStart(e) {
  tx0 = e.touches[0].clientX
  ty0 = e.touches[0].clientY
  dragging = false
}
function onTouchMove(e) {
  const dx = Math.abs(e.touches[0].clientX - tx0)
  const dy = Math.abs(e.touches[0].clientY - ty0)
  // Lock to horizontal swipe once intent is clear
  if (!dragging && dx > dy && dx > 6) dragging = true
  if (dragging) e.preventDefault()
}
function onTouchEnd(e) {
  if (!dragging) return
  const dx = e.changedTouches[0].clientX - tx0
  if (Math.abs(dx) < 40) return
  if (dx < 0 && activePage.value < pages.value.length - 1) activePage.value++
  if (dx > 0 && activePage.value > 0) activePage.value--
}

onMounted(() => {
  const el = viewportRef.value
  if (!el) return
  el.addEventListener('touchstart', onTouchStart, { passive: true })
  el.addEventListener('touchmove',  onTouchMove,  { passive: false }) // non-passive!
  el.addEventListener('touchend',   onTouchEnd,   { passive: true })
})
onUnmounted(() => {
  const el = viewportRef.value
  if (!el) return
  el.removeEventListener('touchstart', onTouchStart)
  el.removeEventListener('touchmove',  onTouchMove)
  el.removeEventListener('touchend',   onTouchEnd)
})
</script>

<template>
  <div class="cg-root">
    <!-- Tab pills -->
    <div class="cg-tabs">
      <button
        v-for="(label, i) in PAGE_LABELS.slice(0, pages.length)"
        :key="i"
        class="cg-tab"
        :class="{ active: activePage === i }"
        @click="activePage = i"
      >{{ label }}</button>
    </div>

    <!-- Slide viewport — touch events added via onMounted (non-passive) -->
    <div class="cg-viewport" ref="viewportRef">
      <div class="cg-track" :style="{ transform: `translateX(${-activePage * 100}%)` }">
        <div v-for="(page, pi) in pages" :key="pi" class="cg-page">
          <div class="cg-grid">
            <div
              v-for="c in page" :key="c.id"
              class="cg-tile liquid-glass lg-interactive"
              role="button"
              @click="$emit('select', c)"
            >
              <div class="cg-icon-circle" :style="{ background: metaFor(c.name).tint }">
                <img v-if="metaFor(c.name).icon" :src="metaFor(c.name).icon" :alt="c.name" class="cg-icon-img" />
                <span v-else style="font-size:24px">{{ c.icon_url }}</span>
              </div>
              <span class="cg-label">{{ c.name }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Dot indicators — OUTSIDE viewport so they're never clipped -->
    <div class="cg-dots">
      <span
        v-for="(_, i) in pages"
        :key="i"
        class="cg-dot"
        :class="{ active: activePage === i }"
        @click="activePage = i"
      />
    </div>
  </div>
</template>

<style scoped>
/* Root wrapper — position:relative isolates the stacking context so dots
   are never overlaid by the viewport's overflow:hidden sibling. */
.cg-root { position: relative; }

/* ── Tab pills ──────────────────────────────────────────────────────────── */
.cg-tabs { display: flex; gap: 8px; margin-bottom: 14px; }
.cg-tab {
  flex: 1; padding: 9px 0; border-radius: 999px;
  border: 1.5px solid var(--fx-border, rgba(0,0,0,0.10));
  background: rgba(255,255,255,0.35);
  backdrop-filter: blur(6px);
  font-size: 13px; font-weight: 600; font-family: inherit;
  color: var(--fx-muted); cursor: pointer;
  transition: background 0.22s, color 0.22s, border-color 0.22s, box-shadow 0.22s;
}
.cg-tab.active {
  background: linear-gradient(180deg, #FF7D54 0%, #FF6635 100%);
  color: #fff; border-color: transparent;
  box-shadow: 0 4px 14px rgba(255,102,53,0.30), inset 0 1px 0 rgba(255,255,255,0.35);
}

/* ── Sliding viewport ───────────────────────────────────────────────────── */
.cg-viewport {
  overflow: hidden;
  width: 100%;
  /* touch-action: pan-y lets the browser scroll vertically but we intercept
     horizontal drags ourselves via the non-passive touchmove listener */
  touch-action: pan-y;
}
.cg-track {
  display: flex;
  will-change: transform;
  transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}
.cg-page { flex: none; width: 100%; }

/* ── Grid ───────────────────────────────────────────────────────────────── */
.cg-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
@media (min-width: 768px) { .cg-grid { grid-template-columns: repeat(6, 1fr); } }

/* ── Tile ───────────────────────────────────────────────────────────────── */
.cg-tile {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 8px;
  padding: 16px 8px; border-radius: 20px; cursor: pointer;
  transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.22s;
}
.cg-tile:hover  { transform: translateY(-4px) scale(1.03); }
.cg-tile:active { transform: scale(0.95); }

.cg-icon-circle {
  width: 60px; height: 60px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  box-shadow: inset 0 2px 6px rgba(0,0,0,0.06),
              inset 0 1px 2px rgba(255,255,255,0.60),
              0 2px 8px rgba(0,0,0,0.06);
  transition: transform 0.22s cubic-bezier(0.34,1.56,0.64,1);
}
.cg-tile:hover .cg-icon-circle { transform: scale(1.10) translateY(-2px); }
.cg-icon-img {
  width: 48px; height: 48px; object-fit: contain;
  filter: drop-shadow(0 4px 8px rgba(0,0,0,0.14));
}
.cg-tile:hover .cg-icon-img { filter: drop-shadow(0 6px 12px rgba(0,0,0,0.20)); }

.cg-label {
  font-size: 12px; font-weight: 700; text-align: center;
  color: var(--fx-text); letter-spacing: -0.01em; line-height: 1.2;
}

/* ── Dot indicators ─────────────────────────────────────────────────────── */
.cg-dots {
  position: relative;   /* stays in normal flow, above the viewport */
  z-index: 1;
  display: flex; justify-content: center; align-items: center;
  gap: 6px; margin-top: 14px;
  padding: 4px 0;       /* click target breathing room */
}
.cg-dot {
  display: block;
  width: 6px; height: 6px; border-radius: 3px;
  background: rgba(0,0,0,0.18);
  cursor: pointer;
  transition: background 0.22s, width 0.22s, border-radius 0.22s;
}
.cg-dot.active {
  width: 20px; border-radius: 3px;
  background: var(--fx-accent);
}
</style>
