<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import * as api from '../services/api'
import { getStripe, mountSaveCardElement } from '../services/stripePayments'
import AppIcon from '../components/AppIcon.vue'

const router = useRouter()

const loading = ref(true)
const error = ref('')
const configured = ref(false)
const savedCard = ref(null)
const replaceMode = ref(false)
const busy = ref(false)
const cardMount = ref(null)
let cardSession = null

const savedCardLabel = computed(() => {
  if (!savedCard.value?.has_saved_payment_method) return null
  const brand = (savedCard.value.brand || 'card').replace(/^./, (c) => c.toUpperCase())
  return `${brand} ending in ${savedCard.value.last4}`
})
const showCardForm = computed(() =>
  configured.value && (!savedCard.value?.has_saved_payment_method || replaceMode.value))

onMounted(async () => {
  try {
    const { config } = await getStripe()
    configured.value = config.configured
    savedCard.value = config.saved_payment_method
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
    if (showCardForm.value) {
      await nextTick()
      await initCard()
    }
  }
})

onUnmounted(() => cardSession?.destroy())

watch(showCardForm, async (show) => {
  if (show) {
    await nextTick()
    await initCard()
  } else {
    cardSession?.destroy()
    cardSession = null
  }
})

async function initCard() {
  if (!cardMount.value) return
  cardSession?.destroy()
  error.value = ''
  try {
    cardSession = await mountSaveCardElement(cardMount.value)
  } catch (e) {
    error.value = e.message
  }
}

async function saveCard() {
  if (!cardSession) return
  busy.value = true
  error.value = ''
  try {
    savedCard.value = await cardSession.confirmSave()
    replaceMode.value = false
    cardSession.destroy()
    cardSession = null
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

async function removeCard() {
  busy.value = true
  error.value = ''
  try {
    await api.removeStripeSavedPaymentMethod()
    savedCard.value = { has_saved_payment_method: false }
    replaceMode.value = false
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="fx-page" style="max-width:480px">
    <div class="d-flex align-items-center gap-2 mb-3">
      <button class="btn btn-light rounded-circle" style="width:34px;height:34px;padding:0" @click="router.back()">
        <AppIcon name="back" :size="16" />
      </button>
      <h1 class="fw-bold m-0" style="font-size:20px">Payment methods</h1>
      <span class="fx-badge ms-auto" style="background:var(--fx-warn-soft);color:var(--fx-warn);font-size:10px">Stripe test</span>
    </div>

    <div class="fx-card">
      <div v-if="loading" style="font-size:13px;color:var(--fx-muted)">Loading…</div>
      <div v-else-if="!configured" style="font-size:13px;color:var(--fx-muted)">
        Stripe test mode is not configured on the server.
      </div>
      <template v-else>
        <div v-if="error" class="alert alert-danger py-2 mb-2" style="font-size:13px">{{ error }}</div>

        <div v-if="savedCard?.has_saved_payment_method && !replaceMode">
          <div class="d-flex align-items-center gap-2 mb-2">
            <AppIcon name="shield" :size="18" />
            <span class="fw-semibold" style="font-size:14px">Saved test card</span>
          </div>
          <p style="font-size:13px;color:var(--fx-muted);margin-bottom:14px">{{ savedCardLabel }}</p>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-primary flex-fill" :disabled="busy" @click="replaceMode = true">Replace</button>
            <button class="btn btn-outline-danger flex-fill" :disabled="busy" @click="removeCard">Remove</button>
          </div>
        </div>

        <div v-if="showCardForm">
          <div class="fw-semibold mb-2" style="font-size:14px">
            {{ replaceMode ? 'Replace card' : 'Add a payment card' }}
          </div>
          <p style="font-size:12px;color:var(--fx-muted)">
            Use Stripe test card <strong>4242 4242 4242 4242</strong>, any future expiry, any CVC.
            Card details go directly to Stripe — never stored on our servers.
          </p>
          <div ref="cardMount" class="mb-3" style="min-height:120px"></div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary flex-fill" :disabled="busy" @click="saveCard">
              {{ busy ? 'Saving…' : 'Save card' }}
            </button>
            <button v-if="replaceMode" class="btn btn-outline-secondary" :disabled="busy" @click="replaceMode = false">Cancel</button>
          </div>
        </div>
      </template>
    </div>

    <div class="mt-3 p-3" style="font-size:11px;color:var(--fx-muted);background:var(--fx-border-soft);border-radius:10px">
      <strong>Security:</strong> Only Stripe IDs (cus_, pm_) are stored. Raw card numbers, expiry, and CVC never touch our database.
    </div>
  </div>
</template>
