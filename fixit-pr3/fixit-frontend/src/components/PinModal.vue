<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { useChatCryptoStore } from '../stores/chatCrypto'
import AppIcon from './AppIcon.vue'

const props = defineProps({ mode: { type: String, required: true } }) // setup | unlock
const emit = defineEmits(['done'])
const crypto = useChatCryptoStore()

// local view mode so 'unlock' can switch to 'reset' in place
const view = ref(props.mode) // 'setup' | 'unlock' | 'reset'
const pin = ref('')
const confirm = ref('')
const localError = ref('')
const pinInput = ref(null)

const isSetupLike = computed(() => view.value === 'setup' || view.value === 'reset')
const title = computed(() => ({
  setup: 'Set chat PIN',
  unlock: 'Unlock encrypted chat',
  reset: 'Reset chat PIN',
}[view.value]))

// digits only
function onPin(e) { pin.value = e.target.value.replace(/\D/g, '').slice(0, 8); localError.value = ''; crypto.error = null }
function onConfirm(e) { confirm.value = e.target.value.replace(/\D/g, '').slice(0, 8); localError.value = '' }

const error = computed(() => localError.value || crypto.error)

watch(view, () => { pin.value = ''; confirm.value = ''; localError.value = ''; crypto.error = null; focusPin() })
onMounted(focusPin)
function focusPin() { nextTick(() => pinInput.value?.focus()) }

async function submit() {
  localError.value = ''
  crypto.error = null
  if (pin.value.length < 4) { localError.value = 'PIN must be 4–8 digits'; return }

  if (isSetupLike.value) {
    if (pin.value !== confirm.value) { localError.value = 'PINs do not match'; return }
    try {
      await crypto.setupPin(pin.value) // setup endpoint upserts (also used for reset)
      emit('done')
    } catch { /* crypto.error is shown */ }
    return
  }

  // unlock
  try {
    await crypto.unlockWithPin(pin.value)
    emit('done')
  } catch { /* crypto.error is shown */ }
}
</script>

<template>
  <div class="fx-pin-backdrop">
    <div class="fx-pin-card">
      <div class="d-flex align-items-center gap-2 mb-2">
        <div class="d-flex align-items-center justify-content-center"
             style="width:34px;height:34px;border-radius:10px;background:var(--fx-accent-soft);color:var(--fx-accent)">
          <AppIcon name="shield" :size="18" />
        </div>
        <span class="fw-bold" style="font-size:16px">{{ title }}</span>
      </div>

      <p style="font-size:13px;color:var(--fx-muted);margin:0 0 14px">
        <template v-if="view === 'setup'">Create a numeric PIN (4–8 digits) to encrypt your chats. You'll need it to read messages on a new device.</template>
        <template v-else-if="view === 'unlock'">Enter your chat PIN to decrypt your end-to-end encrypted messages on this device.</template>
        <template v-else>This creates a new PIN and fresh encryption keys. ⚠️ Previous encrypted messages will no longer be readable.</template>
      </p>

      <div v-if="error" class="alert alert-danger py-2 mb-2" style="font-size:12px">{{ error }}</div>

      <input ref="pinInput" class="fx-pin-input mb-2" type="password" inputmode="numeric" autocomplete="off"
             :value="pin" @input="onPin" :placeholder="isSetupLike ? 'New PIN (4–8 digits)' : 'Your PIN'"
             @keyup.enter="!isSetupLike && submit()" />

      <input v-if="isSetupLike" class="fx-pin-input mb-3" type="password" inputmode="numeric" autocomplete="off"
             :value="confirm" @input="onConfirm" placeholder="Confirm PIN" @keyup.enter="submit" />

      <button class="btn btn-primary w-100" :disabled="crypto.loading" @click="submit">
        {{ crypto.loading ? 'Please wait…' : (view === 'unlock' ? 'Unlock' : (view === 'reset' ? 'Reset & continue' : 'Save PIN')) }}
      </button>

      <div v-if="view === 'unlock'" class="text-center mt-3">
        <button class="btn btn-link" style="font-size:13px" @click="view = 'reset'">Forgot PIN? Reset chat security</button>
      </div>
      <div v-else-if="view === 'reset'" class="text-center mt-2">
        <button class="btn btn-link" style="font-size:13px" @click="view = 'unlock'">Back to unlock</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fx-pin-backdrop {
  position: fixed; inset: 0; z-index: 2000;
  background: rgba(17,24,39,0.55);
  display: flex; align-items: center; justify-content: center; padding: 20px;
}
.fx-pin-card {
  background: var(--fx-surface); border-radius: 16px; padding: 20px;
  width: 100%; max-width: 360px; box-shadow: 0 12px 40px rgba(0,0,0,0.25);
}
.fx-pin-input {
  width: 100%; box-sizing: border-box; border: 1.5px solid var(--fx-border);
  border-radius: 12px; padding: 13px 15px; font-size: 20px; letter-spacing: 6px;
  text-align: center; font-family: inherit; color: var(--fx-text); outline: none;
}
.fx-pin-input:focus { border-color: var(--fx-accent); }
.fx-pin-input::placeholder { letter-spacing: normal; font-size: 14px; color: var(--fx-muted-soft); }
</style>
