<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth   = useAuthStore()
const router = useRouter()

const email    = ref('alex@email.com')
const password = ref('password123')

const DEMOS = [
  { label: 'Customer', icon: 'person',              email: 'alex@email.com',    role: 'customer' },
  { label: 'Provider', icon: 'engineering',         email: 'marcus@email.com',  role: 'provider' },
  { label: 'Admin',    icon: 'admin_panel_settings',email: 'admin@fixit.com',   role: 'admin' },
]
const landing = { customer: 'home', provider: 'pro-requests', admin: 'admin-verify' }

function fillDemo(d) { email.value = d.email; password.value = 'password123' }

async function submit() {
  try {
    const user = await auth.login(email.value, password.value)
    router.push({ name: landing[user.role] })
  } catch { /* error surfaced via auth.error */ }
}
</script>

<template>
  <div class="lg-login-wrap">
    <main class="lg-login-card liquid-glass" style="border-radius:32px">
      <!-- Logo -->
      <div class="lg-logo-section">
        <div class="lg-logo-icon">
          <span class="material-symbols-outlined"
                style="font-size:38px;color:#FF6635;font-variation-settings:'FILL' 1">build</span>
        </div>
        <h1 class="lg-brand">FixIt</h1>
      </div>

      <!-- Heading -->
      <div style="text-align:center;margin-bottom:28px">
        <h2 style="font-size:20px;font-weight:600;letter-spacing:-0.01em;margin:0 0 4px">Sign in</h2>
        <p style="font-size:13px;color:var(--fx-muted);margin:0">Manage your home repairs with ease.</p>
      </div>

      <!-- Form -->
      <form @submit.prevent="submit" style="display:flex;flex-direction:column;gap:14px">
        <div>
          <div class="fx-label-caps" style="margin-bottom:6px">Email Address</div>
          <input class="glass-input lg-field" type="email" v-model="email"
                 placeholder="hello@example.com" autocomplete="email" required />
        </div>
        <div>
          <div class="d-flex justify-content-between align-items-center" style="margin-bottom:6px">
            <span class="fx-label-caps">Password</span>
            <a href="#" style="font-size:11px;font-weight:700;color:var(--fx-accent);text-decoration:none;letter-spacing:0.05em">FORGOT?</a>
          </div>
          <input class="glass-input lg-field" type="password" v-model="password"
                 placeholder="••••••••" autocomplete="current-password" required />
        </div>

        <div v-if="auth.error" class="alert alert-danger" style="font-size:12px;padding:8px 12px">{{ auth.error }}</div>

        <button class="btn btn-primary w-100 lg-submit" type="submit" :disabled="auth.loading">
          {{ auth.loading ? 'Signing in…' : 'Sign In' }}
        </button>
      </form>

      <div style="text-align:center;margin-top:16px;font-size:13px;color:var(--fx-muted)">
        Don't have an account?
        <router-link :to="{ name: 'register' }"
          style="color:var(--fx-accent);font-weight:700;text-decoration:none;margin-left:3px">Register</router-link>
      </div>

      <!-- Demo access -->
      <div class="lg-divider">
        <div class="lg-divider-line"></div>
        <span class="fx-label-caps" style="padding:0 14px;white-space:nowrap;color:var(--fx-muted)">Demo Access</span>
        <div class="lg-divider-line"></div>
      </div>

      <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center">
        <button v-for="d in DEMOS" :key="d.role"
                class="lg-role-chip glass-btn" @click="fillDemo(d)">
          <span class="material-symbols-outlined" style="font-size:17px">{{ d.icon }}</span>
          {{ d.label }}
        </button>
      </div>
    </main>
  </div>
</template>

<style scoped>
.lg-login-wrap {
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px 16px 100px;
}
.lg-login-card {
  width: 100%;
  max-width: 420px;
  padding: 32px 28px;
  display: flex;
  flex-direction: column;
}
.lg-logo-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 24px;
}
.lg-logo-icon {
  width: 76px; height: 76px;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(255,255,255,0.65), rgba(255,255,255,0.20));
  backdrop-filter: blur(20px);
  border: 1px solid rgba(255,255,255,0.80);
  box-shadow: inset 0 1px 2px rgba(255,255,255,1), 0 4px 16px rgba(255,102,53,0.12);
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 10px;
}
.lg-brand {
  font-size: 32px; font-weight: 800; letter-spacing: -0.02em;
  color: #af3100; margin: 0;
}
.lg-field {
  width: 100%; height: 50px;
  padding: 0 16px;
  font-size: 14px;
  font-family: inherit;
}
.lg-submit {
  height: 54px;
  font-size: 16px;
  font-weight: 600;
  margin-top: 4px;
}
.lg-divider {
  display: flex; align-items: center;
  margin: 24px 0 16px;
}
.lg-divider-line {
  flex: 1; height: 1px;
  background: rgba(255,255,255,0.35);
}
.lg-role-chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 16px; border-radius: 999px;
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
  color: var(--fx-text);
  transition: all 0.2s ease;
}
.lg-role-chip:hover { transform: translateY(-2px); }
</style>
