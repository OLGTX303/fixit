<script setup>
import { onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useInfiniteList } from '../../composables/useInfiniteList'
import * as api from '../../services/api'
import { useFavoritesStore } from '../../stores/favorites'
import ProviderGridCard from '../../components/ProviderGridCard.vue'

const router = useRouter()
const favorites = useFavoritesStore()

const { items, loading, done, sentinel, reset } = useInfiniteList(
  (offset, size) => api.getFavorites({ limit: size, offset }), 20)

onMounted(() => favorites.load())

watch(items, (list) => {
  favorites.ids = list.map(p => p.id)
  favorites.loaded = true
}, { deep: true })

function openProvider(p) {
  router.push({ name: 'provider-profile', params: { id: p.id } })
}
</script>

<template>
  <div class="fv-root fx-view-root">
    <header class="fv-header">
      <button class="fv-back" @click="router.back()">
        <span class="material-symbols-outlined" style="font-size:22px">arrow_back</span>
      </button>
      <h1 class="fv-title">Favourites</h1>
    </header>

    <div v-if="loading && !items.length" class="fv-empty">
      <span class="material-symbols-outlined fv-empty-icon">hourglass_empty</span>
      <p>Loading…</p>
    </div>

    <div v-else-if="!items.length" class="fv-empty">
      <span class="material-symbols-outlined fv-empty-icon">favorite</span>
      <p>No favourites yet.</p>
      <button class="fv-find-btn" @click="router.push({ name: 'search' })">Find Providers</button>
    </div>

    <div v-else class="fv-grid">
      <ProviderGridCard
        v-for="p in items" :key="p.id"
        :provider="p"
        show-favorite
        @select="openProvider"
      />
      <div ref="sentinel" class="fv-sentinel"></div>
      <p v-if="loading" class="fv-more">Loading more…</p>
      <p v-else-if="done" class="fv-more muted">End of list</p>
    </div>
  </div>
</template>

<style scoped>
.fv-root { min-height: 100vh; padding-bottom: 80px; }
.fv-header { display: flex; align-items: center; gap: 8px; padding: 16px; }
.fv-back { background: none; border: none; cursor: pointer; color: var(--fx-text); padding: 4px; }
.fv-title { font-size: 20px; font-weight: 800; margin: 0; color: var(--fx-text); }
.fv-empty {
  display: flex; flex-direction: column; align-items: center;
  gap: 10px; padding: 64px 24px; color: var(--fx-muted); text-align: center;
}
.fv-empty-icon { font-size: 52px; opacity: .25; }
.fv-find-btn {
  margin-top: 6px; padding: 10px 28px; border-radius: 999px;
  background: var(--fx-accent); color: #fff; border: none; cursor: pointer;
  font-size: 14px; font-weight: 700; font-family: inherit;
}
.fv-grid {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
  padding: 0 12px 24px;
}
@media (min-width: 768px) {
  .fv-grid { grid-template-columns: repeat(4, 1fr); }
}
.fv-sentinel { height: 1px; grid-column: 1 / -1; }
.fv-more { grid-column: 1 / -1; text-align: center; font-size: 13px; color: var(--fx-muted); }
.fv-more.muted { opacity: .6; }
</style>