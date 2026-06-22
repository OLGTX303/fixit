<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as api from '../services/api'

const router = useRouter()
const auth   = useAuthStore()

const editName  = ref(auth.user?.name || '')
const editPhone = ref(auth.user?.phone || '')
const saving    = ref(false)
const saveMsg   = ref('')

async function save() {
  saving.value = true; saveMsg.value = ''
  try {
    const { user } = await api.updateProfile({ name: editName.value, phone: editPhone.value })
    auth.setUser(user)
    saveMsg.value = 'Saved'
    setTimeout(() => { saveMsg.value = ''; router.back() }, 800)
  } catch (e) { saveMsg.value = e.message }
  finally { saving.value = false }
}
</script>

<template>
  <div class="piv-root">
    <div class="piv-header">
      <button class="piv-back" @click="router.back()">
        <span class="material-symbols-outlined">arrow_back_ios</span>
      </button>
      <span class="piv-title">Personal Info</span>
      <div style="width:36px"></div>
    </div>

    <div class="piv-section-label">Profile</div>
    <div class="piv-group">
      <div class="piv-row-field">
        <span class="piv-field-lbl">Name</span>
        <input class="piv-field-input" v-model="editName" placeholder="Your name" />
      </div>
      <div class="piv-sep"></div>
      <div class="piv-row-field">
        <span class="piv-field-lbl">Phone</span>
        <input class="piv-field-input" v-model="editPhone" placeholder="+60 12-345 6789" type="tel" />
      </div>
      <div class="piv-sep"></div>
      <div class="piv-row-field" style="cursor:default">
        <span class="piv-field-lbl">Email</span>
        <span class="piv-field-val">{{ auth.user?.email }}</span>
      </div>
    </div>

    <button class="piv-save-btn" :disabled="saving" @click="save">
      {{ saving ? 'Saving…' : saveMsg || 'Save Changes' }}
    </button>
  </div>
</template>

<style scoped>
.piv-root { background: var(--fx-bg); min-height: 100vh; }
.piv-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 56px 16px 16px;
  position: sticky; top: 0; z-index: 10;
  background: rgba(255,255,255,0.60);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-bottom: 0.5px solid rgba(255,255,255,0.55);
}
.piv-back  { background: none; border: none; cursor: pointer; display: flex; align-items: center; color: var(--fx-text); padding: 4px; }
.piv-back .material-symbols-outlined { font-size: 20px; }
.piv-title { font-size: 17px; font-weight: 700; color: var(--fx-text); }

.piv-section-label {
  font-size: 12px; font-weight: 600; color: var(--fx-muted);
  padding: 18px 28px 6px; letter-spacing: 0.04em; text-transform: uppercase;
}
.piv-group {
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
.piv-row-field {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 16px;
}
.piv-field-lbl   { font-size: 15px; color: var(--fx-text); font-weight: 500; min-width: 80px; }
.piv-field-input {
  flex: 1; border: none; background: transparent; text-align: right;
  font-size: 15px; color: var(--fx-text); outline: none; font-family: inherit;
}
.piv-field-val   { font-size: 15px; color: var(--fx-muted); }
.piv-sep         { height: 0.5px; background: rgba(255,255,255,0.45); margin: 0 16px; }

.piv-save-btn {
  display: block; width: calc(100% - 32px); margin: 14px 16px;
  padding: 14px; border-radius: 14px; border: none;
  background: #FF6635; color: #fff;
  font-size: 15px; font-weight: 700; cursor: pointer;
}
.piv-save-btn:disabled { opacity: 0.7; }
</style>
