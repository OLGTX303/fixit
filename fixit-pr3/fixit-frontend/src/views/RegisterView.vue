<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as api from '../services/api'
import SliderPuzzleCaptcha from '../components/SliderPuzzleCaptcha.vue'
import { LEGAL_VERSION } from '../content/legal.js'

const auth   = useAuthStore()
const router = useRouter()

const form = ref({ name: '', email: '', password: '', role: 'customer' })
const acceptedTerms   = ref(false)
const acceptedPrivacy = ref(false)
const captchaProof    = ref(null)
const captchaRef      = ref(null)
const showCaptcha     = ref(false)
const landing = { customer: 'home', provider: 'pro-profile', admin: 'admin-verify' }

// Email OTP — verify the email belongs to the registrant before creating the account.
const emailOtp   = ref('')
const otpSent    = ref(false)
const sendingOtp = ref(false)
const otpMsg     = ref('')

// Step 1: clicking "Send code" opens the slider captcha — verifying a human
// BEFORE we send any email, so bots can't abuse the OTP endpoint.
function sendCode() {
  if (!form.value.email.trim()) { otpMsg.value = 'Enter your email address first'; return }
  captchaProof.value = null
  otpMsg.value = ''
  showCaptcha.value = true
  setTimeout(() => captchaRef.value?.reload?.(), 0)
}
function closeCaptcha() { showCaptcha.value = false }

// Captcha solved → send the OTP, passing the one-time pass token.
async function onCaptchaVerified(proof) {
  captchaProof.value = proof
  showCaptcha.value = false
  sendingOtp.value = true
  otpMsg.value = ''
  try {
    const email = form.value.email.trim()
    await api.requestRegisterOtp({
      email,
      captcha_id: proof.captcha_id,
      captcha_pass_token: proof.captcha_pass_token,
    })
    otpSent.value = true
    otpMsg.value = `Code sent to ${email}. Check your inbox.`
  } catch (e) {
    otpMsg.value = e.message || 'Could not send code'
  } finally {
    sendingOtp.value = false
  }
}

// Step 2: final submit just registers — the OTP already proves human + email.
async function submit() {
  if (!acceptedTerms.value || !acceptedPrivacy.value || !emailOtp.value) return
  try {
    const user = await auth.register({
      name:                 form.value.name,
      email:                form.value.email,
      password:             form.value.password,
      role:                 form.value.role,
      accepted_terms:       true,
      accepted_privacy:     true,
      legal_policy_version: LEGAL_VERSION,
      email_otp:            emailOtp.value.trim(),
    })
    router.push({ name: landing[user.role] || 'home' })
  } catch { /* auth.error shown in the form */ }
}
</script>

