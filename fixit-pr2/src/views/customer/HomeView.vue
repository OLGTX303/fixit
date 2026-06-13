<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useProvidersStore } from '../../stores/providers'
import { useAuthStore } from '../../stores/auth'
import CategoryGrid from '../../components/CategoryGrid.vue'
import ProviderCard from '../../components/ProviderCard.vue'
import AppIcon from '../../components/AppIcon.vue'

const providersStore = useProvidersStore()
const auth = useAuthStore()
const router = useRouter()

onMounted(() => providersStore.load())

// Top rated verified providers (computed from shared store).
const topRated = computed(() =>
  [...providersStore.verified].sort((a, b) => b.avg_rating - a.avg_rating).slice(0, 4))

function openCategory(cat) {
  router.push({ name: 'search', query: { category: cat.id } })
}
function openProvider(p) {
  router.push({ name: 'provider-profile', params: { id: p.id } })
}
</script>

<template>
  <div class="fx-page">
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <div style="font-size:22px;font-weight:800">Good morning, {{ auth.user?.name?.split(' ')[0] }}</div>
        <div style="font-size:14px;color:var(--fx-muted)">What do you need fixed today?</div>
      </div>
      <div class="d-flex align-items-center justify-content-center"
           style="width:40px;height:40px;border-radius:50%;background:var(--fx-border-soft);color:var(--fx-muted)">
        <AppIcon name="bell" :size="20" />
      </div>
    </div>

    <div class="fx-input mb-4" role="button" @click="router.push({ name: 'search' })">
      <AppIcon name="search" :size="18" style="color:var(--fx-muted-soft)" />
      <span style="color:var(--fx-muted-soft)">Search services or providers…</span>
    </div>

    <div class="fw-bold mb-3" style="font-size:16px">Service Categories</div>
    <CategoryGrid :categories="providersStore.categories" @select="openCategory" />

    <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
      <span class="fw-bold" style="font-size:16px">Top Rated Nearby</span>
      <router-link :to="{ name: 'search' }" class="text-accent fw-semibold text-decoration-none" style="font-size:13px">See all</router-link>
    </div>

    <div v-if="providersStore.loading" class="text-center py-4" style="color:var(--fx-muted)">Loading…</div>
    <div v-else class="d-flex flex-column gap-2">
      <ProviderCard v-for="p in topRated" :key="p.id" :provider="p" @select="openProvider" />
    </div>
  </div>
</template>
