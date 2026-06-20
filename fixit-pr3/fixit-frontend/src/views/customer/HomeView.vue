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

function greeting() {
  const h = new Date().getHours()
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'
}

function openCategory(cat) { router.push({ name: 'search', query: { category: cat.id } }) }
function openProvider(p)   { router.push({ name: 'provider-profile', params: { id: p.id } }) }
</script>

<template>
  <div class="fx-page">
    <!-- Greeting row -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
      <div>
        <div class="fx-display">{{ greeting() }},<br>{{ auth.user?.name?.split(' ')[0] }} 👋</div>
        <div style="font-size:13px;color:var(--fx-muted);margin-top:4px">What do you need fixed today?</div>
      </div>
      <button class="hv-bell-btn glass-btn">
        <span class="material-symbols-outlined" style="font-size:22px;color:var(--fx-muted)">notifications</span>
      </button>
    </div>

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

    <!-- Categories -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <span class="fx-section-title">Service Categories</span>
    </div>
    <CategoryGrid :categories="providersStore.categories" @select="openCategory" />

    <!-- Top Rated -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin:24px 0 12px">
      <span class="fx-section-title">Top Rated Nearby</span>
      <router-link :to="{ name: 'search' }"
        style="font-size:13px;font-weight:700;color:var(--fx-accent);text-decoration:none">See all</router-link>
    </div>

    <div v-if="providersStore.loading"
         style="text-align:center;padding:24px 0;color:var(--fx-muted);font-size:14px">Loading…</div>
    <div v-else style="display:flex;flex-direction:column;gap:10px">
      <ProviderCard v-for="p in topRated" :key="p.id" :provider="p" @select="openProvider" />
    </div>
  </div>
</template>

<style scoped>
.hv-bell-btn {
  width: 44px; height: 44px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.hv-search { cursor: text; }
.hv-search input {
  flex: 1; min-width: 0; border: none; background: transparent;
  outline: none; font-size: 15px; color: var(--fx-text); font-family: inherit;
}
.hv-search input::placeholder { color: var(--fx-muted-soft); }
.hv-search input::-webkit-search-cancel-button { display: none; }
</style>
