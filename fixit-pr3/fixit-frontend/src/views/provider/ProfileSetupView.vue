<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useProvidersStore } from '../../stores/providers'
import { useAuthStore } from '../../stores/auth'
import * as api from '../../services/api'
import AppIcon from '../../components/AppIcon.vue'

const router = useRouter()
const route  = useRoute()
const subTab = ref(route.query.tab || 'profile') // 'profile' | 'availability' | 'kyc'
const providersStore = useProvidersStore()
const auth = useAuthStore()

const form = ref({ name: '', bio: '', location: '', base_rate: 45, available: true })
const allServices = ['Pipe Repair', 'Drain Cleaning', 'Leak Detection', 'Installation', 'Boiler Service']
const selectedServices = ref([])
const saved = ref(false)
const saving = ref(false)
const saveError = ref('')

// Rate type (stretch goal)
const rateType   = ref('hourly')
const perJobRate = ref(80)

// Availability calendar (stretch goal)
// Each entry: { day_of_week: 0–6, start_time: 'HH:MM', end_time: 'HH:MM', auto_confirm: bool }
const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const availSlots = ref([
  { day_of_week: 1, start_time: '09:00', end_time: '17:00', auto_confirm: true },
  { day_of_week: 2, start_time: '09:00', end_time: '17:00', auto_confirm: true },
  { day_of_week: 3, start_time: '09:00', end_time: '17:00', auto_confirm: true },
  { day_of_week: 4, start_time: '09:00', end_time: '17:00', auto_confirm: true },
  { day_of_week: 5, start_time: '09:00', end_time: '17:00', auto_confirm: true },
])
const savingAvail = ref(false)
const availSaved  = ref(false)

function isDayActive(dow) { return availSlots.value.some(s => s.day_of_week === dow) }
function toggleDay(dow) {
  const i = availSlots.value.findIndex(s => s.day_of_week === dow)
  if (i === -1) availSlots.value.push({ day_of_week: dow, start_time: '09:00', end_time: '17:00', auto_confirm: true })
  else availSlots.value.splice(i, 1)
}
function slotFor(dow) { return availSlots.value.find(s => s.day_of_week === dow) }

async function saveAvailability() {
  if (!myProfile.value) return
  savingAvail.value = true
  try {
    await api.saveProviderAvailability(myProfile.value.id, availSlots.value)
    availSaved.value = true
    setTimeout(() => (availSaved.value = false), 2000)
  } catch { /* ignore */ } finally { savingAvail.value = false }
}

const myProfile = computed(() =>
  providersStore.providers.find(p => p.user_id === auth.user?.id))

const kycStatusLabel = computed(() => {
  const s = myProfile.value?.kyc_status || 'none'
  const map = { none: 'Not started', id_pending: 'Pending', id_passed: 'ID verified', liveness_pending: 'Face check pending', submitted: 'Under review', failed: 'Failed — retry' }
  return map[s] || s
})
const kycVerified = computed(() => myProfile.value?.is_verified)

// Avatar — syncs to auth store so HomeView hv-avatar reflects it
const avatarFileInput = ref(null)
const uploadingAvatar = ref(false)
const avatarError     = ref('')

// Cover photo
const coverFileInput  = ref(null)
const uploadingCover  = ref(false)
const coverError      = ref('')
const coverUrl        = computed(() => myProfile.value?.cover_url || null)

function pickCover() { coverError.value = ''; coverFileInput.value?.click() }
async function onCoverSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (file.size > 8 * 1024 * 1024) { coverError.value = 'Max 8 MB'; return }
  uploadingCover.value = true
  try {
    const dataUrl = await new Promise((res, rej) => {
      const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(file)
    })
    const { url } = await api.uploadImage(dataUrl)
    await api.updateProvider(myProfile.value.id, { cover_url: url })
    await providersStore.load()
  } catch (err) { coverError.value = err.message }
  finally { uploadingCover.value = false; if (coverFileInput.value) coverFileInput.value.value = '' }
}

// Derived: show real avatar if auth.user has one (uploaded in AccountView or here)
const avatarUrl = computed(() => auth.user?.avatar_url || null)
const initials  = computed(() => (form.value.name || '?').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase())

function pickAvatar() { avatarError.value = ''; avatarFileInput.value?.click() }
async function onAvatarSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/')) { avatarError.value = 'Choose an image file'; return }
  if (file.size > 4 * 1024 * 1024) { avatarError.value = 'Max 4 MB'; return }
  uploadingAvatar.value = true
  try {
    const dataUrl = await new Promise((res, rej) => {
      const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(file)
    })
    const { user: updated } = await api.uploadAvatar(dataUrl)
    // Update auth store → HomeView hv-avatar syncs automatically
    auth.setUser(updated)
  } catch (err) { avatarError.value = err.message }
  finally { uploadingAvatar.value = false; if (avatarFileInput.value) avatarFileInput.value.value = '' }
}

