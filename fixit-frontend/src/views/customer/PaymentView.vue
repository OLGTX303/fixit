<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as api from '../../services/api'
import { getStripe, mountSaveCardElement, payWithSavedCard } from '../../services/stripePayments'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const busy = ref(false)
const error = ref('')
const configured = ref(false)
const saved = ref(null)
const mode = ref('test')

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

const savedCardLabel = computed(() => {
  if (!saved.value?.has_saved_payment_method) return null
  const brand = (saved.value.brand || 'card').replace(/^./, (c) => c.toUpperCase())
  return `Use saved test ${brand} ending in ${saved.value.last4}`
})

const showSaveForm = computed(() =>
  configured.value && (!saved.value?.has_saved_payment_method || replaceMode.value))

const showSavedOptions = computed(() =>
  configured.value && saved.value?.has_saved_payment_method && !replaceMode.value)

onMounted(async () => {
  try {
    const { config } = await getStripe()
    configured.value = config.configured
    saved.value = config.saved_payment_method
    mode.value = config.mode || 'test'
    if (route.query.setup === 'complete') {
      await refreshSaved()
    }
  } catch (e) {
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

async function paySaved() {
  if (!amountCents.value) {
    error.value = 'No payment amount specified'
    return
  }
  busy.value = true
  error.value = ''
  try {
    const result = await payWithSavedCard({
      amountCents: amountCents.value,
      bookingId: bookingId.value,
    })
    if (result.paid || result.status === 'succeeded') {
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
          <span class="fw-bold text-accent" style="font-size:20px">${{ amountDollars.toFixed(2) }}</span>
        </div>
        <div v-if="bookingId" style="font-size:12px;color:var(--fx-muted);margin-top:4px">
          Booking #{{ bookingId }}
        </div>
      </div>

      <div v-if="error" class="alert alert-danger py-2 mb-3" style="font-size:13px">{{ error }}</div>

      <!-- Saved card path -->
      <div v-if="showSavedOptions" class="fx-card mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <AppIcon name="shield" :size="18" />
          <span class="fw-semibold" style="font-size:14px">Saved test card</span>
        </div>
        <p style="font-size:13px;color:var(--fx-muted);margin-bottom:12px">
          {{ savedCardLabel }}
        </p>
        <div class="d-flex flex-column gap-2">
          <button
            v-if="amountCents"
            class="btn btn-primary w-100"
            :disabled="busy"
            @click="paySaved"
          >
            {{ busy ? 'Processing…' : savedCardLabel }}
          </button>
          <button class="btn btn-outline-primary w-100" :disabled="busy" @click="startReplace">
            Replace saved test card
          </button>
          <button class="btn btn-outline-danger w-100" :disabled="busy" @click="removeSaved">
            Remove saved test card
          </button>
        </div>
      </div>

      <!-- Save new card path -->
      <div v-if="showSaveForm" class="fx-card mb-3">
        <div class="fw-semibold mb-2" style="font-size:14px">
          {{ replaceMode ? 'Replace test card' : 'Save test card for future payments' }}
        </div>
        <p style="font-size:12px;color:var(--fx-muted)">
          Use Stripe test card <strong>4242 4242 4242 4242</strong>, any future expiry, any CVC.
          Card details go directly to Stripe — never stored on our servers.
        </p>

        <div ref="paymentMount" class="mb-3" style="min-height:120px"></div>

        <div v-if="!replaceMode" class="form-check mb-3">
          <input id="save-card" v-model="saveCardChecked" class="form-check-input" type="checkbox" />
          <label for="save-card" class="form-check-label" style="font-size:13px">
            Save test card for future payments
          </label>
        </div>

        <button
          class="btn btn-primary w-100"
          :disabled="busy || !saveCardChecked"
          @click="saveTestCard"
        >
          {{ busy ? 'Saving…' : 'Save test card' }}
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
        Your test card is saved. Book a service to pay with it.
      </div>

      <div class="mt-3 p-3" style="font-size:11px;color:var(--fx-muted);background:var(--fx-border-soft);border-radius:10px">
        <strong>Security:</strong> Only Stripe IDs (cus_, pm_) are stored. Raw card numbers, expiry, and CVC never touch our database.
      </div>
    </template>
  </div>
</template>