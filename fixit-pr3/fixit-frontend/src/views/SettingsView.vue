<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { Capacitor } from '@capacitor/core'
import { App } from '@capacitor/app'
import { checkForUpdate } from '../capacitor'

const router    = useRouter()
const auth      = useAuthStore()
const isNative  = computed(() => Capacitor.isNativePlatform())
const appVersion = ref('')
const checkingUpdate = ref(false)

onMounted(async () => {
  if (!isNative.value) return
  try {
    const info = await App.getInfo()
    appVersion.value = info.build ? `${info.version} (build ${info.build})` : info.version
  } catch { /* ignore */ }
})

function logout() { auth.logout(); router.push({ name: 'login' }) }

function clearCache() {
  localStorage.clear()
  router.push({ name: 'login' })
}

async function checkUpdate() {
  checkingUpdate.value = true
  try {
    const result = await checkForUpdate({ force: true })
    if (result.status === 'current') {
      window.alert('You are on the latest version.')
    } else if (result.status === 'installing') {
      window.alert('Update ready — confirm the install when Android prompts you.')
    } else if (result.status === 'none') {
      window.alert('No release is available yet.')
    } else if (result.status === 'error') {
      window.alert('Could not check for updates. Try again when you are online.')
    }
  } finally {
    checkingUpdate.value = false
  }
}
</script>

<template>
  <div class="stv-root fx-view-root">
    <!-- Header -->
    <div class="stv-header">
      <button class="stv-back" @click="router.back()">
        <span class="material-symbols-outlined">arrow_back_ios</span>
      </button>
      <span class="stv-title">Settings</span>
      <div style="width:36px"></div>
    </div>

    <!-- Personal -->
    <div class="stv-section-label">Personal</div>
    <div class="stv-group">
      <button class="stv-row" @click="router.push({ name: 'account-personal' })">
        <div class="stv-row-left">
          <div class="stv-row-icon" style="background:rgba(255,102,53,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#FF6635;font-variation-settings:'FILL' 1">person</span>
          </div>
          <span class="stv-row-lbl">Personal Info</span>
        </div>
        <div class="stv-row-right">
          <span class="stv-row-val">{{ auth.user?.name }}</span>
          <span class="material-symbols-outlined stv-chevron">chevron_right</span>
        </div>
      </button>
    </div>

    <!-- Account Security -->
    <div class="stv-section-label">Account Security</div>
    <div class="stv-group">
      <button class="stv-row" @click="router.push({ name: 'account-email' })">
        <div class="stv-row-left">
          <div class="stv-row-icon" style="background:rgba(14,165,233,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#0ea5e9;font-variation-settings:'FILL' 1">mail</span>
          </div>
          <span class="stv-row-lbl">Email &amp; Password</span>
        </div>
        <div class="stv-row-right">
          <span class="stv-row-val">{{ auth.user?.email }}</span>
          <span class="material-symbols-outlined stv-chevron">chevron_right</span>
        </div>
      </button>
      <div class="stv-sep"></div>
      <button class="stv-row" @click="router.push({ name: 'account-privacy' })">
        <div class="stv-row-left">
          <div class="stv-row-icon" style="background:rgba(124,58,237,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#7c3aed;font-variation-settings:'FILL' 1">lock</span>
          </div>
          <span class="stv-row-lbl">Privacy Settings</span>
        </div>
        <span class="material-symbols-outlined stv-chevron">chevron_right</span>
      </button>
    </div>

    <!-- Payments -->
    <div class="stv-section-label">Payments</div>
    <div class="stv-group">
      <button class="stv-row" @click="router.push({ name: 'account-billing' })">
        <div class="stv-row-left">
          <div class="stv-row-icon" style="background:rgba(22,163,74,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#16a34a;font-variation-settings:'FILL' 1">credit_card</span>
          </div>
          <span class="stv-row-lbl">Payment Methods</span>
        </div>
        <span class="material-symbols-outlined stv-chevron">chevron_right</span>
      </button>
    </div>

    <!-- App -->
    <div class="stv-section-label">App</div>
    <div class="stv-group">
      <button class="stv-row" @click="router.push({ name: 'legal-terms' })">
        <div class="stv-row-left">
          <div class="stv-row-icon" style="background:rgba(100,116,139,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#64748b;font-variation-settings:'FILL' 1">description</span>
          </div>
          <span class="stv-row-lbl">Terms of Service</span>
        </div>
        <span class="material-symbols-outlined stv-chevron">chevron_right</span>
      </button>
      <div class="stv-sep"></div>
      <button class="stv-row" @click="router.push({ name: 'legal-privacy' })">
        <div class="stv-row-left">
          <div class="stv-row-icon" style="background:rgba(100,116,139,0.10)">
            <span class="material-symbols-outlined" style="font-size:18px;color:#64748b;font-variation-settings:'FILL' 1">policy</span>
          </div>
          <span class="stv-row-lbl">Privacy Policy</span>
        </div>
        <span class="material-symbols-outlined stv-chevron">chevron_right</span>
      </button>
      <template v-if="isNative">
        <div class="stv-sep"></div>
        <button class="stv-row" :disabled="checkingUpdate" @click="checkUpdate">
          <div class="stv-row-left">
            <div class="stv-row-icon" style="background:rgba(255,102,53,0.10)">
              <span class="material-symbols-outlined" style="font-size:18px;color:#FF6635;font-variation-settings:'FILL' 1">system_update</span>
            </div>
            <span class="stv-row-lbl">{{ checkingUpdate ? 'Checking…' : 'Check for updates' }}</span>
          </div>
          <span class="material-symbols-outlined stv-chevron">chevron_right</span>
        </button>
        <div class="stv-sep"></div>
        <button class="stv-row" @click="clearCache">
          <div class="stv-row-left">
            <div class="stv-row-icon" style="background:rgba(239,68,68,0.10)">
              <span class="material-symbols-outlined" style="font-size:18px;color:#ef4444;font-variation-settings:'FILL' 1">delete_sweep</span>
            </div>
            <span class="stv-row-lbl">Clear Cache</span>
          </div>
          <span class="material-symbols-outlined stv-chevron">chevron_right</span>
        </button>
      </template>
    </div>

    <!-- Logout -->
    <div class="stv-group stv-logout-group">
      <button class="stv-row stv-logout" @click="logout">
        <span class="material-symbols-outlined" style="font-size:18px;margin-right:8px">logout</span>
        Log Out
      </button>
    </div>

    <p class="stv-version">FixIt {{ appVersion || 'v1.0' }} · © 2026 FixIt Ltd</p>
  </div>
