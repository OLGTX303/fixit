<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useBookingsStore } from '../../stores/bookings'
import { useInfiniteList } from '../../composables/useInfiniteList'
import { useModalGuard } from '../../composables/useModalGuard'
import * as api from '../../services/api'

const auth = useAuthStore()
const bookingsStore = useBookingsStore()
const router = useRouter()
const activeTab = ref('all')
const cancellingId = ref(null)
const confirmId = ref(null)
useModalGuard(confirmId)

const TABS = [
  { key: 'all',       label: 'All' },
  { key: 'pending',   label: 'Pending' },
  { key: 'active',    label: 'In Progress' },
  { key: 'done',      label: 'Done' },
  { key: 'cancelled', label: 'Cancelled' },
  { key: 'rate',      label: 'Rate' },
]

const statusParam = computed(() => {
  if (activeTab.value === 'all' || activeTab.value === 'rate') return undefined
  return activeTab.value
})

const { items, loading, done, sentinel, reset } = useInfiniteList(
  (offset, size) => api.getBookings({ limit: size, offset, status: statusParam.value }), 20)

watch(activeTab, reset)

const filtered = computed(() => {
  const all = items.value
  if (activeTab.value === 'rate') return all.filter(b => b.status === 'completed')
  return all
})

const counts = computed(() => ({
  all:       items.value.length,
  pending:   items.value.filter(b => b.status === 'requested').length,
  active:    items.value.filter(b => ['accepted','in_progress'].includes(b.status)).length,
  done:      items.value.filter(b => ['completed','reviewed'].includes(b.status)).length,
  cancelled: items.value.filter(b => b.status === 'cancelled').length,
  rate:      items.value.filter(b => b.status === 'completed').length,
}))

const STATUS_CFG = {
  requested:   { label: 'Pending',     color: '#f59e0b', bg: 'rgba(245,158,11,0.12)' },
  accepted:    { label: 'Accepted',    color: '#3b82f6', bg: 'rgba(59,130,246,0.12)' },
  in_progress: { label: 'In Progress', color: '#8b5cf6', bg: 'rgba(139,92,246,0.12)' },
  completed:   { label: 'Done',        color: '#22c55e', bg: 'rgba(34,197,94,0.12)' },
  reviewed:    { label: 'Reviewed',    color: '#9ca3af', bg: 'rgba(0,0,0,0.06)' },
  cancelled:   { label: 'Cancelled',   color: '#ef4444', bg: 'rgba(239,68,68,0.12)' },
}

function badge(s) { return STATUS_CFG[s] || STATUS_CFG.requested }
function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-MY', { day: 'numeric', month: 'short', year: 'numeric' })
}
function initials(name) { return (name||'?').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase() }
function openDetail(b) { router.push({ name: 'job-detail', params: { id: b.id } }) }
function doRate(b)     { router.push({ name: 'rate-review', params: { id: b.id } }) }
function doChat(b)     { router.push({ name: 'chat', params: { id: b.id } }) }
function canCancel(b)  { return ['requested', 'accepted'].includes(b.status) }

function askCancel(b) { confirmId.value = b.id }
function dismissCancel() { confirmId.value = null }

async function doCancel(b) {
  if (cancellingId.value) return
  cancellingId.value = b.id
  try {
    await api.updateBookingStatus(b.id, 'cancelled')
    b.status = 'cancelled'
    bookingsStore.resetCache()
    confirmId.value = null
  } catch (e) {
    alert(e.message || 'Could not cancel booking')
  } finally {
    cancellingId.value = null
  }
}
</script>

