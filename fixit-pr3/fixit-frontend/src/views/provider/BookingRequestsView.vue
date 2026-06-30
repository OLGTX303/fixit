<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useProvidersStore } from '../../stores/providers'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const bookingsStore = useBookingsStore()
const providersStore = useProvidersStore()
const auth = useAuthStore()

const tab = ref('new')
const brokenAvatars = ref({})
const actionBusy = ref({})
const actionError = ref('')

const myProviderId = computed(() =>
  providersStore.providers.find(p => p.user_id === auth.user?.id)?.id)

onMounted(async () => {
  await Promise.all([bookingsStore.reload(), providersStore.load()])
})

function customerInitials(b) {
  return (b.customer?.name || '—').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
}

function showAvatar(b) {
  return b.customer?.avatar_url && !brokenAvatars.value[b.id]
}

function onAvatarError(bookingId) {
  brokenAvatars.value[bookingId] = true
}

const all      = computed(() => bookingsStore.forProvider(myProviderId.value))
const newJobs  = computed(() => all.value.filter(b => b.status === 'requested'))
const active   = computed(() => all.value.filter(b => ['accepted','in_progress'].includes(b.status)))
const done     = computed(() => all.value.filter(b => ['completed','reviewed'].includes(b.status)))

const shown = computed(() => {
  if (tab.value === 'new')    return newJobs.value
  if (tab.value === 'active') return active.value
  return done.value
})

function busyKey(b, action) {
  return `${b.id}:${action}`
}

function isBusy(b, action) {
  return !!actionBusy.value[busyKey(b, action)]
}

async function runJobAction(b, action, fn) {
  const key = busyKey(b, action)
  actionError.value = ''
  actionBusy.value = { ...actionBusy.value, [key]: true }
  try {
    await fn()
  } catch (err) {
    actionError.value = err.message || 'Could not update this job. Please try again.'
  } finally {
    const next = { ...actionBusy.value }
    delete next[key]
    actionBusy.value = next
  }
}

async function accept(b)  {
  await runJobAction(b, 'accept', () => bookingsStore.advanceStatus(b.id, 'accepted'))
}
async function decline(b) {
  if (!window.confirm(`Decline ${b.customer?.name || 'this customer'}'s booking request?`)) return
  await runJobAction(b, 'decline', () => bookingsStore.remove(b.id))
}
async function setStatus(b, key) {
  await runJobAction(b, key, () => bookingsStore.advanceStatus(b.id, key))
}
async function complete(b) {
  await runJobAction(b, 'completed', () => bookingsStore.advanceStatus(b.id, 'completed'))
}

function openChat(b) { router.push({ name: 'pro-chat', params: { id: b.id } }) }

