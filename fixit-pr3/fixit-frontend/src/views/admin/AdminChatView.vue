<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useBookingsStore } from '../../stores/bookings'
import * as api from '../../services/api'
import AppIcon from '../../components/AppIcon.vue'

const bookingsStore = useBookingsStore()
const selectedId = ref(null)
const messages = ref([])
const loading = ref(false)
const error = ref('')
const listEl = ref(null)
const draft = ref('')
const sending = ref(false)

onMounted(async () => {
  await bookingsStore.load()
  if (bookingsStore.bookings.length) selectId(bookingsStore.bookings[0].id)
})

const selected = computed(() => bookingsStore.byId(selectedId.value))

function senderName(m) {
  const b = selected.value
  if (!b) return 'User'
  return m.sender_id === b.customer_id
    ? (b.customer?.name || 'Customer')
    : (b.provider?.name || 'Provider')
}

function senderInitials(m) {
  return senderName(m).split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
}

function isCustomer(m) {
  return m.sender_id === selected.value?.customer_id
}

async function selectId(id) {
  selectedId.value = id
  loading.value = true
  error.value = ''
  messages.value = []
  try {
    messages.value = await api.getMessagesForJob(id)
    nextTick(() => { if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight })
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function bodyOf(m) {
  if (m.is_encrypted) return null
  return m.body || '[empty]'
}

function timeOf(iso) {
  return new Date(iso).toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' })
}

const STATUS = {
  requested:   { c: 'var(--fx-warn)',    bg: 'var(--fx-warn-soft)',    label: 'Requested' },
  accepted:    { c: 'var(--fx-blue)',    bg: 'var(--fx-blue-soft)',    label: 'Accepted' },
  in_progress: { c: 'var(--fx-blue)',    bg: 'var(--fx-blue-soft)',    label: 'In Progress' },
  completed:   { c: 'var(--fx-success)', bg: 'var(--fx-success-soft)', label: 'Completed' },
  reviewed:    { c: 'var(--fx-muted)',   bg: 'rgba(255,255,255,0.18)', label: 'Reviewed' },
}

function bookingInitials(b) {
  return (b.customer?.name || '—').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
}

async function sendCs() {
  if (!draft.value.trim() || !selectedId.value || sending.value) return
  sending.value = true
  try {
    const msg = await api.sendMessage(selectedId.value, { body: draft.value.trim() })
    messages.value.push(msg)
    draft.value = ''
    nextTick(() => { if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight })
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <div class="admin-chat-layout">
    <!-- Left: conversation list -->
    <div class="admin-sidebar">
      <div class="sidebar-head">
        <h1 class="fw-bold" style="font-size:18px;letter-spacing:-0.01em;margin:0">Chats</h1>
        <div style="font-size:12px;color:var(--fx-muted);margin-top:2px">{{ bookingsStore.bookings.length }} conversations</div>
      </div>

      <div class="sidebar-list">
        <button
          v-for="b in bookingsStore.bookings"
          :key="b.id"
          class="conv-item"
          :class="{ active: b.id === selectedId }"
          @click="selectId(b.id)"
        >
          <div class="fx-avatar" style="width:40px;height:40px;font-size:13px;font-weight:800;flex-shrink:0">
            {{ bookingInitials(b) }}
          </div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="fw-semibold" style="font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              {{ b.customer?.name?.split(' ')[0] }} · {{ b.provider?.name?.split(' ')[0] }}
            </div>
            <div style="font-size:11px;color:var(--fx-muted);margin-top:2px">
              Job #{{ b.id }} · {{ b.category?.name || 'Service' }}
            </div>
          </div>
          <span class="fx-badge"
                :style="{ color: STATUS[b.status]?.c, background: STATUS[b.status]?.bg, fontSize: '10px', padding: '2px 8px' }">
            {{ STATUS[b.status]?.label || b.status }}
          </span>
        </button>

        <div v-if="!bookingsStore.bookings.length" style="padding:32px 16px;text-align:center;color:var(--fx-muted);font-size:13px">
          No bookings to monitor
        </div>
      </div>
    </div>

    <!-- Right: message thread -->
    <div class="admin-main">
      <template v-if="selected">
        <!-- Header -->
        <div class="msg-header">
          <div class="fx-avatar" style="width:40px;height:40px;font-size:14px;font-weight:800;flex-shrink:0">
            {{ bookingInitials(selected) }}
          </div>
          <div class="flex-grow-1">
            <div class="fw-bold" style="font-size:15px">
              {{ selected.customer?.name }} · {{ selected.provider?.name }}
            </div>
            <div style="font-size:12px;color:var(--fx-muted)">
              Job #{{ selected.id }} · {{ selected.category?.name || 'Service' }}
            </div>
          </div>
          <div class="fx-badge" style="background:rgba(255,102,53,0.09);color:var(--fx-accent);font-size:11px;padding:4px 10px;gap:4px">
            <span class="material-symbols-outlined" style="font-size:13px;font-variation-settings:'FILL' 1">support_agent</span>
            CS Mode
          </div>
        </div>

        <!-- CS notice -->
        <div class="e2e-bar">
          <span class="material-symbols-outlined" style="font-size:14px">support_agent</span>
          Customer Service mode �?messages you send appear as support in this conversation
        </div>

        <!-- Messages -->
        <div v-if="loading" class="msg-empty">
          <span style="color:var(--fx-muted);font-size:13px">Loading…</span>
        </div>
        <div v-else-if="error" style="padding:16px 20px">
          <div class="alert alert-danger py-2" style="font-size:13px">{{ error }}</div>
        </div>
        <div v-else ref="listEl" class="msg-list">
          <div v-for="m in messages" :key="m.id" class="msg-row"
               :class="isCustomer(m) ? 'from-customer' : 'from-provider'">
            <div v-if="isCustomer(m)" class="fx-avatar msg-avatar">
              {{ senderInitials(m) }}
            </div>
            <div class="msg-col">
              <div class="msg-name">{{ senderName(m) }}</div>
              <div class="msg-bubble" :class="isCustomer(m) ? 'bubble-customer' : 'bubble-provider'">
                <template v-if="bodyOf(m)">{{ bodyOf(m) }}</template>
                <span v-else class="encrypted-label">🔒 Encrypted message</span>
              </div>
              <div class="msg-meta">
                {{ timeOf(m.sent_at) }}
                <span v-if="m.harm_status && m.harm_status !== 'clear'"
                      style="color:var(--fx-warn);margin-left:6px">
                  · {{ m.harm_status }}
                </span>
              </div>
            </div>
            <div v-if="!isCustomer(m)" class="fx-avatar msg-avatar provider-avatar">
              {{ senderInitials(m) }}
            </div>
          </div>
          <div v-if="!messages.length" class="msg-empty">
            <span class="material-symbols-outlined" style="font-size:32px;color:var(--fx-muted-soft);display:block;margin-bottom:8px">chat_bubble</span>
            No messages in this conversation yet
          </div>
        </div>

        <!-- CS compose bar -->
        <div class="cs-compose">
          <input v-model="draft" class="cs-input" placeholder="Type a customer service message…"
                 @keydown.enter.prevent="sendCs" />
          <button class="cs-send" :disabled="!draft.trim() || sending" @click="sendCs">
            <span class="material-symbols-outlined" style="font-size:20px">send</span>
          </button>
        </div>
      </template>

      <div v-else class="msg-empty">
        <span class="material-symbols-outlined" style="font-size:48px;color:var(--fx-muted-soft);display:block;margin-bottom:10px">forum</span>
        <div style="font-size:14px;color:var(--fx-muted)">Select a conversation to view messages</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.admin-chat-layout {
  display: flex;
  height: calc(100vh - 80px);
  overflow: hidden;
  max-width: 100%;
}

/* ── Sidebar ── */
.admin-sidebar {
  width: 280px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background: rgba(255,255,255,0.30);
  backdrop-filter: blur(40px);
  -webkit-backdrop-filter: blur(40px);
  border-right: 1px solid rgba(255,255,255,0.40);
  position: relative;
}
.admin-sidebar::before {
  content: "";
  position: absolute;
  inset: 0;
  background: linear-gradient(to bottom, rgba(255,255,255,0.18) 0%, rgba(255,255,255,0.04) 100%);
  pointer-events: none;
}

.sidebar-head {
  padding: 20px 16px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.35);
  background: rgba(255,255,255,0.20);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  position: relative;
  z-index: 1;
}

.sidebar-list {
  flex: 1;
  overflow-y: auto;
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 3px;
  position: relative;
  z-index: 1;
}

.conv-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 12px;
  border-radius: 14px;
  border: 1px solid transparent;
  background: transparent;
  cursor: pointer;
  text-align: left;
  color: var(--fx-text);
  font-family: inherit;
  transition: all 0.18s ease;
}
.conv-item:hover {
  background: rgba(255,255,255,0.38);
  border-color: rgba(255,255,255,0.50);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.55);
}
.conv-item.active {
  background: rgba(255,102,53,0.08);
  border-color: rgba(255,102,53,0.20);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.40);
}

