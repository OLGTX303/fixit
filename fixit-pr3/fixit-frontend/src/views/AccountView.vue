<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useBookingsStore } from '../stores/bookings'
import { useWalletStore } from '../stores/wallet'
import * as api from '../services/api'

const auth   = useAuthStore()
const router = useRouter()
const bookingsStore = useBookingsStore()
const wallet = useWalletStore()

const user     = computed(() => auth.user || {})
const initials = computed(() => (user.value.name || '—').split(' ').map(s => s[0]).join('').slice(0,2).toUpperCase())
const isAdmin    = computed(() => auth.role === 'admin')
const isProvider = computed(() => auth.role === 'provider')

// ── Customer data ──────────────────────────────────────────────────
onMounted(async () => {
  if (isAdmin.value) {
    await loadAdminStats()
  } else {
    bookingsStore.load()
    if (!isProvider.value) {
      try { await wallet.load() } catch { /* stripe may be unconfigured */ }
    }
  }
})

const myBookings = computed(() => bookingsStore.bookings.filter(b => b.customer_id === auth.user?.id))
const upcoming   = computed(() => myBookings.value.filter(b => ['requested','accepted','in_progress'].includes(b.status)))
const toReview   = computed(() => myBookings.value.filter(b => b.status === 'completed'))
const walletBal  = computed(() => (wallet.balanceCents / 100).toFixed(2))

// ── Admin stats (fetched once on mount) ────────────────────────────
const adminStats = ref({ users: 0, blocked: 0, providers: 0, pendingVerify: 0, bookings: 0, harmPending: 0 })
async function loadAdminStats() {
  try {
    const [usersRes, verifyStats, harm] = await Promise.all([
      api.getUsers({ limit: 1 }),
      api.getVerifyStats(),
      api.getHarmReviews(),
    ])
    adminStats.value = {
      users:         usersRes.counts?.total ?? usersRes.total ?? 0,
      blocked:       usersRes.counts?.blocked ?? 0,
      providers:     (verifyStats.approved ?? 0) + (verifyStats.pending ?? 0),
      pendingVerify: verifyStats.pending ?? 0,
      harmPending:   harm.filter(h => h.status === 'pending').length,
    }
  } catch {}
}

// ── Avatar upload ──────────────────────────────────────────────────
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

// Customer quick actions (no Wallet �?already in stats row)
const CUSTOMER_QUICK = [
  { icon: 'star',          label: 'Favourites', to: 'favorites' },
  { icon: 'history',       label: 'History',    to: 'browsing-history' },
  { icon: 'redeem',        label: 'Coupons',    to: 'coupons' },
  { icon: 'shopping_cart', label: 'Cart',       to: 'cart' },
]
</script>

