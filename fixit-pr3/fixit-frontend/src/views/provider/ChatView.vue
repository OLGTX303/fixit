<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useAuthStore } from '../../stores/auth'
import { useChatCryptoStore } from '../../stores/chatCrypto'
import * as api from '../../services/api'
import * as harmReview from '../../services/harmReview'
import PinModal from '../../components/PinModal.vue'
import { useModalGuard } from '../../composables/useModalGuard'
import { E2E_ENABLED } from '../../config'

const props = defineProps({
  bookingId: { type: [Number, String], default: null },
  embedded:  { type: Boolean, default: false },
})

// Standalone chat is a focused full-screen view — hide the bottom dock so the
// compose bar isn't overlapped. Embedded (desktop split panel) keeps the nav.
useModalGuard(computed(() => !props.embedded))

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
const brokenAvatars = ref({})

const jobId   = computed(() => Number(props.bookingId || route.params.id))
const booking = computed(() => bookingsStore.byId(jobId.value))
const isAdmin = computed(() => auth.role === 'admin')

const other = computed(() => {
  const b = booking.value
  if (!b || isAdmin.value) return null
  return auth.role === 'provider' ? b.customer : b.provider
})
const otherName = computed(() => {
  if (isAdmin.value && booking.value) {
    return `${booking.value.customer?.name || 'Customer'} · ${booking.value.provider?.name || 'Provider'}`
  }
  return other.value?.name || 'Chat'
})
const otherInitials = computed(() =>
  (other.value?.name || '—').split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase())

const providerUserId = computed(() => booking.value?.provider?.user_id)

function initials(name) {
  return (name || '—').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
}

function isCustomerMsg(m) {
  return m.sender_id === booking.value?.customer_id
}

function isMine(m) {
  return m.sender_id === auth.user?.id
}

function isOutgoing(m) {
  if (isAdmin.value) return !isCustomerMsg(m)
  return isMine(m)
}

function senderLabel(m) {
  const b = booking.value
  if (!b) return 'User'
  if (m.sender_id === auth.user?.id && isAdmin.value) return 'Customer Service'
  if (isCustomerMsg(m)) return b.customer?.name || 'Customer'
  return b.provider?.name || 'Provider'
}

function avatarFor(m) {
  const b = booking.value
  if (!b) return null
  if (isCustomerMsg(m)) return b.customer?.avatar_url
  if (m.sender_id === providerUserId.value) return b.provider?.avatar_url
  return null
}

function showMsgAvatar(m) {
  const url = avatarFor(m)
  return url && !brokenAvatars.value[`m${m.id}`]
}

function onAvatarError(key) {
  brokenAvatars.value[key] = true
}

function showHeaderAvatar(url, key) {
  return url && !brokenAvatars.value[key]
}

onMounted(async () => {
  await bookingsStore.load()

  if (isAdmin.value || !E2E_ENABLED) {
    await initChat()
    return
  }

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
    if (E2E_ENABLED && !isAdmin.value) await chatCrypto.ensureJobKey(jobId.value)
    const raw = await api.getMessagesForJob(jobId.value)
    messages.value = raw
    await hydrateBodies(raw)
    ready.value = true
    scrollToEnd()
  } catch (e) {
    if (E2E_ENABLED && !isAdmin.value) {
      chatCrypto.error = e.message
      showPinUnlock.value = true
    }
  }
}

async function hydrateBodies(msgs) {
  const bodies = {}
  for (const m of msgs) {
    if (isAdmin.value || !E2E_ENABLED) {
      bodies[m.id] = m.is_encrypted ? '' : (m.body || '')
    } else {
      bodies[m.id] = await chatCrypto.decryptMessage(m)
    }
  }
  displayBodies.value = bodies
}

function bodyText(m) {
  if (m.is_encrypted && !displayBodies.value[m.id]) return null
  return displayBodies.value[m.id] || ''
}

function scrollToEnd() {
  nextTick(() => { if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight })
}

