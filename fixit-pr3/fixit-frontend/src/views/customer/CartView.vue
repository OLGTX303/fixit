<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import * as api from '../../services/api'
import { isDesktop } from '../../composables/useViewport.js'

const router = useRouter()
const cart = ref([])
const selectedId = ref(null)
const services = ref([])
const loadingServices = ref(false)
const mobileStep = ref('providers')

function loadCart() {
  try { cart.value = JSON.parse(localStorage.getItem('fixit_cart') || '[]') } catch { cart.value = [] }
}
function saveCart() {
  localStorage.setItem('fixit_cart', JSON.stringify(cart.value))
}
function removeItem(id) {
  cart.value = cart.value.filter((p) => p.id !== id)
  saveCart()
  if (selectedId.value === id) {
    selectedId.value = cart.value[0]?.id ?? null
  }
}
function clearCart() {
  cart.value = []
  saveCart()
  selectedId.value = null
  services.value = []
}

const selectedProvider = computed(() => cart.value.find((p) => p.id === selectedId.value) ?? null)

function initials(name) {
  return (name || '—').split(' ').map((w) => w[0]).join('').slice(0, 2).toUpperCase()
}

async function loadServices(providerId) {
  if (!providerId) { services.value = []; return }
  loadingServices.value = true
  try {
    const list = await api.getProviderServices(providerId)
    services.value = (list || []).filter((s) => s.is_active !== false)
  } catch {
    services.value = []
  } finally {
    loadingServices.value = false
  }
}

function selectProvider(item) {
  selectedId.value = item.id
  if (!isDesktop.value) mobileStep.value = 'services'
}

function checkout(svc) {
  if (!selectedProvider.value) return
  router.push({
    name: 'booking-form',
    params: { id: selectedProvider.value.id },
    query: { service: svc.name, price: svc.price, service_id: svc.id },
  })
}

watch(selectedId, (id) => { if (id) loadServices(id) }, { immediate: true })

watch(cart, (list) => {
  if (!list.length) {
    selectedId.value = null
    services.value = []
    mobileStep.value = 'providers'
    return
  }
  if (!selectedId.value || !list.some((p) => p.id === selectedId.value)) {
    selectedId.value = list[0].id
  }
}, { immediate: true })

onMounted(loadCart)
</script>

