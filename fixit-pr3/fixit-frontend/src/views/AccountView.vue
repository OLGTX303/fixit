<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as api from '../services/api'

const auth   = useAuthStore()
const router = useRouter()

const user      = computed(() => auth.user || {})
const initials  = computed(() => (user.value.name || '?').split(' ').map(s => s[0]).join('').slice(0,2).toUpperCase())
const roleLabel = computed(() => { const r = auth.role; return r ? r.charAt(0).toUpperCase()+r.slice(1) : '' })

const editing       = ref(false)
const form          = ref({ name:'', phone:'' })
const savingProfile = ref(false)
const profileError  = ref('')
const profileMsg    = ref('')

function startEdit() {
  form.value = { name: user.value.name||'', phone: user.value.phone||'' }
  profileError.value = ''; profileMsg.value = ''; editing.value = true
}
async function saveProfile() {
  savingProfile.value = true; profileError.value = ''
  try {
    const { user: updated } = await api.updateProfile({ name: form.value.name, phone: form.value.phone })
    auth.setUser(updated); editing.value = false; profileMsg.value = 'Profile updated'
  } catch (e) { profileError.value = e.message }
  finally { savingProfile.value = false }
}

const fileInput      = ref(null)
const uploadingAvatar = ref(false)
const avatarError    = ref('')

function pickAvatar() { avatarError.value = ''; fileInput.value?.click() }
async function onAvatarSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/')) { avatarError.value = 'Please choose an image file'; return }
  if (file.size > 4*1024*1024) { avatarError.value = 'Image too large (max 4 MB)'; return }
  uploadingAvatar.value = true
  try {
    const dataUrl = await new Promise((res,rej) => { const r=new FileReader(); r.onload=()=>res(r.result); r.onerror=rej; r.readAsDataURL(file) })
    const { user: updated } = await api.uploadAvatar(dataUrl)
    auth.setUser(updated)
  } catch (err) { avatarError.value = err.message }
  finally { uploadingAvatar.value = false; if (fileInput.value) fileInput.value.value='' }
}

// ── KYC demo flow ──────────────────────────────────────────────────────────
const kycStep   = ref(0)   // 0=idle, 1=upload, 2=processing, 3=done, -1=failed
const kycIdFile = ref(null)
const kycIdName = ref('')
const kycBusy   = ref(false)

const kycStatusLabel = computed(() => {
  if (kycStep.value === 3) return 'Verified'
  if (kycStep.value === -1) return 'Failed'
  if (kycStep.value > 0)   return 'In progress'
  return 'Not verified'
})
const kycBadgeStyle = computed(() => {
  if (kycStep.value === 3)  return { color:'var(--fx-success)', background:'var(--fx-success-soft)' }
  if (kycStep.value === -1) return { color:'var(--fx-error)',   background:'var(--fx-error-soft)' }
  if (kycStep.value > 0)   return { color:'var(--fx-warn)',    background:'var(--fx-warn-soft)' }
  return { color:'var(--fx-muted)', background:'rgba(142,112,104,0.10)' }
})

function kycPickId(e) {
  const file = e.target.files?.[0]
  if (!file) return
  kycIdFile.value = file
  kycIdName.value = file.name
}
async function kycSubmit() {
  if (!kycIdFile.value) return
  kycBusy.value = true
  kycStep.value = 2
  // Simulate a 2-second analysis delay for demo
  await new Promise(r => setTimeout(r, 2000))
  // Demo: always pass
  kycStep.value = 3
  kycBusy.value = false
}
function kycReset() { kycStep.value = 0; kycIdFile.value = null; kycIdName.value = '' }

function logout() { auth.logout(); router.push({ name:'login' }) }
</script>