function fmtDate(d) {
  return new Date(d).toLocaleString('en', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

const STATUS_COLOR = {
  requested: { bg: '#fff7ed', color: '#ea580c', label: 'New' },
  accepted:   { bg: '#eff6ff', color: '#2563eb', label: 'Accepted' },
  in_progress:{ bg: '#f0fdf4', color: '#16a34a', label: 'In Progress' },
  completed:  { bg: '#f0fdf4', color: '#16a34a', label: 'Done' },
  reviewed:   { bg: '#faf5ff', color: '#7c3aed', label: 'Reviewed' },
}
</script>

<template>
  <div class="brv-root fx-view-root">
    <div class="brv-header">
      <h1 class="brv-title">My Jobs</h1>
      <span v-if="newJobs.length" class="brv-new-badge">{{ newJobs.length }} new</span>
    </div>
    <div v-if="actionError" class="brv-error">
      <span class="material-symbols-outlined" style="font-size:16px">error</span>
      {{ actionError }}
    </div>

    <!-- Sub-tabs -->
    <div class="brv-tabs">
      <button class="brv-tab" :class="{active: tab==='new'}"    @click="tab='new'">
        New <span v-if="newJobs.length" class="brv-tab-dot">{{ newJobs.length }}</span>
      </button>
      <button class="brv-tab" :class="{active: tab==='active'}" @click="tab='active'">
        Active <span v-if="active.length" class="brv-tab-dot">{{ active.length }}</span>
      </button>
      <button class="brv-tab" :class="{active: tab==='done'}"   @click="tab='done'">Done</button>
    </div>

    <!-- Cards -->
    <div class="brv-list">

      <!-- ── NEW REQUESTS ── -->
      <template v-if="tab==='new'">
        <div v-for="b in shown" :key="b.id" class="brv-card fx-card">
          <div class="brv-card-top">
            <img v-if="showAvatar(b)" :src="b.customer.avatar_url" :alt="b.customer?.name"
                 class="brv-avatar brv-avatar-img" @error="onAvatarError(b.id)" />
            <div v-else class="brv-avatar">{{ customerInitials(b) }}</div>
            <div class="brv-card-info">
              <div class="brv-customer">{{ b.customer?.name || 'Customer' }}</div>
              <div class="brv-meta">{{ b.category?.name }} · {{ fmtDate(b.scheduled_at) }}</div>
              <div class="brv-addr">{{ b.address }}</div>
            </div>
            <div class="brv-amount">RM{{ b.total }}</div>
          </div>
          <div class="brv-actions">
            <button class="brv-btn outline-danger" :disabled="isBusy(b, 'decline') || isBusy(b, 'accept')" @click="decline(b)">
              {{ isBusy(b, 'decline') ? 'Declining...' : 'Decline' }}
            </button>
            <button class="brv-btn primary" :disabled="isBusy(b, 'accept') || isBusy(b, 'decline')" @click="accept(b)">
              <span class="material-symbols-outlined" style="font-size:16px">check_circle</span>
              {{ isBusy(b, 'accept') ? 'Accepting...' : 'Accept' }}
            </button>
          </div>
        </div>
        <div v-if="!shown.length" class="brv-empty">
          <span class="material-symbols-outlined" style="font-size:40px">notifications_none</span>
          <p>No new requests</p>
        </div>
      </template>

      <!-- ── ACTIVE JOBS ── -->
      <template v-if="tab==='active'">
        <div v-for="b in shown" :key="b.id" class="brv-card fx-card">
          <div class="brv-card-top">
            <img v-if="showAvatar(b)" :src="b.customer.avatar_url" :alt="b.customer?.name"
                 class="brv-avatar brv-avatar-img" @error="onAvatarError(b.id)" />
            <div v-else class="brv-avatar" style="background:rgba(37,99,235,.12);color:#2563eb">
              {{ customerInitials(b) }}
            </div>
            <div class="brv-card-info">
              <div class="brv-customer">{{ b.customer?.name || 'Customer' }}</div>
              <div class="brv-meta">{{ b.category?.name }} · #{{ b.id }}</div>
              <div class="brv-addr">{{ b.address }}</div>
            </div>
            <div>
              <span class="brv-status-chip"
                    :style="{background: STATUS_COLOR[b.status]?.bg, color: STATUS_COLOR[b.status]?.color}">
                {{ STATUS_COLOR[b.status]?.label || b.status }}
              </span>
            </div>
          </div>

          <!-- Inline job progress controls -->
          <div class="brv-progress-bar">
            <div class="brv-progress-step" :class="{done: ['accepted','in_progress','completed','reviewed'].includes(b.status)}">
              <div class="brv-step-dot"></div>
              <span>Accepted</span>
            </div>
            <div class="brv-progress-line" :class="{done: ['in_progress','completed','reviewed'].includes(b.status)}"></div>
            <div class="brv-progress-step" :class="{done: ['in_progress','completed','reviewed'].includes(b.status)}">
              <div class="brv-step-dot"></div>
              <span>Working</span>
            </div>
            <div class="brv-progress-line" :class="{done: ['completed','reviewed'].includes(b.status)}"></div>
            <div class="brv-progress-step" :class="{done: ['completed','reviewed'].includes(b.status)}">
              <div class="brv-step-dot"></div>
              <span>Done</span>
            </div>
          </div>

          <div class="brv-actions">
            <button class="brv-btn ghost" @click="openChat(b)">
              <span class="material-symbols-outlined" style="font-size:16px">chat</span>
              Chat
            </button>
            <button v-if="b.status==='accepted'" class="brv-btn primary" :disabled="isBusy(b, 'in_progress')" @click="setStatus(b,'in_progress')">
              <span class="material-symbols-outlined" style="font-size:16px">directions_run</span>
              {{ isBusy(b, 'in_progress') ? 'Starting...' : 'Start Work' }}
            </button>
            <button v-else-if="b.status==='in_progress'" class="brv-btn success" :disabled="isBusy(b, 'completed')" @click="complete(b)">
              <span class="material-symbols-outlined" style="font-size:16px">task_alt</span>
              {{ isBusy(b, 'completed') ? 'Completing...' : 'Mark Complete' }}
            </button>
          </div>
        </div>
        <div v-if="!shown.length" class="brv-empty">
          <span class="material-symbols-outlined" style="font-size:40px">work_outline</span>
          <p>No active jobs</p>
        </div>
      </template>

      <!-- ── DONE ── -->
      <template v-if="tab==='done'">
        <div v-for="b in shown" :key="b.id" class="brv-card fx-card">
          <div class="brv-card-top">
            <img v-if="showAvatar(b)" :src="b.customer.avatar_url" :alt="b.customer?.name"
                 class="brv-avatar brv-avatar-img" @error="onAvatarError(b.id)" />
            <div v-else class="brv-avatar" style="background:rgba(124,58,237,.10);color:#7c3aed">
              {{ customerInitials(b) }}
            </div>
            <div class="brv-card-info">
              <div class="brv-customer">{{ b.customer?.name || 'Customer' }}</div>
              <div class="brv-meta">{{ b.category?.name }} · {{ fmtDate(b.scheduled_at) }}</div>
            </div>
            <div class="brv-amount">RM{{ b.total }}</div>
          </div>
          <div class="brv-done-row">
            <span class="brv-status-chip" style="background:rgba(124,58,237,.10);color:#7c3aed">
              {{ b.status === 'reviewed' ? '✓ Reviewed' : 'Completed' }}
            </span>
            <button class="brv-btn ghost sm" @click="openChat(b)">
              <span class="material-symbols-outlined" style="font-size:14px">chat</span>
            </button>
          </div>
        </div>
        <div v-if="!shown.length" class="brv-empty">
          <span class="material-symbols-outlined" style="font-size:40px">history</span>
          <p>No completed jobs yet</p>
        </div>
      </template>

    </div>
  </div>
</template>

<style scoped>
.brv-root { padding: 16px 16px calc(88px + env(safe-area-inset-bottom)); max-width: 560px; }

.brv-header { display: flex; align-items: center; gap: 10px; margin-bottom: 14px; }
.brv-title  { font-size: 22px; font-weight: 800; margin: 0; }
.brv-new-badge {
  padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 700;
  background: #FF6635; color: #fff;
}
.brv-error {
  display: flex; align-items: center; gap: 6px;
  margin: -4px 0 12px; padding: 10px 12px; border-radius: 12px;
  background: rgba(239,68,68,0.10); color: #dc2626;
  font-size: 12px; font-weight: 700;
}

/* Tabs */
.brv-tabs { display: flex; gap: 0; margin-bottom: 16px; border-bottom: 1px solid var(--fx-border); }
.brv-tab {
  flex: 1; padding: 10px 4px; border: none; background: transparent;
  font-size: 13px; font-weight: 600; color: var(--fx-muted);
  border-bottom: 2.5px solid transparent; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;
}
.brv-tab.active { color: #FF6635; border-bottom-color: #FF6635; }
.brv-tab-dot {
  min-width: 18px; height: 18px; border-radius: 10px; padding: 0 5px;
  background: #FF6635; color: #fff; font-size: 10px;
  display: flex; align-items: center; justify-content: center;
}

/* List & Cards */
.brv-list { display: flex; flex-direction: column; gap: 12px; }
.brv-card {
  border-radius: 16px;
  overflow: hidden;
  padding: 14px;
}

.brv-card-top { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px; }
.brv-avatar {
  width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
  background: rgba(255,102,53,.12); color: #FF6635;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; font-weight: 700;
}
.brv-avatar-img {
  object-fit: cover;
  border: 2px solid rgba(255,255,255,0.65);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.brv-card-info  { flex: 1; min-width: 0; }
.brv-customer   { font-size: 14px; font-weight: 700; color: var(--fx-text); }
.brv-meta       { font-size: 12px; color: var(--fx-muted); margin-top: 1px; }
.brv-addr       { font-size: 11px; color: var(--fx-muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.brv-amount     { font-size: 16px; font-weight: 800; color: #FF6635; flex-shrink: 0; }

.brv-status-chip {
  display: inline-flex; padding: 3px 10px; border-radius: 20px;
  font-size: 11px; font-weight: 700;
}

/* Progress bar */
.brv-progress-bar {
  display: flex; align-items: center; gap: 0; margin-bottom: 12px;
  padding: 0 4px;
}
.brv-progress-step {
  display: flex; flex-direction: column; align-items: center; gap: 3px;
  font-size: 10px; font-weight: 600; color: var(--fx-muted); flex-shrink: 0;
}
.brv-progress-step.done { color: #22c55e; }
.brv-step-dot {
  width: 10px; height: 10px; border-radius: 50%;
  background: var(--fx-border); border: 2px solid var(--fx-border);
}
.brv-progress-step.done .brv-step-dot { background: #22c55e; border-color: #22c55e; }
.brv-progress-line {
  flex: 1; height: 2px; background: var(--fx-border); margin-bottom: 13px;
}
.brv-progress-line.done { background: #22c55e; }

/* Actions */
.brv-actions { display: flex; gap: 8px; }
.brv-btn {
  flex: 1; padding: 9px 12px; border-radius: 12px; border: none;
  font-size: 13px; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 5px;
}
.brv-btn.primary { background: #FF6635; color: #fff; }
.brv-btn.success { background: #22c55e; color: #fff; }
.brv-btn.ghost   { background: var(--fx-glass-bg); color: var(--fx-text); border: 1.5px solid var(--fx-border); flex: 0 0 auto; padding: 9px 14px; }
.brv-btn.sm      { padding: 6px 10px; font-size: 12px; flex: 0 0 auto; }
.brv-btn.outline-danger { background: transparent; color: #ef4444; border: 1.5px solid #ef4444; }
.brv-btn:disabled { opacity: 0.62; cursor: wait; }

.brv-done-row { display: flex; align-items: center; justify-content: space-between; }

.brv-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 36px 0; color: var(--fx-muted); font-size: 14px; }
</style>
