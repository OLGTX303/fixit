<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useBookingsStore } from '../stores/bookings'
import { useWalletStore } from '../stores/wallet'
import { getStripe, mountSaveCardElement } from '../services/stripePayments'
import * as api from '../services/api'

const auth = useAuthStore()
const bookingsStore = useBookingsStore()
const wallet = useWalletStore()

const isProvider = computed(() => auth.role === 'provider')

// ── Wallet balance — from the server ledger (single source of truth) ─────────
const balance = computed(() => wallet.balanceCents / 100)

onMounted(async () => {
  await bookingsStore.load()
  if (!isProvider.value) {
    try { await wallet.load() } catch { /* stripe may be unconfigured */ }
  }
})

// ── Top-up / withdraw bottom sheet ───────────────────────────────────────────
const TOPUP_OPTIONS = [10, 20, 50, 100, 200]
const showSheet  = ref(false)
const sheetMode  = ref('topup')  // 'topup' | 'withdraw'
const topupAmt   = ref(null)
const sheetStep  = ref('amount') // 'amount' | 'pay'

// Stripe state
const cardMountEl      = ref(null)
const stripeLoading    = ref(false)
const stripeBusy       = ref(false)
const stripeErr        = ref('')
const stripeOk         = ref(false)
const stripeConfigured = ref(false)
const savedCard        = ref(null)   // { has_saved_payment_method, brand, last4 }
const useNewCard       = ref(false)  // toggle to enter a new card even if one is saved
let cardSession = null

const hasSavedCard = computed(() =>
  savedCard.value?.has_saved_payment_method && !useNewCard.value)

const savedCardLabel = computed(() => {
  if (!savedCard.value?.has_saved_payment_method) return ''
  const brand = (savedCard.value.brand || 'Card').replace(/^./, c => c.toUpperCase())
  return `${brand} •••• ${savedCard.value.last4}`
})

function openSheet(mode = 'topup') {
  sheetMode.value = mode
  topupAmt.value  = null
  sheetStep.value = 'amount'
  stripeErr.value = ''
  stripeOk.value  = false
  useNewCard.value = false
  showSheet.value = true
  document.body.classList.add('sheet-open')
}
const openWithdraw = () => openSheet('withdraw')

function closeSheet() {
  showSheet.value = false
  document.body.classList.remove('sheet-open')
  cardSession?.destroy()
  cardSession = null
  useNewCard.value = false
}

function selectAmt(amt) { topupAmt.value = amt }

async function mountNewCardForm() {
  await nextTick()
  if (!cardMountEl.value) return
  try {
    cardSession?.destroy()
    cardSession = null
    cardSession = await mountSaveCardElement(cardMountEl.value)
  } catch (e) {
    stripeErr.value = e.message
  }
}

async function proceedToPay() {
  if (!topupAmt.value) return

  // Withdraw needs no card — refund prior top-ups straight away.
  if (sheetMode.value === 'withdraw') {
    sheetStep.value = 'pay'
    return doWithdraw()
  }

  sheetStep.value    = 'pay'
  stripeLoading.value = true
  stripeErr.value    = ''

  try {
    const { config } = await getStripe()
    stripeConfigured.value = config.configured
    savedCard.value = config.saved_payment_method
  } catch (e) {
    stripeErr.value = e.message
    stripeLoading.value = false
    return
  }

  stripeLoading.value = false

  // If no saved card, mount the Stripe element immediately
  if (!hasSavedCard.value) {
    await mountNewCardForm()
  }
}

async function switchToNewCard() {
  useNewCard.value = true
  stripeErr.value  = ''
  await mountNewCardForm()
}

async function removeCard() {
  stripeBusy.value = true
  stripeErr.value  = ''
  try {
    await api.removeStripeSavedPaymentMethod()
    savedCard.value = { has_saved_payment_method: false }
    useNewCard.value = false
    await mountNewCardForm()
  } catch (e) {
    stripeErr.value = e.message
  } finally {
    stripeBusy.value = false
  }
}