<template>
  <div class="fx-page" style="max-width:520px">
    <h1 style="font-size:22px;font-weight:700;letter-spacing:-0.01em;margin-bottom:20px">Profile</h1>

    <!-- Avatar card -->
    <div class="fx-card" style="margin-bottom:12px;display:flex;align-items:center;gap:16px">
      <div style="position:relative;flex-shrink:0">
        <img v-if="user.avatar_url" :src="user.avatar_url" alt="avatar"
             style="width:68px;height:68px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.70)" />
        <div v-else class="fx-avatar" style="width:68px;height:68px;font-size:22px">{{ initials }}</div>
        <button class="ac-cam-btn glossy-primary" :disabled="uploadingAvatar" @click="pickAvatar">
          <span class="material-symbols-outlined" style="font-size:14px;font-variation-settings:'FILL' 1">photo_camera</span>
        </button>
        <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="onAvatarSelected" />
      </div>
      <div>
        <div style="font-size:18px;font-weight:700">{{ user.name || 'Unknown user' }}</div>
        <span style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;background:var(--fx-accent-soft);color:var(--fx-accent);margin-top:4px">
          {{ roleLabel }}
        </span>
        <div v-if="uploadingAvatar" style="font-size:11px;color:var(--fx-muted);margin-top:4px">Uploading…</div>
      </div>
    </div>
    <div v-if="avatarError" class="alert alert-danger" style="font-size:12px;padding:8px 12px;margin-bottom:10px">{{ avatarError }}</div>

    <!-- Account details card -->
    <div class="fx-card" style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <span class="fx-label-caps">Account Details</span>
        <button v-if="!editing" class="btn btn-link" style="font-size:13px" @click="startEdit">Edit</button>
      </div>

      <template v-if="!editing">
        <div style="display:flex;flex-direction:column;gap:12px">
          <div>
            <div style="font-size:11px;color:var(--fx-muted);margin-bottom:2px">Name</div>
            <div style="font-size:14px;font-weight:600">{{ user.name || '—' }}</div>
          </div>
          <div>
            <div style="font-size:11px;color:var(--fx-muted);margin-bottom:2px">Phone</div>
            <div style="font-size:14px;font-weight:600">{{ user.phone || '—' }}</div>
          </div>
        </div>
        <div v-if="profileMsg" style="font-size:12px;color:var(--fx-success);margin-top:10px">✓ {{ profileMsg }}</div>
      </template>

      <template v-else>
        <div style="display:flex;flex-direction:column;gap:10px">
          <div>
            <div class="fx-label-caps" style="margin-bottom:6px">Name</div>
            <div class="fx-input"><input v-model="form.name" placeholder="Your name" /></div>
          </div>
          <div>
            <div class="fx-label-caps" style="margin-bottom:6px">Phone</div>
            <div class="fx-input"><input v-model="form.phone" placeholder="+60 12-345 6789" /></div>
          </div>
        </div>
        <div v-if="profileError" class="alert alert-danger" style="font-size:12px;padding:8px 12px;margin-top:10px">{{ profileError }}</div>
        <div style="display:flex;gap:8px;margin-top:14px">
          <button class="btn btn-primary" style="flex:1" :disabled="savingProfile" @click="saveProfile">
            {{ savingProfile ? 'Saving…' : 'Save' }}
          </button>
          <button class="btn btn-outline-secondary" :disabled="savingProfile" @click="editing=false">Cancel</button>
        </div>
      </template>
    </div>

    <!-- ── KYC Identity Verification (customer demo) ─────────────────── -->
    <div class="fx-card kyc-card" style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <div style="display:flex;align-items:center;gap:8px">
          <div class="kyc-icon-wrap">
            <span class="material-symbols-outlined" style="font-size:18px;color:var(--fx-accent);font-variation-settings:'FILL' 1">verified_user</span>
          </div>
          <span class="fx-label-caps">Identity Verification</span>
        </div>
        <span class="fx-badge" :style="kycBadgeStyle">{{ kycStatusLabel }}</span>
      </div>

      <!-- Idle — not started -->
      <template v-if="kycStep === 0">
        <p style="font-size:13px;color:var(--fx-muted);margin-bottom:14px;line-height:1.5">
          Verify your identity to unlock higher booking limits and priority support.
          Takes less than 2 minutes.
        </p>
        <div class="kyc-steps-row">
          <div class="kyc-step-pill">
            <span class="material-symbols-outlined" style="font-size:16px">badge</span>
            Gov ID
          </div>
          <div class="kyc-step-arrow">›</div>
          <div class="kyc-step-pill">
            <span class="material-symbols-outlined" style="font-size:16px">check_circle</span>
            Review
          </div>
          <div class="kyc-step-arrow">›</div>
          <div class="kyc-step-pill">
            <span class="material-symbols-outlined" style="font-size:16px">verified</span>
            Done
          </div>
        </div>
        <button class="btn btn-primary w-100" style="margin-top:14px" @click="kycStep=1">
          Start Verification
        </button>
      </template>

      <!-- Step 1 — Upload ID -->
      <template v-else-if="kycStep === 1">
        <p style="font-size:13px;color:var(--fx-muted);margin-bottom:12px">
          Upload a clear photo of your passport, national ID, or driving licence.
        </p>
        <label class="kyc-upload-area">
          <input type="file" accept="image/*" class="d-none" @change="kycPickId" />
          <span class="material-symbols-outlined" style="font-size:28px;color:var(--fx-accent)">upload_file</span>
          <span style="font-size:13px;font-weight:600;margin-top:6px">
            {{ kycIdName || 'Tap to upload ID photo' }}
          </span>
          <span style="font-size:11px;color:var(--fx-muted)">JPEG, PNG · max 4 MB</span>
        </label>
        <div style="display:flex;gap:8px;margin-top:12px">
          <button class="btn btn-outline-secondary" @click="kycReset">Cancel</button>
          <button class="btn btn-primary" style="flex:1" :disabled="!kycIdFile" @click="kycSubmit">
            Analyse ID
          </button>
        </div>
      </template>

      <!-- Step 2 — Processing -->
      <template v-else-if="kycStep === 2">
        <div class="kyc-processing">
          <div class="kyc-spinner"></div>
          <div style="font-size:14px;font-weight:600;margin-top:12px">Analysing your document…</div>
          <div style="font-size:12px;color:var(--fx-muted);margin-top:4px">Running OCR + fraud checks</div>
        </div>
      </template>

      <!-- Step 3 — Verified -->
      <template v-else-if="kycStep === 3">
        <div class="kyc-success">
          <div class="kyc-check">
            <span class="material-symbols-outlined" style="font-size:32px;color:#fff;font-variation-settings:'FILL' 1">verified</span>
          </div>
          <div style="font-size:15px;font-weight:700;margin-top:12px">Identity verified</div>
          <div style="font-size:12px;color:var(--fx-muted);margin-top:4px">Your account is now trusted</div>
        </div>
      </template>
    </div>

    <!-- Email row -->
    <button class="fx-card ac-row-btn" style="margin-bottom:10px" @click="router.push({ name:'account-email' })">
      <div class="ac-row-icon">
        <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-accent);font-variation-settings:'FILL' 1">mail</span>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:14px;font-weight:600">Email</div>
        <div style="font-size:12px;color:var(--fx-muted)">{{ user.email }}</div>
      </div>
      <span class="material-symbols-outlined" style="font-size:18px;color:rgba(142,112,104,0.45)">chevron_right</span>
    </button>

    <!-- Payment row -->
    <button class="fx-card ac-row-btn" style="margin-bottom:20px" @click="router.push({ name:'account-billing' })">
      <div class="ac-row-icon">
        <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-accent);font-variation-settings:'FILL' 1">credit_card</span>
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:14px;font-weight:600">Payment methods</div>
        <div style="font-size:12px;color:var(--fx-muted)">Manage your saved card</div>
      </div>
      <span class="material-symbols-outlined" style="font-size:18px;color:rgba(142,112,104,0.45)">chevron_right</span>
    </button>

    <!-- Logout -->
    <button class="btn btn-outline-danger w-100" style="height:50px;font-size:14px" @click="logout">
      <span class="material-symbols-outlined" style="font-size:18px;margin-right:6px">logout</span>
      Log out
    </button>

    <p style="text-align:center;font-size:11px;color:var(--fx-muted);margin-top:16px">
      <router-link :to="{ name:'legal-terms' }"   style="color:var(--fx-accent);text-decoration:none">Terms</router-link>
      ·
      <router-link :to="{ name:'legal-privacy' }" style="color:var(--fx-accent);text-decoration:none">Privacy</router-link>
    </p>
  </div>
