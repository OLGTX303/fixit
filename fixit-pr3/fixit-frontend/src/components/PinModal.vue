<script setup>
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { useChatCryptoStore } from '../stores/chatCrypto'
import { useModalGuard } from '../composables/useModalGuard'

useModalGuard()

const props = defineProps({ mode: { type: String, required: true } }) // setup | unlock
const emit  = defineEmits(['done'])
const crypto = useChatCryptoStore()

const view = ref(props.mode)  // 'setup' | 'unlock' | 'reset'

// Individual digit refs (4 boxes each)
const newDigits  = ref(['','','',''])
const cfmDigits  = ref(['','','',''])
const newRefs    = ref([])
const cfmRefs    = ref([])

const localError = ref('')
const isSetupLike = computed(() => view.value === 'setup' || view.value === 'reset')
const pin         = computed(() => newDigits.value.join(''))
const confirm     = computed(() => cfmDigits.value.join(''))

const title = computed(() => ({
  setup:  'Set chat PIN',
  unlock: 'Unlock encrypted chat',
  reset:  'Reset chat PIN',
}[view.value]))

const error = computed(() => localError.value || crypto.error)

watch(view, () => {
  newDigits.value = ['','','','']
  cfmDigits.value = ['','','','']
  localError.value = ''
  crypto.error = null
  nextTick(() => newRefs.value[0]?.focus())
})

onMounted(() => nextTick(() => newRefs.value[0]?.focus()))

function onDigit(arr, index, e) {
  const val = e.target.value.replace(/\D/g,'').slice(-1)
  arr[index] = val
  if (val && index < 3) {
    const siblings = arr === newDigits.value ? newRefs.value : cfmRefs.value
    siblings[index + 1]?.focus()
  }
  localError.value = ''
  crypto.error = null
}

function onBackspace(arr, index) {
  if (!arr[index] && index > 0) {
    arr[index - 1] = ''
    const siblings = arr === newDigits.value ? newRefs.value : cfmRefs.value
    siblings[index - 1]?.focus()
  }
}

// When last new-PIN box filled, move to confirm automatically
function onNewLast(e) {
  onDigit(newDigits.value, 3, e)
  if (newDigits.value[3] && isSetupLike.value) {
    nextTick(() => cfmRefs.value[0]?.focus())
  }
}

async function submit() {
  localError.value = ''
  crypto.error = null
  if (pin.value.length < 4) { localError.value = 'PIN must be 4 digits'; return }
  if (isSetupLike.value && pin.value !== confirm.value) { localError.value = 'PINs do not match'; return }
  try {
    if (isSetupLike.value) {
      await crypto.setupPin(pin.value)
    } else {
      await crypto.unlockWithPin(pin.value)
    }
    emit('done')
  } catch { /* crypto.error shown */ }
}
</script>