// Real top-up: charges the card (Stripe sandbox PaymentIntent) and credits the
// server ledger. No localStorage — the store holds the authoritative balance.
async function realTopUp() {
  const res = await wallet.topUp(Math.round(topupAmt.value * 100))
  if (res.requires_action) {
    throw new Error('Card needs extra authentication — try a 4242 test card')
  }
  stripeOk.value = true
  setTimeout(() => closeSheet(), 1800)
}

// Pay with saved card (no card entry needed)
async function payWithSaved() {
  stripeBusy.value = true
  stripeErr.value  = ''
  try {
    await realTopUp()
  } catch (e) {
    stripeErr.value = e.message
  } finally {
    stripeBusy.value = false
  }
}

// Pay by submitting the Stripe element (new card), then top up
async function confirmNewCard() {
  if (!cardSession) return
  stripeBusy.value = true
  stripeErr.value  = ''
  try {
    await cardSession.confirmSave()
    const cfg = await api.getStripeConfig()
    savedCard.value = cfg.saved_payment_method
    useNewCard.value = false
    await realTopUp()
  } catch (e) {
    stripeErr.value = e.message
  } finally {
    stripeBusy.value = false
  }
}

// Real withdraw: refunds prior top-ups (Stripe sandbox Refund) and debits ledger.
async function doWithdraw() {
  stripeBusy.value = true
  stripeErr.value  = ''
  try {
    await wallet.withdraw(Math.round(topupAmt.value * 100))
    stripeOk.value = true
    setTimeout(() => closeSheet(), 1800)
  } catch (e) {
    stripeErr.value = e.message
  } finally {
    stripeBusy.value = false
  }
}

onUnmounted(() => {
  cardSession?.destroy()
  document.body.classList.remove('sheet-open')
})

// ── Provider income ─────────────────────────────────────────────────────────
const providerBookings = computed(() => {
  if (!isProvider.value) return []
  return bookingsStore.bookings.filter(
    b => b.provider_id === auth.user?.provider_id &&
         ['completed','reviewed'].includes(b.status)
  )
})
const totalIncome = computed(() =>
  providerBookings.value.reduce((s, b) => s + parseFloat(b.total_price || b.provider?.base_rate || 0), 0)
)
const pendingPayout = computed(() => (totalIncome.value * 0.85).toFixed(2))

// ── Transactions ─────────────────────────────────────────────────────────────
const transactions = computed(() => {
  const txs = []
  if (isProvider.value) {
    providerBookings.value.forEach(b => txs.push({
      id: b.id,
      label: `${b.category?.name || 'Service'} — #${b.id}`,
      sub: `From ${b.customer?.name || 'Customer'}`,
      amount: `+RM ${parseFloat(b.total_price || b.provider?.base_rate || 0).toFixed(2)}`,
      positive: true,
      date: b.updated_at || b.created_at,
    }))
  } else {
    // Real ledger entries (top-ups + withdrawals) from the server.
    wallet.transactions.forEach(tx => {
      const positive = tx.amount_cents > 0
      txs.push({
        id: `w${tx.id}`,
        label: tx.kind === 'topup' ? 'Wallet Top Up' : 'Withdrawal',
        sub: tx.note || (tx.kind === 'topup' ? 'Via Stripe (test)' : 'Refund to card'),
        amount: `${positive ? '+' : '-'}RM ${(Math.abs(tx.amount_cents) / 100).toFixed(2)}`,
        positive,
        date: tx.created_at,
      })
    })
    bookingsStore.forCustomer(auth.user?.id)
      .filter(b => ['completed','reviewed'].includes(b.status))
      .forEach(b => txs.push({
        id: b.id,
        label: `${b.category?.name || 'Service'} — #${b.id}`,
        sub: b.provider?.name || 'Provider',
        amount: `-RM ${parseFloat(b.total_price || b.provider?.base_rate || 0).toFixed(2)}`,
        positive: false,
        date: b.updated_at || b.created_at,
      }))
    txs.sort((a, b) => new Date(b.date) - new Date(a.date))
  }
  return txs
})

function fmtDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-MY', { day:'numeric', month:'short', year:'numeric' })
}
</script>

<template>
  <div class="wv-root">
    <header class="wv-header">
      <h1 class="wv-title">{{ isProvider ? 'My Earnings' : 'My Wallet' }}</h1>
    </header>

    <!-- ── PROVIDER VIEW ── -->
    <template v-if="isProvider">
      <div class="wv-hero liquid-glass">
        <div class="wv-hero-label">Total Earnings</div>
        <div class="wv-hero-amount">RM {{ totalIncome.toFixed(2) }}</div>
        <div class="wv-hero-sub">
          After 15% platform fee:
          <strong style="color:#22c55e">RM {{ pendingPayout }}</strong>
        </div>
        <div class="wv-hero-stats">
          <div class="wv-stat">
            <span class="wv-stat-val">{{ providerBookings.length }}</span>
            <span class="wv-stat-lbl">Jobs Done</span>
          </div>
          <div class="wv-stat-div"></div>
          <div class="wv-stat">
            <span class="wv-stat-val">RM {{ (totalIncome * 0.15).toFixed(2) }}</span>
            <span class="wv-stat-lbl">Platform Fee</span>
          </div>
          <div class="wv-stat-div"></div>
          <div class="wv-stat">
            <span class="wv-stat-val">RM {{ (totalIncome / Math.max(providerBookings.length,1)).toFixed(0) }}</span>
            <span class="wv-stat-lbl">Avg / Job</span>
          </div>
        </div>
      </div>
      <div style="padding:0 16px 16px">
        <button class="wv-primary-btn">
          <span class="material-symbols-outlined" style="font-size:18px">account_balance</span>
          Withdraw to Bank
        </button>
      </div>
    </template>

    <!-- ── CUSTOMER VIEW ── -->
    <template v-else>
      <div class="wv-hero liquid-glass">
        <div class="wv-hero-label">Wallet Balance</div>
        <div class="wv-hero-amount">RM {{ balance.toFixed(2) }}</div>
        <div class="wv-hero-actions">
          <button class="wv-quick-action" @click="openSheet('topup')">
            <span class="material-symbols-outlined" style="font-size:22px">add_circle</span>
            Top Up
          </button>
          <button class="wv-quick-action" :disabled="balance <= 0" @click="openWithdraw">
            <span class="material-symbols-outlined" style="font-size:22px">account_balance</span>
            Withdraw
          </button>
          <button class="wv-quick-action">
            <span class="material-symbols-outlined" style="font-size:22px">history</span>
            History
          </button>
        </div>
      </div>
    </template>

    <!-- Transactions -->
    <div class="wv-section">
      <div class="wv-section-head">
        <span class="wv-section-title">{{ isProvider ? 'Earnings History' : 'Transactions' }}</span>
      </div>
      <div v-if="!transactions.length" class="wv-empty">
        <span class="material-symbols-outlined" style="font-size:42px;opacity:.2">receipt_long</span>
        <p>No transactions yet.</p>
      </div>
      <div v-else class="wv-tx-list">
        <div v-for="tx in transactions" :key="tx.id" class="wv-tx liquid-glass">
          <div class="wv-tx-icon" :class="{ income: tx.positive }">
            <span class="material-symbols-outlined" style="font-size:18px;font-variation-settings:'FILL' 1">
              {{ tx.positive ? 'arrow_downward' : 'arrow_upward' }}
            </span>
          </div>
          <div class="wv-tx-info">
            <span class="wv-tx-label">{{ tx.label }}</span>
            <span class="wv-tx-sub">{{ tx.sub }}</span>
            <span class="wv-tx-date">{{ fmtDate(tx.date) }}</span>
          </div>
          <span class="wv-tx-amount" :class="{ positive: tx.positive }">{{ tx.amount }}</span>
        </div>
      </div>
    </div>

    <!-- ── HALF-SCREEN BOTTOM SHEET ── -->
    <Teleport to="body">
      <!-- Backdrop -->
      <Transition name="fade">
        <div v-if="showSheet" class="wv-backdrop" @click="closeSheet"></div>
      </Transition>

      <!-- Sheet panel -->
      <Transition name="slide-up">
        <div v-if="showSheet" class="wv-sheet">
          <!-- Drag handle -->
          <div class="wv-sheet-handle"></div>

          <!-- Header -->
          <div class="wv-sheet-header">
            <button v-if="sheetStep === 'pay'" class="wv-sheet-back" @click="sheetStep = 'amount'">
              <span class="material-symbols-outlined" style="font-size:20px">arrow_back_ios</span>
            </button>
            <span class="wv-sheet-title">
              {{ sheetStep === 'amount'
                ? (sheetMode === 'withdraw' ? 'Withdraw' : 'Top Up Wallet')
                : (sheetMode === 'withdraw' ? `Withdraw RM ${topupAmt?.toFixed(2)}` : `Pay RM ${topupAmt?.toFixed(2)}`) }}
            </span>
            <button class="wv-sheet-close" @click="closeSheet">
              <span class="material-symbols-outlined" style="font-size:20px">close</span>
            </button>
          </div>

          <!-- STEP 1: Amount selection -->
          <div v-if="sheetStep === 'amount'" class="wv-sheet-body">
            <p class="wv-sheet-sub">
              {{ sheetMode === 'withdraw'
                ? `Refunded to your card. Available: RM ${balance.toFixed(2)}`
                : 'Select the amount you want to add to your wallet' }}
            </p>
            <div class="wv-amt-grid">
              <button
                v-for="amt in TOPUP_OPTIONS" :key="amt"
                class="wv-amt-opt" :class="{ selected: topupAmt === amt }"
                :disabled="sheetMode === 'withdraw' && amt > balance"
                @click="selectAmt(amt)"
              >
                <span class="wv-amt-val">RM {{ amt }}</span>
              </button>
            </div>
            <div class="wv-sheet-footer">
              <button
                class="wv-primary-btn"
                :disabled="!topupAmt"
                @click="proceedToPay"
              >
                Continue — RM {{ topupAmt?.toFixed(2) ?? '0.00' }}
              </button>
            </div>
          </div>

          <!-- STEP 2: Stripe payment -->
          <div v-else class="wv-sheet-body">
            <!-- Success state -->
            <div v-if="stripeOk" class="wv-pay-success">
              <div class="wv-success-icon">
                <span class="material-symbols-outlined" style="font-size:36px;font-variation-settings:'FILL' 1">check_circle</span>
              </div>
              <div class="wv-success-title">
                {{ sheetMode === 'withdraw' ? 'Withdrawal Complete!' : 'Payment Successful!' }}
              </div>
              <div class="wv-success-sub">
                RM {{ topupAmt?.toFixed(2) }}
                {{ sheetMode === 'withdraw' ? 'refunded to your card' : 'added to your wallet' }}
              </div>
            </div>

            <!-- Withdraw: no card UI, just process -->
            <div v-else-if="sheetMode === 'withdraw'" class="wv-pay-loading">
              <div v-if="stripeBusy" class="wv-spinner"></div>
              <span v-if="stripeBusy">Processing withdrawal…</span>
              <div v-if="stripeErr" class="wv-stripe-err">
                <span class="material-symbols-outlined" style="font-size:15px">error</span>
                {{ stripeErr }}
              </div>
            </div>

            <template v-else>
              <!-- Loading -->
              <div v-if="stripeLoading" class="wv-pay-loading">
                <div class="wv-spinner"></div>
                <span>Loading…</span>
              </div>

              <!-- Stripe not configured -->
              <div v-else-if="!stripeConfigured" class="wv-pay-unconfigured">
                <span class="material-symbols-outlined" style="font-size:36px;opacity:.3">credit_card_off</span>
                <p>Stripe test mode is not configured on the server.</p>
              </div>

              <template v-else>
                <!-- ── SAVED CARD: one-tap pay ── -->
                <template v-if="hasSavedCard">
                  <div class="wv-saved-card">
                    <div class="wv-saved-card-icon">
                      <span class="material-symbols-outlined" style="font-size:22px;font-variation-settings:'FILL' 1">credit_card</span>
                    </div>
                    <div class="wv-saved-card-info">
                      <span class="wv-saved-card-label">Saved card</span>
                      <span class="wv-saved-card-num">{{ savedCardLabel }}</span>
                    </div>
                    <span class="wv-saved-card-ok">✓</span>
                  </div>

                  <div class="wv-card-actions">
                    <button class="wv-card-action-btn" :disabled="stripeBusy" @click="switchToNewCard">
                      <span class="material-symbols-outlined" style="font-size:15px">swap_horiz</span>
                      Use different card
                    </button>
                    <button class="wv-card-action-btn danger" :disabled="stripeBusy" @click="removeCard">
                      <span class="material-symbols-outlined" style="font-size:15px">delete</span>
                      Remove
                    </button>
                  </div>
                </template>

                <!-- ── NO SAVED CARD: enter new card ── -->
                <template v-else>
                  <div class="wv-stripe-badge">
                    <span class="material-symbols-outlined" style="font-size:14px">lock</span>
                    Test card: <strong>4242 4242 4242 4242</strong> · any future date · any CVC
                  </div>
                  <div ref="cardMountEl" class="wv-card-mount"></div>
                </template>

                <!-- Error -->
                <div v-if="stripeErr" class="wv-stripe-err">
                  <span class="material-symbols-outlined" style="font-size:15px">error</span>
                  {{ stripeErr }}
                </div>

                <div class="wv-sheet-footer">
                  <button
                    class="wv-primary-btn"
                    :disabled="stripeBusy"
                    @click="hasSavedCard ? payWithSaved() : confirmNewCard()"
                  >
                    <div v-if="stripeBusy" class="wv-spinner white"></div>
                    <template v-else>
                      <span class="material-symbols-outlined" style="font-size:18px">lock</span>
                      {{ hasSavedCard ? `Pay RM ${topupAmt?.toFixed(2)}` : `Add Card & Pay RM ${topupAmt?.toFixed(2)}` }}
                    </template>
                  </button>
                </div>
              </template>
            </template>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
