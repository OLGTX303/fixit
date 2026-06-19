<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppIcon from '../components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

const form = ref({ name: '', email: '', password: '', role: 'customer' })
const landing = { customer: 'home', provider: 'pro-profile', admin: 'admin-verify' }

async function submit() {
  const user = await auth.register({
    name: form.value.name,
    email: form.value.email,
    password: form.value.password,
    role: form.value.role,
  })
  router.push({ name: landing[user.role] || 'home' })
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

      <button class="btn btn-primary w-100" type="submit" :disabled="auth.loading">
        {{ auth.loading ? 'Creating…' : 'Create Account' }}
      </button>
    </form>

    <div class="text-center mt-3" style="font-size:13px;color:var(--fx-muted)">
      Already have an account?
      <router-link :to="{ name: 'login' }" class="text-accent fw-semibold text-decoration-none">Sign in</router-link>
    </div>
  </div>
</template>