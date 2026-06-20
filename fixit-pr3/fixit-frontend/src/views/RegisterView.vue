<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppIcon from '../components/AppIcon.vue'

import SliderPuzzleCaptcha from '../components/SliderPuzzleCaptcha.vue'
import { LEGAL_VERSION } from '../content/legal.js'

const auth = useAuthStore()
const router = useRouter()

const form = ref({ name: '', email: '', password: '', role: 'customer' })
const acceptedTerms = ref(false)
const acceptedPrivacy = ref(false)
const captchaProof = ref(null)
const captchaRef = ref(null)
const showCaptcha = ref(false)
const landing = { customer: 'home', provider: 'pro-profile', admin: 'admin-verify' }

// Submit opens the verification popup; registration runs once the slider passes.
function submit() {
  if (!acceptedTerms.value || !acceptedPrivacy.value) return
  captchaProof.value = null
  showCaptcha.value = true
  // (Re)load a fresh puzzle each time the popup opens.
  setTimeout(() => captchaRef.value?.reload?.(), 0)
}

function closeCaptcha() {
  showCaptcha.value = false
}

async function onCaptchaVerified(proof) {
  captchaProof.value = proof
  await register()
}

async function register() {
  try {
    const user = await auth.register({
      name: form.value.name,
      email: form.value.email,
      password: form.value.password,
      role: form.value.role,
      accepted_terms: true,
      accepted_privacy: true,
      legal_policy_version: LEGAL_VERSION,
      captcha_id: captchaProof.value.captcha_id,
      captcha_pass_token: captchaProof.value.captcha_pass_token,
    })
    showCaptcha.value = false
    router.push({ name: landing[user.role] || 'home' })
  } catch {
    // Registration failed — keep the popup open with a fresh puzzle to retry.
    captchaProof.value = null
    captchaRef.value?.reload?.()
  }
}
</script>

<template>
  <div class="fx-page" style="max-width:420px;padding-top:24px">
    <div class="d-flex align-items-center gap-2 mb-4">
      <div class="d-flex align-items-center justify-content-center"
           style="width:42px;height:42px;border-radius:12px;background:var(--fx-accent);color:#fff">
        <AppIcon name="tool" :size="22" />
      </div>
      <span style="font-size:26px;font-weight:800;letter-spacing:-0.5px">Create account</span>
    </div>

    <div v-if="auth.error" class="alert alert-danger py-2" style="font-size:13px">{{ auth.error }}</div>

    <form @submit.prevent="submit" class="d-flex flex-column gap-3">
      <input class="fx-input" v-model="form.name" placeholder="Full name" required />
      <input class="fx-input" type="email" v-model="form.email" placeholder="Email address" required />
      <input class="fx-input" type="password" v-model="form.password" placeholder="Password (8+ chars, letters & numbers)" required minlength="8" />

      <div>
        <label class="fx-label">I am a…</label>
        <div class="d-flex gap-2">
          <label v-for="r in ['customer','provider']" :key="r" class="fx-chip flex-fill justify-content-center"
                 :class="{ active: form.role === r }">
            <input type="radio" class="d-none" :value="r" v-model="form.role" />
            {{ r }}
          </label>
        </div>
      </div>

      <div class="fx-card" style="padding:14px;background:var(--fx-border-soft);box-shadow:none">
        <label class="form-check mb-2">
          <input v-model="acceptedTerms" type="checkbox" class="form-check-input" required />
          <span class="form-check-label" style="font-size:13px">
            I agree to the
            <router-link :to="{ name: 'legal-terms' }" class="text-accent fw-semibold text-decoration-none" target="_blank">Terms of Service</router-link>
          </span>
        </label>
        <label class="form-check m-0">
          <input v-model="acceptedPrivacy" type="checkbox" class="form-check-input" required />
          <span class="form-check-label" style="font-size:13px">
            I agree to the
            <router-link :to="{ name: 'legal-privacy' }" class="text-accent fw-semibold text-decoration-none" target="_blank">Privacy Policy</router-link>
          </span>
        </label>
      </div>

      <button class="btn btn-primary w-100" type="submit" :disabled="auth.loading || !acceptedTerms || !acceptedPrivacy">
        {{ auth.loading ? 'Creating…' : 'Create Account' }}
      </button>
    </form>

    <div class="text-center mt-3" style="font-size:13px;color:var(--fx-muted)">
      Already have an account?
      <router-link :to="{ name: 'login' }" class="text-accent fw-semibold text-decoration-none">Sign in</router-link>
    </div>

    <!-- Verification popup -->
    <div v-if="showCaptcha" class="fx-modal-backdrop" @click.self="closeCaptcha">
      <div class="fx-modal">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="fw-bold" style="font-size:16px">Verify you're human</span>
          <button class="btn btn-light rounded-circle" style="width:30px;height:30px;padding:0" @click="closeCaptcha">✕</button>
        </div>
        <SliderPuzzleCaptcha ref="captchaRef" @verified="onCaptchaVerified" />
        <div v-if="auth.loading" class="text-center mt-2" style="font-size:13px;color:var(--fx-muted)">Creating your account…</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fx-modal-backdrop {
  position: fixed; inset: 0; z-index: 2000;
  background: rgba(17, 24, 39, 0.55);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.fx-modal {
  background: var(--fx-surface);
  border-radius: 16px;
  padding: 18px;
  width: 100%;
  max-width: 360px;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
}
</style>
