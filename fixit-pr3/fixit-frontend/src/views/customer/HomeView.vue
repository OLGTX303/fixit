<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import * as api from '../../services/api'
import { useInfiniteList } from '../../composables/useInfiniteList'
import { useFavoritesStore } from '../../stores/favorites'
import CategoryGrid from '../../components/CategoryGrid.vue'
import ProviderGridCard from '../../components/ProviderGridCard.vue'
import fixitLogo from '../../assets/fixit-logo.svg'

const auth   = useAuthStore()
const router = useRouter()
const favorites = useFavoritesStore()
const search = ref('')

const categories = ref([])
const sortBy = ref('recommended')

function runSearch() {
  const q = search.value.trim()
  router.push({ name: 'search', query: q ? { q } : {} })
}

// Recommended providers: load 20 at a time, fetch more as you scroll.
const { items: recommended, loading, done, sentinel, reset } = useInfiniteList(
  (offset, size) => api.searchProviders({ sort: sortBy.value, limit: size, offset }), 20)
watch(sortBy, reset)

onMounted(async () => {
  try { categories.value = await api.getCategories() } catch { /* non-fatal */ }
  if (auth.role === 'customer') favorites.load().catch(() => {})
})

const initials = computed(() =>
  (auth.user?.name || '—').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase())

function greeting() {
  const h = new Date().getHours()
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'
}

function openCategory(cat) { router.push({ name: 'search', query: { category: cat.id } }) }
function openProvider(p)   { router.push({ name: 'provider-profile', params: { id: p.id } }) }
</script>

<template>
  <div>
    <!-- Top app bar -->
    <header class="hv-topbar">
      <div style="display:flex;align-items:center">
        <img :src="fixitLogo" alt="FixIt" class="hv-logo" />
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <button class="hv-icon-btn hv-bell" aria-label="Notifications">
          <span class="material-symbols-outlined" style="font-size:24px;color:var(--fx-muted)">notifications</span>
          <span class="hv-bell-dot"></span>
        </button>
        <button class="hv-avatar" @click="router.push({ name: 'account' })" aria-label="Profile">
          <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="avatar" />
          <span v-else>{{ initials }}</span>
        </button>
      </div>
    </header>

    <div class="hv-content">
      <!-- Hero greeting -->
      <section style="padding:18px 0 8px">
        <h2 class="hv-greeting">{{ greeting() }}, {{ auth.user?.name?.split(' ')[0] }}</h2>
        <p style="font-size:14px;color:var(--fx-muted);margin:6px 0 18px">What can we help you fix today?</p>

        <!-- Search pill with inline Search button -->
        <div class="hv-search liquid-glass">
          <span class="material-symbols-outlined" style="font-size:22px;color:var(--fx-muted);flex-shrink:0">search</span>
          <input
            v-model="search"
            type="search"
            placeholder="Search for services…"
            autocomplete="off"
            @keyup.enter="runSearch"
          />
          <button class="hv-search-btn" type="button" @click="runSearch">Search</button>
        </div>
      </section>

      <!-- Service Categories -->
      <section style="padding:18px 0">
        <div class="hv-section-head">
          <span class="fx-headline">Service Categories</span>
          <button class="hv-link" @click="router.push({ name: 'search' })">View All</button>
        </div>
        <CategoryGrid :categories="categories" @select="openCategory" />
      </section>

      <!-- Recommended for you �?responsive card grid (2 cols mobile / 6 desktop) -->
      <section style="padding:8px 0 18px">
        <div class="hv-section-head">
          <span class="fx-headline">Recommended for you</span>
          <select v-model="sortBy" class="hv-sort" aria-label="Sort providers">
            <option value="recommended">Recommended</option>
            <option value="rating">Top rated</option>
            <option value="price">Price: low → high</option>
          </select>
        </div>

        <div class="hv-grid">
          <ProviderGridCard v-for="p in recommended" :key="p.id" :provider="p" show-favorite @select="openProvider" />
        </div>
        <div ref="sentinel" style="height:1px"></div>
        <div v-if="loading" style="text-align:center;padding:18px 0;color:var(--fx-muted);font-size:13px">Loading…</div>
        <div v-else-if="done && recommended.length" style="text-align:center;padding:14px 0;color:var(--fx-muted-soft);font-size:12px">— end —</div>
        <div v-else-if="done && !recommended.length" style="text-align:center;padding:24px 0;color:var(--fx-muted);font-size:14px">No providers yet.</div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.hv-topbar {
  position: sticky;
  top: env(safe-area-inset-top);
  z-index: 40;
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 20px;
  background:
    radial-gradient(ellipse 60% 80% at 10% 50%, rgba(255,255,255,0.08) 0%, transparent 70%),
    rgba(255,255,255,0.18);
  backdrop-filter: blur(10px) saturate(1.80) brightness(1.04);
  -webkit-backdrop-filter: blur(10px) saturate(1.80) brightness(1.04);
  box-shadow:
    inset 0 -1px 0 rgba(255,255,255,0.55),
    inset 0 1px 0 rgba(255,255,255,0.70),
    0 2px 12px rgba(0,0,0,0.04);
}
.hv-icon-btn {
  background: none; border: none; cursor: pointer; padding: 6px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.2s ease;
}
.hv-icon-btn:hover { background: rgba(255,255,255,0.40); }
.hv-bell { position: relative; }
.hv-bell-dot {
  position: absolute; top: 6px; right: 6px;
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--fx-accent); border: 1.5px solid #fff;
}
.hv-brand { font-size: 22px; font-weight: 800; letter-spacing: -0.02em; color: #af3100; }
.hv-logo { width: 56px; height: 56px; object-fit: contain; }
.hv-avatar {
  width: 42px; height: 42px; border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.70);
  box-shadow: 0 2px 8px rgba(0,0,0,0.10), inset 0 1px 2px rgba(255,255,255,0.50);
  overflow: hidden; cursor: pointer; padding: 0;
  background: linear-gradient(135deg, rgba(255,102,53,0.20), rgba(255,181,159,0.15));
  color: var(--fx-accent); font-weight: 800; font-size: 14px;
  display: flex; align-items: center; justify-content: center;
}
.hv-avatar img { width: 100%; height: 100%; object-fit: cover; }