<template>
  <div class="pm-backdrop">
    <div class="pm-card liquid-glass-high" style="border-radius:24px">
      <!-- Shield icon -->
      <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:18px">
        <div class="pm-shield-wrap">
          <span class="material-symbols-outlined"
                style="font-size:36px;color:var(--fx-accent);font-variation-settings:'FILL' 1">
            {{ view === 'unlock' ? 'lock_open' : 'shield' }}
          </span>
        </div>
        <h2 style="font-size:20px;font-weight:600;margin:14px 0 4px;letter-spacing:-0.01em">{{ title }}</h2>
        <p style="font-size:13px;color:var(--fx-muted);margin:0;text-align:center;line-height:1.5">
          <template v-if="view === 'setup'">Create a 4-digit PIN to encrypt your chats on this device.</template>
          <template v-else-if="view === 'unlock'">Enter your chat PIN to decrypt end-to-end encrypted messages.</template>
          <template v-else>⚠️ Creates a new PIN &amp; fresh keys. Previous encrypted messages will be unreadable.</template>
        </p>
      </div>

      <!-- Error -->
      <div v-if="error" class="alert alert-danger" style="font-size:12px;padding:8px 12px;margin-bottom:14px">{{ error }}</div>

      <!-- New PIN boxes -->
      <div style="margin-bottom:16px">
        <div class="fx-label-caps" style="margin-bottom:8px;text-align:left">
          {{ isSetupLike ? 'New PIN' : 'Your PIN' }}
        </div>
        <div style="display:flex;justify-content:space-between;gap:8px">
          <input v-for="(_, i) in newDigits" :key="'n'+i"
                 :ref="el => newRefs[i] = el"
                 class="pm-digit glass-input"
                 type="password" inputmode="numeric" maxlength="1"
                 :value="newDigits[i]"
                 @input="i === 3 ? onNewLast($event) : onDigit(newDigits, i, $event)"
                 @keydown.backspace="onBackspace(newDigits, i)"
                 @keydown.enter="!isSetupLike && submit()"
                 placeholder="•" />
        </div>
      </div>

      <!-- Confirm PIN boxes (setup/reset only) -->
      <div v-if="isSetupLike" style="margin-bottom:20px">
        <div class="fx-label-caps" style="margin-bottom:8px;text-align:left">Confirm PIN</div>
        <div style="display:flex;justify-content:space-between;gap:8px">
          <input v-for="(_, i) in cfmDigits" :key="'c'+i"
                 :ref="el => cfmRefs[i] = el"
                 class="pm-digit glass-input"
                 type="password" inputmode="numeric" maxlength="1"
                 :value="cfmDigits[i]"
                 @input="onDigit(cfmDigits, i, $event)"
                 @keydown.backspace="onBackspace(cfmDigits, i)"
                 @keydown.enter="submit()"
                 placeholder="•" />
        </div>
      </div>

      <!-- Security note (setup) -->
      <div v-if="view === 'setup'" class="pm-security-note">
        <span class="material-symbols-outlined" style="font-size:18px;color:var(--fx-accent);flex-shrink:0">lock</span>
        <span style="font-size:12px;color:var(--fx-muted);line-height:1.5">
          Your chats are end-to-end encrypted. This PIN protects your private key on this device.
        </span>
      </div>

      <!-- Action button -->
      <button class="btn btn-primary w-100"
              style="height:52px;font-size:15px;margin-top:4px"
              :disabled="crypto.loading" @click="submit">
        {{ crypto.loading ? 'Please wait…'
           : view === 'unlock' ? 'Unlock'
           : view === 'reset'  ? 'Reset & Continue'
           : 'Secure My Chat' }}
      </button>

      <!-- Forgot / Back links -->
      <div v-if="view === 'unlock'" style="text-align:center;margin-top:14px">
        <button class="btn btn-link" style="font-size:13px" @click="view = 'reset'">
          Forgot PIN? Reset chat security
        </button>
      </div>
      <div v-else-if="view === 'reset'" style="text-align:center;margin-top:10px">
        <button class="btn btn-link" style="font-size:13px" @click="view = 'unlock'">
          Back to unlock
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pm-backdrop {
  position: fixed; inset: 0; z-index: 2000;
  background: rgba(17,24,39,0.30);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.pm-card {
  width: 100%; max-width: 360px;
  padding: 28px 24px;
  display: flex; flex-direction: column;
  animation: pm-in 0.25s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes pm-in {
  from { transform: scale(0.92) translateY(12px); opacity: 0; }
  to   { transform: scale(1)    translateY(0);    opacity: 1; }
}
.pm-shield-wrap {
  width: 64px; height: 64px; border-radius: 50%;
  background: rgba(255,102,53,0.10);
  display: flex; align-items: center; justify-content: center;
  animation: pm-float 5s ease-in-out infinite;
}
@keyframes pm-float {
  0%,100% { transform: translateY(0); }
  50%      { transform: translateY(-7px); }
}
.pm-digit {
  width: 0; flex: 1;
  height: 62px;
  text-align: center;
  font-size: 22px;
  font-weight: 700;
  color: var(--fx-accent);
  letter-spacing: 0;
  padding: 0;
  border-radius: 14px;
}
.pm-digit::placeholder {
  color: rgba(142,112,104,0.30);
  font-size: 26px;
  letter-spacing: 0;
}
.pm-security-note {
  display: flex; align-items: flex-start; gap: 10px;
  background: rgba(255,102,53,0.06);
  border: 1px solid rgba(255,102,53,0.12);
  border-radius: 12px; padding: 12px 14px;
  margin-bottom: 16px;
}
</style>
