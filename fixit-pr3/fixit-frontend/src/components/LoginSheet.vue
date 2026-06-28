<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import { loginSheetOpen, closeLoginSheet } from '../composables/useLoginPrompt'
import fixitLogo from '../assets/fixit-logo.svg'

// Desktop bottom-sheet login. On success we reload so all stores re-init
// cleanly with the authenticated session.
const auth = useAuthStore()
const email = ref('alex@email.com')
const password = ref('password123')

const DEMOS = [
  { label: 'Customer', email: 'alex@email.com' },
  { label: 'Provider', email: 'marcus@email.com' },
  { label: 'Admin', email: 'admin@fixit.com' },
]

async function submit() {
  try {
    await auth.login(email.value, password.value)
    closeLoginSheet()
    window.location.assign('/home')
  } catch { /* surfaced via auth.error */ }
}
</script>

<template>
  <Teleport to="body">
    <Transition name="ls-fade">
      <div v-if="loginSheetOpen" class="ls-backdrop" @click.self="closeLoginSheet">
        <Transition name="ls-slide" appear>
          <div class="ls-sheet">
            <div class="ls-handle"></div>
            <button class="ls-close" aria-label="Close" @click="closeLoginSheet">×</button>
            <img :src="fixitLogo" alt="FixIt" class="ls-logo" />
            <h2 class="ls-title">Sign in to continue</h2>
            <p class="ls-sub">Log in to book services, chat, and manage orders.</p>
            <form @submit.prevent="submit" class="ls-form">
              <input class="glass-input ls-field" type="email" v-model="email" placeholder="Email" autocomplete="email" required />
              <input class="glass-input ls-field" type="password" v-model="password" placeholder="Password" autocomplete="current-password" required />
              <div v-if="auth.error" class="alert alert-danger" style="font-size:12px;padding:8px 12px;margin:0">{{ auth.error }}</div>
              <button class="btn btn-primary ls-submit" :disabled="auth.loading">{{ auth.loading ? 'Signing in…' : 'Sign In' }}</button>
            </form>
            <div class="ls-demos">
              <button v-for="d in DEMOS" :key="d.email" class="ls-chip" @click="email = d.email; password = 'password123'">{{ d.label }}</button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.ls-backdrop {
  position: fixed; inset: 0; z-index: 4000;
  display: flex; align-items: flex-end; justify-content: center;
  background: rgba(0, 0, 0, 0.40); backdrop-filter: blur(4px);
}
.ls-sheet {
  position: relative;
  width: 100%; max-width: 460px;
  background: #fff; color: var(--fx-text);
  border-radius: 24px 24px 0 0;
  padding: 14px 28px calc(28px + env(safe-area-inset-bottom));
  box-shadow: 0 -16px 48px rgba(0, 0, 0, 0.25);
  display: flex; flex-direction: column; align-items: center;
}
.ls-handle { width: 40px; height: 4px; border-radius: 2px; background: var(--fx-border); margin-bottom: 14px; }
.ls-close { position: absolute; top: 12px; right: 16px; border: none; background: none; font-size: 26px; line-height: 1; color: var(--fx-muted); cursor: pointer; }
.ls-logo { width: 72px; height: 72px; object-fit: contain; }
.ls-title { font-size: 19px; font-weight: 700; margin: 8px 0 2px; }
.ls-sub { font-size: 13px; color: var(--fx-muted); margin: 0 0 18px; text-align: center; }
.ls-form { width: 100%; display: flex; flex-direction: column; gap: 12px; }
.ls-field { width: 100%; height: 48px; padding: 0 16px; font-size: 14px; font-family: inherit; }
.ls-submit { height: 52px; font-size: 16px; font-weight: 600; }
.ls-demos { display: flex; gap: 8px; margin-top: 14px; }
.ls-chip {
  padding: 7px 16px; border-radius: 999px; cursor: pointer;
  border: 1px solid var(--fx-border); background: transparent; color: var(--fx-muted);
  font-size: 12px; font-weight: 700;
}
.ls-chip:hover { border-color: var(--fx-accent); color: var(--fx-accent); }

.ls-fade-enter-active, .ls-fade-leave-active { transition: opacity 0.25s ease; }
.ls-fade-enter-from, .ls-fade-leave-to { opacity: 0; }
.ls-slide-enter-active { transition: transform 0.32s cubic-bezier(0.32, 0.72, 0, 1); }
.ls-slide-enter-from { transform: translateY(100%); }
</style>
