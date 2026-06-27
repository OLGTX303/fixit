<script setup>
import { ref, computed, watch, onUnmounted, nextTick, toRef } from 'vue'
import * as api from '../services/api'
import { getStripe, mountSaveCardElement, payBooking, formatSavedCard } from '../services/stripePayments'
import { useWalletStore } from '../stores/wallet'
import { useModalGuard } from '../composables/useModalGuard'

const props = defineProps({
  open: { type: Boolean, default: false },
  bookingId: { type: Number, default: null },
  amount: { type: Number, default: null },
  setupComplete: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'paid'])

const wallet = useWalletStore()
const openRef = toRef(props, 'open')
useModalGuard(openRef)

const loading = ref(false)
const loadError = ref('')
const busy = ref(false)
const error = ref('')
const configured = ref(false)
const saved = ref(null)
const mode = ref('test')
const useWallet = ref(true)

const replaceMode = ref(false)
const saveCardChecked = ref(true)
const paidSuccess = ref(false)
const paymentMount = ref(null)
let cardSession = null
let successTimer = null

const SUCCESS_MS = 2000

const amountCents = computed(() =>
  props.amount ? Math.round(props.amount * 100) : null)

const walletBalanceCents = computed(() => wallet.balanceCents)
const walletBalanceLabel = computed(() => `RM${(walletBalanceCents.value / 100).toFixed(2)}`)

const walletAppliedCents = computed(() => {
  if (!useWallet.value || !amountCents.value) return 0
  return Math.min(walletBalanceCents.value, amountCents.value)
})

const cardDueCents = computed(() => {
  if (!amountCents.value) return 0
  return Math.max(0, amountCents.value - walletAppliedCents.value)
})

const canPayWithWalletOnly = computed(() =>
  amountCents.value > 0 && walletAppliedCents.value >= amountCents.value)

const needsSavedCard = computed(() => cardDueCents.value > 0)

const savedCardLabel = computed(() => formatSavedCard(saved.value))

const showSaveForm = computed(() =>
  configured.value && needsSavedCard.value && (!saved.value?.has_saved_payment_method || replaceMode.value))

const showSavedCard = computed(() =>
  configured.value && needsSavedCard.value && saved.value?.has_saved_payment_method && !replaceMode.value)

const payButtonLabel = computed(() => {
  if (busy.value) return 'Processing…'
  if (!amountCents.value) return 'Pay'
  if (canPayWithWalletOnly.value) return `Pay ${walletBalanceLabel.value} from wallet`
  if (walletAppliedCents.value > 0 && cardDueCents.value > 0) {
    return `Pay RM${(walletAppliedCents.value / 100).toFixed(2)} wallet + RM${(cardDueCents.value / 100).toFixed(2)} card`
  }
  return savedCardLabel.value
    ? `Pay RM${props.amount?.toFixed(2)} with card`
    : `Pay RM${props.amount?.toFixed(2)}`
})

const stripeReturnUrl = computed(() => {
  if (!props.bookingId || !props.amount) {
    return `${window.location.origin}/payment?setup=complete`
  }
  return `${window.location.origin}/payment?setup=complete&booking_id=${props.bookingId}&amount=${props.amount}`
})

async function loadPaymentModule() {
  loading.value = true
  loadError.value = ''
  error.value = ''
  try {
    const [stripePack] = await Promise.all([
      getStripe(),
      wallet.load().catch(() => null),
    ])
    const { config } = stripePack
    configured.value = config.configured
    saved.value = config.saved_payment_method
    mode.value = config.mode || 'test'
    useWallet.value = walletBalanceCents.value > 0
    if (props.setupComplete) {
      await refreshSaved()
    }
  } catch (e) {
    loadError.value = e.message || 'Could not load the payment module'
    error.value = e.message
  } finally {
    loading.value = false
    if (showSaveForm.value) {
      await nextTick()
      await initSaveCard()
    }
  }
}

watch(openRef, async (isOpen) => {
  if (isOpen) {
    paidSuccess.value = false
    replaceMode.value = false
    await loadPaymentModule()
  } else {
    clearSuccessTimer()
    cardSession?.destroy()
    cardSession = null
  }
}, { immediate: true })

onUnmounted(() => {
  clearSuccessTimer()
  cardSession?.destroy()
})

function clearSuccessTimer() {
  if (successTimer) {
    clearTimeout(successTimer)
    successTimer = null
  }
}