onMounted(async () => {
  await providersStore.load()
  if (myProfile.value) {
    form.value = {
      name:      myProfile.value.name,
      bio:       myProfile.value.bio,
      location:  myProfile.value.location,
      base_rate: myProfile.value.base_rate,
      available: true,
    }
    selectedServices.value = [...myProfile.value.services]
    rateType.value   = myProfile.value.rate_type   || 'hourly'
    perJobRate.value = myProfile.value.per_job_rate || 80
    // Load saved availability slots from API
    try {
      const slots = await api.getProviderAvailability(myProfile.value.id)
      if (slots?.length) availSlots.value = slots
    } catch { /* no availability yet, use defaults */ }
  }
})

function toggleService(s) {
  const i = selectedServices.value.indexOf(s)
  i === -1 ? selectedServices.value.push(s) : selectedServices.value.splice(i, 1)
}

async function save() {
  if (!myProfile.value) return
  saving.value = true
  saveError.value = ''
  try {
    await api.updateProvider(myProfile.value.id, {
      bio:          form.value.bio,
      location:     form.value.location,
      base_rate:    form.value.base_rate,
      rate_type:    rateType.value,
      per_job_rate: rateType.value === 'per_job' ? perJobRate.value : null,
      latitude:     myProfile.value.latitude,
      longitude:    myProfile.value.longitude,
      services:     selectedServices.value,
    })
    await providersStore.reload()
    saved.value = true
    setTimeout(() => (saved.value = false), 2000)
  } catch (e) {
    saveError.value = e.message || 'Save failed'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="fx-page psp-root" style="max-width:560px;padding-top:0">

    <!-- Back bar -->
    <div class="psp-topbar">
      <button class="psp-back-btn" @click="router.push({ name: 'pro-profile' })">
        <span class="material-symbols-outlined">arrow_back_ios</span>
      </button>
      <span class="psp-topbar-title">Edit Profile</span>
      <div style="width:36px"></div>
    </div>

    <!-- Sub-tab bar -->
    <div class="psp-tabs">
      <button class="psp-tab" :class="{active: subTab==='profile'}"      @click="subTab='profile'">
        <span class="material-symbols-outlined" style="font-size:18px">person</span>Profile
      </button>
      <button class="psp-tab" :class="{active: subTab==='availability'}" @click="subTab='availability'">
        <span class="material-symbols-outlined" style="font-size:18px">calendar_month</span>Schedule
      </button>
      <button class="psp-tab" :class="{active: subTab==='kyc'}"          @click="subTab='kyc'">
        <span class="material-symbols-outlined" style="font-size:18px">verified_user</span>KYC
      </button>
    </div>

    <!-- ── PROFILE TAB ── -->
    <div v-if="subTab==='profile'" class="psp-tab-body">

    <!-- Cover photo -->
    <div class="psp-cover-wrap mb-3" @click="pickCover" role="button" aria-label="Change cover photo">
      <img v-if="coverUrl" :src="coverUrl" class="psp-cover-img" alt="Cover" />
      <div v-else class="psp-cover-placeholder">
        <span class="material-symbols-outlined" style="font-size:28px;color:rgba(255,255,255,0.8)">add_photo_alternate</span>
        <span style="font-size:12px;color:rgba(255,255,255,0.8);margin-top:4px">Add cover photo</span>
      </div>
      <div v-if="uploadingCover" class="psp-cover-overlay">Uploading…</div>
      <div v-else class="psp-cover-edit-badge">
        <span class="material-symbols-outlined" style="font-size:14px">edit</span>
      </div>
    </div>
    <div v-if="coverError" style="font-size:11px;color:var(--fx-error);margin-bottom:8px">{{ coverError }}</div>
    <input ref="coverFileInput" type="file" accept="image/*" style="display:none" @change="onCoverSelected" />

    <!-- Avatar section — uploads sync to auth.user.avatar_url → HomeView badge -->
    <div class="psp-avatar-section fx-card mb-4">
      <div class="psp-avatar-wrap">
        <img v-if="avatarUrl" :src="avatarUrl" alt="avatar" class="psp-avatar-img" />
        <div v-else class="fx-avatar psp-avatar-fallback">{{ initials }}</div>
        <button class="psp-cam-btn glossy-primary" :disabled="uploadingAvatar" @click="pickAvatar"
                aria-label="Change photo">
          <span class="material-symbols-outlined" style="font-size:14px;font-variation-settings:'FILL' 1">photo_camera</span>
        </button>
        <input ref="avatarFileInput" type="file" accept="image/*" style="display:none" @change="onAvatarSelected" />
      </div>
      <div class="psp-avatar-info">
        <div style="font-size:17px;font-weight:700">{{ form.name || auth.user?.name || 'Provider' }}</div>
        <div style="font-size:12px;color:var(--fx-muted);margin-top:2px">Tap the camera to update your photo</div>
        <div v-if="uploadingAvatar" style="font-size:11px;color:var(--fx-accent);margin-top:4px">Uploading…</div>
        <div v-if="avatarError" style="font-size:11px;color:var(--fx-error);margin-top:4px">{{ avatarError }}</div>
      </div>
    </div>

    <div class="d-flex flex-column gap-3">
      <div>
        <label class="fx-label">Full Name</label>
        <div class="fx-input"><input v-model="form.name" placeholder="Your full name" /></div>
      </div>
      <div>
        <label class="fx-label">Bio</label>
        <div class="fx-input" style="height:auto"><textarea rows="3" v-model="form.bio" style="resize:none;border:none;background:transparent;width:100%;outline:none;font-family:inherit;font-size:14px;color:var(--fx-text);padding:0" placeholder="Tell customers about yourself…"></textarea></div>
      </div>
      <div>
        <label class="fx-label">Location</label>
        <div class="fx-input"><input v-model="form.location" placeholder="Your city or area" /></div>
      </div>
      <!-- Rate type toggle (stretch goal) -->
      <div>
        <label class="fx-label">Pricing Model</label>
        <div class="d-flex gap-2 mb-2">
          <button class="fx-chip sm flex-fill" :class="{ active: rateType === 'hourly' }"
                  @click="rateType = 'hourly'">Hourly Rate</button>
          <button class="fx-chip sm flex-fill" :class="{ active: rateType === 'per_job' }"
                  @click="rateType = 'per_job'">Per Job (flat)</button>
        </div>
        <div v-if="rateType === 'hourly'" class="fx-input" style="display:flex;align-items:center;gap:10px">
          <span class="fw-bold" style="font-size:18px;color:var(--fx-accent)">$</span>
          <span class="fw-bold" style="font-size:22px">{{ form.base_rate }}</span>
          <span style="color:var(--fx-muted)">/hr</span>
          <div style="margin-left:auto;display:flex;gap:8px">
            <button class="btn btn-light" style="width:32px;height:32px;padding:0;border-radius:8px"
                    @click="form.base_rate = Math.max(10, form.base_rate - 5)">−</button>
            <button class="btn btn-primary" style="width:32px;height:32px;padding:0;border-radius:8px"
                    @click="form.base_rate += 5">+</button>
          </div>
        </div>
        <div v-else class="fx-input" style="display:flex;align-items:center;gap:10px">
          <span class="fw-bold" style="font-size:18px;color:var(--fx-accent)">$</span>
          <span class="fw-bold" style="font-size:22px">{{ perJobRate }}</span>
          <span style="color:var(--fx-muted)">/job</span>
          <div style="margin-left:auto;display:flex;gap:8px">
            <button class="btn btn-light" style="width:32px;height:32px;padding:0;border-radius:8px"
                    @click="perJobRate = Math.max(10, perJobRate - 5)">−</button>
            <button class="btn btn-primary" style="width:32px;height:32px;padding:0;border-radius:8px"
                    @click="perJobRate += 5">+</button>
          </div>
        </div>
      </div>
      <div>
        <label class="fx-label">Services Offered</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px">
          <span v-for="s in allServices" :key="s" class="fx-chip sm" :class="{ active: selectedServices.includes(s) }"
                @click="toggleService(s)">{{ s }}</span>
        </div>
      </div>

      <div class="fx-card" style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <div class="fw-semibold" style="font-size:14px">Available for Bookings</div>
          <div style="font-size:12px;color:var(--fx-muted)">Customers can book your services</div>
        </div>
        <div class="form-check form-switch m-0">
          <input class="form-check-input" type="checkbox" v-model="form.available" style="cursor:pointer" />
        </div>
      </div>
    </div>

    <div v-if="saveError" class="alert alert-danger py-2 mt-3" style="font-size:13px">{{ saveError }}</div>
    <button class="btn btn-primary w-100 mt-4" :disabled="saving || !myProfile" @click="save">
      {{ saving ? 'Saving…' : saved ? '✓ Saved' : 'Save Changes' }}
    </button>

    </div><!-- end profile tab -->

    <!-- ── AVAILABILITY TAB ── -->
    <div v-if="subTab==='availability'" class="psp-tab-body">
      <div class="psp-section-title">Weekly Schedule</div>
      <div style="font-size:13px;color:var(--fx-muted);margin-bottom:14px">Tap a day to toggle it, then set your hours.</div>

      <div class="d-flex gap-2 mb-4 flex-wrap">
        <button v-for="(day, dow) in DAYS" :key="dow"
          class="fx-chip sm" :class="{ active: isDayActive(dow) }"
          @click="toggleDay(dow)">{{ day }}</button>
      </div>

      <div class="d-flex flex-column gap-3">
        <div v-for="(day, dow) in DAYS" :key="`slot-${dow}`">
          <div v-if="isDayActive(dow)" class="psp-avail-card">
            <div class="psp-avail-day-label">{{ day }}</div>
            <div class="psp-avail-times">
              <input class="fx-input psp-avail-time" type="time" v-model="slotFor(dow).start_time" />
              <span style="color:var(--fx-muted);font-size:13px;padding:0 4px">–</span>
              <input class="fx-input psp-avail-time" type="time" v-model="slotFor(dow).end_time" />
            </div>
            <label class="psp-avail-auto">
              <input type="checkbox" v-model="slotFor(dow).auto_confirm" />
              <span style="font-size:11px;color:var(--fx-muted)">Auto-accept</span>
            </label>
          </div>
        </div>
      </div>

      <button class="btn btn-primary w-100 mt-4" :disabled="savingAvail || !myProfile" @click="saveAvailability">
        {{ savingAvail ? 'Saving…' : availSaved ? '✓ Saved' : 'Save Schedule' }}
      </button>
    </div><!-- end availability tab -->

    <!-- ── KYC TAB ── -->
    <div v-if="subTab==='kyc'" class="psp-tab-body">
      <div class="psp-section-title">Identity Verification</div>

      <div class="fx-card psp-kyc-card mb-4"
           :class="kycVerified ? 'psp-kyc-ok' : (myProfile?.kyc_status === 'failed' ? 'psp-kyc-fail' : 'psp-kyc-warn')">
        <div class="psp-kyc-inner">
          <div class="psp-kyc-icon">
            <span class="material-symbols-outlined"
                  :style="{ fontSize: '22px', fontVariationSettings: `'FILL' 1`,
                            color: kycVerified ? '#22c55e' : myProfile?.kyc_status === 'failed' ? 'var(--fx-error)' : 'var(--fx-accent)' }">
              {{ kycVerified ? 'verified_user' : 'shield' }}
            </span>
          </div>
          <div style="flex:1;min-width:0">
            <div class="fw-semibold" style="font-size:15px">
              {{ kycVerified ? 'Identity Verified ✓' : 'Verification Required' }}
            </div>
            <div style="font-size:12px;color:var(--fx-muted);margin-top:4px;line-height:1.5">
              {{ kycVerified
                ? 'Your ID and face liveness check passed. Your account is approved.'
                : 'Upload a government-issued ID and complete the 8-colour face check to get approved.'
              }}
            </div>
            <div v-if="myProfile?.kyc_status && myProfile.kyc_status !== 'none'" style="margin-top:8px">
              <span class="fx-badge" :style="{
                color: kycVerified ? '#22c55e' : myProfile?.kyc_status === 'failed' ? 'var(--fx-error)' : 'var(--fx-accent)',
                background: kycVerified ? 'rgba(34,197,94,0.12)' : myProfile?.kyc_status === 'failed' ? 'var(--fx-error-soft)' : 'var(--fx-accent-soft)'
              }">{{ kycStatusLabel }}</span>
            </div>
          </div>
        </div>
      </div>

      <button v-if="!kycVerified" class="btn btn-primary w-100" @click="router.push({ name: 'pro-kyc' })">
        <span class="material-symbols-outlined" style="font-size:18px;vertical-align:middle;margin-right:6px">photo_camera</span>
        {{ myProfile?.kyc_status === 'failed' ? 'Retry Verification' : 'Start Verification' }}
      </button>

      <div v-if="kycVerified" style="text-align:center;padding:24px 0;color:var(--fx-muted);font-size:14px">
        <span class="material-symbols-outlined" style="font-size:48px;color:#22c55e;display:block;font-variation-settings:'FILL' 1">verified_user</span>
        All good — your identity is confirmed.
      </div>
    </div><!-- end kyc tab -->

  </div>