<template>
  <!-- ── DESKTOP: split panel — providers | services ─────────────────── -->
  <div v-if="isDesktop" class="cart-split">

    <div class="cart-panel-left">
      <div class="cart-panel-header">
        <div>
          <span class="cart-panel-title">Saved Providers</span>
          <span class="cart-panel-sub">{{ cart.length }} saved</span>
        </div>
        <button v-if="cart.length" class="cart-clear-btn" @click="clearCart">Clear all</button>
      </div>

      <div class="cart-provider-list">
        <button
          v-for="item in cart" :key="item.id"
          class="cart-provider-row"
          :class="{ active: selectedId === item.id }"
          @click="selectProvider(item)"
        >
          <div class="cart-provider-avatar"
               :style="item.avatar_url ? { backgroundImage: `url(${item.avatar_url})` } : {}">
            <span v-if="!item.avatar_url">{{ initials(item.name) }}</span>
          </div>
          <div class="cart-provider-body">
            <div class="cart-provider-name">{{ item.name }}</div>
            <div class="cart-provider-sub">{{ item.category }}</div>
            <div v-if="item.rate" class="cart-provider-rate">RM{{ item.rate }}/hr</div>
          </div>
          <button class="cart-row-remove" aria-label="Remove" @click.stop="removeItem(item.id)">
            <span class="material-symbols-outlined" style="font-size:18px">close</span>
          </button>
        </button>

        <div v-if="!cart.length" class="cart-empty-panel">
          <span class="material-symbols-outlined" style="font-size:40px;color:var(--fx-muted-soft)">shopping_cart</span>
          <p>Your cart is empty</p>
          <button class="cart-find-btn" @click="router.push({ name: 'search' })">Find providers</button>
        </div>
      </div>
    </div>

    <div class="cart-panel-right">
      <template v-if="selectedProvider">
        <div class="cart-services-header">
          <div class="cart-services-avatar"
               :style="selectedProvider.avatar_url ? { backgroundImage: `url(${selectedProvider.avatar_url})` } : {}">
            <span v-if="!selectedProvider.avatar_url">{{ initials(selectedProvider.name) }}</span>
          </div>
          <div style="flex:1;min-width:0">
            <div class="cart-services-title">{{ selectedProvider.name }}</div>
            <div class="cart-services-sub">Select a service to continue to checkout</div>
          </div>
          <button class="cart-profile-link" @click="router.push({ name: 'provider-profile', params: { id: selectedProvider.id } })">
            View profile
          </button>
        </div>

        <div v-if="loadingServices" class="cart-services-loading">Loading services…</div>

        <div v-else-if="services.length" class="cart-services-list">
          <button
            v-for="svc in services" :key="svc.id"
            class="cart-service-card"
            @click="checkout(svc)"
          >
            <div class="cart-svc-thumb">
              <img v-if="svc.image_url" :src="svc.image_url" :alt="svc.name" class="cart-svc-thumb-img" />
              <span v-else class="material-symbols-outlined" style="font-size:28px;color:#FF6635;font-variation-settings:'FILL' 1">home_repair_service</span>
            </div>
            <div class="cart-svc-info">
              <div class="cart-svc-name">{{ svc.name }}</div>
              <div class="cart-svc-price">RM{{ svc.price }}</div>
              <div v-if="svc.description" class="cart-svc-desc">{{ svc.description }}</div>
            </div>
            <span class="cart-svc-chevron material-symbols-outlined">chevron_right</span>
          </button>
        </div>

        <div v-else class="cart-services-empty">
          <span class="material-symbols-outlined" style="font-size:48px;color:var(--fx-muted-soft)">inventory_2</span>
          <p>No services listed yet</p>
          <button class="cart-find-btn" @click="router.push({ name: 'provider-profile', params: { id: selectedProvider.id } })">
            View provider profile
          </button>
        </div>
      </template>

      <div v-else class="cart-select-empty">
        <span class="material-symbols-outlined" style="font-size:56px;color:var(--fx-muted-soft)">storefront</span>
        <p>Select a saved provider to choose a service</p>
      </div>
    </div>
  </div>

  <!-- ── MOBILE: two-step flow ─────────────────────────────────────────── -->
  <div v-else class="fx-page" style="max-width:560px">
    <!-- Step 1: providers -->
    <template v-if="mobileStep === 'providers'">
      <div class="cart-header">
        <div>
          <h1 style="font-size:22px;font-weight:800;margin:0">Saved Providers</h1>
          <p style="font-size:13px;color:var(--fx-muted);margin:4px 0 0">{{ cart.length }} saved</p>
        </div>
        <button v-if="cart.length" class="fx-btn-ghost" style="font-size:12px;color:var(--fx-error)" @click="clearCart">
          Clear all
        </button>
      </div>

      <div v-if="!cart.length" class="cart-empty fx-card">
        <span class="material-symbols-outlined" style="font-size:48px;color:var(--fx-muted-soft);display:block;margin-bottom:10px">shopping_cart</span>
        <div style="font-size:15px;font-weight:600;margin-bottom:6px">Your cart is empty</div>
        <div style="font-size:13px;color:var(--fx-muted);margin-bottom:18px">Save providers while browsing to book them later</div>
        <button class="glossy-primary" style="padding:10px 28px;border-radius:12px" @click="router.push({ name: 'search' })">
          Find Providers
        </button>
      </div>

      <div v-else class="d-flex flex-column gap-2">
        <button v-for="item in cart" :key="item.id" class="cart-card fx-card cart-mobile-row" @click="selectProvider(item)">
          <div class="d-flex align-items-center gap-3">
            <div class="fx-avatar" style="width:52px;height:52px;font-size:18px;font-weight:800;flex-shrink:0"
                 :style="{ backgroundImage: item.avatar_url ? `url(${item.avatar_url})` : '', backgroundSize:'cover', backgroundPosition:'center' }">
              <span v-if="!item.avatar_url">{{ initials(item.name) }}</span>
            </div>
            <div style="flex:1;min-width:0;text-align:left">
              <div style="font-size:15px;font-weight:700">{{ item.name }}</div>
              <div style="font-size:12px;color:var(--fx-muted)">{{ item.category }}</div>
              <div v-if="item.rate" style="font-size:12px;color:var(--fx-accent);font-weight:600;margin-top:2px">
                RM{{ item.rate }}/hr
              </div>
            </div>
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-muted-soft)">chevron_right</span>
          </div>
        </button>
      </div>
    </template>

    <!-- Step 2: services → checkout -->
    <template v-else-if="selectedProvider">
      <div class="cart-header">
        <button class="cart-back-btn" @click="mobileStep = 'providers'">
          <span class="material-symbols-outlined" style="font-size:20px">arrow_back</span>
        </button>
        <div style="flex:1;min-width:0">
          <h1 style="font-size:20px;font-weight:800;margin:0">{{ selectedProvider.name }}</h1>
          <p style="font-size:13px;color:var(--fx-muted);margin:4px 0 0">Choose a service</p>
        </div>
      </div>

      <div v-if="loadingServices" class="text-center py-5" style="font-size:13px;color:var(--fx-muted)">Loading services…</div>

      <div v-else-if="services.length" class="d-flex flex-column gap-2">
        <button v-for="svc in services" :key="svc.id" class="cart-card fx-card cart-mobile-row" @click="checkout(svc)">
          <div class="d-flex align-items-center gap-3">
            <div class="cart-svc-thumb" style="width:56px;height:56px">
              <img v-if="svc.image_url" :src="svc.image_url" :alt="svc.name" class="cart-svc-thumb-img" />
              <span v-else class="material-symbols-outlined" style="font-size:24px;color:#FF6635;font-variation-settings:'FILL' 1">home_repair_service</span>
            </div>
            <div style="flex:1;min-width:0;text-align:left">
              <div style="font-size:15px;font-weight:700">{{ svc.name }}</div>
              <div style="font-size:14px;font-weight:800;color:#FF6635">RM{{ svc.price }}</div>
              <div v-if="svc.description" style="font-size:12px;color:var(--fx-muted);margin-top:2px">{{ svc.description }}</div>
            </div>
            <span class="material-symbols-outlined" style="font-size:20px;color:var(--fx-muted-soft)">chevron_right</span>
          </div>
        </button>
      </div>

      <div v-else class="fx-card text-center py-5">
        <span class="material-symbols-outlined" style="font-size:44px;color:var(--fx-muted-soft);display:block;margin-bottom:12px">inventory_2</span>
        <div class="fw-semibold" style="font-size:15px;margin-bottom:4px">No services listed</div>
        <button class="btn btn-primary mt-3" style="border-radius:999px;padding:11px 28px"
                @click="router.push({ name: 'provider-profile', params: { id: selectedProvider.id } })">
          View profile
        </button>
      </div>
    </template>
  </div>
