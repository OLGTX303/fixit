<script setup>
import { ref } from 'vue'
import { useChatCryptoStore } from '../stores/chatCrypto'
import AppIcon from './AppIcon.vue'

const emit = defineEmits(['done'])
const crypto = useChatCryptoStore()
const pin = ref('')
const confirm = ref('')

async function submit() {
  if (pin.value !== confirm.value) {
    crypto.error = 'PINs do not match'
    return
  }
  await crypto.setupPin(pin.value)
  emit('done')
}
</script>

<template>
  <div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center"
       style="background:rgba(0,0,0,0.55);z-index:2000">
    <div class="bg-white p-4 rounded-4 shadow" style="max-width:360px;width:92%">
      <div class="d-flex align-items-center gap-2 mb-3">
        <AppIcon name="tool" :size="20" />
        <span class="fw-bold">Set chat PIN</span>
      </div>
      <p style="font-size:13px;color:var(--fx-muted)">
        Create a PIN to encrypt your messages. On new devices you'll need this PIN to unlock chat.
      </p>
      <div v-if="crypto.error" class="alert alert-danger py-2" style="font-size:12px">{{ crypto.error }}</div>
      <input class="fx-input mb-2" type="password" inputmode="numeric" maxlength="8"
             v-model="pin" placeholder="4–8 digit PIN" />
      <input class="fx-input mb-3" type="password" inputmode="numeric" maxlength="8"
             v-model="confirm" placeholder="Confirm PIN" />
      <button class="btn btn-primary w-100" :disabled="crypto.loading" @click="submit">
        {{ crypto.loading ? 'Saving…' : 'Save PIN' }}
      </button>
    </div>
  </div>
</template>