<template>
  <div class="lg-reg-wrap">
    <!-- Header bar -->
    <header class="lg-topbar">
      <span class="lg-topbar-brand">FixIt</span>
      <router-link :to="{ name: 'login' }" class="lg-topbar-link">Log in</router-link>
    </header>

    <main class="lg-reg-card liquid-glass" style="border-radius:32px">
      <div style="text-align:center;margin-bottom:20px">
        <h1 style="font-size:28px;font-weight:700;letter-spacing:-0.02em;margin:0 0 6px">Create account</h1>
        <p style="font-size:13px;color:var(--fx-muted);margin:0">Join the community of expert fixers and local owners.</p>
      </div>

      <!-- Role selector — glass pill toggle -->
      <div class="lg-role-toggle glass-input" style="display:flex;gap:6px;padding:5px;border-radius:999px;margin-bottom:20px">
        <button v-for="r in ['customer','provider']" :key="r"
                :class="['lg-role-btn', form.role === r ? 'lg-role-btn--active' : '']"
                type="button" @click="form.role = r">
          {{ r.charAt(0).toUpperCase() + r.slice(1) }}
        </button>
      </div>

      <div v-if="auth.error" class="alert alert-danger" style="font-size:12px;padding:8px 12px;margin-bottom:12px">{{ auth.error }}</div>

      <form @submit.prevent="submit" style="display:flex;flex-direction:column;gap:14px">
        <!-- Full name -->
        <div>
          <div class="fx-label-caps" style="margin-bottom:6px">Full Name</div>
          <div class="lg-input-wrap glass-input">
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-muted)">person</span>
            <input v-model="form.name" placeholder="John Doe" required type="text" />
          </div>
        </div>
        <!-- Email -->
        <div>
          <div class="fx-label-caps" style="margin-bottom:6px">Email Address</div>
          <div style="display:flex;gap:8px;align-items:stretch">
            <div class="lg-input-wrap glass-input" style="flex:1">
              <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-muted)">mail</span>
              <input v-model="form.email" placeholder="john@example.com" required type="email" autocomplete="email" />
            </div>
            <button type="button" class="lg-otp-send" :disabled="sendingOtp || !form.email" @click="sendCode">
              {{ sendingOtp ? 'Sending…' : (otpSent ? 'Resend' : 'Send code') }}
            </button>
          </div>
        </div>

        <!-- Email verification code -->
        <div v-if="otpSent">
          <div class="fx-label-caps" style="margin-bottom:6px">Verification Code</div>
          <div class="lg-input-wrap glass-input">
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-muted)">verified</span>
            <input v-model="emailOtp" placeholder="6-digit code" required inputmode="numeric"
                   maxlength="6" autocomplete="one-time-code" style="letter-spacing:4px" />
          </div>
        </div>
        <div v-if="otpMsg" style="font-size:12px;color:var(--fx-muted);margin-top:-6px">{{ otpMsg }}</div>
        <!-- Password -->
        <div>
          <div class="fx-label-caps" style="margin-bottom:6px">Password</div>
          <div class="lg-input-wrap glass-input">
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-muted)">lock</span>
            <input v-model="form.password" placeholder="••••••••" required type="password"
                   minlength="8" autocomplete="new-password" />
          </div>
        </div>

        <!-- Terms -->
        <div style="display:flex;align-items:flex-start;gap:10px;margin-top:2px">
          <div style="display:flex;flex-direction:column;gap:8px;flex:1">
            <label style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--fx-muted);cursor:pointer">
              <input v-model="acceptedTerms" type="checkbox" required
                     style="width:17px;height:17px;accent-color:var(--fx-accent);flex-shrink:0" />
              I agree to the
              <router-link :to="{ name: 'legal-terms' }" target="_blank"
                style="color:var(--fx-accent);text-decoration:none;font-weight:700">Terms</router-link>
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;color:var(--fx-muted);cursor:pointer">
              <input v-model="acceptedPrivacy" type="checkbox" required
                     style="width:17px;height:17px;accent-color:var(--fx-accent);flex-shrink:0" />
              I agree to the
              <router-link :to="{ name: 'legal-privacy' }" target="_blank"
                style="color:var(--fx-accent);text-decoration:none;font-weight:700">Privacy Policy</router-link>
            </label>
          </div>
        </div>

        <button class="btn btn-primary w-100 lg-submit" type="submit"
                :disabled="auth.loading || !acceptedTerms || !acceptedPrivacy || !emailOtp">
          {{ auth.loading ? 'Creating…' : 'Create Account' }}
        </button>
      </form>

      <div style="text-align:center;margin-top:16px;font-size:13px;color:var(--fx-muted)">
        Already have an account?
        <router-link :to="{ name: 'login' }"
          style="color:var(--fx-accent);font-weight:700;text-decoration:none;margin-left:3px">Sign in</router-link>
      </div>
    </main>
  </div>

  <!-- CAPTCHA Modal — liquid-glass-high overlay -->
  <Teleport to="body">
    <div v-if="showCaptcha" class="lg-captcha-backdrop" @click.self="closeCaptcha">
      <div class="lg-captcha-modal liquid-glass-high" style="border-radius:28px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
          <div>
            <div style="font-size:18px;font-weight:600;letter-spacing:-0.01em">Security Check</div>
            <div style="font-size:13px;color:var(--fx-muted);margin-top:2px">Slide to complete the image</div>
          </div>
          <button class="glass-btn lg-close-btn" @click="closeCaptcha">
            <span class="material-symbols-outlined" style="font-size:20px">close</span>
          </button>
        </div>
        <SliderPuzzleCaptcha ref="captchaRef" @verified="onCaptchaVerified" />
        <div v-if="auth.loading" style="text-align:center;margin-top:10px;font-size:13px;color:var(--fx-muted)">
          Creating your account…
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.lg-reg-wrap {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 16px 24px;
}
.lg-topbar {
  position: fixed;
  top: 0; left: 0; right: 0;
  height: 60px;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 24px;
  background: rgba(255,255,255,0.40);
  backdrop-filter: blur(30px);
  -webkit-backdrop-filter: blur(30px);
  border-bottom: 1px solid rgba(255,255,255,0.35);
  z-index: 50;
}
.lg-topbar-brand { font-size:20px; font-weight:800; letter-spacing:-0.02em; color:#af3100; }
.lg-topbar-link  { font-size:13px; font-weight:700; color:var(--fx-muted); text-decoration:none; }
.lg-topbar-link:hover { color:var(--fx-accent); }
.lg-reg-card {
  width: 100%;
  max-width: 460px;
  padding: 32px 28px;
  display: flex;
  flex-direction: column;
}
.lg-role-toggle { align-items: center; }
.lg-role-btn {
  flex: 1; padding: 8px 0; border-radius: 999px;
  font-size: 13px; font-weight: 600;
  background: transparent; border: none; cursor: pointer;
  color: var(--fx-muted); font-family: inherit;
  transition: all 0.2s ease;
}
.lg-role-btn--active {
  background: #fff;
  color: var(--fx-accent);
  box-shadow: 0 2px 6px rgba(0,0,0,0.10);
}
.lg-input-wrap {
  display: flex; align-items: center; gap: 10px;
  padding: 0 14px; height: 50px; width: 100%;
}
.lg-input-wrap input {
  border: none; background: transparent; outline: none;
  flex: 1; font-size: 14px; color: var(--fx-text); font-family: inherit;
}
.lg-input-wrap input::placeholder { color: rgba(94,65,58,0.40); }
.lg-submit { height: 52px; font-size: 15px; margin-top: 6px; }
.lg-otp-send {
  flex-shrink: 0; padding: 0 14px; border-radius: 14px;
  border: 1px solid var(--fx-accent); background: var(--fx-accent-soft);
  color: var(--fx-accent); font-size: 13px; font-weight: 700; cursor: pointer;
  font-family: inherit; white-space: nowrap;
}
.lg-otp-send:disabled { opacity: 0.5; cursor: not-allowed; }
.lg-captcha-backdrop {
  position: fixed; inset: 0; z-index: 2000;
  background: rgba(17,24,39,0.30);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.lg-captcha-modal {
  width: 100%; max-width: 380px;
  padding: 24px;
}
.lg-close-btn {
  width: 34px; height: 34px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; color: var(--fx-muted);
}
</style>
