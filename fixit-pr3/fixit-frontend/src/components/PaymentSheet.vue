<script setup>
import { ref, computed, watch, onUnmounted, nextTick, toRef } from 'vue'
import * as api from '../services/api'
import { getStripe, mountSaveCardElement, payBooking } from '../services/stripePayments'
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
const paymentMount = ref(null)
let cardSession = null

const amountDollars = computed(() => props.amount)
const amountCents = computed(() =>
  amountDollars.value ? Math.round(amountDollars.value * 100) : null)

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

const savedCardLabel = computed(() => {
  if (!saved.value?.has_saved_payment_method) return null
  const brand = (saved.value.brand || 'card').replace(/^./, (c) => c.toUpperCase())
  return `${brand} •••• ${saved.value.last4}`
})

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
    ? `Pay RM${amountDollars.value?.toFixed(2)} with card`
    : `Pay RM${amountDollars.value?.toFixed(2)}`
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
    replaceMode.value = false
    await loadPaymentModule()
  } else {
    cardSession?.destroy()
    cardSession = null
  }
}, { immediate: true })

onUnmounted(() => {
  cardSession?.destroy()
})

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
      emit('paid', props.bookingId)
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
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="lg-overlay-center" @click.self="close">
      <div class="lg-sheet liquid-glass-high pv-sheet" @click.stop>
        <div class="pv-sheet-handle"></div>

        <div class="pv-sheet-header">
          <span class="pv-sheet-title">Payment</span>
          <span class="pv-sheet-badge">Stripe {{ mode }}</span>
          <button class="pv-sheet-close" type="button" aria-label="Close" @click="close">
            <span class="material-symbols-outlined" style="font-size:20px">close</span>
          </button>
        </div>

        <div class="pv-sheet-body">
          <div v-if="loading" class="text-center py-4" style="color:var(--fx-muted)">Loading payment…</div>

          <div v-else-if="loadError" class="fx-card" style="padding:14px">
            <p style="font-size:13px;color:var(--fx-muted);margin:0 0 10px">
              Couldn't load payment. Try again or sign in again.
            </p>
            <button class="btn btn-sm btn-outline-secondary" type="button" @click="loadPaymentModule">Retry</button>
          </div>

          <div v-else-if="!configured" class="fx-card" style="padding:14px">
            <p style="font-size:13px;color:var(--fx-muted);margin:0">
              Stripe is not configured on the server.
            </p>
          </div>

          <template v-else>
            <div v-if="amountDollars" class="fx-card mb-3" style="background:var(--fx-accent-soft)">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-semibold" style="font-size:14px">Amount due</span>
                <span class="fw-bold text-accent" style="font-size:20px">RM{{ amountDollars.toFixed(2) }}</span>
              </div>
              <div v-if="bookingId" style="font-size:12px;color:var(--fx-muted);margin-top:4px">
                Booking #{{ bookingId }}
              </div>
            </div>

            <div v-if="error" class="alert alert-danger py-2 mb-3" style="font-size:13px">{{ error }}</div>

            <div v-if="walletBalanceCents > 0" class="fx-card mb-3">
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

            <div v-if="showSavedCard" class="fx-card mb-3">
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

            <div v-if="showSaveForm" class="fx-card mb-3">
              <div class="fw-semibold mb-2" style="font-size:14px">
                {{ replaceMode ? 'Replace card' : 'Save card for payment' }}
              </div>
              <p style="font-size:12px;color:var(--fx-muted)">
                Test card <strong>4242 4242 4242 4242</strong>, any future expiry, any CVC.
              </p>
              <div ref="paymentMount" class="mb-3" style="min-height:120px"></div>
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
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.pv-sheet {
  max-height: min(68vh, 640px);
  padding: 0;
  display: flex;
  flex-direction: column;
}
.pv-sheet-handle {
  width: 40px;
  height: 4px;
  border-radius: 2px;
  background: rgba(0, 0, 0, 0.18);
  margin: 12px auto 0;
  flex-shrink: 0;
}
.pv-sheet-header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px 10px;
  flex-shrink: 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
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
</style>