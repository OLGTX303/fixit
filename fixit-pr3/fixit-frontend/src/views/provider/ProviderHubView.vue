<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useProvidersStore } from '../../stores/providers'
import { useBookingsStore } from '../../stores/bookings'
import * as api from '../../services/api'

const router         = useRouter()
const auth           = useAuthStore()
const providersStore = useProvidersStore()
const bookingsStore  = useBookingsStore()

onMounted(async () => {
  await Promise.all([providersStore.load(), bookingsStore.load()])
})

const user      = computed(() => auth.user || {})
const initials  = computed(() => (user.value.name || '?').split(' ').map(s => s[0]).join('').slice(0,2).toUpperCase())
const myProfile = computed(() => providersStore.providers.find(p => p.user_id === auth.user?.id))

const kycVerified = computed(() => myProfile.value?.is_verified)
const kycLabel    = computed(() => {
  if (kycVerified.value) return 'Verified'
  const s = myProfile.value?.kyc_status || 'none'
  return { none: 'Not Verified', id_pending: 'Pending', submitted: 'Under Review', failed: 'Failed' }[s] || 'Not Verified'
})

const myJobs     = computed(() => bookingsStore.forProvider(myProfile.value?.id))
const doneJobs   = computed(() => myJobs.value.filter(b => ['completed','reviewed'].includes(b.status)))
const activeJobs = computed(() => myJobs.value.filter(b => ['accepted','in_progress'].includes(b.status)))
const newJobs    = computed(() => myJobs.value.filter(b => b.status === 'requested'))

const avgRating  = computed(() => {
  const rated = doneJobs.value.filter(b => b.rating)
  if (!rated.length) return '—'
  return (rated.reduce((s, b) => s + b.rating, 0) / rated.length).toFixed(1)
})

const earningsTotal = computed(() => {
  return doneJobs.value.reduce((s, b) => s + parseFloat(b.total || 0), 0).toFixed(0)
})

const recentDone = computed(() => [...doneJobs.value].reverse().slice(0, 3))

// Avatar upload
const fileInput = ref(null)
const uploading = ref(false)
async function onAvatarSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  uploading.value = true
  try {
    const dataUrl = await new Promise((res, rej) => {
      const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(file)
    })
    const { user: updated } = await api.uploadAvatar(dataUrl)
    auth.setUser(updated)
  } finally { uploading.value = false; if (fileInput.value) fileInput.value.value = '' }
}

function fmtDate(d) {
  return new Date(d).toLocaleString('en', { month: 'short', day: 'numeric' })
}

const QUICK = [
  { icon: 'manage_accounts', label: 'Edit Profile', to: 'pro-profile-edit' },
  { icon: 'calendar_month',  label: 'Schedule',     to: 'pro-profile-edit', query: { tab: 'availability' } },
  { icon: 'verified_user',   label: 'KYC',          to: 'pro-kyc' },
  { icon: 'account_balance_wallet', label: 'Earnings', to: 'wallet' },
]
</script>