</template>

<style scoped>
.stv-root { min-height: 100vh; padding-bottom: 40px; }

.stv-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 56px 16px 16px;
  position: sticky; top: 0; z-index: 10;
  background: rgba(255,255,255,0.60);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-bottom: 0.5px solid rgba(255,255,255,0.55);
}
.stv-back  { background: none; border: none; cursor: pointer; display: flex; align-items: center; color: var(--fx-text); padding: 4px; }
.stv-back .material-symbols-outlined { font-size: 20px; }
.stv-title { font-size: 17px; font-weight: 700; color: var(--fx-text); }

.stv-section-label {
  font-size: 12px; font-weight: 600; color: var(--fx-muted);
  padding: 18px 28px 6px; letter-spacing: 0.04em; text-transform: uppercase;
}
.stv-group {
  background:
    radial-gradient(ellipse 44% 30% at 16% 7%, rgba(255,255,255,0.28) 0%, transparent 62%),
    linear-gradient(to bottom, rgba(255,255,255,0.22) 0%, transparent 26%),
    rgba(255,255,255,0.06);
  border: 0.5px solid rgba(255,255,255,0.55);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.75), 0 4px 20px rgba(0,0,0,0.05);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-radius: 20px; overflow: hidden;
  margin: 0 16px 4px;
}
.stv-row {
  width: 100%; display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px; border: none; background: transparent; cursor: pointer;
}
.stv-row-left  { display: flex; align-items: center; gap: 12px; flex: 1; }
.stv-row-right { display: flex; align-items: center; gap: 4px; }
.stv-row-icon  {
  width: 34px; height: 34px; border-radius: 10px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.stv-row-lbl   { font-size: 15px; color: var(--fx-text); font-weight: 500; text-align: left; }
.stv-row-val   { font-size: 13px; color: var(--fx-muted); }
.stv-chevron   { font-size: 18px; color: var(--fx-muted); }
.stv-sep       { height: 0.5px; background: rgba(255,255,255,0.45); margin: 0 16px; }

.stv-logout-group { margin-top: 12px; }
.stv-logout {
  color: #ef4444; font-size: 15px; font-weight: 600;
  justify-content: center;
}
.stv-version {
  text-align: center; font-size: 11px; color: var(--fx-muted);
  margin: 20px 0 calc(24px + env(safe-area-inset-bottom));
}
</style>