.wv-root { min-height: 100vh; background: var(--fx-bg); padding-bottom: 100px; }

.wv-header { padding: 16px 16px 12px; }
.wv-title  { font-size: 20px; font-weight: 800; color: var(--fx-text); margin: 0; }

/* Hero card */
.wv-hero {
  margin: 0 12px 12px; border-radius: 22px; padding: 24px;
  background: linear-gradient(145deg, rgba(255,102,53,0.12), rgba(255,181,159,0.08));
}
.wv-hero-label  { font-size: 13px; color: var(--fx-muted); margin-bottom: 6px; }
.wv-hero-amount { font-size: 38px; font-weight: 900; color: var(--fx-text); letter-spacing: -1px; margin-bottom: 20px; }
.wv-hero-sub    { font-size: 13px; color: var(--fx-muted); margin-bottom: 20px; }

.wv-hero-stats { display: flex; align-items: center; }
.wv-stat { flex: 1; text-align: center; }
.wv-stat-val { display: block; font-size: 16px; font-weight: 800; color: var(--fx-text); }
.wv-stat-lbl { font-size: 11px; color: var(--fx-muted); }
.wv-stat-div { width: 1px; height: 36px; background: rgba(0,0,0,0.08); flex-shrink: 0; }

.wv-hero-actions { display: flex; gap: 12px; }
.wv-quick-action {
  flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px;
  padding: 12px 8px; border-radius: 14px; border: none; cursor: pointer;
  background: rgba(255,255,255,0.50); color: var(--fx-accent);
  font-size: 12px; font-weight: 600; font-family: inherit;
}
.wv-quick-action:active { transform: scale(0.95); }