<template>
  <div class="phv-root">

    <!-- ── Hero ── -->
    <div class="phv-hero">
      <div class="phv-hero-actions">
        <button class="phv-icon-btn" @click="router.push({ name: 'account-settings' })">
          <span class="material-symbols-outlined">settings</span>
        </button>
      </div>

      <!-- Avatar + name -->
      <div class="phv-profile-row">
        <div class="phv-avatar-wrap" @click="fileInput?.click()">
          <img v-if="user.avatar_url" :src="user.avatar_url" class="phv-avatar-img" />
          <div v-else class="phv-avatar-fallback">{{ initials }}</div>
          <div class="phv-avatar-cam">
            <span class="material-symbols-outlined" style="font-size:12px;color:#fff;font-variation-settings:'FILL' 1">photo_camera</span>
          </div>
          <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onAvatarSelected" />
        </div>
        <div class="phv-name-block">
          <div class="phv-username">{{ user.name || 'Provider' }}</div>
          <div class="phv-category">{{ myProfile?.category?.name || 'Home Services' }}</div>
          <div class="phv-badge-row">
            <div class="phv-kyc-chip" :class="kycVerified ? 'verified' : 'pending'">
              <span class="material-symbols-outlined" style="font-size:12px;font-variation-settings:'FILL' 1">
                {{ kycVerified ? 'verified_user' : 'shield' }}
              </span>
              {{ kycLabel }}
            </div>
            <div v-if="newJobs.length" class="phv-new-chip">
              {{ newJobs.length }} new request{{ newJobs.length > 1 ? 's' : '' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Stats row -->
      <div class="phv-stats-row">
        <div class="phv-stat" @click="router.push({ name: 'wallet' })">
          <div class="phv-stat-num">RM{{ earningsTotal }}</div>
          <div class="phv-stat-lbl">Earnings</div>
        </div>
        <div class="phv-stat-div"></div>
        <div class="phv-stat" @click="router.push({ name: 'pro-requests' })">
          <div class="phv-stat-num">{{ activeJobs.length }}</div>
          <div class="phv-stat-lbl">Active Jobs</div>
        </div>
        <div class="phv-stat-div"></div>
        <div class="phv-stat">
          <div class="phv-stat-num">{{ avgRating }} <span style="font-size:14px">★</span></div>
          <div class="phv-stat-lbl">Rating</div>
        </div>
        <div class="phv-stat-div"></div>
        <div class="phv-stat">
          <div class="phv-stat-num">{{ doneJobs.length }}</div>
          <div class="phv-stat-lbl">Done</div>
        </div>
      </div>
    </div>

    <!-- ── Quick actions ── -->
    <div class="phv-card phv-quick-grid">
      <button v-for="q in QUICK" :key="q.label"
              class="phv-quick-item"
              @click="router.push({ name: q.to, query: q.query })">
        <div class="phv-quick-icon">
          <span class="material-symbols-outlined" style="font-size:22px;font-variation-settings:'FILL' 1">{{ q.icon }}</span>
        </div>
        <span class="phv-quick-lbl">{{ q.label }}</span>
      </button>
    </div>

    <!-- ── Job requests strip ── -->
    <div v-if="newJobs.length" class="phv-card phv-requests-strip"
         @click="router.push({ name: 'pro-requests' })">
      <div class="phv-requests-left">
        <div class="phv-requests-icon">
          <span class="material-symbols-outlined" style="font-size:20px;color:#FF6635;font-variation-settings:'FILL' 1">notifications_active</span>
        </div>
        <div>
          <div style="font-size:14px;font-weight:700;color:var(--fx-text)">{{ newJobs.length }} Pending Request{{ newJobs.length > 1 ? 's' : '' }}</div>
          <div style="font-size:12px;color:var(--fx-muted);margin-top:1px">Tap to review and accept</div>
        </div>
      </div>
      <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-muted)">chevron_right</span>
    </div>

    <!-- ── Recent Jobs ── -->
    <div class="phv-card">
      <div class="phv-card-header">
        <span class="phv-card-title">Recent Jobs</span>
        <button class="phv-see-all" @click="router.push({ name: 'pro-requests' })">
          View All <span class="material-symbols-outlined" style="font-size:14px">chevron_right</span>
        </button>
      </div>
      <div v-if="recentDone.length" class="phv-job-list">
        <div v-for="b in recentDone" :key="b.id" class="phv-job-row">
          <div class="phv-job-avatar">{{ (b.customer?.name||'?').split(' ').map(w=>w[0]).join('') }}</div>
          <div class="phv-job-info">
            <div class="phv-job-name">{{ b.customer?.name || 'Customer' }}</div>
            <div class="phv-job-meta">{{ b.category?.name }} · {{ fmtDate(b.scheduled_at) }}</div>
          </div>
          <div class="phv-job-right">
            <div class="phv-job-amount">RM{{ b.total }}</div>
            <div class="phv-job-chip">{{ b.status === 'reviewed' ? '★ Reviewed' : 'Done' }}</div>
          </div>
        </div>
      </div>
      <div v-else class="phv-empty">
        <span class="material-symbols-outlined" style="font-size:36px">work_outline</span>
        <p style="font-size:13px;color:var(--fx-muted);margin-top:6px">No completed jobs yet</p>
      </div>
    </div>

    <div style="height: calc(88px + env(safe-area-inset-bottom))"></div>
  </div>
</template>

<style scoped>
.phv-root { background: var(--fx-bg); min-height: 100vh; }

/* ── Hero ── */
.phv-hero {
  background: linear-gradient(160deg, #1e3a5f 0%, #1e4080 50%, #162d5e 100%);
  padding: 52px 20px 0; border-radius: 0 0 28px 28px;
  position: relative; overflow: hidden; margin-bottom: 14px;
}
.phv-hero::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse at 70% 20%, rgba(99,179,237,0.15) 0%, transparent 60%);
  pointer-events: none;
}
.phv-hero-actions { position: absolute; top: 16px; right: 16px; }
.phv-icon-btn {
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,0.15); border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center; color: #fff;
  backdrop-filter: blur(8px);
}
.phv-icon-btn .material-symbols-outlined { font-size: 20px; }

