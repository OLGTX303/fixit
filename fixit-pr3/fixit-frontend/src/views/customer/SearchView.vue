<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as api from '../../services/api'
import { useInfiniteList } from '../../composables/useInfiniteList'
import { useAuthStore } from '../../stores/auth'
import { useFavoritesStore } from '../../stores/favorites'
import { getUserLocation, distanceKmFrom, isNativeApp } from '../../services/geolocation'
import RatingStars from '../../components/RatingStars.vue'

const route  = useRoute()
const router = useRouter()
const auth = useAuthStore()
const favorites = useFavoritesStore()

const categories = ref([])
const q        = ref(route.query.q ? String(route.query.q) : '')
const category = ref(route.query.category ? Number(route.query.category) : null)
const sortMode = ref('distance')   // 'distance' | 'rating' | 'price'
const priorityOnly = ref(false)

const userCenter    = ref([3.1390, 101.6869])
const locationLabel = ref('Kuala Lumpur')
const locating      = ref(false)

watch(() => route.query.q,        v => { q.value        = v ? String(v)  : '' })
watch(() => route.query.category, v => { category.value = v ? Number(v)  : null })

const isDiscovery = computed(() => !q.value.trim() && category.value == null)

// ── Server-paginated results: 20 at a time, more on scroll ───────────────────
const { items: results, loading, done, sentinel, reset } = useInfiniteList(async (offset, size) => {
  if (isDiscovery.value) return []   // discovery view shows curated content, not a query
  const distance = sortMode.value === 'distance'
  return api.searchProviders({
    q: q.value.trim(),
    category: category.value || undefined,
    sort: sortMode.value,
    priority: priorityOnly.value || undefined,
    lat: distance ? userCenter.value[0] : undefined,
    lng: distance ? userCenter.value[1] : undefined,
    limit: size,
    offset,
  })
}, 20)

let qTimer = null
watch(q, () => { clearTimeout(qTimer); qTimer = setTimeout(reset, 350) })
watch([category, sortMode, priorityOnly], reset)

async function toggleFavorite(e, providerId) {
  e.stopPropagation()
  if (auth.role !== 'customer') return
  try { await favorites.toggle(providerId) } catch {}
}

onMounted(async () => {
  try { categories.value = await api.getCategories() } catch { /* non-fatal */ }
  if (auth.role === 'customer') favorites.load().catch(() => {})
  locating.value = true
  try {
    userCenter.value    = await getUserLocation()
    locationLabel.value = isNativeApp() ? 'Your location' : 'Near you'
    if (sortMode.value === 'distance' && !isDiscovery.value) reset()
  } finally { locating.value = false }
})

// ── Trending discovery content ─────────────────────────────────────────────
const TRENDING = [
  'Pipe Repair', 'Deep Cleaning', 'AC Service', 'Electrical Wiring',
  'Garden Trim', 'Furniture Assembly', 'Painting', 'Pest Control',
  'Roof Check', 'Move & Pack', 'Pool Service', 'Handyman',
]

const HOT_SERVICES = [
  'Deep Cleaning', 'AC Service', 'Plumbing', 'Electrical',
  'Painting', 'Garden Trimming', 'Pest Control', 'Moving',
  'Roof Repair', 'Handyman',
]
const HOT_CATEGORIES = [
  'Cleaning', 'AC Service', 'Plumbing', 'Electrical',
  'Gardening', 'Moving', 'Painting', 'Carpentry',
  'Roofing', 'Handyman',
]

// Distance is shown per loaded card (ranking is done server-side).
function distanceKm(p) {
  return distanceKmFrom(userCenter.value[0], userCenter.value[1], p)
}

function openProvider(p) { router.push({ name: 'provider-profile', params: { id: p.id } }) }
function clearSearch()   { q.value = ''; category.value = null }
function setTrending(t)  { q.value = t }
function setCategoryName(name) {
  const cat = categories.value.find(c => c.name.toLowerCase() === name.toLowerCase())
  if (cat) category.value = cat.id
  else q.value = name
}

const activeSortLabel = computed(() =>
  sortMode.value === 'rating' ? 'Top Rated' : sortMode.value === 'price' ? 'Lowest Price' : 'Nearest')

function cycleSortMode() {
  sortMode.value = sortMode.value === 'distance' ? 'rating'
    : sortMode.value === 'rating' ? 'price' : 'distance'
}

const activeCategoryName = computed(() =>
  category.value ? categories.value.find(c => c.id === category.value)?.name : null)

function initials(name) {
  return (name || '?').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase()
}
</script>