/* Shared primary button */
.wv-primary-btn {
  width: 100%; padding: 14px; border-radius: 14px; border: none; cursor: pointer;
  background: linear-gradient(180deg, #FF7D54, #FF6635); color: #fff;
  font-size: 15px; font-weight: 700; font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 4px 16px rgba(255,102,53,0.28);
  transition: opacity 0.2s, transform 0.15s;
}
.wv-primary-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.wv-primary-btn:not(:disabled):active { transform: scale(0.98); }

/* Transactions */
.wv-section { padding: 0 0 20px; }
.wv-section-head { padding: 8px 16px 10px; }
.wv-section-title { font-size: 16px; font-weight: 700; color: var(--fx-text); }
.wv-empty {
  text-align: center; padding: 40px 24px; color: var(--fx-muted);
  font-size: 14px; display: flex; flex-direction: column; align-items: center; gap: 8px;
}
.wv-tx-list { display: flex; flex-direction: column; gap: 0; }
.wv-tx {
  display: flex; align-items: center; gap: 12px;
  margin: 0 12px 8px; border-radius: 14px; padding: 14px;
}
.wv-tx-icon {
  width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: rgba(239,68,68,0.10); color: #ef4444;
}
.wv-tx-icon.income { background: rgba(34,197,94,0.10); color: #22c55e; }
.wv-tx-info { flex: 1; min-width: 0; }
.wv-tx-label { display: block; font-size: 13px; font-weight: 700; color: var(--fx-text); }
.wv-tx-sub   { display: block; font-size: 11px; color: var(--fx-muted); }
.wv-tx-date  { font-size: 11px; color: var(--fx-muted); }
.wv-tx-amount { font-size: 14px; font-weight: 800; color: #ef4444; flex-shrink: 0; }
.wv-tx-amount.positive { color: #22c55e; }

/* ── Bottom sheet ─────────────────────────────────────────────────────────── */
.wv-backdrop {
  position: fixed; inset: 0; background: rgba(0,0,0,0.38);
  z-index: 200; backdrop-filter: blur(2px);
}
.wv-sheet {
  position: fixed; bottom: 0; left: 0; right: 0; z-index: 201;
  background: var(--fx-bg, #f5f5f7);
  border-radius: 24px 24px 0 0;
  box-shadow: 0 -8px 40px rgba(0,0,0,0.18);
  max-height: 62vh;
  display: flex; flex-direction: column;
}

.wv-sheet-handle {
  width: 40px; height: 4px; border-radius: 2px;
  background: rgba(0,0,0,0.18); margin: 12px auto 0;
  flex-shrink: 0;
}

.wv-sheet-header {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 16px 10px; flex-shrink: 0;
  border-bottom: 1px solid rgba(0,0,0,0.06);
}
.wv-sheet-back, .wv-sheet-close {
  background: none; border: none; cursor: pointer; padding: 4px;
  display: flex; align-items: center; color: var(--fx-muted);
}
.wv-sheet-title { flex: 1; font-size: 16px; font-weight: 700; color: var(--fx-text); }

.wv-sheet-body {
  flex: 1; overflow-y: auto; padding: 16px;
  display: flex; flex-direction: column;
}
.wv-sheet-sub { font-size: 13px; color: var(--fx-muted); margin-bottom: 16px; }

.wv-sheet-footer { margin-top: auto; padding-top: 16px; padding-bottom: 8px; }

/* Amount grid */
.wv-amt-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
  margin-bottom: 16px;
}
.wv-amt-opt {
  padding: 16px 0; border-radius: 14px;
  border: 2px solid rgba(0,0,0,0.08);
  background: rgba(255,255,255,0.60);
  cursor: pointer; font-family: inherit;
  transition: border-color 0.18s, background 0.18s, transform 0.12s;
}
.wv-amt-opt:active { transform: scale(0.95); }
.wv-amt-opt.selected {
  border-color: var(--fx-accent);
  background: rgba(255,102,53,0.08);
}
.wv-amt-val { display: block; font-size: 16px; font-weight: 800; color: var(--fx-text); }
.wv-amt-opt.selected .wv-amt-val { color: var(--fx-accent); }

/* Stripe area */
.wv-stripe-badge {
  display: flex; align-items: center; gap: 6px;
  padding: 8px 12px; border-radius: 8px; margin-bottom: 14px;
  background: rgba(99,102,241,0.08); color: #4f46e5;
  font-size: 12px; font-weight: 600;
}
.wv-card-mount { min-height: 140px; margin-bottom: 4px; }

/* Saved card row */
.wv-saved-card {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px; border-radius: 14px; margin-bottom: 10px;
  background: rgba(255,102,53,0.07); border: 1.5px solid rgba(255,102,53,0.2);
}
.wv-saved-card-icon {
  width: 40px; height: 40px; border-radius: 10px;
  background: linear-gradient(135deg,#FF7D54,#FF6635);
  display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0;
}
.wv-saved-card-info { flex: 1; min-width: 0; }
.wv-saved-card-label { display: block; font-size: 11px; color: var(--fx-muted); font-weight: 600; text-transform: uppercase; letter-spacing:.5px; }
.wv-saved-card-num   { display: block; font-size: 15px; font-weight: 700; color: var(--fx-text); }
.wv-saved-card-ok    { font-size: 18px; color: #22c55e; font-weight: 800; }

.wv-card-actions { display: flex; gap: 8px; margin-bottom: 12px; }
.wv-card-action-btn {
  flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px;
  padding: 8px 10px; border-radius: 10px; border: 1.5px solid var(--fx-border);
  background: var(--fx-glass-bg); color: var(--fx-text); font-size: 12px; font-weight: 600;
  cursor: pointer; transition: background .15s;
}
.wv-card-action-btn:hover { background: rgba(0,0,0,0.06); }
.wv-card-action-btn.danger { color: #ef4444; border-color: rgba(239,68,68,.25); }
.wv-card-action-btn.danger:hover { background: rgba(239,68,68,.07); }
.wv-card-action-btn:disabled { opacity: .45; cursor: default; }

.wv-stripe-err {
  display: flex; align-items: center; gap: 6px;
  color: #ef4444; font-size: 13px; padding: 6px 0;
}

.wv-pay-loading, .wv-pay-unconfigured {
  display: flex; flex-direction: column; align-items: center;
  gap: 10px; padding: 30px 0; color: var(--fx-muted); font-size: 14px;
}

/* Success */
.wv-pay-success {
  display: flex; flex-direction: column; align-items: center;
  gap: 10px; padding: 30px 0; text-align: center;
}
.wv-success-icon {
  width: 72px; height: 72px; border-radius: 50%;
  background: rgba(34,197,94,0.12); color: #22c55e;
  display: flex; align-items: center; justify-content: center;
}
.wv-success-title { font-size: 18px; font-weight: 800; color: var(--fx-text); }
.wv-success-sub   { font-size: 14px; color: var(--fx-muted); }

/* Spinner */
.wv-spinner {
  width: 20px; height: 20px; border-radius: 50%;
  border: 2.5px solid rgba(255,102,53,0.25);
  border-top-color: var(--fx-accent);
  animation: spin 0.7s linear infinite;
}
.wv-spinner.white {
  border-color: rgba(255,255,255,0.3);
  border-top-color: #fff;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

.slide-up-enter-active, .slide-up-leave-active { transition: transform 0.32s cubic-bezier(0.32,0.72,0,1); }
.slide-up-enter-from, .slide-up-leave-to       { transform: translateY(100%); }
</style>