async function send() {
  const text = draft.value.trim()
  if (!text || !ready.value) return

  const review = harmReview.reviewMessage(text)
  harmWarning.value = review.message
  if (!review.allowed) return

  let payload
  if (E2E_ENABLED && !isAdmin.value) {
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

  <div class="chat-root d-flex flex-column" :class="{ embedded }">
    <!-- Header -->
    <div class="chat-header">
      <button v-if="!embedded" class="chat-back glass-btn" @click="router.back()">
        <span class="material-symbols-outlined">arrow_back</span>
      </button>

      <template v-if="isAdmin && booking">
        <div class="chat-header-avatars">
          <img v-if="showHeaderAvatar(booking.customer?.avatar_url, 'cu')"
               :src="booking.customer.avatar_url" :alt="booking.customer?.name"
               class="chat-hdr-avatar" @error="onAvatarError('cu')" />
          <div v-else class="chat-hdr-avatar chat-hdr-fallback">{{ initials(booking.customer?.name) }}</div>
          <img v-if="showHeaderAvatar(booking.provider?.avatar_url, 'pr')"
               :src="booking.provider.avatar_url" :alt="booking.provider?.name"
               class="chat-hdr-avatar chat-hdr-avatar--overlap" @error="onAvatarError('pr')" />
          <div v-else class="chat-hdr-avatar chat-hdr-avatar--overlap chat-hdr-fallback provider">
            {{ initials(booking.provider?.name) }}
          </div>
        </div>
        <div class="flex-grow-1" style="min-width:0">
          <div class="fw-bold" style="font-size:15px">{{ otherName }}</div>
          <div style="font-size:12px;color:var(--fx-muted)">
            Job #{{ jobId }} · {{ booking.category?.name || 'Service' }}
          </div>
        </div>
        <div class="chat-cs-badge">
          <span class="material-symbols-outlined" style="font-size:13px;font-variation-settings:'FILL' 1">support_agent</span>
          CS Mode
        </div>
      </template>

      <template v-else>
        <img v-if="showHeaderAvatar(other?.avatar_url, 'other')"
             :src="other.avatar_url" :alt="otherName"
             class="chat-hdr-avatar" @error="onAvatarError('other')" />
        <div v-else class="chat-hdr-avatar chat-hdr-fallback">{{ otherInitials }}</div>
        <div class="flex-grow-1" style="min-width:0">
          <div class="fw-bold" style="font-size:15px">{{ otherName }}</div>
          <div v-if="E2E_ENABLED" style="font-size:12px;color:var(--fx-success);font-weight:500">
            🔒 E2E encrypted · #{{ jobId }}
          </div>
          <div v-else style="font-size:12px;color:var(--fx-muted);font-weight:500">
            Job #{{ jobId }}
          </div>
        </div>
        <button v-if="E2E_ENABLED" class="glass-btn chat-lock-btn" @click="chatCrypto.lock(); showPinUnlock = true">
          Lock
        </button>
      </template>
    </div>

    <div v-if="isAdmin" class="chat-cs-bar">
      <span class="material-symbols-outlined" style="font-size:14px">support_agent</span>
      Customer Service mode — your messages appear in this thread
    </div>

    <div v-if="!ready" class="chat-loading">
      {{ E2E_ENABLED && !isAdmin ? 'Unlock with PIN to view messages…' : 'Loading messages…' }}
    </div>

    <div v-else ref="listEl" class="chat-list">
      <div v-for="m in messages" :key="m.id" class="chat-row"
           :class="isOutgoing(m) ? 'outgoing' : 'incoming'">
        <template v-if="isAdmin && !isOutgoing(m)">
          <img v-if="showMsgAvatar(m)" :src="avatarFor(m)" :alt="senderLabel(m)"
               class="chat-msg-avatar" @error="onAvatarError(`m${m.id}`)" />
          <div v-else class="chat-msg-avatar chat-msg-fallback">{{ initials(senderLabel(m)) }}</div>
        </template>

        <div class="chat-col">
          <div v-if="isAdmin" class="chat-sender">{{ senderLabel(m) }}</div>
          <div class="chat-bubble" :class="isOutgoing(m) ? 'bubble-out' : 'bubble-in'">
            <template v-if="bodyText(m) !== null">{{ bodyText(m) }}</template>
            <span v-else class="chat-encrypted">🔒 Encrypted message</span>
          </div>
          <div class="chat-meta">
            {{ timeOf(m.sent_at) }}
            <span v-if="m.harm_status === 'flagged'" class="chat-flagged">· flagged</span>
            <span v-if="isAdmin && m.harm_status && m.harm_status !== 'clear'" class="chat-flagged">
              · {{ m.harm_status }}
            </span>
          </div>
        </div>

        <template v-if="isAdmin && isOutgoing(m)">
          <img v-if="showMsgAvatar(m)" :src="avatarFor(m)" :alt="senderLabel(m)"
               class="chat-msg-avatar" @error="onAvatarError(`m${m.id}`)" />
          <div v-else class="chat-msg-avatar chat-msg-fallback outgoing">
            {{ initials(senderLabel(m)) }}
          </div>
        </template>
      </div>
    </div>

    <div v-if="harmWarning" class="chat-harm-warn">{{ harmWarning }}</div>

    <form v-if="ready" class="chat-compose" @submit.prevent="send">
      <div class="fx-input chat-input-wrap">
        <input v-model="draft"
               :placeholder="isAdmin ? 'Type a customer service message…' : 'Type a message…'" />
      </div>
      <button type="submit" class="glossy-primary chat-send" :disabled="!draft.trim()">
        <span class="material-symbols-outlined" style="font-size:20px;font-variation-settings:'FILL' 1">send</span>
      </button>
    </form>
  </div>
</template>

<style scoped>
.chat-root { background: transparent; }
.chat-root.embedded { height: 100%; }

/* Standalone (not embedded): own the viewport — fits mobile (dynamic vh + safe
   areas, dock hidden via useModalGuard) and desktop (centered column). */
.chat-root:not(.embedded) {
  position: fixed;
  inset: 0;
  z-index: 1200;
  height: 100dvh;
  padding-top: env(safe-area-inset-top);
}
.chat-root:not(.embedded) .chat-compose {
  padding-bottom: max(10px, env(safe-area-inset-bottom));
}
:global(body.fx-desktop) .chat-root:not(.embedded) {
  max-width: 860px;
  margin: 0 auto;
  border-left: 0.5px solid rgba(255, 255, 255, 0.40);
  border-right: 0.5px solid rgba(255, 255, 255, 0.40);
}

.chat-header {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 16px; flex-shrink: 0;
  background: rgba(255,255,255,0.40);
  backdrop-filter: blur(30px);
  -webkit-backdrop-filter: blur(30px);
  border-bottom: 0.5px solid rgba(255,255,255,0.40);
}
.chat-back {
  width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.chat-back .material-symbols-outlined { font-size: 20px; color: var(--fx-muted); }

.chat-header-avatars {
  position: relative; width: 52px; height: 40px; flex-shrink: 0;
}
.chat-hdr-avatar {
  width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
  border: 2px solid rgba(255,255,255,0.65);
  box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.chat-hdr-avatar--overlap { position: absolute; left: 18px; top: 0; }
.chat-hdr-fallback {
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 800; color: #fff;
  background: linear-gradient(160deg, #FF8056, #FF6635);
}
.chat-hdr-fallback.provider {
  background: linear-gradient(160deg, #3b82f6, #2563eb);
}

.chat-cs-badge {
  display: inline-flex; align-items: center; gap: 4px; flex-shrink: 0;
  padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700;
  background: rgba(255,102,53,0.09); color: var(--fx-accent);
}
.chat-lock-btn {
  border-radius: 999px; padding: 6px 14px; font-size: 12px; font-weight: 700; flex-shrink: 0;
}

.chat-cs-bar {
  display: flex; align-items: center; gap: 6px; flex-shrink: 0;
  padding: 7px 16px; font-size: 12px; color: var(--fx-muted);
  background: rgba(255,255,255,0.18);
  border-bottom: 0.5px solid rgba(255,255,255,0.28);
}

.chat-loading {
  flex: 1; display: flex; align-items: center; justify-content: center;
  font-size: 13px; color: var(--fx-muted);
}

.chat-list {
  flex: 1; overflow-y: auto; padding: 16px;
  display: flex; flex-direction: column; gap: 12px;
}

.chat-row {
  display: flex; align-items: flex-end; gap: 8px;
}
.chat-row.incoming { justify-content: flex-start; }
.chat-row.outgoing { justify-content: flex-end; }

.chat-msg-avatar {
  width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
  object-fit: cover; border: 1.5px solid rgba(255,255,255,0.55);
}
.chat-msg-fallback {
  display: flex; align-items: center; justify-content: center;
  font-size: 10px; font-weight: 800; color: #fff;
  background: linear-gradient(160deg, #FF8056, #FF6635);
}
.chat-msg-fallback.outgoing {
  background: linear-gradient(160deg, #FF7D54, #FF6635);
}

.chat-col {
  max-width: 72%; display: flex; flex-direction: column;
}
:global(body.fx-desktop) .chat-root.embedded .chat-col { max-width: min(480px, 58%); }
:global(body.fx-desktop) .chat-root.embedded .chat-list { padding: 20px 28px; }
.incoming .chat-col { align-items: flex-start; }
.outgoing .chat-col { align-items: flex-end; }

.chat-sender {
  font-size: 11px; color: var(--fx-muted); margin-bottom: 3px;
}

.chat-bubble {
  padding: 10px 14px; border-radius: 18px;
  font-size: 13px; line-height: 1.55;
}
.bubble-in {
  background: rgba(255,255,255,0.50);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border: 0.5px solid rgba(255,255,255,0.60);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.55), 0 2px 8px rgba(0,0,0,0.05);
  color: var(--fx-text);
  border-bottom-left-radius: 4px;
}
.bubble-out {
  background: linear-gradient(180deg, #FF7D54, #FF6635);
  color: #fff;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.30), 0 4px 12px rgba(255,102,53,0.22);
  border-bottom-right-radius: 4px;
}
.chat-encrypted { color: var(--fx-muted); font-style: italic; }

.chat-meta {
  font-size: 10px; color: var(--fx-muted-soft); margin-top: 3px;
}
.chat-flagged { color: var(--fx-warn); margin-left: 4px; }

.chat-harm-warn {
  padding: 4px 16px; font-size: 12px; color: var(--fx-warn); flex-shrink: 0;
}

.chat-compose {
  display: flex; gap: 8px; align-items: center;
  padding: 10px 16px; flex-shrink: 0;
  background: rgba(255,255,255,0.40);
  backdrop-filter: blur(30px);
  -webkit-backdrop-filter: blur(30px);
  border-top: 0.5px solid rgba(255,255,255,0.35);
}
.chat-input-wrap { border-radius: 999px; flex: 1; }
.chat-input-wrap input { width: 100%; }
.chat-send {
  width: 44px; height: 44px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; padding: 0;
}
.chat-send:disabled { opacity: 0.45; cursor: default; }
</style>