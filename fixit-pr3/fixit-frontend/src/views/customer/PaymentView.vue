<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as api from '../../services/api'
import { getStripe, mountSaveCardElement, payBooking } from '../../services/stripePayments'
import { useWalletStore } from '../../stores/wallet'
import AppIcon from '../../components/AppIcon.vue'
import { useModalGuard } from '../../composables/useModalGuard'

useModalGuard()

const route = useRoute()
const router = useRouter()
const wallet = useWalletStore()

const loading = ref(true)
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

const bookingId = computed(() => route.query.booking_id ? Number(route.query.booking_id) : null)
const amountDollars = computed(() => {
  const raw = route.query.amount
  return raw ? parseFloat(raw) : null
})
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
  return savedCardLabel.value ? `Pay RM${amountDollars.value?.toFixed(2)} with card` : `Pay RM${amountDollars.value?.toFixed(2)}`
})

onMounted(async () => {
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
    if (route.query.setup === 'complete') {
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
})

onUnmounted(() => {
  cardSession?.destroy()
})

watch(showSaveForm, async (show) => {
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
    cardSession = await mountSaveCardElement(paymentMount.value)
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
  if (!bookingId.value) {
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
      bookingId: bookingId.value,
      useWallet: useWallet.value && walletAppliedCents.value > 0,
    })
    if (result.paid || result.status === 'succeeded') {
      await wallet.load().catch(() => null)
      router.push({
        name: 'job-tracker',
        query: { id: bookingId.value, paid: '1' },
      })
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
</script>

<template>
  <div class="fx-page" style="max-width:520px">
    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-light rounded-circle" style="width:34px;height:34px;padding:0" @click="router.back()">
        <AppIcon name="back" :size="16" />
      </button>
      <h1 class="fw-bold m-0" style="font-size:20px">Payment</h1>
      <span class="fx-badge ms-auto" style="background:var(--fx-warn-soft);color:var(--fx-warn);font-size:10px">
        Stripe {{ mode }} mode
      </span>
    </div>

    <div v-if="loading" class="text-center py-5" style="color:var(--fx-muted)">Loading payment module…</div>

    <div v-else-if="loadError" class="fx-card" style="padding:16px">
      <p style="font-size:13px;color:var(--fx-muted);margin:0 0 10px">
        Couldn't load the payment module. Your session may have expired — try reloading or signing in again.
      </p>
      <button class="btn btn-sm btn-outline-secondary" @click="$router.go(0)">Reload</button>
    </div>

    <div v-else-if="!configured" class="fx-card" style="padding:16px">
      <p style="font-size:13px;color:var(--fx-muted);margin:0">
        Stripe test keys are not configured. Add <code>STRIPE_SECRET_KEY</code> and
        <code>STRIPE_PUBLISHABLE_KEY</code> (sk_test_ / pk_test_) to the backend <code>.env</code>.
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

      <!-- Wallet -->
      <div v-if="walletBalanceCents > 0" class="fx-card mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <span class="material-symbols-outlined" style="font-size:20px;color:#16a34a;font-variation-settings:'FILL' 1">account_balance_wallet</span>
          <span class="fw-semibold" style="font-size:14px">Wallet balance</span>
          <span class="ms-auto fw-bold" style="font-size:15px;color:#16a34a">{{ walletBalanceLabel }}</span>
        </div>
        <div v-if="amountCents" class="form-check">
          <input id="use-wallet" v-model="useWallet" class="form-check-input" type="checkbox" />
          <label for="use-wallet" class="form-check-label" style="font-size:13px">
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

      <!-- Pay CTA -->
      <div v-if="amountCents && bookingId && (canPayWithWalletOnly || showSavedCard)" class="fx-card mb-3">
        <button
          class="btn btn-primary w-100"
          :disabled="busy"
          @click="confirmPay"
        >
          {{ payButtonLabel }}
        </button>
      </div>

      <!-- Saved card -->
      <div v-if="showSavedCard" class="fx-card mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <AppIcon name="shield" :size="18" />
          <span class="fw-semibold" style="font-size:14px">Saved card</span>
        </div>
        <p style="font-size:13px;color:var(--fx-muted);margin-bottom:12px">{{ savedCardLabel }}</p>
        <div class="d-flex flex-column gap-2">
          <button class="btn btn-outline-primary w-100" :disabled="busy" @click="startReplace">
            Replace saved card
          </button>
          <button class="btn btn-outline-danger w-100" :disabled="busy" @click="removeSaved">
            Remove saved card
          </button>
        </div>
      </div>

      <!-- Save card when needed -->
      <div v-if="showSaveForm" class="fx-card mb-3">
        <div class="fw-semibold mb-2" style="font-size:14px">
          {{ replaceMode ? 'Replace card' : 'Save card for payment' }}
        </div>
        <p style="font-size:12px;color:var(--fx-muted)">
          Use Stripe test card <strong>4242 4242 4242 4242</strong>, any future expiry, any CVC.
          Card details go directly to Stripe — never stored on our servers.
        </p>

        <div ref="paymentMount" class="mb-3" style="min-height:120px"></div>

        <div v-if="!replaceMode" class="form-check mb-3">
          <input id="save-card" v-model="saveCardChecked" class="form-check-input" type="checkbox" />
          <label for="save-card" class="form-check-label" style="font-size:13px">
            Save card for future payments
          </label>
        </div>

        <button
          class="btn btn-primary w-100"
          :disabled="busy || !saveCardChecked"
          @click="saveTestCard"
        >
          {{ busy ? 'Saving…' : 'Save card' }}
        </button>

        <button
          v-if="replaceMode && saved?.has_saved_payment_method"
          class="btn btn-link w-100 mt-2"
          style="font-size:13px"
          @click="replaceMode = false"
        >
          Cancel
        </button>
      </div>

      <div v-if="!amountCents && !showSaveForm && saved?.has_saved_payment_method" class="fx-card" style="font-size:13px;color:var(--fx-muted)">
        Your card is saved. Book a service to pay with wallet or card.
      </div>

      <p class="mt-3 mb-0" style="font-size:11px;color:var(--fx-muted);text-align:center">
        Payments are subject to our
        <router-link :to="{ name: 'legal-terms' }" class="text-accent text-decoration-none">Terms of Service</router-link>
        and
        <router-link :to="{ name: 'legal-privacy' }" class="text-accent text-decoration-none">Privacy Policy</router-link>.
      </p>

      <div class="mt-3 p-3" style="font-size:11px;color:var(--fx-muted);background:var(--fx-border-soft);border-radius:10px">
        <strong>Security:</strong> Only Stripe IDs (cus_, pm_) are stored. Raw card numbers, expiry, and CVC never touch our database.
      </div>
    </template>
  </div>
</template>