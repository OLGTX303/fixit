<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useAuthStore } from '../../stores/auth'
import { useProvidersStore } from '../../stores/providers'
import { isDesktop } from '../../composables/useViewport.js'
import ChatView from './ChatView.vue'

const router = useRouter()
const bookingsStore = useBookingsStore()
const auth = useAuthStore()
const providersStore = useProvidersStore()

const selectedId = ref(null)
const selectedGroupKey = ref(null)
const brokenAvatars = ref({})
let pollTimer = null
let polling = false

onMounted(async () => {
  await Promise.all([bookingsStore.reload(), providersStore.load()])
  pollTimer = window.setInterval(refreshConversations, 3000)
})

onUnmounted(() => {
  if (pollTimer) window.clearInterval(pollTimer)
})

const profile = computed(() => providersStore.providers.find(p => p.user_id === auth.user?.id))
const myJobs  = computed(() => profile.value ? bookingsStore.forProvider(profile.value.id) : [])
const conversations = computed(() => groupByPerson(myJobs.value, 'customer'))

watch(conversations, (list) => {
  if (!isDesktop.value || !list.length) return
  if (!selectedGroupKey.value || !list.some((c) => c.key === selectedGroupKey.value)) {
    select(list[0])
  }
}, { immediate: true })

const STATUS = {
  inquiry:     { c: 'var(--fx-blue)',    bg: 'var(--fx-blue-soft)',    label: 'Inquiry' },
  requested:   { c: 'var(--fx-warn)',    bg: 'var(--fx-warn-soft)',    label: 'Requested' },
  accepted:    { c: 'var(--fx-blue)',    bg: 'var(--fx-blue-soft)',    label: 'Accepted' },
  in_progress: { c: 'var(--fx-blue)',    bg: 'var(--fx-blue-soft)',    label: 'In Progress' },
  completed:   { c: 'var(--fx-success)', bg: 'var(--fx-success-soft)', label: 'Completed' },
  reviewed:    { c: 'var(--fx-muted)',   bg: 'rgba(255,255,255,0.18)', label: 'Reviewed' },
  cancelled:   { c: 'var(--fx-muted)',   bg: 'rgba(255,255,255,0.18)', label: 'Cancelled' },
}

function initials(name) {
  return (name || '—').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
}

function showAvatar(c) {
  return c.person?.avatar_url && !brokenAvatars.value[c.key]
}

function onAvatarError(key) {
  brokenAvatars.value[key] = true
}

function messagePreview(job) {
  const m = job?.latest_message
  if (!m) return 'No messages yet'
  if (m.is_system) return 'Booking details'
  if (m.is_encrypted) return 'Encrypted message'
  return m.body || 'Message'
}

function activityTime(job) {
  return job?.latest_message?.sent_at || job?.scheduled_at || ''
}

function groupByPerson(jobs, mode) {
  const groups = new Map()
  for (const job of jobs) {
    const person = mode === 'provider' ? job.provider : job.customer
    const personId = mode === 'provider' ? job.provider_id : job.customer_id
    const key = `${mode}-${personId}`
    const current = groups.get(key) || { key, person, jobs: [] }
    current.jobs.push(job)
    groups.set(key, current)
  }

  return [...groups.values()].map((group) => {
    const sortedJobs = [...group.jobs].sort((a, b) =>
      new Date(activityTime(b)).getTime() - new Date(activityTime(a)).getTime())
    const latestJob = sortedJobs[0]
    return {
      ...group,
      jobs: sortedJobs,
      latestJob,
      latestCategory: latestJob?.category?.name || 'Service',
      latestStatus: latestJob?.status,
      latestPreview: messagePreview(latestJob),
      latestAt: activityTime(latestJob),
    }
  }).sort((a, b) => new Date(b.latestAt).getTime() - new Date(a.latestAt).getTime())
}

async function refreshConversations() {
  if (polling) return
  polling = true
  try {
    await bookingsStore.reload()
  } catch {
    // Keep the visible list if a poll fails; the next interval retries.
  } finally {
    polling = false
  }
}

function select(c) {
  selectedGroupKey.value = c.key
  if (isDesktop.value) { selectedId.value = c.latestJob.id }
  else { router.push({ name: 'pro-chat', params: { id: c.latestJob.id } }) }
}