function showSuccessThenNavigate() {
  paidSuccess.value = true
  clearSuccessTimer()
  successTimer = setTimeout(() => {
    successTimer = null
    emit('paid', props.bookingId)
  }, SUCCESS_MS)
}

watch(showSaveForm, async (show) => {
  if (!props.open) return
  if (show) {
    await nextTick()
    await initSaveCard()
  } else {
    cardSession?.destroy()
    cardSession = null
  }
})

async function refreshSaved() {
  const config = await api.getStripeConfig()
  saved.value = config.saved_payment_method
}

async function initSaveCard() {
  if (!paymentMount.value) return
  cardSession?.destroy()
  error.value = ''
  try {
    cardSession = await mountSaveCardElement(paymentMount.value, {
      returnUrl: stripeReturnUrl.value,
    })
  } catch (e) {
    error.value = e.message
  }
}

async function saveTestCard() {
  if (!cardSession) return
  busy.value = true
  error.value = ''
  try {
    const summary = await cardSession.confirmSave()
    saved.value = summary
    replaceMode.value = false
    cardSession.destroy()
    cardSession = null
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

async function confirmPay() {
  if (!props.bookingId) {
    error.value = 'No booking specified'
    return
  }
  if (needsSavedCard.value && !saved.value?.has_saved_payment_method) {
    error.value = 'Save a card to pay the remaining balance'
    return
  }

  busy.value = true
  error.value = ''
  try {
    const result = await payBooking({
      bookingId: props.bookingId,
      useWallet: useWallet.value && walletAppliedCents.value > 0,
    })
    if (result.paid || result.status === 'succeeded') {
      await wallet.load().catch(() => null)
      showSuccessThenNavigate()
    } else {
      error.value = `Payment status: ${result.status}`
    }
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

async function removeSaved() {
  busy.value = true
  error.value = ''
  try {
    await api.removeStripeSavedPaymentMethod()
    saved.value = { has_saved_payment_method: false }
    replaceMode.value = false
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

function startReplace() {
  replaceMode.value = true
  setTimeout(initSaveCard, 50)
}

function close() {
  if (paidSuccess.value) return
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="pv-overlay" @click.self="close">
      <div class="lg-sheet liquid-glass-high acv-glass pv-sheet pv-sheet-enter" @click.stop>
        <div class="pv-sheet-handle"></div>

        <div class="pv-sheet-header">
          <span class="pv-sheet-title">{{ paidSuccess ? 'Paid' : 'Payment' }}</span>
          <span v-if="!paidSuccess" class="pv-sheet-badge">Stripe {{ mode }}</span>
          <button
            v-if="!paidSuccess"
            class="pv-sheet-close"
            type="button"
            aria-label="Close"
            @click="close"
          >
            <span class="material-symbols-outlined" style="font-size:20px">close</span>
          </button>
        </div>

        <div class="pv-sheet-body">
          <Transition name="pv-success" mode="out-in">
          <div v-if="paidSuccess" key="success" class="pv-pay-success">
            <div class="pv-success-ring" aria-hidden="true"></div>
            <div class="pv-success-icon">
              <span class="material-symbols-outlined" style="font-size:40px;font-variation-settings:'FILL' 1">check_circle</span>
            </div>
            <div class="pv-success-title">Payment successful!</div>
            <div class="pv-success-sub">
              RM{{ amount?.toFixed(2) }} paid for booking #{{ bookingId }}
            </div>
            <div class="pv-success-hint">Taking you to your bookings…</div>
          </div>

          <div v-else-if="loading" key="loading" class="text-center py-4" style="color:var(--fx-muted)">Loading payment…</div>

          <div v-else-if="loadError" key="error" class="pv-panel">
            <p style="font-size:13px;color:var(--fx-muted);margin:0 0 10px">
              Couldn't load payment. Try again or sign in again.
            </p>
            <button class="btn btn-sm btn-outline-secondary" type="button" @click="loadPaymentModule">Retry</button>
          </div>

          <div v-else-if="!configured" key="unconfigured" class="pv-panel">
            <p style="font-size:13px;color:var(--fx-muted);margin:0">
              Stripe is not configured on the server.
            </p>
          </div>

          <div v-else key="form">
            <div v-if="amount" class="pv-panel pv-panel-accent mb-3">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold" style="font-size:14px">Amount due</span>
                <span class="fw-bold text-accent" style="font-size:20px">RM{{ amount.toFixed(2) }}</span>
              </div>
              <div v-if="bookingId" style="font-size:12px;color:var(--fx-muted);margin-top:4px">
                Booking #{{ bookingId }}
              </div>
            </div>

            <div v-if="error" class="alert alert-danger py-2 mb-3" style="font-size:13px">{{ error }}</div>

            <div v-if="walletBalanceCents > 0" class="pv-panel mb-3">
              <div class="d-flex align-items-center gap-2 mb-2">
                <span class="material-symbols-outlined" style="font-size:20px;color:#16a34a;font-variation-settings:'FILL' 1">account_balance_wallet</span>
                <span class="fw-semibold" style="font-size:14px">Wallet balance</span>
                <span class="ms-auto fw-bold" style="font-size:15px;color:#16a34a">{{ walletBalanceLabel }}</span>
              </div>
              <div v-if="amountCents" class="form-check">
                <input :id="`use-wallet-${bookingId}`" v-model="useWallet" class="form-check-input" type="checkbox" />
                <label :for="`use-wallet-${bookingId}`" class="form-check-label" style="font-size:13px">
                  Use wallet balance first
                  <span v-if="useWallet && walletAppliedCents > 0" style="color:var(--fx-muted)">
                    (RM{{ (walletAppliedCents / 100).toFixed(2) }} applied)
                  </span>
                </label>
              </div>
              <div v-if="amountCents && useWallet && cardDueCents > 0" style="font-size:12px;color:var(--fx-muted);margin-top:8px">
                RM{{ (cardDueCents / 100).toFixed(2) }} will be charged to your saved card.
              </div>
            </div>

            <div v-if="amountCents && bookingId && (canPayWithWalletOnly || showSavedCard)" class="mb-3">
              <button class="btn btn-primary w-100" type="button" :disabled="busy" @click="confirmPay">
                {{ payButtonLabel }}
              </button>
            </div>

            <div v-if="showSavedCard" class="pv-panel mb-3">
              <div class="fw-semibold mb-2" style="font-size:14px">Saved card</div>
              <p style="font-size:13px;color:var(--fx-muted);margin-bottom:12px">{{ savedCardLabel }}</p>
              <div class="d-flex flex-column gap-2">
                <button class="btn btn-outline-primary w-100 btn-sm" type="button" :disabled="busy" @click="startReplace">
                  Replace card
                </button>
                <button class="btn btn-outline-danger w-100 btn-sm" type="button" :disabled="busy" @click="removeSaved">
                  Remove card
                </button>
              </div>
            </div>

            <div v-if="showSaveForm" class="pv-panel mb-3">
              <div class="fw-semibold mb-2" style="font-size:14px">
                {{ replaceMode ? 'Replace card' : 'Save card for payment' }}
              </div>
              <p style="font-size:12px;color:var(--fx-muted)">
                Test card <strong>4242 4242 4242 4242</strong>, any future expiry, any CVC.
              </p>
              <div ref="paymentMount" class="pv-card-mount mb-3"></div>
              <div v-if="!replaceMode" class="form-check mb-3">
                <input :id="`save-card-${bookingId}`" v-model="saveCardChecked" class="form-check-input" type="checkbox" />
                <label :for="`save-card-${bookingId}`" class="form-check-label" style="font-size:13px">
                  Save card for future payments
                </label>
              </div>
              <button class="btn btn-primary w-100" type="button" :disabled="busy || !saveCardChecked" @click="saveTestCard">
                {{ busy ? 'Saving…' : 'Save card' }}
              </button>
              <button
                v-if="replaceMode && saved?.has_saved_payment_method"
                class="btn btn-link w-100 mt-2"
                type="button"
                style="font-size:13px"
                @click="replaceMode = false"
              >
                Cancel
              </button>
            </div>

            <p class="mb-0" style="font-size:11px;color:var(--fx-muted);text-align:center">
              Secured by Stripe. Card details never touch our servers.
            </p>
          </div>
          </Transition>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.pv-overlay {
  position: fixed;
  inset: 0;
  z-index: var(--fx-z-modal-backdrop);
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: 16px;
  padding-bottom: max(16px, env(safe-area-inset-bottom));
  background: rgba(255, 252, 248, 0.55);
}
:global(body.fx-desktop) .pv-overlay {
  align-items: center;
  padding-bottom: 16px;
}
.pv-sheet-enter {
  animation: pv-sheet-up 0.38s cubic-bezier(0.16, 1, 0.3, 1) both;
}
@keyframes pv-sheet-up {
  from { opacity: 0; transform: translateY(28px); }
  to { opacity: 1; transform: translateY(0); }
}
.pv-success-enter-active {
  transition: opacity 0.35s ease, transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
.pv-success-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.pv-success-enter-from,
.pv-success-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
.pv-sheet {
  max-height: min(68vh, 640px);
  padding: 0;
  display: flex;
  flex-direction: column;
  border: 0.5px solid rgba(255, 255, 255, 0.72);
  box-shadow:
    var(--lg-edge),
    0 12px 40px rgba(255, 102, 53, 0.08),
    0 24px 48px rgba(0, 0, 0, 0.06);
}
.pv-sheet-handle {
  width: 40px;
  height: 4px;
  border-radius: 2px;
  background: rgba(255, 102, 53, 0.28);
  margin: 12px auto 0;
  flex-shrink: 0;
}
.pv-sheet-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px 10px;
  flex-shrink: 0;
  border-bottom: 0.5px solid rgba(255, 255, 255, 0.55);
}
.pv-panel {
  border-radius: 16px;
  padding: 14px 16px;
  background:
    radial-gradient(ellipse 44% 30% at 16% 7%, rgba(255, 255, 255, 0.28) 0%, transparent 62%),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.2) 0%, transparent 26%),
    rgba(255, 255, 255, 0.08);
  border: 0.5px solid rgba(255, 255, 255, 0.58);
  box-shadow: var(--lg-edge), 0 4px 14px rgba(255, 102, 53, 0.04);
}
.pv-panel-accent {
  background:
    radial-gradient(ellipse 50% 40% at 20% 10%, rgba(255, 140, 100, 0.22) 0%, transparent 60%),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.24) 0%, transparent 28%),
    rgba(255, 102, 53, 0.06);
}
.pv-card-mount {
  min-height: 120px;
  padding: 10px 12px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.72);
  border: 0.5px solid rgba(255, 255, 255, 0.65);
}
.pv-pay-success {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 10px;
  padding: 36px 12px 28px;
  text-align: center;
}
.pv-success-ring {
  position: absolute;
  top: 28px;
  width: 88px;
  height: 88px;
  border-radius: 50%;
  border: 2px solid rgba(34, 197, 94, 0.35);
  animation: pv-ring-pulse 1.2s ease-out infinite;
}
.pv-success-icon {
  width: 76px;
  height: 76px;
  border-radius: 50%;
  background: rgba(34, 197, 94, 0.14);
  color: #16a34a;
  display: flex;
  align-items: center;
  justify-content: center;
  animation: pv-success-pop 0.55s cubic-bezier(0.16, 1, 0.3, 1) forwards;
  box-shadow: 0 8px 24px rgba(34, 197, 94, 0.18);
}
.pv-success-title {
  font-size: 18px;
  font-weight: 800;
  color: var(--fx-text);
  animation: pv-fade-up 0.45s 0.12s both;
}
.pv-success-sub {
  font-size: 14px;
  color: var(--fx-muted);
  animation: pv-fade-up 0.45s 0.22s both;
}
.pv-success-hint {
  font-size: 12px;
  color: var(--fx-muted);
  margin-top: 4px;
  animation: pv-fade-up 0.45s 0.32s both;
}
@keyframes pv-success-pop {
  0% { transform: scale(0.5); opacity: 0; }
  65% { transform: scale(1.08); opacity: 1; }
  100% { transform: scale(1); opacity: 1; }
}
@keyframes pv-ring-pulse {
  0% { transform: scale(0.85); opacity: 0.7; }
  70% { transform: scale(1.15); opacity: 0; }
  100% { transform: scale(1.15); opacity: 0; }
}
@keyframes pv-fade-up {
  from { opacity: 0; transform: translateY(8px); }
  to { opacity: 1; transform: translateY(0); }
}
.pv-sheet-title {
  flex: 1;
  font-size: 16px;
  font-weight: 700;
  color: var(--fx-text);
}
.pv-sheet-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 999px;
  background: var(--fx-warn-soft);
  color: var(--fx-warn);
}
.pv-sheet-close {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  color: var(--fx-muted);
}
.pv-sheet-body {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  -webkit-overflow-scrolling: touch;
}

@media (prefers-reduced-motion: reduce) {
  .pv-sheet-enter {
    animation: none;
  }
  .pv-success-enter-active,
  .pv-success-leave-active {
    transition: none;
  }
  .pv-success-icon,
  .pv-success-ring,
  .pv-success-title,
  .pv-success-sub,
  .pv-success-hint {
    animation: none;
  }
}
</style>