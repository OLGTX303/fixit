<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useProvidersStore } from '../../stores/providers'
import { useAuthStore } from '../../stores/auth'
import * as api from '../../services/api'
import AppIcon from '../../components/AppIcon.vue'

const router = useRouter()
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
  <div class="fx-page" style="max-width:560px">
    <h1 class="fw-bold mb-4" style="font-size:20px">My Profile</h1>

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

    <!-- KYC / Identity verification card (iOS 26 glass style) -->
    <div class="fx-card mb-4 psp-kyc-card"
         :class="kycVerified ? 'psp-kyc-ok' : (myProfile?.kyc_status === 'failed' ? 'psp-kyc-fail' : 'psp-kyc-warn')">
      <div class="psp-kyc-inner">
        <div class="psp-kyc-icon">
          <span class="material-symbols-outlined"
                :style="{ fontSize: '20px', fontVariationSettings: `'FILL' 1`,
                          color: kycVerified ? '#22c55e' : myProfile?.kyc_status === 'failed' ? 'var(--fx-error)' : 'var(--fx-accent)' }">
            {{ kycVerified ? 'verified_user' : 'shield' }}
          </span>
        </div>
        <div style="flex:1;min-width:0">
          <div class="fw-semibold" style="font-size:14px">
            {{ kycVerified ? 'Identity Verified' : 'Identity Verification Required' }}
          </div>
          <div style="font-size:12px;color:var(--fx-muted);margin-top:3px">
            {{ kycVerified
              ? 'Your ID and face liveness check passed. Account approved.'
              : 'Upload a government ID and complete the 8-colour face check to get approved.'
            }}
          </div>
          <div v-if="myProfile?.kyc_status && myProfile.kyc_status !== 'none'" style="margin-top:6px">
            <span class="fx-badge" :style="{
              color: kycVerified ? '#22c55e' : myProfile?.kyc_status === 'failed' ? 'var(--fx-error)' : 'var(--fx-accent)',
              background: kycVerified ? 'rgba(34,197,94,0.12)' : myProfile?.kyc_status === 'failed' ? 'var(--fx-error-soft)' : 'var(--fx-accent-soft)'
            }">{{ kycStatusLabel }}</span>
          </div>
        </div>
        <button v-if="!kycVerified" class="btn btn-primary btn-sm psp-kyc-btn"
                @click="router.push({ name: 'pro-kyc' })">
          {{ myProfile?.kyc_status === 'failed' ? 'Retry' : 'Start' }}
        </button>
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

    <!-- ── Availability Calendar (stretch goal) ── -->
    <div class="fx-card mt-4" style="padding:16px">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
          <div class="fw-bold" style="font-size:15px">Weekly Availability</div>
          <div style="font-size:12px;color:var(--fx-muted)">Set your open time windows per day</div>
        </div>
      </div>

      <!-- Day selector pills -->
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <button v-for="(day, dow) in DAYS" :key="dow"
          class="fx-chip sm" :class="{ active: isDayActive(dow) }"
          @click="toggleDay(dow)">{{ day }}</button>
      </div>

      <!-- Time slots for active days -->
      <div v-for="(day, dow) in DAYS" :key="`slot-${dow}`">
        <div v-if="isDayActive(dow)" class="psp-avail-row">
          <span class="psp-avail-day">{{ day }}</span>
          <input class="fx-input psp-avail-time" type="time" v-model="slotFor(dow).start_time" />
          <span style="color:var(--fx-muted);font-size:13px">to</span>
          <input class="fx-input psp-avail-time" type="time" v-model="slotFor(dow).end_time" />
          <label class="psp-avail-auto" :title="slotFor(dow).auto_confirm ? 'Auto-accept on' : 'Auto-accept off'">
            <input type="checkbox" v-model="slotFor(dow).auto_confirm" />
            <span style="font-size:11px;color:var(--fx-muted)">Auto</span>
          </label>
        </div>
      </div>

      <button class="btn btn-primary w-100 mt-3" :disabled="savingAvail || !myProfile" @click="saveAvailability">
        {{ savingAvail ? 'Saving…' : availSaved ? '✓ Saved' : 'Save Availability' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
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