const selectedConversation = computed(() =>
  conversations.value.find((c) => c.key === selectedGroupKey.value) || null)

function selectJob(jobId) {
  selectedId.value = Number(jobId)
}
</script>

<template>
  <!-- ── DESKTOP: Xianyu-style split panel ─────────────────────────── -->
  <div v-if="isDesktop" class="msg-split">

    <div class="msg-panel-left">
      <div class="msg-panel-header">
        <span class="msg-panel-title">Messages</span>
        <span class="msg-panel-sub">{{ conversations.length }} conversation{{ conversations.length !== 1 ? 's' : '' }}</span>
      </div>

      <div class="msg-conv-list">
        <button v-for="c in conversations" :key="c.key"
                class="msg-conv-row" :class="{ active: selectedGroupKey === c.key }"
                @click="select(c)">
          <img v-if="showAvatar(c)" :src="c.person.avatar_url" :alt="c.person?.name"
               class="msg-conv-avatar msg-conv-avatar-img" @error="onAvatarError(c.key)" />
          <div v-else class="msg-conv-avatar">{{ initials(c.person?.name) }}</div>
          <div class="msg-conv-body">
            <div class="msg-conv-name">{{ c.person?.name || 'Customer' }}</div>
            <div class="msg-conv-sub">{{ c.latestCategory }} · {{ c.jobs.length }} job{{ c.jobs.length !== 1 ? 's' : '' }}</div>
            <div class="msg-conv-preview">{{ c.latestPreview }}</div>
          </div>
          <span class="fx-badge msg-conv-badge"
                :style="{ color: STATUS[c.latestStatus]?.c, background: STATUS[c.latestStatus]?.bg }">
            {{ STATUS[c.latestStatus]?.label || c.latestStatus }}
          </span>
        </button>

        <div v-if="!conversations.length" class="msg-empty-list">
          <span class="material-symbols-outlined" style="font-size:40px;color:var(--fx-muted-soft)">chat_bubble</span>
          <p>No conversations yet</p>
          <button class="msg-find-btn" @click="router.push({ name: 'pro-requests' })">View requests</button>
        </div>
      </div>
    </div>

    <div class="msg-panel-right">
      <ChatView
        v-if="selectedId"
        :key="selectedId"
        :booking-id="selectedId"
        :embedded="true"
        :related-jobs="selectedConversation?.jobs || []"
        @select-job="selectJob"
      />
      <div v-else class="msg-chat-empty">
        <span class="material-symbols-outlined" style="font-size:56px;color:var(--fx-muted-soft)">forum</span>
        <p>Select a conversation to start chatting</p>
      </div>
    </div>

  </div>

  <!-- ── MOBILE ─────────────────────────────────────────────────────── -->
  <div v-else class="fx-page">
    <div class="mb-4">
      <h1 class="fw-bold mb-1" style="font-size:24px;letter-spacing:-0.02em">Messages</h1>
      <p style="font-size:14px;color:var(--fx-muted);margin:0">Conversations with your customers</p>
    </div>
    <div class="d-flex flex-column gap-2">
      <button v-for="c in conversations" :key="c.key"
              class="conv-row fx-card d-flex align-items-center gap-3" @click="select(c)">
        <img v-if="showAvatar(c)" :src="c.person.avatar_url" :alt="c.person?.name"
             class="msg-conv-avatar msg-conv-avatar-img" style="width:48px;height:48px"
             @error="onAvatarError(c.key)" />
        <div v-else class="fx-avatar" style="width:48px;height:48px;font-size:16px;font-weight:800;flex-shrink:0">
          {{ initials(c.person?.name) }}
        </div>
        <div class="flex-grow-1" style="min-width:0;text-align:left">
          <div class="fw-semibold" style="font-size:15px;margin-bottom:3px">{{ c.person?.name || 'Customer' }}</div>
          <div style="font-size:12px;color:var(--fx-muted)">{{ c.latestCategory }} · {{ c.jobs.length }} job{{ c.jobs.length !== 1 ? 's' : '' }}</div>
          <div class="msg-conv-preview">{{ c.latestPreview }}</div>
        </div>
        <div class="d-flex flex-column align-items-end gap-2" style="flex-shrink:0">
          <span class="fx-badge" :style="{ color: STATUS[c.latestStatus]?.c, background: STATUS[c.latestStatus]?.bg }">
            {{ STATUS[c.latestStatus]?.label || c.latestStatus }}
          </span>
          <span class="material-symbols-outlined" style="font-size:18px;color:var(--fx-muted-soft)">chevron_right</span>
        </div>
      </button>
      <div v-if="!conversations.length" class="fx-card text-center py-5">
        <span class="material-symbols-outlined" style="font-size:44px;color:var(--fx-muted-soft);display:block;margin-bottom:12px">chat</span>
        <div class="fw-semibold" style="font-size:15px;margin-bottom:4px">No conversations yet</div>
        <div style="font-size:13px;color:var(--fx-muted)">Accept a booking to start chatting</div>
        <button class="btn btn-primary mt-4" style="border-radius:999px;padding:11px 28px" @click="router.push({ name: 'pro-requests' })">View requests</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.msg-split { display: flex; height: calc(100vh - 0px); overflow: hidden; }

