<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppIcon from '../components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()

const email = ref('alex@email.com')
const password = ref('password123')

const landing = { customer: 'home', provider: 'pro-requests', admin: 'admin-verify' }

async function submit() {
  try {
    const user = await auth.login(email.value, password.value)
    router.push({ name: landing[user.role] })
  } catch { /* error surfaced via auth.error */ }
}
async function demo(role) {
  await auth.loginAs(role)
  router.push({ name: landing[role] })
}
</script>

<template>
  <div class="fx-page" style="max-width:420px;padding-top:24px">
    <!-- Logo -->
    <div class="mb-4">
      <div class="d-flex align-items-center gap-2 mb-2">
        <div class="d-flex align-items-center justify-content-center"
             style="width:42px;height:42px;border-radius:12px;background:var(--fx-accent);color:#fff">
          <AppIcon name="tool" :size="22" />
        </div>
        <span style="font-size:26px;font-weight:800;letter-spacing:-0.5px">FixIt</span>
      </div>
      <div style="font-size:14px;color:var(--fx-muted)">Your home, perfectly maintained.</div>
    </div>

    <!-- Tabs -->
    <div class="d-flex mb-4" style="background:var(--fx-border-soft);border-radius:12px;padding:4px">
      <div class="flex-fill text-center" style="padding:9px 0;border-radius:9px;font-weight:600;font-size:14px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.1)">Login</div>
      <router-link :to="{ name: 'register' }" class="flex-fill text-center text-decoration-none"
                   style="padding:9px 0;font-weight:600;font-size:14px;color:var(--fx-muted)">Register</router-link>
    </div>

    <form @submit.prevent="submit">
      <div class="d-flex flex-column gap-3 mb-3">
        <input class="fx-input" type="email" v-model="email" placeholder="Email address" required />
        <input class="fx-input" type="password" v-model="password" placeholder="Password" required />
      </div>

      <div v-if="auth.error" class="alert alert-danger py-2" style="font-size:13px">{{ auth.error }}</div>

      <button class="btn btn-primary w-100 mb-3" type="submit" :disabled="auth.loading">
        {{ auth.loading ? 'Signing in…' : 'Sign In' }}
      </button>
    </form>

    <div class="d-flex align-items-center gap-2 my-3">
      <div class="flex-fill" style="height:1px;background:var(--fx-border)"></div>
      <span style="font-size:13px;color:var(--fx-muted-soft)">or try a demo role</span>
      <div class="flex-fill" style="height:1px;background:var(--fx-border)"></div>
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-outline-primary flex-fill" @click="demo('customer')">Customer</button>
      <button class="btn btn-outline-primary flex-fill" @click="demo('provider')">Provider</button>
      <button class="btn btn-outline-primary flex-fill" @click="demo('admin')">Admin</button>
    </div>
  </div>
</template>
