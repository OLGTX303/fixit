<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProvidersStore } from '../../stores/providers'
import { useAuthStore } from '../../stores/auth'
import CategoryGrid from '../../components/CategoryGrid.vue'
import ProviderCard  from '../../components/ProviderCard.vue'

const providersStore = useProvidersStore()
const auth   = useAuthStore()
const router = useRouter()
const search = ref('')

function runSearch() {
  const q = search.value.trim()
  router.push({ name: 'search', query: q ? { q } : {} })
}

onMounted(() => providersStore.load())

const topRated = computed(() =>
  [...providersStore.verified].sort((a, b) => b.avg_rating - a.avg_rating).slice(0, 4))

const initials = computed(() =>
  (auth.user?.name || '?').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase())

function openCategory(cat) { router.push({ name: 'search', query: { category: cat.id } }) }
function openProvider(p)   { router.push({ name: 'provider-profile', params: { id: p.id } }) }
</script>

<template>
  <div>
    <!-- Top app bar -->
    <header class="hv-topbar">
      <div style="display:flex;align-items:center;gap:12px">
        <button class="hv-menu-btn" aria-label="Menu">
          <span class="material-symbols-outlined" style="font-size:24px;color:var(--fx-accent)">menu</span>
        </button>
        <span class="hv-brand">FixIt</span>
      </div>
      <div style="display:flex;align-items:center;gap:14px">
        <button class="hv-bell" aria-label="Notifications">
          <span class="material-symbols-outlined" style="font-size:24px;color:var(--fx-text)">notifications</span>
          <span class="hv-bell-dot"></span>
        </button>
        <button class="hv-avatar" @click="router.push({ name: 'account' })" aria-label="Profile">
          <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="avatar" />
          <span v-else>{{ initials }}</span>
        </button>
      </div>
    </header>

    <div class="fx-page" style="padding-top:12px">
      <!-- Search bar -->
      <div class="hv-search fx-input" style="margin-bottom:24px">
        <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-muted-soft)">search</span>
        <input
          v-model="search"
          type="search"
          placeholder="Search services or providers…"
          autocomplete="off"
          @keyup.enter="runSearch"
        />
        <button v-if="search" class="btn btn-primary btn-sm" type="button"
                style="flex-shrink:0;padding:6px 14px;font-size:12px"
                @click="runSearch">Search</button>
      </div>

      <!-- Service Categories -->
      <div class="hv-section-head">
        <span class="fx-section-title">Service Categories</span>
        <button class="hv-link" @click="router.push({ name: 'search' })">View All</button>
      </div>
      <CategoryGrid :categories="providersStore.categories" @select="openCategory" />

      <!-- Top Rated Nearby -->
      <div class="hv-section-head" style="margin-top:28px">
        <span class="fx-section-title">Top Rated Nearby</span>
        <button class="hv-link" @click="router.push({ name: 'search' })">Explore Map</button>
      </div>

      <div v-if="providersStore.loading"
           style="text-align:center;padding:24px 0;color:var(--fx-muted);font-size:14px">Loading…</div>
      <div v-else style="display:flex;flex-direction:column;gap:10px">
        <ProviderCard v-for="p in topRated" :key="p.id" :provider="p" @select="openProvider" />
      </div>
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
  background: rgba(255,255,255,0.45);
  backdrop-filter: blur(30px);
  -webkit-backdrop-filter: blur(30px);
  border-bottom: 1px solid rgba(255,255,255,0.35);
}
.hv-menu-btn {
  background: none; border: none; cursor: pointer; padding: 0;
  display: flex; align-items: center; justify-content: center;
}
.hv-brand {
  font-size: 22px; font-weight: 800; letter-spacing: -0.02em; color: #af3100;
}
.hv-bell {
  position: relative; background: none; border: none; cursor: pointer; padding: 0;
  display: flex; align-items: center; justify-content: center;
}
.hv-bell-dot {
  position: absolute; top: 1px; right: 1px;
  width: 8px; height: 8px; border-radius: 50%;
  background: var(--fx-accent);
  border: 1.5px solid #fff;
}
.hv-avatar {
  width: 42px; height: 42px; border-radius: 50%;
  border: 2px solid rgba(255,255,255,0.70);
  box-shadow: 0 2px 8px rgba(0,0,0,0.10), 0 0 0 3px rgba(255,255,255,0.30);
  overflow: hidden; cursor: pointer; padding: 0;
  background: linear-gradient(135deg, rgba(255,102,53,0.20), rgba(255,181,159,0.15));
  color: var(--fx-accent); font-weight: 800; font-size: 14px;
  display: flex; align-items: center; justify-content: center;
}
.hv-avatar img { width: 100%; height: 100%; object-fit: cover; }
.hv-section-head {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 14px;
}
.hv-link {
  background: none; border: none; cursor: pointer;
  font-size: 14px; font-weight: 600; color: var(--fx-accent);
  font-family: inherit; padding: 0;
}
.hv-search { cursor: text; }
.hv-search input {
  flex: 1; min-width: 0; border: none; background: transparent;
  outline: none; font-size: 15px; color: var(--fx-text); font-family: inherit;
}
.hv-search input::placeholder { color: var(--fx-muted-soft); }
.hv-search input::-webkit-search-cancel-button { display: none; }
</style>