</template>

<style scoped>
.ac-cam-btn {
  position: absolute; bottom: -4px; right: -4px;
  width: 28px; height: 28px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  border: 2px solid rgba(255,255,255,0.70);
}
.ac-row-btn {
  width: 100%; display: flex; align-items: center; gap: 14px;
  border: none; text-align: left; cursor: pointer;
  transition: transform 0.15s ease;
}
.ac-row-btn:hover  { transform: translateX(3px); }
.ac-row-btn:active { transform: scale(0.98); }
.ac-row-icon {
  width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
  background: var(--fx-accent-soft);
  display: flex; align-items: center; justify-content: center;
}

/* KYC */
.kyc-icon-wrap {
  width: 32px; height: 32px; border-radius: 10px;
  background: var(--fx-accent-soft);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.kyc-steps-row {
  display: flex; align-items: center; gap: 6px;
}
.kyc-step-pill {
  display: flex; align-items: center; gap: 4px;
  padding: 5px 10px; border-radius: 999px; font-size: 12px; font-weight: 600;
  background: rgba(255,102,53,0.08); color: var(--fx-accent);
  flex: 1; justify-content: center;
}
.kyc-step-arrow { color: var(--fx-muted-soft); font-size: 16px; }

.kyc-upload-area {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 4px; padding: 20px; border-radius: 16px; cursor: pointer;
  border: 2px dashed rgba(255,102,53,0.30);
  background: rgba(255,102,53,0.04);
  transition: background 0.2s ease;
  width: 100%;
}
.kyc-upload-area:hover { background: rgba(255,102,53,0.08); }

.kyc-processing {
  display: flex; flex-direction: column; align-items: center;
  padding: 24px 0 16px;
}
.kyc-spinner {
  width: 40px; height: 40px; border-radius: 50%;
  border: 3px solid rgba(255,102,53,0.20);
  border-top-color: var(--fx-accent);
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.kyc-success {
  display: flex; flex-direction: column; align-items: center; padding: 20px 0 12px;
}
.kyc-check {
  width: 56px; height: 56px; border-radius: 50%;
  background: linear-gradient(145deg, #34d399, #22c55e);
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(34,197,94,0.30);
}
</style>