</template>

<style scoped>
/* ── Desktop split (layout shell in global styles.css) ─────────────────── */
.cart-panel-left {
  width: 300px;
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  background:
    radial-gradient(ellipse 60% 40% at 10% 5%, rgba(255,255,255,0.32) 0%, transparent 65%),
    rgba(255,255,255,0.08);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-right: 0.5px solid rgba(255,255,255,0.50);
  box-shadow: inset -1px 0 0 rgba(255,255,255,0.30);
}

.cart-panel-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 20px 20px 14px;
  border-bottom: 0.5px solid rgba(255,255,255,0.40);
  flex-shrink: 0;
}
.cart-panel-title { font-size: 20px; font-weight: 800; color: var(--fx-text); display: block; }
.cart-panel-sub   { font-size: 12px; color: var(--fx-muted); margin-top: 2px; display: block; }
.cart-clear-btn {
  background: none; border: none; cursor: pointer;
  font-size: 12px; font-weight: 600; color: var(--fx-error); flex-shrink: 0;
}

.cart-provider-list { flex: 1; overflow-y: auto; padding: 8px 0; }

.cart-provider-row {
  width: 100%; display: flex; align-items: center; gap: 12px;
  padding: 12px 16px; border: none; background: transparent; cursor: pointer;
  text-align: left; transition: background 0.12s; position: relative;
}
.cart-provider-row::before {
  content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px;
  background: var(--fx-accent); opacity: 0; border-radius: 0 3px 3px 0;
  transition: opacity 0.15s;
}
.cart-provider-row:hover { background: rgba(255,255,255,0.18); }
.cart-provider-row.active { background: rgba(255,102,53,0.08); }
.cart-provider-row.active::before { opacity: 1; }