.msg-panel-left {
  width: 300px; flex-shrink: 0; display: flex; flex-direction: column;
  background:
    radial-gradient(ellipse 60% 40% at 10% 5%, rgba(255,255,255,0.32) 0%, transparent 65%),
    rgba(255,255,255,0.08);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-right: 0.5px solid rgba(255,255,255,0.50);
  box-shadow: inset -1px 0 0 rgba(255,255,255,0.30);
}
.msg-panel-header { padding: 20px 20px 14px; border-bottom: 0.5px solid rgba(255,255,255,0.40); flex-shrink: 0; }
.msg-panel-title  { font-size: 20px; font-weight: 800; color: var(--fx-text); display: block; }
.msg-panel-sub    { font-size: 12px; color: var(--fx-muted); margin-top: 2px; display: block; }

.msg-conv-list { flex: 1; overflow-y: auto; padding: 8px 0; }

.msg-conv-row {
  width: 100%; display: flex; align-items: center; gap: 12px;
  padding: 12px 16px; border: none; background: transparent; cursor: pointer;
  text-align: left; transition: background 0.12s; position: relative;
}
.msg-conv-row::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
  background: var(--fx-accent); opacity: 0; border-radius: 0 3px 3px 0; transition: opacity 0.15s;
}
.msg-conv-row:hover  { background: rgba(255,255,255,0.18); }
.msg-conv-row.active { background: rgba(255,102,53,0.08); }
.msg-conv-row.active::before { opacity: 1; }

.msg-conv-avatar {
  width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(160deg, #FF8056, #FF6635); color: #fff;
  font-size: 15px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 8px rgba(255,102,53,0.22);
}
.msg-conv-avatar-img {
  object-fit: cover;
  border: 2px solid rgba(255,255,255,0.65);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
  background: var(--fx-bg);
}
.msg-conv-body { flex: 1; min-width: 0; }
.msg-conv-name { font-size: 14px; font-weight: 600; color: var(--fx-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-conv-sub  { font-size: 12px; color: var(--fx-muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-conv-preview { font-size: 11px; color: var(--fx-muted-soft); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-conv-badge { font-size: 10px; flex-shrink: 0; }

.msg-empty-list { display: flex; flex-direction: column; align-items: center; padding: 48px 20px; gap: 10px; text-align: center; }
.msg-empty-list p { font-size: 13px; color: var(--fx-muted); margin: 0; }
.msg-find-btn { margin-top: 6px; padding: 9px 22px; border-radius: 999px; border: none; background: var(--fx-accent); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer; }

.msg-panel-right { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.msg-chat-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; color: var(--fx-muted); font-size: 14px; }

.conv-row { width: 100%; border: none; cursor: pointer; padding: 14px 16px; text-align: left; transition: transform 0.15s ease, box-shadow 0.15s ease; }
.conv-row:hover { transform: translateY(-1px); box-shadow: inset 0 1px 1px rgba(255,255,255,0.75), 0 12px 36px rgba(0,0,0,0.09); }
.conv-row:active { transform: scale(0.98); }
</style>
