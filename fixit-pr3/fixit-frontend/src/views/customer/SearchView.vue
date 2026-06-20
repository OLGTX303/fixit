<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProvidersStore } from '../../stores/providers'
import ProviderMap from '../../components/ProviderMap.vue'
import ProviderCard from '../../components/ProviderCard.vue'
import FilterBar from '../../components/FilterBar.vue'
import AppIcon from '../../components/AppIcon.vue'
import { getUserLocation, distanceKmFrom, isNativeApp } from '../../services/geolocation'

const providersStore = useProvidersStore()
const route = useRoute()
const router = useRouter()

const userCenter = ref([1.4927, 103.7414])
const locating = ref(false)
const locationLabel = ref('Johor Bahru')

const q = ref(route.query.q ? String(route.query.q) : '')
const category = ref(route.query.category ? Number(route.query.category) : null)
const maxDistance = ref(10)
const maxPrice = ref(100)
const minRating = ref(0)

// Keep the text box in sync if the user re-enters from Home with a new query.
watch(() => route.query.q, (val) => { q.value = val ? String(val) : '' })

onMounted(async () => {
  await providersStore.load()
  locating.value = true
  try {
    userCenter.value = await getUserLocation()
    locationLabel.value = isNativeApp() ? 'Your location' : 'Near you'
  } finally {
    locating.value = false
  }
})

function distanceKm(p) {
  return distanceKmFrom(userCenter.value[0], userCenter.value[1], p)
}

function categoryName(id) {
  return providersStore.categories.find((c) => c.id === id)?.name || ''
}

// Full-text match against name, location and the provider's category names.
function matchesQuery(p) {
  const term = q.value.trim().toLowerCase()
  if (!term) return true
  const haystack = [
    p.name,
    p.location,
    ...(p.category_ids || []).map(categoryName),
  ].join(' ').toLowerCase()
  return haystack.includes(term)
}

const filtered = computed(() =>
  providersStore.verified
    .map((p) => ({ ...p, _distance: distanceKm(p) }))
    .filter((p) => category.value == null || p.category_ids.includes(category.value))
    .filter(matchesQuery)
    .filter((p) => p._distance <= maxDistance.value)
    .filter((p) => p.base_rate <= maxPrice.value)
    .filter((p) => p.avg_rating >= minRating.value)
    .sort((a, b) => a._distance - b._distance))

function openProvider(p) {
  router.push({ name: 'provider-profile', params: { id: p.id } })
}

function clearSearch() {
  q.value = ''
}
</script>

<template>
  <div class="fx-page">
    <h1 class="fw-bold mb-1" style="font-size:20px">Find a provider</h1>
    <div class="mb-3" style="font-size:13px;color:var(--fx-muted)">
      {{ locating ? 'Detecting location…' : `Verified providers near ${locationLabel}` }}
    </div>

    <!-- Real text search -->
    <div class="fx-input mb-3">
      <AppIcon name="search" :size="18" style="color:var(--fx-muted-soft)" />
      <input
        v-model="q"
        class="fx-search-input"
        type="search"
        placeholder="Search by name, service or area…"
        autocomplete="off"
      />
      <button v-if="q" class="fx-search-clear" type="button" @click="clearSearch" aria-label="Clear">✕</button>
    </div>

    <FilterBar
      :categories="providersStore.categories"
      v-model:model-category="category"
      v-model:model-max-distance="maxDistance"
      v-model:model-max-price="maxPrice"
      v-model:model-min-rating="minRating" />

    <ProviderMap :providers="filtered" :center="userCenter" @select="openProvider" class="mb-3" />

    <div class="fw-bold mb-2" style="font-size:14px">
      {{ filtered.length }} {{ filtered.length === 1 ? 'provider' : 'providers' }} found
      <span v-if="q" style="font-weight:400;color:var(--fx-muted)">for “{{ q }}”</span>
    </div>
    <div class="d-flex flex-column gap-2">
      <ProviderCard v-for="p in filtered" :key="p.id" :provider="p" :distance="p._distance" @select="openProvider" />
      <div v-if="!filtered.length" class="text-center py-4" style="color:var(--fx-muted)">
        No providers match{{ q ? ` “${q}”` : ' these filters' }}.
      </div>
    </div>
  </div>
</template>

<style scoped>
.fx-search-input {
  flex: 1; min-width: 0; border: none; background: transparent;
  outline: none; font-size: 15px; color: var(--fx-text);
  font-family: inherit;
}
.fx-search-input::placeholder { color: var(--fx-muted-soft); }
/* hide native search clear (we provide our own) */
.fx-search-input::-webkit-search-cancel-button { display: none; }
.fx-search-clear {
  border: none; background: none; color: var(--fx-muted-soft);
  font-size: 14px; cursor: pointer; padding: 0 2px; line-height: 1;
}
</style>
