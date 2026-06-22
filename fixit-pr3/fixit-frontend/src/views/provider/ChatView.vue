<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useAuthStore } from '../../stores/auth'
import { useChatCryptoStore } from '../../stores/chatCrypto'
import * as api from '../../services/api'
import * as harmReview from '../../services/harmReview'
import PinModal from '../../components/PinModal.vue'
import { E2E_ENABLED } from '../../config'

const props = defineProps({
  bookingId: { type: [Number, String], default: null },
  embedded:  { type: Boolean, default: false },
})

const route = useRoute()
const router = useRouter()
const bookingsStore = useBookingsStore()
const auth = useAuthStore()
const chatCrypto = useChatCryptoStore()

const messages = ref([])
const displayBodies = ref({})
const draft = ref('')
const listEl = ref(null)
const harmWarning = ref(null)
const showPinSetup = ref(false)
const showPinUnlock = ref(false)
const ready = ref(false)

const jobId   = computed(() => Number(props.bookingId || route.params.id))
const booking = computed(() => bookingsStore.byId(jobId.value))

// Show the other party: a provider chats with the customer, a customer with the provider.
const other = computed(() => {
  const b = booking.value
  if (!b) return null
  return auth.role === 'provider' ? b.customer : b.provider
})
const otherName = computed(() => other.value?.name || 'Chat')
const otherInitials = computed(() =>
  (other.value?.name || '?').split(' ').map((w) => w[0]).join('').slice(0, 2))

onMounted(async () => {
  await bookingsStore.load()

  // ── Debug: E2E disabled → skip PIN, load/send plain text ──────────────────
  if (!E2E_ENABLED) {
    await initChat()
    return
  }

  // ── E2E enabled → PIN setup / unlock then decrypt ─────────────────────────
  await chatCrypto.loadStatus()
  if (!chatCrypto.pinConfigured) {
    showPinSetup.value = true
    return
  }
  if (!chatCrypto.unlocked) {
    showPinUnlock.value = true
    return
  }
  await initChat()
})

async function onPinReady() {
  showPinSetup.value = false
  showPinUnlock.value = false
  await initChat()
}

async function initChat() {
  try {
    if (E2E_ENABLED) await chatCrypto.ensureJobKey(jobId.value)
    const raw = await api.getMessagesForJob(jobId.value)
    messages.value = raw
    await decryptAll(raw)
    ready.value = true
    scrollToEnd()
  } catch (e) {
    if (E2E_ENABLED) {
      chatCrypto.error = e.message
      showPinUnlock.value = true
    }
  }
}

async function decryptAll(msgs) {
  const bodies = {}
  for (const m of msgs) {
    // With E2E off, just show the stored plain-text body.
    bodies[m.id] = E2E_ENABLED ? await chatCrypto.decryptMessage(m) : (m.body || '')
  }
  displayBodies.value = bodies
}

function scrollToEnd() {
  nextTick(() => { if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight })
}
function isMine(m) { return m.sender_id === auth.user.id }

async function send() {
  const text = draft.value.trim()
  if (!text || !ready.value) return

  const review = harmReview.reviewMessage(text)
  harmWarning.value = review.message
  if (!review.allowed) return

  let payload
  if (E2E_ENABLED) {
    const encrypted = await chatCrypto.encryptForJob(jobId.value, text)
    payload = {
      is_encrypted: true,
      ciphertext: encrypted.ciphertext,
      iv: encrypted.iv,
      content_hash: encrypted.content_hash,
      harm_status: review.status,
      harm_categories: review.categories,
    }
  } else {
    payload = {
      is_encrypted: false,
      body: text,
      harm_status: review.status,
      harm_categories: review.categories,
    }
  }

  const msg = await api.sendMessage(jobId.value, payload)

  messages.value.push(msg)
  displayBodies.value[msg.id] = text
  draft.value = ''
  harmWarning.value = review.status === 'flagged' ? review.message : null
  scrollToEnd()
}

function timeOf(iso) {
  return new Date(iso).toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' })
}
</script>