</template>

<style scoped>
/* Sub-tab widget */
.psp-root { padding-top: 0 !important; }

/* Back bar */
.psp-topbar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 52px 16px 12px;
  background: rgba(255,255,255,0.55);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-bottom: 0.5px solid rgba(255,255,255,0.60);
  box-shadow: 0 1px 0 rgba(255,255,255,0.50);
}
.psp-back-btn {
  background: none; border: none; cursor: pointer; color: var(--fx-text);
  display: flex; align-items: center; padding: 4px;
}
.psp-back-btn .material-symbols-outlined { font-size: 20px; }
.psp-topbar-title { font-size: 17px; font-weight: 700; color: var(--fx-text); }

.psp-tabs {
  display: flex; position: sticky; top: 0; z-index: 10;
  background: rgba(255,255,255,0.60);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-bottom: 0.5px solid rgba(255,255,255,0.55);
  margin: 0 -16px 0; padding: 0 8px;
}
.psp-tab {
  flex: 1; display: flex; flex-direction: column; align-items: center; gap: 2px;
  padding: 10px 4px; border: none; background: transparent;
  font-size: 11px; font-weight: 600; color: var(--fx-muted);
  border-bottom: 2.5px solid transparent; cursor: pointer; transition: all .15s;
}
.psp-tab.active { color: #FF6635; border-bottom-color: #FF6635; }
.psp-tab-body   { padding-top: 16px; }
.psp-section-title { font-size: 17px; font-weight: 800; margin-bottom: 6px; }

/* Availability card rows */
.psp-avail-card {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 14px; border-radius: 14px;
  background:
    linear-gradient(to bottom, rgba(255,255,255,0.28) 0%, rgba(255,255,255,0.06) 100%),
    rgba(255,255,255,0.06);
  border: 0.5px solid rgba(255,255,255,0.60);
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.70), 0 2px 8px rgba(0,0,0,0.04);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}
.psp-avail-day-label { font-size: 13px; font-weight: 700; color: var(--fx-text); width: 36px; flex-shrink: 0; }
.psp-avail-times { display: flex; align-items: center; flex: 1; }

