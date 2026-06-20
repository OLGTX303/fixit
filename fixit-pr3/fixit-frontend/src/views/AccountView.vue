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
</style>