<template>
  <div class="acv-root fx-view-root">

    <!-- ── Hero ── -->
    <div class="acv-hero" :class="isAdmin ? 'hero-admin' : 'hero-customer'">
      <div class="acv-hero-actions">
        <button class="acv-icon-btn" @click="router.push({ name: 'account-settings' })">
          <span class="material-symbols-outlined">settings</span>
        </button>
      </div>

      <div class="acv-profile-row">
        <div class="acv-avatar-wrap" @click="fileInput?.click()">
          <img v-if="user.avatar_url" :src="user.avatar_url" class="acv-avatar-img" />
          <div v-else class="acv-avatar-fallback">{{ initials }}</div>
          <div class="acv-avatar-cam">
            <span class="material-symbols-outlined" style="font-size:12px;color:#fff;font-variation-settings:'FILL' 1">photo_camera</span>
          </div>
          <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onAvatarSelected" />
        </div>
        <div class="acv-name-block">
          <div class="acv-username">{{ user.name || 'User' }}</div>
          <div class="acv-subline">{{ user.email || user.phone || '' }}</div>
          <div class="acv-role-chip">
            <span class="material-symbols-outlined" style="font-size:12px;font-variation-settings:'FILL' 1">
              {{ isAdmin ? 'admin_panel_settings' : isProvider ? 'build' : 'home_repair_service' }}
            </span>
            {{ isAdmin ? 'Administrator' : isProvider ? 'Provider' : 'Customer' }}
          </div>
        </div>
      </div>

      <!-- ── Admin stats (real numbers) ── -->
      <div v-if="isAdmin" class="acv-stats-row">
        <div class="acv-stat" @click="router.push({ name: 'admin-users' })">
          <div class="acv-stat-num">{{ adminStats.users }}</div>
          <div class="acv-stat-lbl">Total Users</div>
        </div>
        <div class="acv-stat-div"></div>
        <div class="acv-stat" @click="router.push({ name: 'admin-verify' })">
          <div class="acv-stat-num">{{ adminStats.pendingVerify }}</div>
          <div class="acv-stat-lbl">Unverified</div>
        </div>
        <div class="acv-stat-div"></div>
        <div class="acv-stat" @click="router.push({ name: 'admin-harm' })">
          <div class="acv-stat-num">{{ adminStats.harmPending }}</div>
          <div class="acv-stat-lbl">Reports</div>
        </div>
      </div>

      <!-- ── Customer / Provider stats ── -->
      <div v-else class="acv-stats-row">
        <div class="acv-stat" @click="router.push({ name: 'job-tracker' })">
          <div class="acv-stat-num">{{ myBookings.length }}</div>
          <div class="acv-stat-lbl">Bookings</div>
        </div>
        <div class="acv-stat-div"></div>
        <div class="acv-stat" @click="router.push({ name: 'wallet' })">
          <div class="acv-stat-num">RM{{ walletBal }}</div>
          <div class="acv-stat-lbl">Wallet</div>
        </div>
        <div class="acv-stat-div"></div>
        <div class="acv-stat">
          <div class="acv-stat-num">{{ toReview.length }}</div>
          <div class="acv-stat-lbl">To Review</div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════
         ADMIN CONTENT
    ══════════════════════════════════════════ -->
    <template v-if="isAdmin">

      <!-- Feature cards 2×2 -->
      <div class="adm-grid">
        <!-- Users -->
        <button class="adm-card acv-glass lg-surface lg-interactive" @click="router.push({ name: 'admin-users' })">
          <div class="adm-card-icon" style="background:rgba(124,58,237,0.12);color:#7c3aed">
            <span class="material-symbols-outlined" style="font-size:26px;font-variation-settings:'FILL' 1">manage_accounts</span>
          </div>
          <div class="adm-card-body">
            <div class="adm-card-title">Users</div>
            <div class="adm-card-sub">{{ adminStats.users }} total
              <span v-if="adminStats.blocked" style="color:#ef4444"> · {{ adminStats.blocked }} blocked</span>
            </div>
          </div>
          <span class="material-symbols-outlined adm-chevron">chevron_right</span>
        </button>

        <!-- Providers -->
        <button class="adm-card acv-glass lg-surface lg-interactive" @click="router.push({ name: 'admin-verify' })">
          <div class="adm-card-icon" style="background:rgba(59,130,246,0.12);color:#3b82f6">
            <span class="material-symbols-outlined" style="font-size:26px;font-variation-settings:'FILL' 1">verified_user</span>
          </div>
          <div class="adm-card-body">
            <div class="adm-card-title">Providers</div>
            <div class="adm-card-sub">{{ adminStats.providers }} total
              <span v-if="adminStats.pendingVerify" style="color:#f59e0b"> · {{ adminStats.pendingVerify }} pending</span>
            </div>
          </div>
          <span class="material-symbols-outlined adm-chevron">chevron_right</span>
        </button>

        <!-- Bookings -->
        <button class="adm-card acv-glass lg-surface lg-interactive" @click="router.push({ name: 'admin-bookings' })">
          <div class="adm-card-icon" style="background:rgba(16,185,129,0.12);color:#10b981">
            <span class="material-symbols-outlined" style="font-size:26px;font-variation-settings:'FILL' 1">calendar_month</span>
          </div>
          <div class="adm-card-body">
            <div class="adm-card-title">Bookings</div>
            <div class="adm-card-sub">All job orders</div>
          </div>
          <span class="material-symbols-outlined adm-chevron">chevron_right</span>
        </button>

        <!-- CS Chat -->
        <button class="adm-card acv-glass lg-surface lg-interactive" @click="router.push({ name: 'admin-chats' })">
          <div class="adm-card-icon" style="background:rgba(245,158,11,0.12);color:#f59e0b">
            <span class="material-symbols-outlined" style="font-size:26px;font-variation-settings:'FILL' 1">support_agent</span>
          </div>
          <div class="adm-card-body">
            <div class="adm-card-title">CS Chat</div>
            <div class="adm-card-sub">Customer service</div>
          </div>
          <span class="material-symbols-outlined adm-chevron">chevron_right</span>
        </button>

        <!-- Safety / Harm reviews -->
        <button class="adm-card acv-glass lg-surface lg-interactive" @click="router.push({ name: 'admin-harm' })">
          <div class="adm-card-icon" style="background:rgba(239,68,68,0.12);color:#ef4444">
            <span class="material-symbols-outlined" style="font-size:26px;font-variation-settings:'FILL' 1">shield</span>
          </div>
          <div class="adm-card-body">
            <div class="adm-card-title">Safety Reports</div>
            <div class="adm-card-sub">
              <span v-if="adminStats.harmPending" style="color:#ef4444;font-weight:700">{{ adminStats.harmPending }} pending review</span>
              <span v-else style="color:var(--fx-success)">All clear</span>
            </div>
          </div>
          <span class="material-symbols-outlined adm-chevron">chevron_right</span>
        </button>

        <!-- Reviews moderation -->
        <button class="adm-card acv-glass lg-surface lg-interactive" @click="router.push({ name: 'admin-bookings' })">
          <div class="adm-card-icon" style="background:rgba(236,72,153,0.12);color:#ec4899">
            <span class="material-symbols-outlined" style="font-size:26px;font-variation-settings:'FILL' 1">rate_review</span>
          </div>
          <div class="adm-card-body">
            <div class="adm-card-title">Reviews</div>
            <div class="adm-card-sub">Moderate feedback</div>
          </div>
          <span class="material-symbols-outlined adm-chevron">chevron_right</span>
        </button>

        <!-- System coupons -->
        <button class="adm-card acv-glass lg-surface lg-interactive" @click="router.push({ name: 'admin-coupons' })">
          <div class="adm-card-icon" style="background:rgba(255,102,53,0.12);color:#FF6635">
            <span class="material-symbols-outlined" style="font-size:26px;font-variation-settings:'FILL' 1">redeem</span>
          </div>
          <div class="adm-card-body">
            <div class="adm-card-title">Coupons</div>
            <div class="adm-card-sub">System-wide discounts</div>
          </div>
          <span class="material-symbols-outlined adm-chevron">chevron_right</span>
        </button>
      </div>

    </template>

    <!-- ══════════════════════════════════════════
         CUSTOMER / PROVIDER CONTENT
    ══════════════════════════════════════════ -->
    <template v-else>

      <!-- Quick actions -->
      <div class="acv-card acv-glass lg-surface acv-quick-grid">
        <button v-for="q in CUSTOMER_QUICK" :key="q.label"
                class="acv-quick-item lg-interactive"
                @click="q.to && router.push({ name: q.to })">
          <div class="acv-quick-icon lg-icon-tile">
            <span class="material-symbols-outlined" style="font-size:22px;font-variation-settings:'FILL' 1">{{ q.icon }}</span>
          </div>
          <span class="acv-quick-lbl">{{ q.label }}</span>
        </button>
      </div>

      <!-- My Bookings -->
      <div class="acv-card acv-glass lg-surface">
        <div class="acv-card-header">
          <span class="acv-card-title">My Bookings</span>
          <button class="acv-see-all" @click="router.push({ name: 'job-tracker' })">
            View All <span class="material-symbols-outlined" style="font-size:14px">chevron_right</span>
          </button>
        </div>
        <div class="acv-orders-grid">
          <button class="acv-order-btn lg-interactive" @click="router.push({ name: 'job-tracker' })">
            <div class="acv-order-tile lg-icon-tile">
              <span class="material-symbols-outlined acv-order-icon">list_alt</span>
            </div>
            <span class="acv-order-lbl">All</span>
          </button>
          <button class="acv-order-btn lg-interactive" @click="router.push({ name: 'job-tracker' })">
            <div class="acv-order-tile lg-icon-tile">
              <span class="material-symbols-outlined acv-order-icon">schedule</span>
              <span v-if="upcoming.length" class="acv-order-badge">{{ upcoming.length }}</span>
            </div>
            <span class="acv-order-lbl">Upcoming</span>
          </button>
          <button class="acv-order-btn lg-interactive" @click="router.push({ name: 'job-tracker' })">
            <div class="acv-order-tile lg-icon-tile">
              <span class="material-symbols-outlined acv-order-icon">rate_review</span>
              <span v-if="toReview.length" class="acv-order-badge">{{ toReview.length }}</span>
            </div>
            <span class="acv-order-lbl">To Review</span>
          </button>
          <button class="acv-order-btn lg-interactive" @click="router.push({ name: 'job-tracker' })">
            <div class="acv-order-tile lg-icon-tile">
              <span class="material-symbols-outlined acv-order-icon">cancel</span>
            </div>
            <span class="acv-order-lbl">Cancelled</span>
          </button>
        </div>
      </div>

    </template>

    <div class="fx-mobile-spacer"></div>
  </div>
