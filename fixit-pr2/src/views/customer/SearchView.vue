<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useProvidersStore } from '../../stores/providers'
import ProviderMap from '../../components/ProviderMap.vue'
import ProviderCard from '../../components/ProviderCard.vue'
import FilterBar from '../../components/FilterBar.vue'

const providersStore = useProvidersStore()
const route = useRoute()
const router = useRouter()

const CITY = [51.5074, -0.1278] // Metro City centre

// Reactive filter state — workflow #2 (Provider Search & Filter).
const category = ref(route.query.category ? Number(route.query.category) : null)
const maxDistance = ref(10)
const maxPrice = ref(100)
const minRating = ref(0)

onMounted(() => providersStore.load())

// Haversine distance (km) from the city centre.
function distanceKm(p) {
  const R = 6371, toRad = (d) => (d * Math.PI) / 180
  const dLat = toRad(p.latitude - CITY[0]), dLng = toRad(p.longitude - CITY[1])
  const a = Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(CITY[0])) * Math.cos(toRad(p.latitude)) * Math.sin(dLng / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

// Only verified providers, then apply the reactive filters.
const filtered = computed(() =>
  providersStore.verified
    .map(p => ({ ...p, _distance: distanceKm(p) }))
    .filter(p => category.value == null || p.category_ids.includes(category.value))
    .filter(p => p._distance <= maxDistance.value)
    .filter(p => p.base_rate <= maxPrice.value)
    .filter(p => p.avg_rating >= minRating.value)
    .sort((a, b) => a._distance - b._distance))

function openProvider(p) {
  router.push({ name: 'provider-profile', params: { id: p.id } })
}
</script>

<template>
  <div class="fx-page">
    <h1 class="fw-bold mb-1" style="font-size:20px">Find a provider</h1>
    <div class="mb-3" style="font-size:13px;color:var(--fx-muted)">Verified providers near Metro City</div>

    <FilterBar
      :categories="providersStore.categories"
      v-model:model-category="category"
      v-model:model-max-distance="maxDistance"
      v-model:model-max-price="maxPrice"
      v-model:model-min-rating="minRating" />

    <!-- Leaflet map — markers update with the filtered set -->
    <ProviderMap :providers="filtered" :center="CITY" @select="openProvider" class="mb-3" />

    <div class="fw-bold mb-2" style="font-size:14px">{{ filtered.length }} providers found</div>
    <div class="d-flex flex-column gap-2">
      <ProviderCard v-for="p in filtered" :key="p.id" :provider="p" :distance="p._distance" @select="openProvider" />
      <div v-if="!filtered.length" class="text-center py-4" style="color:var(--fx-muted)">
        No providers match these filters.
      </div>
    </div>
  </div>
</template>