.cart-provider-avatar {
  width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(160deg, #FF8056, #FF6635);
  background-size: cover; background-position: center;
  color: #fff; font-size: 15px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 8px rgba(255,102,53,0.22);
}
.cart-provider-body { flex: 1; min-width: 0; }
.cart-provider-name { font-size: 14px; font-weight: 600; color: var(--fx-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cart-provider-sub  { font-size: 12px; color: var(--fx-muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cart-provider-rate { font-size: 11px; color: var(--fx-accent); font-weight: 600; margin-top: 2px; }

.cart-row-remove {
  background: none; border: none; cursor: pointer;
  color: var(--fx-muted); padding: 4px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.cart-row-remove:hover { background: rgba(255,255,255,0.25); color: var(--fx-error); }

.cart-empty-panel, .cart-select-empty, .cart-services-empty {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; gap: 10px; text-align: center; padding: 48px 24px;
  color: var(--fx-muted); font-size: 14px; flex: 1;
}
.cart-empty-panel p, .cart-select-empty p, .cart-services-empty p { margin: 0; font-size: 13px; }
.cart-find-btn {
  margin-top: 6px; padding: 9px 22px; border-radius: 999px; border: none;
  background: var(--fx-accent); color: #fff; font-size: 13px; font-weight: 700; cursor: pointer;
}

.cart-panel-right {
  flex: 1; min-width: 0;
  display: flex; flex-direction: column;
  background: rgba(255,255,255,0.04);
}

.cart-services-header {
  display: flex; align-items: center; gap: 14px;
  padding: 20px 24px 16px; flex-shrink: 0;
  border-bottom: 0.5px solid rgba(255,255,255,0.35);
}
.cart-services-avatar {
  width: 48px; height: 48px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(160deg, #FF8056, #FF6635);
  background-size: cover; background-position: center;
  color: #fff; font-size: 16px; font-weight: 800;
  display: flex; align-items: center; justify-content: center;
}
.cart-services-title { font-size: 18px; font-weight: 800; color: var(--fx-text); }
.cart-services-sub   { font-size: 12px; color: var(--fx-muted); margin-top: 2px; }
.cart-profile-link {
  padding: 8px 14px; border-radius: 999px; border: 1.5px solid rgba(255,255,255,0.45);
  background: rgba(255,255,255,0.20); font-size: 12px; font-weight: 700;
  color: var(--fx-text); cursor: pointer; flex-shrink: 0;
}

.cart-services-loading {
  flex: 1; display: flex; align-items: center; justify-content: center;
  font-size: 13px; color: var(--fx-muted);
}

.cart-services-list {
  flex: 1; overflow-y: auto; padding: 16px 20px;
  display: flex; flex-direction: column; gap: 10px;
}

.cart-service-card {
  display: flex; align-items: center; gap: 14px;
  padding: 14px 16px; border: none; cursor: pointer; text-align: left;
  border-radius: 16px;
  background: rgba(255,255,255,0.42);
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 0.5px solid rgba(255,255,255,0.55);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.65), 0 4px 16px rgba(0,0,0,0.05);
  transition: transform 0.12s, box-shadow 0.12s;
}
.cart-service-card:hover {
  transform: translateY(-1px);
  box-shadow: inset 0 1px 1px rgba(255,255,255,0.75), 0 8px 24px rgba(255,102,53,0.10);
}

.cart-svc-thumb {
  width: 64px; height: 64px; border-radius: 12px; flex-shrink: 0; overflow: hidden;
  background: rgba(255,102,53,0.08);
  display: flex; align-items: center; justify-content: center;
}
.cart-svc-thumb-img { width: 100%; height: 100%; object-fit: cover; }
.cart-svc-info { flex: 1; min-width: 0; }
.cart-svc-name { font-size: 15px; font-weight: 700; color: var(--fx-text); margin-bottom: 4px; }
.cart-svc-price { font-size: 16px; font-weight: 800; color: #FF6635; }
.cart-svc-desc {
  font-size: 12px; color: var(--fx-muted); margin-top: 4px; line-height: 1.4;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.cart-svc-chevron { font-size: 22px; color: var(--fx-muted-soft); flex-shrink: 0; }

/* ── Mobile ──────────────────────────────────────────────────────────── */
.cart-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}
.cart-empty { text-align: center; padding: 40px 24px; }
.cart-card { padding: 16px; }
.cart-mobile-row {
  width: 100%; border: none; cursor: pointer; text-align: left;
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}
.cart-mobile-row:active { transform: scale(0.98); }
.cart-back-btn {
  width: 40px; height: 40px; border-radius: 50%; border: none; flex-shrink: 0;
  background: rgba(255,255,255,0.35); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
}
.fx-btn-ghost { background: none; border: none; cursor: pointer; }
</style>