.phv-profile-row { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.phv-avatar-wrap { position: relative; flex-shrink: 0; cursor: pointer; }
.phv-avatar-img  { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,0.50); }
.phv-avatar-fallback {
  width: 70px; height: 70px; border-radius: 50%;
  background: rgba(255,255,255,0.20); color: #fff;
  font-size: 24px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  border: 3px solid rgba(255,255,255,0.30);
}
.phv-avatar-cam {
  position: absolute; bottom: 0; right: 0;
  width: 22px; height: 22px; border-radius: 50%;
  background: rgba(0,0,0,0.50); border: 2px solid rgba(255,255,255,0.5);
  display: flex; align-items: center; justify-content: center;
}
.phv-name-block  { flex: 1; min-width: 0; }
.phv-username    { font-size: 20px; font-weight: 800; color: #fff; }
.phv-category    { font-size: 12px; color: rgba(255,255,255,0.70); margin-top: 2px; }
.phv-badge-row   { display: flex; align-items: center; gap: 6px; margin-top: 6px; flex-wrap: wrap; }
.phv-kyc-chip {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
}
.phv-kyc-chip.verified { background: rgba(34,197,94,0.20); color: #86efac; }
.phv-kyc-chip.pending  { background: rgba(234,88,12,0.20);  color: #fdba74; }
.phv-new-chip {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
  background: rgba(255,102,53,0.25); color: #fca5a5;
}

.phv-stats-row {
  display: flex; align-items: center;
  background: rgba(255,255,255,0.10); backdrop-filter: blur(10px);
  border-radius: 14px 14px 0 0; padding: 14px 0;
}
.phv-stat     { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; cursor: pointer; }
.phv-stat-num { font-size: 15px; font-weight: 800; color: #fff; }
.phv-stat-lbl { font-size: 10px; color: rgba(255,255,255,0.70); }
.phv-stat-div { width: 1px; height: 28px; background: rgba(255,255,255,0.20); }

/* ── Cards ── */
.phv-card {
  background:
    radial-gradient(ellipse 44% 30% at 16% 7%, rgba(255,255,255,0.28) 0%, transparent 62%),
    linear-gradient(to bottom, rgba(255,255,255,0.22) 0%, transparent 26%),
    rgba(255,255,255,0.06);
  border: 0.5px solid rgba(255,255,255,0.55);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.75), 0 4px 20px rgba(0,0,0,0.06);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-radius: 22px;
  padding: 16px; margin: 0 12px 12px;
}
.phv-card-header  { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.phv-card-title   { font-size: 15px; font-weight: 800; color: var(--fx-text); }
.phv-see-all      {
  display: flex; align-items: center; gap: 2px;
  font-size: 12px; color: var(--fx-muted); background: none; border: none; cursor: pointer; padding: 0;
}

/* Quick grid */
.phv-quick-grid { display: grid; grid-template-columns: repeat(4,1fr); padding: 14px 12px; }
.phv-quick-item { display: flex; flex-direction: column; align-items: center; gap: 6px; background: none; border: none; cursor: pointer; padding: 4px 0; }
.phv-quick-icon {
  width: 48px; height: 48px; border-radius: 14px;
  background:
    linear-gradient(to bottom, rgba(255,255,255,0.30) 0%, rgba(255,255,255,0.08) 100%),
    rgba(37,99,235,0.08);
  border: 0.5px solid rgba(255,255,255,0.60);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.70), 0 2px 8px rgba(37,99,235,0.10);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  color: #2563eb;
  display: flex; align-items: center; justify-content: center;
}
.phv-quick-lbl { font-size: 11px; font-weight: 600; color: var(--fx-text); text-align: center; }

/* Requests strip */
.phv-requests-strip {
  display: flex; align-items: center; justify-content: space-between;
  cursor: pointer; background: rgba(255,102,53,0.06); border-color: rgba(255,102,53,0.20);
}
.phv-requests-left { display: flex; align-items: center; gap: 12px; }
.phv-requests-icon {
  width: 40px; height: 40px; border-radius: 12px;
  background: rgba(255,102,53,0.12);
  display: flex; align-items: center; justify-content: center;
}

/* Jobs list */
.phv-job-list { display: flex; flex-direction: column; gap: 10px; }
.phv-job-row  { display: flex; align-items: center; gap: 12px; }
.phv-job-avatar {
  width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
  background: rgba(37,99,235,0.10); color: #2563eb;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 700;
}
.phv-job-info { flex: 1; min-width: 0; }
.phv-job-name { font-size: 13px; font-weight: 700; color: var(--fx-text); }
.phv-job-meta { font-size: 11px; color: var(--fx-muted); margin-top: 1px; }
.phv-job-right { display: flex; flex-direction: column; align-items: flex-end; gap: 3px; }
.phv-job-amount { font-size: 14px; font-weight: 800; color: #16a34a; }
.phv-job-chip   { font-size: 10px; color: #7c3aed; background: rgba(124,58,237,0.10); padding: 2px 7px; border-radius: 10px; }

.phv-empty { display: flex; flex-direction: column; align-items: center; padding: 20px 0; color: var(--fx-muted); }

/* Section labels */
.phv-section-lbl {
  font-size: 11px; font-weight: 700; color: var(--fx-muted);
  padding: 10px 28px 4px; letter-spacing: 0.06em; text-transform: uppercase;
}

/* Groups */
.phv-group  {
  background:
    radial-gradient(ellipse 44% 30% at 16% 7%, rgba(255,255,255,0.28) 0%, transparent 62%),
    linear-gradient(to bottom, rgba(255,255,255,0.22) 0%, transparent 26%),
    rgba(255,255,255,0.06);
  border: 0.5px solid rgba(255,255,255,0.55);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.75), 0 4px 20px rgba(0,0,0,0.06);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-radius: 20px; overflow: hidden;
  margin: 0 12px 4px;
}
.phv-row    {
  width: 100%; display: flex; align-items: center; gap: 12px;
  padding: 13px 16px; border: none; background: transparent; cursor: pointer;
}
.phv-row-icon {
  width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.phv-row-lbl    { font-size: 15px; color: var(--fx-text); font-weight: 500; flex: 1; text-align: left; }
.phv-row-right  { display: flex; align-items: center; gap: 4px; }
.phv-row-chevron { font-size: 18px; color: var(--fx-border); }
.phv-row-sep    { height: 1px; background: var(--fx-border); margin: 0 16px; }

.phv-logout-group { margin-top: 12px; margin-bottom: 8px; }
.phv-logout { color: #ef4444; font-size: 15px; font-weight: 600; justify-content: center; gap: 0; }
</style>
