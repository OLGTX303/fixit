<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as api from '../services/api'
import AppIcon from '../components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

const user = computed(() => auth.user || {})
const initials = computed(() => {
  const n = user.value.name || '?'
  return n.split(' ').map((s) => s[0]).join('').slice(0, 2).toUpperCase()
})
const roleLabel = computed(() => {
  const r = auth.role
  return r ? r.charAt(0).toUpperCase() + r.slice(1) : ''
})

// ── Editable profile (name + phone; email is OTP-verified on its own page) ────
const editing = ref(false)
const form = ref({ name: '', phone: '' })
const savingProfile = ref(false)
const profileError = ref('')
const profileMsg = ref('')

function startEdit() {
  form.value = { name: user.value.name || '', phone: user.value.phone || '' }
  profileError.value = ''
  profileMsg.value = ''
  editing.value = true
}

async function saveProfile() {
  savingProfile.value = true
  profileError.value = ''
  try {
    const { user: updated } = await api.updateProfile({
      name: form.value.name,
      phone: form.value.phone,
    })
    auth.setUser(updated)
    editing.value = false
    profileMsg.value = 'Profile updated'
  } catch (e) {
    profileError.value = e.message
  } finally {
    savingProfile.value = false
  }
}

// ── Avatar ──────────────────────────────────────────────────────────────────
const fileInput = ref(null)
const uploadingAvatar = ref(false)
const avatarError = ref('')

function pickAvatar() {
  avatarError.value = ''
  fileInput.value?.click()
}

async function onAvatarSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (!file.type.startsWith('image/')) {
    avatarError.value = 'Please choose an image file'
    return
  }
  if (file.size > 4 * 1024 * 1024) {
    avatarError.value = 'Image too large (max 4 MB)'
    return
  }
  uploadingAvatar.value = true
  try {
    const dataUrl = await new Promise((resolve, reject) => {
      const r = new FileReader()
      r.onload = () => resolve(r.result)
      r.onerror = reject
      r.readAsDataURL(file)
    })
    const { user: updated } = await api.uploadAvatar(dataUrl)
    auth.setUser(updated)
  } catch (err) {
    avatarError.value = err.message
  } finally {
    uploadingAvatar.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

function logout() {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="fx-page" style="max-width:520px">
    <h1 class="fw-bold mb-3" style="font-size:20px">Profile</h1>

    <!-- Avatar + identity -->
    <div class="fx-card mb-3 d-flex align-items-center gap-3">
      <div class="position-relative" style="flex-shrink:0">
        <img v-if="user.avatar_url" :src="user.avatar_url" alt="avatar"
             style="width:64px;height:64px;border-radius:50%;object-fit:cover" />
        <div v-else class="fx-avatar" style="width:64px;height:64px;font-size:22px">{{ initials }}</div>
        <button class="btn btn-primary rounded-circle"
                style="position:absolute;bottom:-4px;right:-4px;width:26px;height:26px;padding:0"
                :disabled="uploadingAvatar" @click="pickAvatar" title="Change photo">
          <AppIcon name="tool" :size="12" />
        </button>
        <input ref="fileInput" type="file" accept="image/*" class="d-none" @change="onAvatarSelected" />
      </div>
      <div>
        <div class="fw-bold" style="font-size:18px">{{ user.name || 'Unknown user' }}</div>
        <span class="fx-badge bg-accent-soft text-accent" style="font-size:11px">{{ roleLabel }}</span>
        <div v-if="uploadingAvatar" style="font-size:11px;color:var(--fx-muted);margin-top:4px">Uploading photo…</div>
      </div>
    </div>
    <div v-if="avatarError" class="alert alert-danger py-2 mb-3" style="font-size:13px">{{ avatarError }}</div>

    <!-- Account details (name + phone) -->
    <div class="fx-card mb-3">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fx-label m-0">Account details</div>
        <button v-if="!editing" class="btn btn-link" style="font-size:13px" @click="startEdit">Edit</button>
      </div>

      <template v-if="!editing">
        <div class="d-flex flex-column gap-3 mt-1">
          <div>
            <div style="font-size:11px;color:var(--fx-muted)">Name</div>
            <div style="font-size:14px">{{ user.name || '—' }}</div>
          </div>
          <div>
            <div style="font-size:11px;color:var(--fx-muted)">Phone</div>
            <div style="font-size:14px">{{ user.phone || '—' }}</div>
          </div>
        </div>
        <div v-if="profileMsg" class="mt-2" style="font-size:12px;color:var(--fx-success)">{{ profileMsg }}</div>
      </template>

      <template v-else>
        <div class="d-flex flex-column gap-2">
          <div>
            <label class="fx-label">Name</label>
            <input v-model="form.name" class="fx-input" placeholder="Your name" />
          </div>
          <div>
            <label class="fx-label">Phone</label>
            <input v-model="form.phone" class="fx-input" placeholder="+60 12-345 6789" />
          </div>
        </div>
        <div v-if="profileError" class="alert alert-danger py-2 mt-2 mb-0" style="font-size:13px">{{ profileError }}</div>
        <div class="d-flex gap-2 mt-3">
          <button class="btn btn-primary flex-fill" :disabled="savingProfile" @click="saveProfile">
            {{ savingProfile ? 'Saving…' : 'Save' }}
          </button>
          <button class="btn btn-outline-secondary" :disabled="savingProfile" @click="editing = false">Cancel</button>
        </div>
      </template>
    </div>

    <!-- Email (managed on its own OTP-verified page) -->
    <button class="fx-card mb-3 w-100 d-flex align-items-center gap-3"
            style="border:none;text-align:left;cursor:pointer"
            @click="router.push({ name: 'account-email' })">
      <AppIcon name="send" :size="20" />
      <div class="flex-fill">
        <div class="fw-semibold" style="font-size:14px">Email</div>
        <div style="font-size:13px;color:var(--fx-muted)">{{ user.email }}</div>
      </div>
      <span style="color:var(--fx-muted-soft);font-size:18px">›</span>
    </button>

    <!-- Payment methods (own page) -->
    <button class="fx-card mb-3 w-100 d-flex align-items-center gap-3"
            style="border:none;text-align:left;cursor:pointer"
            @click="router.push({ name: 'account-billing' })">
      <AppIcon name="shield" :size="20" />
      <div class="flex-fill">
        <div class="fw-semibold" style="font-size:14px">Payment methods</div>
        <div style="font-size:13px;color:var(--fx-muted)">Manage your saved card</div>
      </div>
      <span style="color:var(--fx-muted-soft);font-size:18px">›</span>
    </button>

    <button class="btn btn-outline-danger w-100" @click="logout">
      <AppIcon name="logout" :size="18" />
      <span class="ms-2">Log out</span>
    </button>

    <p class="mt-3 mb-0 text-center" style="font-size:11px;color:var(--fx-muted)">
      <router-link :to="{ name: 'legal-terms' }" class="text-accent text-decoration-none">Terms</router-link>
      ·
      <router-link :to="{ name: 'legal-privacy' }" class="text-accent text-decoration-none">Privacy</router-link>
    </p>
  </div>
</template>
