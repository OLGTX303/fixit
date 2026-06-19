<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useAuthStore } from '../../stores/auth'
import { useChatCryptoStore } from '../../stores/chatCrypto'
import * as api from '../../services/api'
import * as harmReview from '../../services/harmReview'
import * as deviceCrypto from '../../services/crypto'
import PinSetupModal from '../../components/PinSetupModal.vue'
import PinUnlockModal from '../../components/PinUnlockModal.vue'
import AppIcon from '../../components/AppIcon.vue'

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

const booking = computed(() => bookingsStore.byId(route.params.id))
const jobId = computed(() => Number(route.params.id))

onMounted(async () => {
  await bookingsStore.load()
  await chatCrypto.loadStatus()

  if (!chatCrypto.pinConfigured) {
    showPinSetup.value = true
    return
  }
  if (!deviceCrypto.isUnlockedThisSession()) {
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
    await chatCrypto.ensureJobKey(jobId.value)
    const raw = await api.getMessagesForJob(route.params.id)
    messages.value = raw
    await decryptAll(raw)
    ready.value = true
    scrollToEnd()
  } catch (e) {
    chatCrypto.error = e.message
    showPinUnlock.value = true
  }
}

async function decryptAll(msgs) {
  const bodies = {}
  for (const m of msgs) {
    bodies[m.id] = await chatCrypto.decryptMessage(m)
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

  const encrypted = await chatCrypto.encryptForJob(jobId.value, text)
  const msg = await api.sendMessage(jobId.value, {
    is_encrypted: true,
    ciphertext: encrypted.ciphertext,
    iv: encrypted.iv,
    content_hash: encrypted.content_hash,
    harm_status: review.status,
    harm_categories: review.categories,
  })

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
  <PinSetupModal v-if="showPinSetup" @done="onPinReady" />
  <PinUnlockModal v-else-if="showPinUnlock" @done="onPinReady" />

  <div class="d-flex flex-column" style="height:calc(100vh - 88px)">
    <div class="d-flex align-items-center gap-3 px-3 py-2" style="border-bottom:1px solid var(--fx-border)">
      <button class="btn btn-light rounded-circle" style="width:32px;height:32px;padding:0" @click="router.back()">
        <AppIcon name="back" :size="15" />
      </button>
      <div class="fx-avatar" style="width:40px;height:40px;background:var(--fx-blue-soft);color:var(--fx-blue)">
        {{ (booking?.customer?.name || '?').split(' ').map(w => w[0]).join('') }}
      </div>
      <div class="flex-grow-1">
        <div class="fw-bold" style="font-size:15px">{{ booking?.customer?.name || 'Chat' }}</div>
        <div style="font-size:12px;color:var(--fx-success);font-weight:500">
          🔒 E2E encrypted · #{{ route.params.id }}
        </div>
      </div>
      <button class="btn btn-sm btn-light" @click="chatCrypto.lock(); showPinUnlock = true" title="Lock chat">
        Lock
      </button>
    </div>

    <div v-if="!ready" class="flex-grow-1 d-flex align-items-center justify-content-center text-muted" style="font-size:13px">
      Unlock with PIN to view messages…
    </div>

    <div v-else ref="listEl" class="flex-grow-1 d-flex flex-column gap-2 px-3 py-3" style="overflow-y:auto">
      <div v-for="m in messages" :key="m.id" class="d-flex"
           :style="{ justifyContent: isMine(m) ? 'flex-end' : 'flex-start' }">
        <div style="max-width:72%">
          <div :style="{
            padding: '10px 13px', borderRadius: '16px', fontSize: '13px', lineHeight: 1.5,
            background: isMine(m) ? 'var(--fx-accent)' : 'var(--fx-border-soft)',
            color: isMine(m) ? '#fff' : 'var(--fx-text)',
            borderBottomRightRadius: isMine(m) ? '4px' : '16px',
            borderBottomLeftRadius: isMine(m) ? '16px' : '4px',
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

    <form v-if="ready" class="d-flex gap-2 align-items-center px-3 py-2" style="border-top:1px solid var(--fx-border)" @submit.prevent="send">
      <input class="fx-input" style="border-radius:22px" v-model="draft" placeholder="Type a message…" />
      <button type="submit" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
              style="width:40px;height:40px;padding:0;flex-shrink:0">
        <AppIcon name="send" :size="18" />
      </button>
    </form>
  </div>
</template>