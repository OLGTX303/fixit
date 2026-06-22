<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const cart = ref([])    // array of { id, name, category, rate, avatar_url }

function loadCart() {
  try { cart.value = JSON.parse(localStorage.getItem('fixit_cart') || '[]') } catch { cart.value = [] }
}
function saveCart() {
  localStorage.setItem('fixit_cart', JSON.stringify(cart.value))
}
function removeItem(id) {
  cart.value = cart.value.filter(p => p.id !== id)
  saveCart()
}
function clearCart() { cart.value = []; saveCart() }

function bookNow(item) {
  router.push({ name: 'provider-profile', params: { id: item.id } })
}

onMounted(loadCart)
</script>

<template>
  <div class="fx-page" style="max-width:560px">
    <!-- Header -->
    <div class="cart-header">
      <div>
        <h1 style="font-size:22px;font-weight:800;margin:0">Saved Providers</h1>
        <p style="font-size:13px;color:var(--fx-muted);margin:4px 0 0">{{ cart.length }} saved</p>
      </div>
      <button v-if="cart.length" class="fx-btn-ghost" style="font-size:12px;color:var(--fx-error)" @click="clearCart">
        Clear all
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="!cart.length" class="cart-empty fx-card">
      <span class="material-symbols-outlined" style="font-size:48px;color:var(--fx-muted-soft);display:block;margin-bottom:10px">shopping_cart</span>
      <div style="font-size:15px;font-weight:600;margin-bottom:6px">Your cart is empty</div>
      <div style="font-size:13px;color:var(--fx-muted);margin-bottom:18px">Save providers while browsing to book them later</div>
      <button class="glossy-primary" style="padding:10px 28px;border-radius:12px" @click="router.push({name:'search'})">
        Find Providers
      </button>
    </div>

    <!-- Cart items -->
    <div v-else class="d-flex flex-column gap-3">
      <div v-for="item in cart" :key="item.id" class="cart-card fx-card">
        <!-- Provider info -->
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="fx-avatar" style="width:52px;height:52px;font-size:18px;font-weight:800;flex-shrink:0"
               :style="{ backgroundImage: item.avatar_url ? `url(${item.avatar_url})` : '', backgroundSize:'cover', backgroundPosition:'center' }">
            <span v-if="!item.avatar_url">{{ (item.name||'?').split(' ').map(w=>w[0]).join('').slice(0,2) }}</span>
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:15px;font-weight:700">{{ item.name }}</div>
            <div style="font-size:12px;color:var(--fx-muted)">{{ item.category }}</div>
            <div v-if="item.rate" style="font-size:12px;color:var(--fx-accent);font-weight:600;margin-top:2px">
              RM{{ item.rate }}/hr
            </div>
          </div>
          <button class="cart-remove" @click="removeItem(item.id)" aria-label="Remove">
            <span class="material-symbols-outlined" style="font-size:18px">close</span>
          </button>
        </div>

        <!-- Actions -->
        <div class="d-flex gap-2">
          <button class="flex-fill fx-btn-outline" style="font-size:13px;padding:9px"
                  @click="router.push({name:'provider-profile', params:{id:item.id}})">
            View Profile
          </button>
          <button class="flex-fill glossy-primary" style="font-size:13px;padding:9px;border-radius:12px"
                  @click="bookNow(item)">
            Book Now
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cart-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 18px;
}
.cart-empty {
  text-align: center;
  padding: 40px 24px;
}
.cart-card { padding: 16px; }
.cart-remove {
  background: none; border: none; cursor: pointer;
  color: var(--fx-muted); padding: 4px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}
.cart-remove:hover { background: var(--fx-border-soft); color: var(--fx-error); }
.fx-btn-ghost { background: none; border: none; cursor: pointer; }
.fx-btn-outline {
  background: transparent;
  border: 1.5px solid var(--fx-border);
  border-radius: 12px;
  color: var(--fx-text);
  cursor: pointer;
  font-weight: 600;
  font-size: 13px;
}
</style>
