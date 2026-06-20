<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as api from '../services/api'
import AppIcon from '../components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

const currentEmail = computed(() => auth.user?.email || '')
const step = ref('enter') // 'enter' | 'verify' | 'done'
const newEmail = ref('')
const otp = ref('')
const busy = ref(false)
const error = ref('')
const info = ref('')

async function sendCode() {
  error.value = ''
  const email = newEmail.value.trim().toLowerCase()
  if (!email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
    error.value = 'Enter a valid email address'
    return
  }
  if (email === currentEmail.value) {
    error.value = 'That is already your email'
    return
  }
  busy.value = true
  try {
    await api.requestEmailOtp(email)
    info.value = `We sent a 6-digit code to ${email}.`
    step.value = 'verify'
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

async function verify() {
  error.value = ''
  if (!/^\d{6}$/.test(otp.value.trim())) {
    error.value = 'Enter the 6-digit code'
    return
  }
  busy.value = true
  try {
    const { user } = await api.verifyEmailOtp(newEmail.value.trim().toLowerCase(), otp.value.trim())
    auth.setUser(user)
    step.value = 'done'
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

async function resend() {
  otp.value = ''
  await sendCode()
}
</script>

<template>
  <div class="fx-page" style="max-width:480px">
    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-light rounded-circle" style="width:34px;height:34px;padding:0" @click="router.back()">
        <AppIcon name="back" :size="16" />
      </button>
      <h1 class="fw-bold m-0" style="font-size:20px">Change email</h1>
    </div>

    <div class="fx-card">
      <div class="fx-label">Current email</div>
      <div style="font-size:14px;margin-bottom:14px">{{ currentEmail }}</div>

      <div v-if="error" class="alert alert-danger py-2 mb-2" style="font-size:13px">{{ error }}</div>

      <!-- Step 1: enter new email -->
      <template v-if="step === 'enter'">
        <label class="fx-label">New email address</label>
        <input v-model="newEmail" type="email" class="fx-input mb-3" placeholder="new@email.com" />
        <button class="btn btn-primary w-100" :disabled="busy" @click="sendCode">
          {{ busy ? 'Sending…' : 'Send verification code' }}
        </button>
      </template>

      <!-- Step 2: enter OTP -->
      <template v-else-if="step === 'verify'">
        <div v-if="info" style="font-size:13px;color:var(--fx-muted);margin-bottom:12px">{{ info }}</div>
        <label class="fx-label">6-digit code</label>
        <input v-model="otp" inputmode="numeric" maxlength="6" class="fx-input mb-3"
               placeholder="123456" style="letter-spacing:6px;font-size:18px;text-align:center" />
        <button class="btn btn-primary w-100 mb-2" :disabled="busy" @click="verify">
          {{ busy ? 'Verifying…' : 'Verify & update email' }}
        </button>
        <button class="btn btn-link w-100" style="font-size:13px" :disabled="busy" @click="resend">
          Resend code
        </button>
      </template>

      <!-- Step 3: done -->
      <template v-else>
        <div class="text-center py-3">
          <div class="d-flex align-items-center justify-content-center mb-2"
               style="width:48px;height:48px;border-radius:50%;background:var(--fx-success-soft);color:var(--fx-success);margin:0 auto">
            <AppIcon name="check" :size="24" />
          </div>
          <div class="fw-bold" style="font-size:16px">Email updated</div>
          <div style="font-size:13px;color:var(--fx-muted);margin-top:4px">{{ currentEmail }}</div>
          <button class="btn btn-primary mt-3" @click="router.push({ name: 'account' })">Back to profile</button>
        </div>
      </template>
    </div>
  </div>
</template>