</template>

<style scoped>
.acv-root { min-height: 100vh; padding-bottom: 8px; }

/* ── Hero ── */
.acv-hero {
  padding: 52px 20px 0; border-radius: 0 0 28px 28px;
  position: relative; overflow: hidden; margin-bottom: 14px;
}
.hero-customer { background: linear-gradient(160deg, #FF7D54 0%, #FF6635 60%, #e8501e 100%); }
.hero-admin    { background: linear-gradient(160deg, #9333ea 0%, #7c3aed 60%, #5b21b6 100%); }
.acv-hero::before {
  content: ''; position: absolute; inset: 0;
  background: radial-gradient(ellipse at 70% 20%, rgba(255,255,255,0.15) 0%, transparent 60%);
  pointer-events: none;
}
.acv-hero-actions { position: absolute; top: 16px; right: 16px; display: flex; gap: 8px; }
.acv-icon-btn {
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,0.20); border: none; cursor: pointer;
  display: flex; align-items: center; justify-content: center; color: #fff;
  backdrop-filter: blur(8px);
}
.acv-icon-btn .material-symbols-outlined { font-size: 20px; }

.acv-profile-row { display: flex; align-items: center; gap: 16px; margin-bottom: 20px; }
.acv-avatar-wrap { position: relative; flex-shrink: 0; cursor: pointer; }
.acv-avatar-img  { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 3px solid rgba(255,255,255,0.60); }
.acv-avatar-fallback {
  width: 70px; height: 70px; border-radius: 50%;
  background: rgba(255,255,255,0.25); color: #fff;
  font-size: 24px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  border: 3px solid rgba(255,255,255,0.40);
}
.acv-avatar-cam {
  position: absolute; bottom: 0; right: 0;
  width: 22px; height: 22px; border-radius: 50%;
  background: rgba(0,0,0,0.45); border: 2px solid rgba(255,255,255,0.6);
  display: flex; align-items: center; justify-content: center;
}
.acv-name-block { flex: 1; min-width: 0; }
.acv-username   { font-size: 20px; font-weight: 800; color: #fff; }
.acv-subline    { font-size: 12px; color: rgba(255,255,255,0.75); margin-top: 2px; }
.acv-role-chip  {
  display: inline-flex; align-items: center; gap: 4px; margin-top: 6px;
  padding: 3px 10px; border-radius: 20px;
  background: rgba(255,255,255,0.20); color: #fff;
  font-size: 11px; font-weight: 700; backdrop-filter: blur(6px);
}
.acv-stats-row {
  display: flex; align-items: center;
  background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
  border-radius: 14px 14px 0 0; padding: 14px 0;
}
.acv-stat     { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px; cursor: pointer; }
.acv-stat-num { font-size: 17px; font-weight: 800; color: #fff; }
.acv-stat-lbl { font-size: 11px; color: rgba(255,255,255,0.80); }
.acv-stat-div { width: 1px; height: 28px; background: rgba(255,255,255,0.25); }

/* ── Admin feature cards ── */
.adm-grid {
  display: flex; flex-direction: column; gap: 10px;
  padding: 0 12px;
}
.adm-card {
  display: flex; align-items: center; gap: 14px;
  cursor: pointer; text-align: left; width: 100%;
  border: none;
}
.adm-card-icon {
  width: 48px; height: 48px; border-radius: 14px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.adm-card-body { flex: 1; min-width: 0; }
.adm-card-title { font-size: 15px; font-weight: 700; color: var(--fx-text); }
.adm-card-sub   { font-size: 12px; color: var(--fx-muted); margin-top: 2px; }
.adm-chevron    { font-size: 20px; color: var(--fx-muted); flex-shrink: 0; }

/* ── Customer glass panels ── */
.acv-card { margin: 0 12px 12px; }
.acv-card-header  { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.acv-card-title   { font-size: 15px; font-weight: 800; color: var(--fx-text); letter-spacing: -0.02em; }
.acv-see-all      {
  display: flex; align-items: center; gap: 2px;
  font-size: 12px; color: var(--fx-muted); background: none; border: none; cursor: pointer; padding: 0;
  transition: color 0.18s ease;
}
.acv-see-all:hover { color: var(--fx-accent); }

.acv-quick-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; }
.acv-quick-item {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  background: none; border: none; cursor: pointer; padding: 8px 4px;
  border-radius: 12px;
  transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}
.acv-quick-item:hover { transform: translateY(-1px); }
.acv-quick-item:active { transform: scale(0.97); }
.acv-quick-icon {
  width: 48px; height: 48px;
  color: #FF6635;
  display: flex; align-items: center; justify-content: center;
}

.acv-quick-lbl  { font-size: 11px; font-weight: 600; color: var(--fx-text); text-align: center; }

.acv-orders-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }
.acv-order-btn  {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  background: none; border: none; cursor: pointer; padding: 6px 4px;
  border-radius: 12px;
  transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}
.acv-order-btn:hover { transform: translateY(-1px); }
.acv-order-btn:active { transform: scale(0.97); }
.acv-order-tile {
  position: relative;
  width: 52px; height: 52px;
  display: flex; align-items: center; justify-content: center;
  color: var(--fx-text);
}
.acv-order-icon { font-size: 24px; }
.acv-order-lbl  { font-size: 11px; font-weight: 600; color: var(--fx-text); text-align: center; }
.acv-order-badge {
  position: absolute; top: -3px; right: -4px;
  min-width: 16px; height: 16px; border-radius: 10px; padding: 0 4px;
  background: #FF6635; color: #fff; font-size: 9px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.35), 0 2px 6px rgba(255,102,53,0.35);
}

@media (prefers-reduced-motion: reduce) {
  .acv-quick-item, .acv-order-btn, .acv-see-all { transition: none; }
  .acv-quick-item:hover, .acv-order-btn:hover { transform: none; }
}
</style>
