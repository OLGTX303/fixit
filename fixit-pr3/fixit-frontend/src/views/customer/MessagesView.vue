<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useAuthStore } from '../../stores/auth'
import { isDesktop } from '../../composables/useViewport.js'
import ChatView from '../provider/ChatView.vue'

const router = useRouter()
const bookingsStore = useBookingsStore()
const auth = useAuthStore()

const selectedId = ref(null)

onMounted(() => { bookingsStore.load() })

const myBookings = computed(() => bookingsStore.forCustomer(auth.user?.id))

const STATUS = {
  inquiry:     { c: 'var(--fx-blue)',    bg: 'var(--fx-blue-soft)',    label: 'Inquiry' },
  requested:   { c: 'var(--fx-warn)',    bg: 'var(--fx-warn-soft)',    label: 'Requested' },
  accepted:    { c: 'var(--fx-blue)',    bg: 'var(--fx-blue-soft)',    label: 'Accepted' },
  in_progress: { c: 'var(--fx-blue)',    bg: 'var(--fx-blue-soft)',    label: 'In Progress' },
  completed:   { c: 'var(--fx-success)', bg: 'var(--fx-success-soft)', label: 'Completed' },
  reviewed:    { c: 'var(--fx-muted)',   bg: 'rgba(255,255,255,0.18)', label: 'Reviewed' },
}

function initials(name) {
  return (name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
}

function select(b) {
  if (isDesktop.value) { selectedId.value = b.id }
  else { router.push({ name: 'chat', params: { id: b.id } }) }
}
</script>

<template>
  <!-- ── DESKTOP: Xianyu-style split panel ─────────────────────────── -->
  <div v-if="isDesktop" class="msg-split">

    <!-- Left: conversation list -->
    <div class="msg-panel-left">
      <div class="msg-panel-header">
        <span class="msg-panel-title">Messages</span>
        <span class="msg-panel-sub">{{ myBookings.length }} conversation{{ myBookings.length !== 1 ? 's' : '' }}</span>
      </div>

      <div class="msg-conv-list">
        <button
          v-for="b in myBookings" :key="b.id"
          class="msg-conv-row"
          :class="{ active: selectedId === b.id }"
          @click="select(b)"
        >
          <div class="msg-conv-avatar">{{ initials(b.provider?.name) }}</div>
          <div class="msg-conv-body">
            <div class="msg-conv-name">{{ b.provider?.name || 'Provider' }}</div>
            <div class="msg-conv-sub">{{ b.category?.name || 'Service' }} · Job #{{ b.id }}</div>
          </div>
          <span class="fx-badge msg-conv-badge"
                :style="{ color: STATUS[b.status]?.c, background: STATUS[b.status]?.bg }">
            {{ STATUS[b.status]?.label || b.status }}
          </span>
        </button>

        <div v-if="!myBookings.length" class="msg-empty-list">
          <span class="material-symbols-outlined" style="font-size:40px;color:var(--fx-muted-soft)">chat_bubble</span>
          <p>No conversations yet</p>
          <button class="msg-find-btn" @click="router.push({ name: 'search' })">Find a provider</button>
        </div>
      </div>
    </div>

    <!-- Right: active chat or placeholder -->
    <div class="msg-panel-right">
      <ChatView v-if="selectedId" :key="selectedId" :booking-id="selectedId" :embedded="true" />
      <div v-else class="msg-chat-empty">
        <span class="material-symbols-outlined" style="font-size:56px;color:var(--fx-muted-soft)">forum</span>
        <p>Select a conversation to start chatting</p>
      </div>
    </div>

  </div>

  <!-- ── MOBILE: existing card list ────────────────────────────────── -->
  <div v-else class="fx-page">
    <div class="mb-4">
      <h1 class="fw-bold mb-1" style="font-size:24px;letter-spacing:-0.02em">Messages</h1>
      <p style="font-size:14px;color:var(--fx-muted);margin:0">Your conversations with service providers</p>
    </div>
    <div class="d-flex flex-column gap-2">
      <button v-for="b in myBookings" :key="b.id"
              class="conv-row fx-card d-flex align-items-center gap-3"
              @click="select(b)">
        <div class="fx-avatar" style="width:48px;height:48px;font-size:16px;font-weight:800;flex-shrink:0">
          {{ initials(b.provider?.name) }}
        </div>
        <div class="flex-grow-1" style="min-width:0;text-align:left">
          <div class="fw-semibold" style="font-size:15px;margin-bottom:3px">{{ b.provider?.name || 'Provider' }}</div>
          <div style="font-size:12px;color:var(--fx-muted)">{{ b.category?.name || 'Service' }} · Job #{{ b.id }}</div>
        </div>
        <div class="d-flex flex-column align-items-end gap-2" style="flex-shrink:0">
          <span class="fx-badge" :style="{ color: STATUS[b.status]?.c, background: STATUS[b.status]?.bg }">
            {{ STATUS[b.status]?.label || b.status }}
          </span>
          <span class="material-symbols-outlined" style="font-size:18px;color:var(--fx-muted-soft)">chevron_right</span>
        </div>
      </button>
      <div v-if="!myBookings.length" class="fx-card text-center py-5">
        <span class="material-symbols-outlined" style="font-size:44px;color:var(--fx-muted-soft);display:block;margin-bottom:12px">chat</span>
        <div class="fw-semibold" style="font-size:15px;margin-bottom:4px">No conversations yet</div>
        <div style="font-size:13px;color:var(--fx-muted)">Book a service to start chatting</div>
        <button class="btn btn-primary mt-4" style="border-radius:999px;padding:11px 28px" @click="router.push({ name: 'search' })">Find a provider</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ── Desktop split panel ─────────────────────────────────────────────── */
.msg-split {
  display: flex;
  height: calc(100vh - 0px);
  overflow: hidden;
}

/* Left panel */
.msg-panel-left {
  width: 300px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background:
    radial-gradient(ellipse 60% 40% at 10% 5%, rgba(255,255,255,0.32) 0%, transparent 65%),
    rgba(255,255,255,0.08);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-right: 0.5px solid rgba(255,255,255,0.50);
  box-shadow: inset -1px 0 0 rgba(255,255,255,0.30);
}

.msg-panel-header {
  padding: 20px 20px 14px;
  border-bottom: 0.5px solid rgba(255,255,255,0.40);
  flex-shrink: 0;
}
.msg-panel-title { font-size: 20px; font-weight: 800; color: var(--fx-text); display: block; }
.msg-panel-sub   { font-size: 12px; color: var(--fx-muted); margin-top: 2px; display: block; }

.msg-conv-list { flex: 1; overflow-y: auto; padding: 8px 0; }

.msg-conv-row {
  width: 100%; display: flex; align-items: center; gap: 12px;
  padding: 12px 16px; border: none; background: transparent; cursor: pointer;
  text-align: left; transition: background 0.12s;
  border-radius: 0;
  position: relative;
}
.msg-conv-row::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
  background: var(--fx-accent); opacity: 0; border-radius: 0 3px 3px 0;
  transition: opacity 0.15s;
}
.msg-conv-row:hover { background: rgba(255,255,255,0.18); }
.msg-conv-row.active { background: rgba(255,102,53,0.08); }
.msg-conv-row.active::before { opacity: 1; }

