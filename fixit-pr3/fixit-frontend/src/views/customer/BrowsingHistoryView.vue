<script setup>
import { useRouter } from 'vue-router'
import { useInfiniteList } from '../../composables/useInfiniteList'
import * as api from '../../services/api'
import ProviderGridCard from '../../components/ProviderGridCard.vue'

const router = useRouter()

const { items, loading, done, sentinel, reset } = useInfiniteList(
  (offset, size) => api.getBrowsingHistory({ limit: size, offset }), 20)

function openProvider(p) {
  router.push({ name: 'provider-profile', params: { id: p.id } })
}

async function clearAll() {
  if (!items.value.length) return
  if (!confirm('Clear all browsing history?')) return
  try {
    await api.clearBrowsingHistory()
    reset()
  } catch (e) {
    alert(e.message || 'Could not clear history')
  }
}
</script>

<template>
  <div class="bh-root fx-view-root">
    <header class="bh-header">
      <button class="bh-back" @click="router.back()">
        <span class="material-symbols-outlined" style="font-size:22px">arrow_back</span>
      </button>
      <h1 class="bh-title">Browsing History</h1>
      <button v-if="items.length" class="bh-clear" @click="clearAll">Clear</button>
    </header>

    <div v-if="loading && !items.length" class="bh-empty">
      <span class="material-symbols-outlined bh-empty-icon">hourglass_empty</span>
      <p>Loading…</p>
    </div>

    <div v-else-if="!items.length" class="bh-empty">
      <span class="material-symbols-outlined bh-empty-icon">history</span>
      <p>No recently viewed providers.</p>
      <button class="bh-find-btn" @click="router.push({ name: 'search' })">Browse Providers</button>
    </div>

    <div v-else class="bh-grid">
      <ProviderGridCard
        v-for="p in items" :key="p.id"
        :provider="p"
        @select="openProvider"
      />
      <div ref="sentinel" class="bh-sentinel"></div>
      <p v-if="loading" class="bh-more">Loading more…</p>
      <p v-else-if="done" class="bh-more muted">End of list</p>
    </div>
  </div>
</template>

<style scoped>
.bh-root { min-height: 100vh; padding-bottom: 80px; }
.bh-header { display: flex; align-items: center; gap: 8px; padding: 16px; }
.bh-back { background: none; border: none; cursor: pointer; color: var(--fx-text); padding: 4px; }
.bh-title { font-size: 20px; font-weight: 800; margin: 0; flex: 1; color: var(--fx-text); }
.bh-clear {
  background: none; border: none; cursor: pointer;
  font-size: 13px; font-weight: 600; color: var(--fx-muted); padding: 4px 8px;
}
.bh-empty {
  display: flex; flex-direction: column; align-items: center;
  gap: 10px; padding: 64px 24px; color: var(--fx-muted); text-align: center;
}
.bh-empty-icon { font-size: 52px; opacity: .25; }
.bh-find-btn {
  margin-top: 6px; padding: 10px 28px; border-radius: 999px;
  background: var(--fx-accent); color: #fff; border: none; cursor: pointer;
  font-size: 14px; font-weight: 700; font-family: inherit;
}
.bh-grid {
  display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
  padding: 0 12px 24px;
}
@media (min-width: 768px) {
  .bh-grid { grid-template-columns: repeat(4, 1fr); }
}
.bh-sentinel { height: 1px; grid-column: 1 / -1; }
.bh-more { grid-column: 1 / -1; text-align: center; font-size: 13px; color: var(--fx-muted); }
.bh-more.muted { opacity: .6; }
</style>