/* ── Main panel ── */
.admin-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: rgba(255,255,255,0.12);
}

.msg-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 20px;
  background: rgba(255,255,255,0.40);
  backdrop-filter: blur(30px);
  -webkit-backdrop-filter: blur(30px);
  border-bottom: 1px solid rgba(255,255,255,0.40);
  flex-shrink: 0;
  position: relative;
}
.msg-header::before {
  content: "";
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(to right, rgba(255,255,255,0.80), rgba(255,255,255,0.20));
  pointer-events: none;
}

.e2e-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 7px 20px;
  font-size: 12px;
  color: var(--fx-muted);
  background: rgba(255,255,255,0.18);
  border-bottom: 1px solid rgba(255,255,255,0.28);
  flex-shrink: 0;
}

.msg-list {
  flex: 1;
  overflow-y: auto;
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.msg-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}
.from-customer { justify-content: flex-start; }
.from-provider { justify-content: flex-end; }

.msg-avatar {
  width: 28px;
  height: 28px;
  font-size: 10px;
  font-weight: 800;
  flex-shrink: 0;
}
.provider-avatar {
  background: linear-gradient(135deg, rgba(255,102,53,0.22), rgba(255,181,159,0.18));
}

.msg-col {
  max-width: 64%;
  display: flex;
  flex-direction: column;
}
.from-customer .msg-col { align-items: flex-start; }
.from-provider .msg-col { align-items: flex-end; }