.msg-conv-avatar {
  width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(160deg, #FF8056, #FF6635);
  color: #fff; font-size: 15px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 8px rgba(255,102,53,0.22);
}
.msg-conv-body { flex: 1; min-width: 0; }
.msg-conv-name { font-size: 14px; font-weight: 600; color: var(--fx-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-conv-sub  { font-size: 12px; color: var(--fx-muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-conv-badge { font-size: 10px; flex-shrink: 0; }

.msg-empty-list {
  display: flex; flex-direction: column; align-items: center;
  padding: 48px 20px; gap: 10px; text-align: center;
}
.msg-empty-list p { font-size: 13px; color: var(--fx-muted); margin: 0; }
.msg-find-btn {
  margin-top: 6px; padding: 9px 22px; border-radius: 999px; border: none;
  background: var(--fx-accent); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer;
}

/* Right panel */
.msg-panel-right {
  flex: 1; min-width: 0;
  display: flex; flex-direction: column;
}
.msg-chat-empty {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 12px;
  color: var(--fx-muted); font-size: 14px;
}

/* ── Mobile card row ─────────────────────────────────────────────────── */
.conv-row {
  width: 100%; border: none; cursor: pointer;
  padding: 14px 16px; text-align: left;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.conv-row:hover { transform: translateY(-1px); box-shadow: inset 0 1px 1px rgba(255,255,255,0.75), 0 12px 36px rgba(0,0,0,0.09); }
.conv-row:active { transform: scale(0.98); }
</style>