/* page content padding �?full-bleed so the rail can scroll edge-to-edge */
.hv-content { max-width: 640px; margin: 0 auto; padding: 0 20px; }
@media (min-width: 992px) { .hv-content { max-width: 980px; padding: 0 32px; } }

.hv-greeting { font-size: 28px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.2; margin: 0; }

.hv-search {
  display: flex; align-items: center; gap: 10px;
  border-radius: 999px; padding: 6px 6px 6px 16px;
}
.hv-search input {
  flex: 1; min-width: 0; border: none; background: transparent;
  outline: none; font-size: 15px; color: var(--fx-text); font-family: inherit;
}
.hv-search input::placeholder { color: var(--fx-muted-soft); }
.hv-search input::-webkit-search-cancel-button { display: none; }
.hv-search-btn {
  flex-shrink: 0;
  background: linear-gradient(180deg, #FF7D54 0%, #FF6635 100%);
  color: #fff; border: none; cursor: pointer;
  padding: 10px 20px; border-radius: 999px;
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
  font-family: inherit;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.40), 0 4px 12px rgba(255,102,53,0.25);
}
.hv-search-btn:active { transform: scale(0.96); }

.hv-section-head {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 14px;
}
.hv-link {
  background: none; border: none; cursor: pointer;
  font-size: 14px; font-weight: 600; color: var(--fx-accent);
  font-family: inherit; padding: 0;
}
.hv-sort {
  font-family: inherit; font-size: 13px; font-weight: 600; color: var(--fx-text);
  border: 1.5px solid var(--fx-border); border-radius: 999px;
  padding: 6px 12px; background: #fff; cursor: pointer;
}

/* Recommended grid �?2 cards per row on mobile, 6 per row on desktop. */
.hv-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}
@media (min-width: 992px) {
  .hv-grid { grid-template-columns: repeat(6, 1fr); gap: 16px; }
}
</style>