<template>
  <div class="jt-root">
    <header class="jt-header">
      <h1 class="jt-title">My Bookings</h1>
    </header>

    <div class="jt-tabs">
      <button
        v-for="t in TABS" :key="t.key"
        class="jt-tab" :class="{ active: activeTab === t.key }"
        @click="activeTab = t.key"
      >
        {{ t.label }}
        <span v-if="counts[t.key]" class="jt-tab-count">{{ counts[t.key] }}</span>
      </button>
    </div>

    <div v-if="loading && !filtered.length" class="jt-empty">
      <span class="material-symbols-outlined jt-empty-icon">hourglass_empty</span>
      <p>Loading…</p>
    </div>

    <div v-else-if="!filtered.length" class="jt-empty">
      <span class="material-symbols-outlined jt-empty-icon">calendar_month</span>
      <p>No bookings here yet.</p>
      <button class="jt-find-btn" @click="router.push({ name: 'search' })">Find a Provider</button>
    </div>

    <div v-else class="jt-list">
      <div
        v-for="b in filtered" :key="b.id"
        class="jt-card liquid-glass"
        :class="{ cancelled: b.status === 'cancelled' }"
        @click="openDetail(b)"
      >
        <div class="jt-card-head">
          <div class="jt-avatar">{{ initials(b.provider?.name) }}</div>
          <div class="jt-prov-info">
            <span class="jt-prov-name">{{ b.provider?.name || 'Provider' }}</span>
            <span class="jt-prov-cat">{{ b.category?.name || 'Service' }}</span>
          </div>
          <span class="jt-badge" :style="{ color: badge(b.status).color, background: badge(b.status).bg }">
            {{ badge(b.status).label }}
          </span>
        </div>

        <div class="jt-sep"></div>

        <div class="jt-row">
          <div class="jt-cell">
            <span class="jt-cell-label">Date</span>
            <span class="jt-cell-val">{{ fmtDate(b.scheduled_at) }}</span>
          </div>
          <div class="jt-cell">
            <span class="jt-cell-label">Booking</span>
            <span class="jt-cell-val">#{{ b.id }}</span>
          </div>
          <div class="jt-cell right">
            <span class="jt-cell-label">Amount</span>
            <span class="jt-cell-val accent">RM {{ b.total ?? b.total_price ?? b.provider?.base_rate ?? '—' }}</span>
          </div>
        </div>

        <div class="jt-actions">
          <button
            v-if="['accepted','in_progress'].includes(b.status)"
            class="jt-btn primary" @click.stop="openDetail(b)"
          >Track Job</button>
          <button
            v-if="b.status === 'completed'"
            class="jt-btn primary" @click.stop="doRate(b)"
          >Rate & Review</button>
          <button v-if="b.status !== 'cancelled'" class="jt-btn outline" @click.stop="doChat(b)">Message</button>
          <button
            v-if="canCancel(b)"
            class="jt-btn ghost" @click.stop="askCancel(b)"
          >Cancel</button>
        </div>
      </div>

      <div ref="sentinel" class="jt-sentinel"></div>
      <p v-if="loading" class="jt-more">Loading more…</p>
    </div>

    <!-- Cancel confirm dialog -->
    <Teleport to="body">
      <div v-if="confirmId" class="jt-modal-backdrop" @click.self="dismissCancel">
        <div class="jt-modal">
          <h3 class="jt-modal-title">Cancel booking?</h3>
          <p class="jt-modal-text">If you already paid, your card will be refunded automatically.</p>
          <div class="jt-modal-actions">
            <button class="jt-btn outline" @click="dismissCancel">Keep booking</button>
            <button
              class="jt-btn danger"
              :disabled="!!cancellingId"
              @click="doCancel(filtered.find(x => x.id === confirmId))"
            >{{ cancellingId ? 'Cancelling…' : 'Yes, cancel' }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.jt-root { min-height: 100vh; background: var(--fx-bg); }
.jt-header { padding: 16px 16px 0; }
.jt-title  { font-size: 20px; font-weight: 800; color: var(--fx-text); margin: 0 0 14px; }
.jt-tabs {
  display: flex; overflow-x: auto; padding: 0 16px 0;
  scrollbar-width: none;
  border-bottom: 1px solid rgba(0,0,0,0.07);
}
.jt-tabs::-webkit-scrollbar { display: none; }
.jt-tab {
  flex-shrink: 0; display: flex; align-items: center; gap: 5px;
  padding: 10px 14px;
  font-size: 14px; font-weight: 600; font-family: inherit;
  color: var(--fx-muted); background: none; border: none; cursor: pointer;
  border-bottom: 2.5px solid transparent; transition: color 0.18s, border-color 0.18s;
  white-space: nowrap;
}
.jt-tab.active { color: var(--fx-accent); border-bottom-color: var(--fx-accent); }
.jt-tab-count {
  font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 999px;
  background: rgba(255,102,53,0.12); color: var(--fx-accent);
}
.jt-empty {
  display: flex; flex-direction: column; align-items: center;
  gap: 10px; padding: 64px 24px; color: var(--fx-muted); text-align: center;
}
.jt-empty-icon { font-size: 52px; opacity: .25; }
.jt-find-btn {
  margin-top: 6px; padding: 10px 28px; border-radius: 999px;
  background: var(--fx-accent); color: #fff; border: none; cursor: pointer;
  font-size: 14px; font-weight: 700; font-family: inherit;
  box-shadow: 0 3px 12px rgba(255,102,53,0.28);
}
.jt-list { padding: 12px 0 80px; display: flex; flex-direction: column; gap: 0; }
.jt-card {
  margin: 0 12px 10px; border-radius: 18px; padding: 16px;
  cursor: pointer; transition: transform 0.15s ease;
}
.jt-card.cancelled { opacity: 0.72; border: 1px dashed rgba(239,68,68,0.25); }
.jt-card:active { transform: scale(0.985); }
.jt-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.jt-avatar {
  width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; font-weight: 800; color: var(--fx-accent);
  background: linear-gradient(135deg, rgba(255,102,53,0.15), rgba(255,181,159,0.12));
  border: 2px solid rgba(255,255,255,0.65);
}
.jt-prov-info { flex: 1; min-width: 0; }
.jt-prov-name { display: block; font-size: 14px; font-weight: 700; color: var(--fx-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.jt-prov-cat  { font-size: 12px; color: var(--fx-muted); }
.jt-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 999px; flex-shrink: 0; }
.jt-sep { height: 1px; background: rgba(0,0,0,0.06); margin-bottom: 12px; }
.jt-row { display: flex; margin-bottom: 14px; }
.jt-cell { flex: 1; display: flex; flex-direction: column; gap: 2px; }
.jt-cell.right { align-items: flex-end; }
.jt-cell-label { font-size: 11px; color: var(--fx-muted); }
.jt-cell-val   { font-size: 13px; font-weight: 600; color: var(--fx-text); }
.jt-cell-val.accent { color: var(--fx-accent); font-size: 14px; font-weight: 800; }
.jt-actions { display: flex; gap: 8px; }
.jt-btn {
  flex: 1; padding: 9px 0; border-radius: 999px; cursor: pointer;
  font-size: 13px; font-weight: 700; font-family: inherit; border: none;
  transition: transform 0.15s, opacity 0.15s;
}
.jt-btn:active  { transform: scale(0.96); }
.jt-btn.primary { background: var(--fx-accent); color: #fff; box-shadow: 0 3px 10px rgba(255,102,53,0.25); }
.jt-btn.outline { background: transparent; color: var(--fx-accent); border: 1.5px solid var(--fx-accent); flex: 0.85; }
.jt-btn.ghost   { background: rgba(0,0,0,0.05); color: var(--fx-muted); flex: 0.65; }
.jt-btn.danger  { background: #ef4444; color: #fff; }
.jt-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.jt-sentinel { height: 1px; }
.jt-more { text-align: center; font-size: 13px; color: var(--fx-muted); padding: 8px; }
.jt-modal-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center; z-index: 9999; padding: 20px;
}
.jt-modal {
  background: #fff; border-radius: 18px; padding: 24px; max-width: 360px; width: 100%;
  box-shadow: 0 12px 40px rgba(0,0,0,0.18);
}
.jt-modal-title { margin: 0 0 8px; font-size: 18px; font-weight: 800; }
.jt-modal-text { margin: 0 0 20px; font-size: 14px; color: var(--fx-muted); line-height: 1.5; }
.jt-modal-actions { display: flex; gap: 10px; }
</style>