.msg-name {
  font-size: 11px;
  color: var(--fx-muted);
  margin-bottom: 3px;
}

.msg-bubble {
  padding: 10px 14px;
  border-radius: 18px;
  font-size: 13px;
  line-height: 1.55;
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.55), 0 2px 8px rgba(0,0,0,0.05);
  color: var(--fx-text);
}
.bubble-customer {
  background: rgba(255,255,255,0.50);
  border: 1px solid rgba(255,255,255,0.60);
  border-bottom-left-radius: 4px;
}
.bubble-provider {
  background: linear-gradient(160deg, rgba(255,125,84,0.16), rgba(255,102,53,0.10));
  border: 1px solid rgba(255,102,53,0.18);
  border-bottom-right-radius: 4px;
}
.encrypted-label {
  color: var(--fx-muted);
  font-style: italic;
}

.msg-meta {
  font-size: 10px;
  color: var(--fx-muted-soft);
  margin-top: 3px;
}

.msg-empty {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--fx-muted);
  font-size: 13px;
  padding: 40px;
}

/* CS compose bar */
.cs-compose {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: rgba(255,255,255,0.40);
  backdrop-filter: blur(30px);
  border-top: 1px solid rgba(255,255,255,0.40);
  flex-shrink: 0;
}
.cs-input {
  flex: 1;
  padding: 10px 14px;
  border-radius: 22px;
  border: 1.5px solid rgba(255,255,255,0.60);
  background: rgba(255,255,255,0.55);
  font-size: 13px;
  outline: none;
  color: var(--fx-text);
}
.cs-input:focus { border-color: var(--fx-accent); }
.cs-send {
  width: 40px; height: 40px;
  border-radius: 50%;
  border: none;
  background: var(--fx-accent);
  color: #fff;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}
.cs-send:disabled { opacity: 0.45; cursor: default; }

/* Mobile: stacked layout */
@media (max-width: 680px) {
  .admin-chat-layout {
    flex-direction: column;
    height: auto;
  }
  .admin-sidebar {
    width: 100%;
    max-height: 220px;
    border-right: none;
    border-bottom: 1px solid rgba(255,255,255,0.40);
  }
  .sidebar-list {
    flex-direction: row;
    overflow-x: auto;
    overflow-y: hidden;
    gap: 6px;
    padding: 8px 12px;
  }
  .conv-item {
    flex-direction: column;
    align-items: center;
    min-width: 100px;
    padding: 8px;
    gap: 4px;
    text-align: center;
  }
  .admin-main {
    height: calc(100vh - 300px);
    min-height: 360px;
  }
}
</style>
