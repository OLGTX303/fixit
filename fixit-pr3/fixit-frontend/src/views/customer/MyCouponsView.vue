<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import * as api from '../../services/api'

const router = useRouter()
const coupons = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    coupons.value = await api.getAvailableCoupons() // system-wide
  } catch {
    coupons.value = []
  } finally {
    loading.value = false
  }
})

function discountLabel(c) {
  return c.discount_type === 'percent'
    ? `${c.discount_value}% off${c.max_discount ? ` (up to RM${c.max_discount})` : ''}`
    : `RM${c.discount_value} off`
}

function expiryLabel(c) {
  if (!c.expires_at) return ''
  return `Valid until ${String(c.expires_at).slice(0, 10)}`
}

const copied = ref('')
function copyCode(code) {
  navigator.clipboard?.writeText(code)
  copied.value = code
  setTimeout(() => { if (copied.value === code) copied.value = '' }, 1500)
}
</script>

<template>
  <div class="mc-root">
    <header class="mc-header">
      <button class="mc-back" @click="router.back()">
        <span class="material-symbols-outlined" style="font-size:22px">arrow_back</span>
      </button>
      <h1 class="mc-title">Coupons</h1>
    </header>

    <div v-if="loading" class="mc-empty">
      <span class="material-symbols-outlined mc-empty-icon">hourglass_empty</span>
      <p>Loading…</p>
    </div>

    <div v-else-if="!coupons.length" class="mc-empty">
      <span class="material-symbols-outlined mc-empty-icon">redeem</span>
      <p>No coupons available right now.</p>
      <p class="mc-note">Providers may offer their own coupons at checkout.</p>
    </div>

    <div v-else class="mc-list">
      <p class="mc-note">Enter a coupon code at checkout to redeem. Provider-specific coupons appear when you book that provider.</p>
      <div v-for="c in coupons" :key="c.id" class="mc-card">
        <div class="mc-card-left">
          <div class="mc-disc">{{ discountLabel(c) }}</div>
          <div class="mc-code">{{ c.code }}</div>
          <div v-if="c.min_spend > 0" class="mc-min">Min spend RM{{ c.min_spend }}</div>
          <div v-if="expiryLabel(c)" class="mc-exp">{{ expiryLabel(c) }}</div>
        </div>
        <button class="mc-copy" @click="copyCode(c.code)">{{ copied === c.code ? 'Copied' : 'Copy' }}</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.mc-root { min-height: 100vh; background: var(--fx-bg); padding-bottom: 80px; }
.mc-header { display: flex; align-items: center; gap: 8px; padding: 16px; }
.mc-back { background: none; border: none; cursor: pointer; color: var(--fx-text); padding: 4px; }
.mc-title { font-size: 20px; font-weight: 800; margin: 0; color: var(--fx-text); }
.mc-empty { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 64px 24px; color: var(--fx-muted); text-align: center; }
.mc-empty-icon { font-size: 52px; opacity: .25; }
.mc-note { font-size: 13px; color: var(--fx-muted); padding: 0 16px; margin: 0 0 4px; }
.mc-list { display: flex; flex-direction: column; gap: 12px; padding: 8px 12px 24px; }
.mc-card {
  display: flex; align-items: center; justify-content: space-between;
  background: var(--fx-card, #fff); border: 1px dashed var(--fx-accent);
  border-radius: 12px; padding: 14px 16px;
}
.mc-card-left { display: flex; flex-direction: column; gap: 2px; }
.mc-disc { font-size: 16px; font-weight: 800; color: var(--fx-accent); }
.mc-code { font-size: 14px; font-weight: 700; letter-spacing: .5px; color: var(--fx-text); }
.mc-min, .mc-exp { font-size: 12px; color: var(--fx-muted); }
.mc-copy {
  padding: 8px 18px; border-radius: 999px; background: var(--fx-accent);
  color: #fff; border: none; cursor: pointer; font-size: 13px; font-weight: 700; font-family: inherit;
}
</style>