/* Cover photo */
.psp-cover-wrap {
  position: relative; height: 130px; border-radius: 16px; overflow: hidden;
  background: linear-gradient(160deg, #1e3a5f, #1e4080);
  display: flex; align-items: center; justify-content: center; flex-direction: column;
  cursor: pointer;
}
.psp-cover-img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
.psp-cover-placeholder { display: flex; flex-direction: column; align-items: center; z-index: 1; }
.psp-cover-overlay {
  position: absolute; inset: 0; background: rgba(0,0,0,0.45); display: flex;
  align-items: center; justify-content: center; font-size: 13px; color: #fff;
}
.psp-cover-edit-badge {
  position: absolute; bottom: 8px; right: 10px; z-index: 2;
  background: rgba(0,0,0,0.45); border-radius: 50%; width: 28px; height: 28px;
  display: flex; align-items: center; justify-content: center; color: #fff;
}

/* Avatar section */
.psp-avatar-section { display: flex; align-items: center; gap: 16px; }
.psp-avatar-wrap { position: relative; flex-shrink: 0; }
.psp-avatar-img {
  width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
  border: 2.5px solid rgba(255,255,255,0.75);
  box-shadow: 0 2px 10px rgba(0,0,0,0.12);
}
.psp-avatar-fallback { width: 72px; height: 72px; font-size: 26px; border: 2.5px solid rgba(255,255,255,0.75); }
.psp-cam-btn {
  position: absolute; bottom: -4px; right: -4px;
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid rgba(255,255,255,0.70);
  cursor: pointer;
}
.psp-avatar-info { flex: 1; min-width: 0; }

/* KYC card accent */
.psp-kyc-inner { display: flex; align-items: flex-start; gap: 12px; }
.psp-kyc-icon {
  width: 38px; height: 38px; border-radius: 12px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.psp-kyc-ok   .psp-kyc-icon { background: rgba(34,197,94,0.12); }
.psp-kyc-warn .psp-kyc-icon { background: var(--fx-accent-soft); }
.psp-kyc-fail .psp-kyc-icon { background: var(--fx-error-soft); }
.psp-kyc-btn { border-radius: 8px; margin-left: auto; flex-shrink: 0; align-self: center; }

/* Availability calendar rows */
.psp-avail-row {
  display: flex; align-items: center; gap: 8px; margin-bottom: 10px;
}
.psp-avail-day  { width: 34px; font-size: 12px; font-weight: 600; color: var(--fx-muted); flex-shrink: 0; }
.psp-avail-time { flex: 1; min-width: 0; padding: 6px 10px; font-size: 13px; }
.psp-avail-auto { display: flex; align-items: center; gap: 4px; flex-shrink: 0; cursor: pointer; }
</style>
