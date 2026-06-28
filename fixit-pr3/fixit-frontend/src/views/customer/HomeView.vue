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
import { promptLogin } from '../../composables/useLoginPrompt'

const auth   = useAuthStore()
const router = useRouter()
const favorites = useFavoritesStore()
const search = ref('')

// Guests see only the 9 cards; any action opens login.
const isGuest = computed(() => !auth.isAuthenticated)
const guestCards = computed(() => recommended.value.slice(0, 9))

const categories = ref([])
const sortBy = ref('recommended')

function runSearch() {
  if (isGuest.value) return promptLogin()
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

function openCategory(cat) { if (isGuest.value) return promptLogin(); router.push({ name: 'search', query: { category: cat.id } }) }
function openProvider(p)   { if (isGuest.value) return promptLogin(); router.push({ name: 'provider-profile', params: { id: p.id } }) }
</script>

<template>
  <div class="hv-root">
    <!-- Top app bar -->
    <header class="hv-topbar">
      <div style="display:flex;align-items:center">
        <img :src="fixitLogo" alt="FixIt" class="hv-logo" />
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <button v-if="!isGuest" class="hv-icon-btn hv-bell" aria-label="Notifications">
          <span class="material-symbols-outlined" style="font-size:24px;color:var(--fx-muted)">notifications</span>
          <span class="hv-bell-dot"></span>
        </button>
        <button class="hv-avatar" @click="isGuest ? promptLogin() : router.push({ name: 'account' })" aria-label="Profile">
          <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="avatar" />
          <img v-else-if="isGuest" :src="fixitLogo" alt="Sign in" class="hv-avatar-logo" />
          <span v-else>{{ initials }}</span>
        </button>
      </div>
    </header>

    <div class="hv-content">
      <!-- Hero greeting + search + categories: members only -->
      <template v-if="!isGuest">
      <section class="hv-hero" style="padding:18px 0 8px">
        <p class="hv-hero-kicker">Home services, on demand</p>
        <h2 class="hv-greeting">
          {{ greeting() }},
          <span class="hv-greeting-name">{{ auth.user?.name?.split(' ')[0] }}</span>
        </h2>
        <p class="hv-hero-sub">What can we help you fix today?</p>

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
      </template>

      <!-- Guest hero -->
      <section v-if="isGuest" class="hv-hero" style="padding:20px 0 4px">
        <p class="hv-hero-kicker">Home services, on demand</p>
        <h2 class="hv-greeting">Popular near you</h2>
        <p class="hv-hero-sub">Sign in to search, book, and chat with providers.</p>
      </section>

      <!-- Provider cards: 9 for guests (gated), infinite for members -->
      <section style="padding:8px 0 18px">
        <div v-if="!isGuest" class="hv-section-head">
          <span class="fx-headline">Recommended for you</span>
          <select v-model="sortBy" class="hv-sort" aria-label="Sort providers">
            <option value="recommended">Recommended</option>
            <option value="rating">Top rated</option>
            <option value="price">Price: low → high</option>
          </select>
        </div>

        <div class="hv-grid">
          <ProviderGridCard v-for="p in (isGuest ? guestCards : recommended)" :key="p.id"
                            :provider="p" :show-favorite="!isGuest" @select="openProvider" />
        </div>

        <template v-if="!isGuest">
          <div ref="sentinel" style="height:1px"></div>
          <div v-if="loading" style="text-align:center;padding:18px 0;color:var(--fx-muted);font-size:13px">Loading…</div>
          <div v-else-if="done && recommended.length" style="text-align:center;padding:14px 0;color:var(--fx-muted-soft);font-size:12px">— end —</div>
          <div v-else-if="done && !recommended.length" style="text-align:center;padding:24px 0;color:var(--fx-muted);font-size:14px">No providers yet.</div>
        </template>

        <button v-if="isGuest" class="btn btn-primary w-100" style="margin-top:16px;height:50px;border-radius:999px;font-weight:700" @click="promptLogin()">
          Sign in to get started
        </button>
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
  margin: 0 -20px;
  background:
    radial-gradient(ellipse 55% 90% at 8% 50%, rgba(255, 102, 53, 0.08) 0%, transparent 68%),
    rgba(255, 255, 255, 0.14);
  backdrop-filter: blur(14px) saturate(1.85) brightness(1.05);
  -webkit-backdrop-filter: blur(14px) saturate(1.85) brightness(1.05);
  box-shadow:
    inset 0 -1px 0 rgba(255, 255, 255, 0.55),
    inset 0 1px 0 rgba(255, 255, 255, 0.72),
    0 4px 20px rgba(255, 102, 53, 0.06);
}
@media (min-width: 992px) {
  .hv-topbar { margin: 0 -32px; }
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
.hv-avatar img.hv-avatar-logo { object-fit: contain; padding: 4px; background: #fff; }

/* page content padding �?full-bleed so the rail can scroll edge-to-edge */
.hv-content { max-width: 640px; margin: 0 auto; padding: 0 20px; }
@media (min-width: 992px) { .hv-content { max-width: 980px; padding: 0 32px; } }

.hv-hero-kicker {
  margin: 0 0 6px;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--fx-accent);
}
.hv-greeting {
  font-size: 28px;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.2;
  margin: 0;
  text-wrap: balance;
}
.hv-greeting-name {
  background: linear-gradient(135deg, var(--fx-accent) 0%, var(--fx-accent-light) 100%);
  -webkit-background-clip: text;
  background-clip: text;
  color: transparent;
}
.hv-hero-sub {
  font-size: 14px;
  color: var(--fx-muted);
  margin: 8px 0 18px;
}

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