<template>
  <PinModal v-if="showPinSetup" mode="setup" @done="onPinReady" />
  <PinModal v-else-if="showPinUnlock" mode="unlock" @done="onPinReady" />

  <div class="d-flex flex-column" :style="{ height: embedded ? '100%' : 'calc(100vh - 100px)' }">
    <div class="d-flex align-items-center gap-3 px-3 py-2"
         style="background:rgba(255,255,255,0.40);backdrop-filter:blur(30px);border-bottom:1px solid rgba(255,255,255,0.35)">
      <button v-if="!embedded" class="glass-btn" style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0" @click="router.back()">
        <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-muted)">arrow_back</span>
      </button>
      <div class="fx-avatar" style="width:40px;height:40px;font-size:15px;font-weight:800">
        {{ otherInitials }}
      </div>
      <div class="flex-grow-1">
        <div class="fw-bold" style="font-size:15px">{{ otherName }}</div>
        <div v-if="E2E_ENABLED" style="font-size:12px;color:var(--fx-success);font-weight:500">
          🔒 E2E encrypted · #{{ route.params.id }}
        </div>
        <div v-else style="font-size:12px;color:var(--fx-muted);font-weight:500">
          Job #{{ jobId }}
        </div>
      </div>
      <button v-if="E2E_ENABLED" class="glass-btn" style="border-radius:999px;padding:6px 14px;font-size:12px;font-weight:700" @click="chatCrypto.lock(); showPinUnlock = true">
        Lock
      </button>
    </div>

    <div v-if="!ready" class="flex-grow-1 d-flex align-items-center justify-content-center text-muted" style="font-size:13px">
      {{ E2E_ENABLED ? 'Unlock with PIN to view messages…' : 'Loading messages…' }}
    </div>

    <div v-else ref="listEl" class="flex-grow-1 d-flex flex-column gap-2 px-3 py-3" style="overflow-y:auto">
      <div v-for="m in messages" :key="m.id" class="d-flex"
           :style="{ justifyContent: isMine(m) ? 'flex-end' : 'flex-start' }">
        <div style="max-width:72%">
          <div :style="{
            padding: '10px 14px', borderRadius: '18px', fontSize: '13px', lineHeight: 1.55,
            background: isMine(m)
              ? 'linear-gradient(180deg,#FF7D54,#FF6635)'
              : 'rgba(255,255,255,0.45)',
            backdropFilter: isMine(m) ? 'none' : 'blur(20px)',
            border: isMine(m) ? 'none' : '1px solid rgba(255,255,255,0.55)',
            boxShadow: isMine(m)
              ? 'inset 0 1px 0 rgba(255,255,255,0.30), 0 4px 12px rgba(255,102,53,0.22)'
              : 'inset 0 1px 1px rgba(255,255,255,0.55), 0 2px 8px rgba(0,0,0,0.05)',
            color: isMine(m) ? '#fff' : 'var(--fx-text)',
            borderBottomRightRadius: isMine(m) ? '4px' : '18px',
            borderBottomLeftRadius: isMine(m) ? '18px' : '4px',
          }">{{ displayBodies[m.id] }}</div>
          <div style="font-size:10px;color:var(--fx-muted-soft);margin-top:3px"
               :style="{ textAlign: isMine(m) ? 'right' : 'left' }">
            {{ timeOf(m.sent_at) }}
            <span v-if="m.harm_status === 'flagged'" class="text-warning ms-1">· flagged</span>
          </div>
        </div>
      </div>
    </div>

    <div v-if="harmWarning" class="px-3 py-1" style="font-size:12px;color:var(--fx-warn)">{{ harmWarning }}</div>

    <form v-if="ready" class="d-flex gap-2 align-items-center px-3 py-2"
          style="background:rgba(255,255,255,0.40);backdrop-filter:blur(30px);border-top:1px solid rgba(255,255,255,0.35)"
          @submit.prevent="send">
      <div class="fx-input" style="border-radius:999px;flex:1">
        <input v-model="draft" placeholder="Type a message…" style="width:100%" />
      </div>
      <button type="submit" class="glossy-primary"
              style="width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:0">
        <span class="material-symbols-outlined" style="font-size:20px;font-variation-settings:'FILL' 1">send</span>
      </button>
    </form>
  </div>
</template>