<template>
  <div class="sv-root">
    <!-- ── Sticky search bar (always visible) ── -->
    <header class="sv-topbar liquid-glass">
      <div class="sv-search-pill">
        <span class="material-symbols-outlined sv-search-icon">search</span>
        <input
          v-model="q"
          class="sv-search-input"
          type="search"
          placeholder="Search services, providers…"
          autocomplete="off"
          @keyup.enter="isDiscovery ? null : null"
        />
        <button v-if="q || category" class="sv-clear" @click="clearSearch">✕</button>
      </div>
      <button class="sv-search-btn" @click="null">Search</button>
    </header>

    <!-- ── DISCOVERY STATE (no active search) ── -->
    <div v-if="isDiscovery" class="sv-discovery">
      <!-- Trending chips -->
      <div class="sv-section-head">
        <span class="sv-section-title">Search Discovery</span>
        <span class="material-symbols-outlined" style="font-size:18px;color:var(--fx-muted)">visibility</span>
      </div>
      <div class="sv-chips">
        <button
          v-for="t in TRENDING" :key="t"
          class="sv-chip"
          @click="setTrending(t)"
        >{{ t }}</button>
      </div>

      <!-- Hot rankings — 2 columns -->
      <div class="sv-hot-row">
        <div class="sv-hot-col liquid-glass">
          <div class="sv-hot-head">
            <span class="sv-hot-fire">🔥</span>
            <span class="sv-hot-title">Top Services</span>
            <button class="sv-hot-more" @click="null">More ›</button>
          </div>
          <div
            v-for="(item, i) in HOT_SERVICES" :key="item"
            class="sv-hot-item"
            @click="setTrending(item)"
          >
            <span class="sv-hot-rank" :class="{ top3: i < 3 }">{{ i + 1 }}</span>
            <span class="sv-hot-name">{{ item }}</span>
            <span v-if="i === 0" class="sv-hot-badge">HOT</span>
          </div>
        </div>

        <div class="sv-hot-col liquid-glass">
          <div class="sv-hot-head">
            <span class="sv-hot-fire">👍</span>
            <span class="sv-hot-title">Top Categories</span>
          </div>
          <div
            v-for="(item, i) in HOT_CATEGORIES" :key="item"
            class="sv-hot-item"
            @click="setCategoryName(item)"
          >
            <span class="sv-hot-rank" :class="{ top3: i < 3 }">{{ i + 1 }}</span>
            <span class="sv-hot-name">{{ item }}</span>
            <span v-if="i === 0" class="sv-hot-badge">HOT</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── RESULTS STATE (active search or category) ── -->
    <div v-else class="sv-results">
      <!-- Filter bar -->
      <div class="sv-filters">
        <button class="sv-filter-chip location">
          <span class="material-symbols-outlined" style="font-size:14px">location_on</span>
          {{ locating ? '…' : locationLabel }}
          <span class="material-symbols-outlined" style="font-size:13px">expand_more</span>
        </button>
        <button class="sv-filter-chip" :class="{ active: !!category }" @click="category = null">
          {{ activeCategoryName || 'All Categories' }}
          <span class="material-symbols-outlined" style="font-size:13px">expand_more</span>
        </button>
        <button class="sv-filter-chip" @click="cycleSortMode">
          {{ activeSortLabel }}
          <span class="material-symbols-outlined" style="font-size:13px">expand_more</span>
        </button>
        <button class="sv-filter-chip priority" :class="{ active: priorityOnly }" @click="priorityOnly = !priorityOnly">
          ⚡ Priority
        </button>
      </div>

      <!-- Result count -->
      <div class="sv-result-count">
        {{ results.length }}{{ done ? '' : '+' }} provider{{ results.length !== 1 ? 's' : '' }} found
        <span v-if="q" style="color:var(--fx-muted)"> for "{{ q }}"</span>
        <span v-if="activeCategoryName" style="color:var(--fx-muted)"> · {{ activeCategoryName }}</span>
      </div>

      <!-- Listing cards (Meituan style) -->
      <div class="sv-list">
        <div
          v-for="p in results" :key="p.id"
          class="sv-card liquid-glass"
          @click="openProvider(p)"
        >
          <!-- Thumbnail / avatar -->
          <div class="sv-card-thumb">
            <img v-if="p.avatar_url" :src="p.avatar_url" :alt="p.name" class="sv-card-img" />
            <div v-else class="sv-card-initials">{{ initials(p.name) }}</div>
            <span v-if="p.is_priority" class="sv-priority-badge">⚡</span>
          </div>

          <button
            v-if="auth.role === 'customer'"
            class="sv-fav-btn"
            :class="{ active: favorites.has(p.id) }"
            :aria-label="favorites.has(p.id) ? 'Remove from favourites' : 'Add to favourites'"
            @click="toggleFavorite($event, p.id)"
          >
            <span class="material-symbols-outlined" style="font-size:18px">favorite</span>
          </button>

          <!-- Details -->
          <div class="sv-card-body">
            <div class="sv-card-row1">
              <span class="sv-card-name">{{ p.name }}</span>
              <span class="sv-card-price">
                RM{{ p.rate_type === 'per_job' ? p.per_job_rate : p.base_rate }}
                <span class="sv-card-unit">{{ p.rate_type === 'per_job' ? '/job' : '/hr' }}</span>
              </span>
            </div>
            <div class="sv-card-cats">{{ p.category_names?.join(' · ') }}</div>
            <div class="sv-card-meta">
              <RatingStars :rating="p.avg_rating" :size="11" />
              <span class="sv-card-rating">{{ p.avg_rating.toFixed(1) }}</span>
              <span class="sv-card-reviews">{{ p.review_count }} reviews</span>
              <span v-if="p.latitude != null" class="sv-card-dist">
                · {{ distanceKm(p).toFixed(1) }}km
              </span>
            </div>
            <div class="sv-card-tags">
              <span v-if="p.is_verified" class="sv-tag verified">✓ Verified</span>
              <span v-for="s in (p.services || []).slice(0,2)" :key="s" class="sv-tag">{{ s }}</span>
            </div>
          </div>
        </div>

        <!-- infinite-scroll sentinel + states -->
        <div ref="sentinel" style="height:1px"></div>
        <div v-if="loading" style="text-align:center;padding:18px;color:var(--fx-muted);font-size:13px">Loading…</div>
        <div v-else-if="done && results.length" style="text-align:center;padding:14px;color:var(--fx-muted-soft);font-size:12px">— end of results —</div>

        <div v-if="done && !results.length" class="sv-empty">
          <span class="material-symbols-outlined" style="font-size:48px;opacity:.3">search_off</span>
          <p>No providers match{{ q ? ` "${q}"` : ' these filters' }}.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sv-root { min-height: 100vh; background: var(--fx-bg, #f5f5f7); }

/* ── Top bar ──────────────────────────────────────────────────────────────── */
.sv-topbar {
  position: sticky; top: 0; z-index: 30;
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px;
  border-radius: 0;
  border-bottom: 1px solid rgba(255,255,255,0.40);
}
.sv-search-pill {
  flex: 1; display: flex; align-items: center; gap: 8px;
  background: rgba(255,255,255,0.55);
  border: 1px solid rgba(255,255,255,0.70);
  border-radius: 999px;
  padding: 9px 14px;
  box-shadow: inset 0 1px 3px rgba(0,0,0,0.06);
}
.sv-search-icon { font-size: 20px; color: var(--fx-muted); flex-shrink: 0; }
.sv-search-input {
  flex: 1; min-width: 0; border: none; background: transparent;
  outline: none; font-size: 15px; color: var(--fx-text); font-family: inherit;
}
.sv-search-input::placeholder { color: var(--fx-muted-soft, #bbb); }
.sv-search-input::-webkit-search-cancel-button { display: none; }
.sv-clear {
  border: none; background: rgba(0,0,0,0.08); border-radius: 50%;
  width: 20px; height: 20px; font-size: 12px; cursor: pointer;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  color: var(--fx-muted);
}
.sv-search-btn {
  flex-shrink: 0;
  background: #FF6635; color: #fff; border: none; cursor: pointer;
  padding: 10px 18px; border-radius: 999px;
  font-size: 13px; font-weight: 700; font-family: inherit;
  box-shadow: 0 3px 10px rgba(255,102,53,0.30);
}
.sv-search-btn:active { transform: scale(0.95); }

/* ── Discovery ────────────────────────────────────────────────────────────── */
.sv-discovery { padding: 16px; }
.sv-section-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 14px;
}
.sv-section-title { font-size: 16px; font-weight: 700; color: var(--fx-text); }

.sv-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
.sv-chip {
  padding: 7px 14px; border-radius: 999px;
  border: 1px solid rgba(0,0,0,0.10);
  background: rgba(255,255,255,0.70);
  font-size: 13px; font-weight: 500; color: var(--fx-text);
  cursor: pointer; font-family: inherit;
  transition: background 0.18s, transform 0.12s;
}
.sv-chip:active { transform: scale(0.95); background: rgba(255,102,53,0.08); }

.sv-hot-row { display: flex; gap: 12px; }
.sv-hot-col { flex: 1; min-width: 0; border-radius: 16px; padding: 14px 12px; overflow: hidden; }
.sv-hot-head {
  display: flex; align-items: center; gap: 6px; margin-bottom: 12px;
}
.sv-hot-fire  { font-size: 16px; }
.sv-hot-title { font-size: 13px; font-weight: 700; color: var(--fx-accent); flex: 1; }
.sv-hot-more  {
  font-size: 11px; color: var(--fx-muted); background: none; border: none;
  cursor: pointer; font-family: inherit; padding: 0;
}

.sv-hot-item {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 0;
  border-bottom: 1px solid rgba(0,0,0,0.04);
  cursor: pointer;
}
.sv-hot-item:last-child { border-bottom: none; }
.sv-hot-item:active { opacity: 0.7; }
.sv-hot-rank {
  font-size: 14px; font-weight: 700; width: 18px; flex-shrink: 0;
  color: var(--fx-muted); text-align: center;
}
.sv-hot-rank.top3 { color: var(--fx-accent); }
.sv-hot-name { font-size: 13px; color: var(--fx-text); flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sv-hot-badge {
  font-size: 9px; font-weight: 800; color: #fff;
  background: var(--fx-accent); border-radius: 3px; padding: 1px 4px;
  flex-shrink: 0;
}

/* ── Results ──────────────────────────────────────────────────────────────── */
.sv-results { padding: 0 0 24px; }

.sv-filters {
  display: flex; gap: 8px; overflow-x: auto; padding: 12px 16px;
  scrollbar-width: none;
}
.sv-filters::-webkit-scrollbar { display: none; }
.sv-filter-chip {
  flex-shrink: 0; display: flex; align-items: center; gap: 4px;
  padding: 7px 13px; border-radius: 999px;
  border: 1px solid rgba(0,0,0,0.10);
  background: rgba(255,255,255,0.65);
  font-size: 12px; font-weight: 600; color: var(--fx-text);
  cursor: pointer; font-family: inherit; white-space: nowrap;
  transition: background 0.18s, border-color 0.18s;
}
.sv-filter-chip.active {
  background: rgba(255,102,53,0.10);
  border-color: var(--fx-accent);
  color: var(--fx-accent);
}
.sv-filter-chip.priority.active {
  background: rgba(255,190,0,0.15);
  border-color: #f59e0b;
  color: #92680a;
}
.sv-filter-chip.location { color: var(--fx-muted); }

.sv-result-count {
  padding: 2px 16px 10px;
  font-size: 13px; font-weight: 600; color: var(--fx-text);
}

/* ── Listing cards ────────────────────────────────────────────────────────── */
.sv-list { display: flex; flex-direction: column; gap: 0; }

.sv-card {
  display: flex; gap: 14px; align-items: flex-start;
  padding: 16px; margin: 0 12px 10px;
  border-radius: 18px; cursor: pointer;
  transition: transform 0.16s ease;
}
.sv-card:active { transform: scale(0.98); }
.sv-card { position: relative; }

.sv-fav-btn {
  position: absolute; top: 12px; right: 12px; z-index: 2;
  width: 32px; height: 32px; border-radius: 50%; border: none;
  background: rgba(255,255,255,0.92); color: var(--fx-muted);
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}
.sv-fav-btn.active { color: #ef4444; }
.sv-fav-btn.active .material-symbols-outlined { font-variation-settings: 'FILL' 1; }

.sv-card-thumb {
  position: relative; flex-shrink: 0;
  width: 80px; height: 80px; border-radius: 14px; overflow: hidden;
  background: linear-gradient(135deg, rgba(255,102,53,0.15), rgba(255,181,159,0.12));
}
.sv-card-img { width: 100%; height: 100%; object-fit: cover; }
.sv-card-initials {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; font-weight: 800; color: var(--fx-accent);
}
.sv-priority-badge {
  position: absolute; top: 4px; right: 4px;
  background: rgba(255,190,0,0.90); border-radius: 4px;
  font-size: 11px; padding: 1px 4px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.12);
}

.sv-card-body { flex: 1; min-width: 0; }
.sv-card-row1 { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 3px; }
.sv-card-name { font-size: 15px; font-weight: 700; color: var(--fx-text); flex: 1; min-width: 0; padding-right: 8px; }
.sv-card-price { font-size: 15px; font-weight: 800; color: var(--fx-accent); flex-shrink: 0; }
.sv-card-unit  { font-size: 11px; font-weight: 400; color: var(--fx-muted); }

.sv-card-cats { font-size: 12px; color: var(--fx-muted); margin-bottom: 5px; }

.sv-card-meta {
  display: flex; align-items: center; gap: 5px; margin-bottom: 6px;
}
.sv-card-rating  { font-size: 12px; font-weight: 700; color: var(--fx-text); }
.sv-card-reviews { font-size: 11px; color: var(--fx-muted); }
.sv-card-dist    { font-size: 11px; color: var(--fx-muted); }

.sv-card-tags { display: flex; flex-wrap: wrap; gap: 5px; }
.sv-tag {
  font-size: 11px; padding: 2px 8px; border-radius: 4px;
  background: rgba(0,0,0,0.05); color: var(--fx-muted); font-weight: 500;
}
.sv-tag.verified {
  background: rgba(34,197,94,0.12); color: #16a34a;
}

.sv-empty {
  text-align: center; padding: 48px 24px;
  color: var(--fx-muted); font-size: 14px;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
}
</style>
