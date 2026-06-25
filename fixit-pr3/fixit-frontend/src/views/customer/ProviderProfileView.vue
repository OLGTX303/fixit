<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useBookingsStore } from '../../stores/bookings'
import * as api from '../../services/api'
import RatingStars from '../../components/RatingStars.vue'
import { useFavoritesStore } from '../../stores/favorites'
import { useModalGuard } from '../../composables/useModalGuard'

const route   = useRoute()
const router  = useRouter()
const auth    = useAuthStore()
const favorites = useFavoritesStore()
const isFav = computed(() => provider.value && favorites.has(provider.value.id))

// Load just this provider (fast), not the whole directory.
const provider = ref(null)
const reviews  = ref([])
const catalog  = ref([])          // rich service catalog (ProviderService)
const activeTab = ref('services')
const loading  = ref(true)

// Customers see active services only; the owner sees all (incl. hidden).
const visibleServices = computed(() =>
  isOwner.value ? catalog.value : catalog.value.filter(s => s.is_active))

// Cart helpers
function isInCart() {
  try {
    const cart = JSON.parse(localStorage.getItem('fixit_cart') || '[]')
    return cart.some(p => String(p.id) === String(provider.value?.id))
  } catch { return false }
}
const inCart = ref(false)
function toggleCart() {
  try {
    let cart = JSON.parse(localStorage.getItem('fixit_cart') || '[]')
    const id = String(provider.value?.id)
    if (cart.some(p => String(p.id) === id)) {
      cart = cart.filter(p => String(p.id) !== id)
      inCart.value = false
    } else {
      cart.push({
        id: provider.value.id,
        name: provider.value.name,
        category: provider.value.categories?.[0]?.name || '',
        rate: provider.value.hourly_rate,
        avatar_url: provider.value.avatar_url || null,
      })
      inCart.value = true
    }
    localStorage.setItem('fixit_cart', JSON.stringify(cart))
  } catch {}
}

// Is the logged-in user this provider?
const isOwner = computed(() =>
  auth.user && provider.value && (int(auth.user.id) === int(provider.value.user_id)))
function int(v) { return parseInt(v, 10) }

async function toggleFavorite() {
  if (!provider.value || auth.role !== 'customer') return
  try { await favorites.toggle(provider.value.id) } catch {}
}

onMounted(async () => {
  document.body.classList.add('provider-page')
  if (auth.role === 'customer') {
    favorites.load().catch(() => {})
    api.recordBrowsingHistory(Number(route.params.id)).catch(() => {})
  }
  try { provider.value = await api.getProvider(route.params.id) } catch {}
  inCart.value = isInCart()
  try { reviews.value = await api.getReviewsForProvider(route.params.id) } catch {}
  try { catalog.value = await api.getProviderServices(route.params.id) } catch {}
  loading.value = false
})

onUnmounted(() => {
  document.body.classList.remove('provider-page')
})

// ── Computed stats ─────────────────────────────────────────────────────────
const avgRating = computed(() => provider.value?.avg_rating ?? 0)
const reviewCount = computed(() => provider.value?.review_count ?? 0)

const ratingBreakdown = computed(() => {
  const rs = reviews.value.map(r => r.rating)
  const total = rs.length || 1
  return [5,4,3,2,1].map(star => ({
    star,
    count: rs.filter(r => r === star).length,
    pct: Math.round(rs.filter(r => r === star).length / total * 100),
  }))
})

const reviewTags = computed(() => {
  const tagMap = {}
  for (const r of reviews.value) {
    if (!r.comment) continue
    for (const t of r.comment.split(',').map(s => s.trim())) {
      if (t.length > 2 && t.length < 30) tagMap[t] = (tagMap[t] || 0) + 1
    }
  }
  return Object.entries(tagMap)
    .sort((a,b) => b[1]-a[1])
    .slice(0, 8)
    .map(([t, n]) => ({ tag: t, count: n }))
})

// ── Edit mode ──────────────────────────────────────────────────────────────
const lightboxUrl  = ref(null)
function openLightbox(url) { lightboxUrl.value = url }

async function messageProvider() {
  // Reuse an existing conversation (any booking or prior inquiry) with this provider…
  const bookingsStore = useBookingsStore()
  const existing = bookingsStore.bookings?.find(b =>
    String(b.provider_id) === String(provider.value?.id) ||
    String(b.provider?.id) === String(provider.value?.id)
  )
  if (existing) {
    router.push({ name: 'chat', params: { id: existing.id } })
    return
  }
  // …otherwise start a pre-order inquiry thread so the customer can message now.
  try {
    const job = await api.startInquiry(provider.value.id)
    if (!bookingsStore.bookings.some(b => b.id === job.id)) bookingsStore.bookings.unshift(job)
    router.push({ name: 'chat', params: { id: job.id } })
  } catch {
    router.push({ name: 'messages' })
  }
}

const editing      = ref(false)
useModalGuard(editing)
useModalGuard(lightboxUrl)
const editBio      = ref('')
const editLocation = ref('')
const editRate     = ref('')
const editCover    = ref(null)
const editCoverPrev = ref(null)
const editSaving   = ref(false)
const editErr      = ref('')

function openEdit() {
  editBio.value      = provider.value?.bio || ''
  editLocation.value = provider.value?.location || ''
  editRate.value     = provider.value?.base_rate || ''
  editCoverPrev.value = provider.value?.cover_url || null
  editCover.value    = null
  editErr.value      = ''
  editing.value      = true
}

function onCoverPick(e) {
  const file = e.target.files?.[0]
  if (!file) return
  const img = new Image()
  const url = URL.createObjectURL(file)
  img.onload = () => {
    URL.revokeObjectURL(url)
    let { width: w, height: h } = img
    const ratio = Math.min(1000 / w, 660 / h, 1)
    w = Math.round(w * ratio); h = Math.round(h * ratio)
    const canvas = document.createElement('canvas')
    canvas.width = w; canvas.height = h
    canvas.getContext('2d').drawImage(img, 0, 0, w, h)
    const dataUrl = canvas.toDataURL('image/jpeg', 0.80)
    editCoverPrev.value = dataUrl
    editCover.value = dataUrl
  }
  img.src = url
}

async function saveEdit() {
  editSaving.value = true
  editErr.value = ''
  try {
    let coverUrl = provider.value?.cover_url || null
    if (editCover.value) {
      const res = await api.uploadImage(editCover.value)
      coverUrl = res.url
    }
    await api.updateProvider(provider.value.id, {
      bio:       editBio.value,
      location:  editLocation.value,
      base_rate: parseFloat(editRate.value) || provider.value.base_rate,
      latitude:  provider.value.latitude,
      longitude: provider.value.longitude,
      cover_url: coverUrl,
    })
    provider.value = await api.getProvider(route.params.id)
    editing.value = false
  } catch(e) {
    editErr.value = e.message
  } finally {
    editSaving.value = false
  }
}

// ── Initials helper ─────────────────────────────────────────────────────────
const initials = computed(() =>
  (provider.value?.name || '?').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase())

function ratingColor(r) {
  if (r >= 4) return '#22c55e'
  if (r >= 3) return '#f59e0b'
  return '#ef4444'
}

function fmtDate(d) {
  return new Date(d).toLocaleDateString('en-MY', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <div v-if="loading" class="ppv-loading">
    <div class="ppv-spinner"></div>
  </div>

  <div v-else-if="!provider" class="ppv-empty">Provider not found</div>

  <div v-else class="ppv-root">

    <!-- ── COVER BANNER ──────────────────────────────────────────────── -->
    <div class="ppv-cover" :style="provider.cover_url ? `background-image:url(${provider.cover_url})` : ''">
      <div class="ppv-cover-overlay"></div>

      <!-- Back button -->
      <button class="ppv-back-btn" @click="router.back()">
        <span class="material-symbols-outlined" style="font-size:20px">arrow_back</span>
      </button>

      <button
        v-if="!isOwner && auth.role === 'customer'"
        class="ppv-fav-btn"
        :class="{ active: isFav }"
        :aria-label="isFav ? 'Remove from favourites' : 'Add to favourites'"
        @click.stop="toggleFavorite"
      >
        <span class="material-symbols-outlined" style="font-size:22px">favorite</span>
      </button>

      <!-- Edit cover (owner only) -->
      <label v-if="isOwner" class="ppv-edit-cover-btn" title="Change cover photo">
        <span class="material-symbols-outlined" style="font-size:16px">photo_camera</span>
        <input type="file" accept="image/*" style="display:none" @change="onCoverPick">
      </label>

      <!-- Provider brief on cover -->
      <div class="ppv-cover-info">
        <div class="ppv-avatar-wrap">
          <img v-if="provider.avatar_url" :src="provider.avatar_url" class="ppv-avatar-img" :alt="provider.name" />
          <div v-else class="ppv-avatar-initials">{{ initials }}</div>
          <span v-if="provider.is_verified" class="ppv-verified-badge" title="Verified">
            <span class="material-symbols-outlined" style="font-size:13px;font-variation-settings:'FILL' 1">verified</span>
          </span>
        </div>
        <div class="ppv-cover-name">
          {{ provider.name }}
          <span v-if="isOwner" class="ppv-owner-tag">You</span>
        </div>
        <div class="ppv-cover-cats">{{ provider.category_names?.join(' · ') }}</div>
        <div class="ppv-cover-rating">
          <span class="ppv-rating-score" :style="{color: ratingColor(avgRating)}">{{ avgRating.toFixed(1) }}</span>
          <RatingStars :rating="avgRating" :size="13" />
          <span class="ppv-rating-count">{{ reviewCount }} reviews</span>
        </div>
      </div>
    </div>

    <!-- ── META STRIP ────────────────────────────────────────────────── -->
    <div class="ppv-meta-strip">
      <div class="ppv-meta-item">
        <span class="material-symbols-outlined ppv-meta-icon">schedule</span>
        <span>Mon–Sun · All day</span>
      </div>
      <div class="ppv-meta-item">
        <span class="material-symbols-outlined ppv-meta-icon">location_on</span>
        <span>{{ provider.location }}</span>
      </div>
      <div class="ppv-meta-item">
        <span class="material-symbols-outlined ppv-meta-icon">currency_exchange</span>
        <span>From RM{{ provider.base_rate }}/hr</span>
      </div>
      <button v-if="isOwner" class="ppv-edit-btn" @click="openEdit">
        <span class="material-symbols-outlined" style="font-size:16px">edit</span>
        Edit Profile
      </button>
    </div>

    <!-- ── TABS ──────────────────────────────────────────────────────── -->
    <div class="ppv-tabs">
      <button class="ppv-tab" :class="{active: activeTab==='services'}" @click="activeTab='services'">Services</button>
      <button class="ppv-tab" :class="{active: activeTab==='reviews'}"  @click="activeTab='reviews'">Reviews <span v-if="reviewCount" class="ppv-tab-count">{{ reviewCount }}</span></button>
      <button class="ppv-tab" :class="{active: activeTab==='info'}"     @click="activeTab='info'">Info</button>
    </div>

    <!-- ── TAB: SERVICES ─────────────────────────────────────────────── -->
    <div v-if="activeTab==='services'" class="ppv-tab-body">

      <!-- Category filter chips -->
      <div v-if="provider.category_names?.length" class="ppv-chip-row">
        <span v-for="cat in provider.category_names" :key="cat" class="ppv-chip">{{ cat }}</span>
      </div>

      <!-- Service cards (rich catalog: photo, per-service price, description) -->
      <div v-if="visibleServices.length" class="ppv-service-list">
        <div v-for="svc in visibleServices" :key="svc.id" class="ppv-service-card" :class="{ inactive: !svc.is_active }">
          <div class="ppv-svc-thumb">
            <img v-if="svc.image_url" :src="svc.image_url" :alt="svc.name" class="ppv-svc-thumb-img" />
            <span v-else class="material-symbols-outlined" style="font-size:30px;color:#FF6635;font-variation-settings:'FILL' 1">home_repair_service</span>
          </div>
          <div class="ppv-svc-info">
            <div class="ppv-svc-name">{{ svc.name }} <span v-if="isOwner && !svc.is_active" class="ppv-svc-hidden">hidden</span></div>
            <div class="ppv-svc-price">
              <span class="ppv-svc-curr">RM{{ svc.price }}</span>
            </div>
            <div v-if="svc.description" class="ppv-svc-desc-line">{{ svc.description }}</div>
          </div>
          <button class="ppv-book-pill" @click="router.push({name:'booking-form',params:{id:provider.id},query:{service:svc.name,price:svc.price,service_id:svc.id}})">Book</button>
        </div>
      </div>

      <!-- No services fallback -->
      <div v-else class="ppv-empty-section">
        <span class="material-symbols-outlined" style="font-size:40px;color:var(--fx-muted)">home_repair_service</span>
        <p>No services listed yet.</p>
        <button v-if="isOwner" class="ppv-edit-btn" @click="openEdit">+ Add Services</button>
      </div>
    </div>

    <!-- ── TAB: REVIEWS ──────────────────────────────────────────────── -->
    <div v-if="activeTab==='reviews'" class="ppv-tab-body">

      <!-- Score summary -->
      <div class="ppv-score-card">
        <div class="ppv-score-big" :style="{color: ratingColor(avgRating)}">{{ avgRating.toFixed(1) }}</div>
        <div class="ppv-score-right">
          <RatingStars :rating="avgRating" :size="16" />
          <div class="ppv-score-total">{{ reviewCount }} reviews</div>
          <!-- Bar breakdown -->
          <div class="ppv-bar-list">
            <div v-for="b in ratingBreakdown" :key="b.star" class="ppv-bar-row">
              <span class="ppv-bar-label">{{ b.star }}★</span>
              <div class="ppv-bar-track">
                <div class="ppv-bar-fill" :style="{width: b.pct+'%', background: ratingColor(b.star)}"></div>
              </div>
              <span class="ppv-bar-num">{{ b.count }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Tag chips (derived from review comments) -->
      <div v-if="reviewTags.length" class="ppv-tag-cloud">
        <span v-for="t in reviewTags" :key="t.tag" class="ppv-review-tag">
          {{ t.tag }} <span class="ppv-tag-count">{{ t.count }}</span>
        </span>
      </div>

      <!-- Review cards -->
      <div v-if="reviews.length" class="ppv-review-list">
        <div v-for="r in reviews" :key="r.id" class="ppv-review-card">
          <div class="ppv-rev-header">
            <div class="ppv-rev-avatar">
              <img v-if="r.customer_avatar" :src="r.customer_avatar" class="ppv-rev-avatar-img" />
              <span v-else class="ppv-rev-avatar-initials">
                {{ (r.customer_name || 'U').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase() }}
              </span>
            </div>
            <div class="ppv-rev-meta">
              <div class="ppv-rev-name">{{ r.customer_name || 'Anonymous' }}</div>
              <div class="ppv-rev-date">{{ fmtDate(r.created_at) }}</div>
            </div>
            <div class="ppv-rev-stars">
              <RatingStars :rating="r.rating" :size="12" />
            </div>
          </div>
          <div v-if="r.comment" class="ppv-rev-text">{{ r.comment }}</div>
          <!-- Review images -->
          <div v-if="r.image_urls?.length" class="ppv-rev-images">
            <img v-for="(url,i) in r.image_urls" :key="i" :src="url" class="ppv-rev-img" @click="openLightbox(url)" />
          </div>
        </div>
      </div>

      <div v-else class="ppv-empty-section">
        <span class="material-symbols-outlined" style="font-size:40px;color:var(--fx-muted)">rate_review</span>
        <p>No reviews yet. Be the first!</p>
      </div>
    </div>

    <!-- ── TAB: INFO ─────────────────────────────────────────────────── -->
    <div v-if="activeTab==='info'" class="ppv-tab-body">
      <div class="ppv-info-section">
        <div class="ppv-info-label">About</div>
        <div class="ppv-info-text">{{ provider.bio || 'No bio provided.' }}</div>
      </div>
      <div class="ppv-info-section">
        <div class="ppv-info-label">Service Area</div>
        <div class="ppv-info-text">{{ provider.location }}</div>
      </div>
      <div class="ppv-info-section">
        <div class="ppv-info-label">Categories</div>
        <div class="ppv-chip-row" style="margin-top:6px">
          <span v-for="c in provider.category_names" :key="c" class="ppv-chip">{{ c }}</span>
        </div>
      </div>
      <div class="ppv-info-section">
        <div class="ppv-info-label">Rate</div>
        <div class="ppv-info-text">RM{{ provider.base_rate }} / hour</div>
      </div>
      <div class="ppv-info-section">
        <div class="ppv-info-label">Verification</div>
        <div class="ppv-info-text d-flex align-items-center gap-1">
          <span v-if="provider.is_verified" class="material-symbols-outlined" style="font-size:16px;color:#22c55e;font-variation-settings:'FILL' 1">verified</span>
          {{ provider.is_verified ? 'Verified provider' : 'Not yet verified' }}
        </div>
      </div>
    </div>

    <!-- ── BOTTOM BOOK BUTTON ─────────────────────────────────────────── -->
    <div v-if="!isOwner" class="ppv-book-bar">
      <button class="ppv-cart-btn" :class="{ saved: inCart }" @click="toggleCart" :title="inCart ? 'Remove from cart' : 'Save to cart'">
        <span class="material-symbols-outlined" style="font-size:20px" :style="inCart ? 'font-variation-settings:\'FILL\' 1' : ''">
          {{ inCart ? 'shopping_cart_checkout' : 'add_shopping_cart' }}
        </span>
      </button>
      <button class="ppv-book-btn" style="flex:1" @click="messageProvider">
        <span class="material-symbols-outlined" style="font-size:18px">chat</span>
        Message Provider
      </button>
    </div>

    <!-- ── LIGHTBOX ──────────────────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="lightboxUrl" class="ppv-lightbox" @click="lightboxUrl=null">
        <img :src="lightboxUrl" class="ppv-lightbox-img" />
      </div>
    </Teleport>

    <!-- ── EDIT MODAL ────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="editing" class="ppv-modal-backdrop" @click.self="editing=false"></div>
      </Transition>
      <Transition name="slide-up">
        <div v-if="editing" class="ppv-modal">
          <div class="ppv-modal-header">
            <span class="ppv-modal-title">Edit Profile</span>
            <button class="ppv-modal-close" @click="editing=false">
              <span class="material-symbols-outlined" style="font-size:20px">close</span>
            </button>
          </div>

          <!-- Cover preview -->
          <label class="ppv-cover-pick">
            <div class="ppv-cover-pick-img"
                 :style="editCoverPrev ? `background-image:url(${editCoverPrev})` : ''">
              <span class="material-symbols-outlined" style="font-size:28px;color:#fff">photo_camera</span>
              <span style="font-size:12px;color:#fff;margin-top:4px">Tap to change cover</span>
            </div>
            <input type="file" accept="image/*" style="display:none" @change="onCoverPick">
          </label>

          <div class="ppv-modal-body">
            <div class="ppv-field">
              <label class="ppv-field-label">Bio</label>
              <textarea class="ppv-field-input" rows="3" v-model="editBio" placeholder="Describe your services…"></textarea>
            </div>
            <div class="ppv-field">
              <label class="ppv-field-label">Location / Service Area</label>
              <input class="ppv-field-input" v-model="editLocation" placeholder="e.g. Kuala Lumpur" />
            </div>
            <div class="ppv-field">
              <label class="ppv-field-label">Hourly Rate (RM)</label>
              <input class="ppv-field-input" type="number" min="1" v-model="editRate" placeholder="e.g. 80" />
            </div>
            <div v-if="editErr" class="ppv-edit-err">{{ editErr }}</div>
          </div>

          <div class="ppv-modal-footer">
            <button class="ppv-modal-cancel" @click="editing=false">Cancel</button>
            <button class="ppv-modal-save" :disabled="editSaving" @click="saveEdit">
              <span v-if="editSaving" class="ppv-mini-spinner"></span>
              <span v-else>Save Changes</span>
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<style scoped>
/* ── Root ───────────────────────────────────────────────────────────────── */
.ppv-root   { min-height: 100vh; background: var(--fx-bg); padding-bottom: 88px; }
.ppv-loading { display:flex; align-items:center; justify-content:center; height:100vh; }
.ppv-empty   { display:flex; align-items:center; justify-content:center; height:100vh; color:var(--fx-muted); }

.ppv-spinner {
  width:36px; height:36px; border:3px solid rgba(255,102,53,.2);
  border-top-color:#FF6635; border-radius:50%; animation:spin .8s linear infinite;
}
@keyframes spin { to { transform:rotate(360deg) } }

/* ── Cover banner ───────────────────────────────────────────────────────── */
.ppv-cover {
  position:relative; height:220px;
  background: linear-gradient(135deg,#FF7D54,#FF6635);
  background-size:cover; background-position:center;
}
.ppv-cover-overlay {
  position:absolute; inset:0;
  background:linear-gradient(to bottom, rgba(0,0,0,.18) 0%, rgba(0,0,0,.55) 100%);
}
.ppv-back-btn {
  position:absolute; top:14px; left:14px; z-index:2;
  width:36px; height:36px; border-radius:50%; border:none;
  background:rgba(0,0,0,.32); color:#fff; display:flex; align-items:center; justify-content:center;
  cursor:pointer;
}
.ppv-fav-btn {
  position:absolute; top:14px; right:58px; z-index:2;
  width:36px; height:36px; border-radius:50%; border:none;
  background:rgba(0,0,0,.32); color:#fff; display:flex; align-items:center; justify-content:center;
  cursor:pointer;
}
.ppv-fav-btn.active { color:#ef4444; }
.ppv-fav-btn.active .material-symbols-outlined { font-variation-settings:'FILL' 1; }
.ppv-edit-cover-btn {
  position:absolute; top:14px; right:14px; z-index:2;
  width:36px; height:36px; border-radius:50%;
  background:rgba(0,0,0,.32); color:#fff; display:flex; align-items:center; justify-content:center;
  cursor:pointer;
}
.ppv-cover-info {
  position:absolute; bottom:16px; left:16px; right:16px; z-index:2;
  display:flex; flex-direction:column; align-items:flex-start; gap:3px;
}
.ppv-avatar-wrap { position:relative; margin-bottom:6px; }
.ppv-avatar-img  { width:56px; height:56px; border-radius:50%; border:2px solid #fff; object-fit:cover; }
.ppv-avatar-initials {
  width:56px; height:56px; border-radius:50%; border:2px solid #fff;
  background:rgba(255,255,255,.25); color:#fff; font-weight:800; font-size:20px;
  display:flex; align-items:center; justify-content:center;
}
.ppv-verified-badge {
  position:absolute; bottom:0; right:-2px;
  width:18px; height:18px; border-radius:50%; background:#4f9; border:1.5px solid #fff;
  display:flex; align-items:center; justify-content:center; color:#0a5c2c;
}
.ppv-cover-name  { font-size:18px; font-weight:800; color:#fff; display:flex; align-items:center; gap:6px; }
.ppv-owner-tag   { font-size:10px; background:rgba(255,255,255,.25); color:#fff; padding:1px 7px; border-radius:20px; font-weight:700; }
.ppv-cover-cats  { font-size:12px; color:rgba(255,255,255,.8); }
.ppv-cover-rating { display:flex; align-items:center; gap:5px; margin-top:2px; }
.ppv-rating-score { font-size:15px; font-weight:800; }
.ppv-rating-count { font-size:12px; color:rgba(255,255,255,.75); }

/* ── Meta strip ─────────────────────────────────────────────────────────── */
.ppv-meta-strip {
  padding:12px 16px; background:var(--fx-card);
  display:flex; flex-direction:column; gap:7px;
  border-bottom:1px solid var(--fx-border);
}
.ppv-meta-item { display:flex; align-items:center; gap:7px; font-size:13px; color:var(--fx-muted); }
.ppv-meta-icon { font-size:16px; color:#FF6635; }
.ppv-edit-btn {
  align-self:flex-start; display:flex; align-items:center; gap:5px;
  padding:6px 14px; border-radius:20px; border:1.5px solid #FF6635;
  background:transparent; color:#FF6635; font-size:13px; font-weight:600; cursor:pointer;
  margin-top:4px;
}

/* ── Tabs ───────────────────────────────────────────────────────────────── */
.ppv-tabs {
  display:flex; background:var(--fx-card);
  border-bottom:1px solid var(--fx-border);
  position:sticky; top:0; z-index:10;
}
.ppv-tab {
  flex:1; padding:12px 4px; border:none; background:transparent;
  font-size:14px; font-weight:600; color:var(--fx-muted);
  border-bottom:2.5px solid transparent; cursor:pointer; transition:all .15s;
}
.ppv-tab.active { color:#FF6635; border-bottom-color:#FF6635; }
.ppv-tab-count {
  display:inline-block; background:#FF6635; color:#fff;
  font-size:10px; border-radius:10px; padding:0 5px; margin-left:3px;
}

/* ── Tab body ───────────────────────────────────────────────────────────── */
.ppv-tab-body { padding:14px 16px; }

/* Chip row */
.ppv-chip-row { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
.ppv-chip {
  padding:5px 12px; border-radius:20px; border:1.5px solid var(--fx-border);
  font-size:12px; font-weight:600; color:var(--fx-text); background:var(--fx-glass-bg);
}

/* Service cards */
.ppv-service-list { display:flex; flex-direction:column; gap:12px; }
.ppv-service-card {
  display:flex; align-items:center; gap:12px;
  background:var(--fx-card); border-radius:14px; padding:12px;
  border:1px solid var(--fx-border);
}
.ppv-svc-thumb {
  width:72px; height:72px; border-radius:10px; flex-shrink:0; overflow:hidden;
  background:rgba(255,102,53,.08); display:flex; align-items:center; justify-content:center;
}
.ppv-svc-thumb-img { width:100%; height:100%; object-fit:cover; }
.ppv-service-card.inactive { opacity:.55; }
.ppv-svc-hidden { font-size:10px; font-weight:700; color:var(--fx-muted); background:rgba(0,0,0,.06); padding:1px 6px; border-radius:8px; vertical-align:middle; }
.ppv-svc-desc-line { font-size:12px; color:var(--fx-muted); line-height:1.4; margin-top:2px;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.ppv-svc-info { flex:1; min-width:0; }
.ppv-svc-name  { font-size:14px; font-weight:700; color:var(--fx-text); margin-bottom:4px; }
.ppv-svc-price { margin-bottom:4px; }
.ppv-svc-curr  { font-size:16px; font-weight:800; color:#FF6635; }
.ppv-svc-unit  { font-size:12px; color:var(--fx-muted); }
.ppv-svc-cats  { display:flex; gap:4px; flex-wrap:wrap; }
.ppv-svc-cat-tag {
  font-size:10px; padding:2px 7px; border-radius:10px;
  background:rgba(255,102,53,.1); color:#FF6635; font-weight:600;
}
.ppv-book-pill {
  padding:8px 16px; border-radius:20px; border:none;
  background:#FF6635; color:#fff; font-size:13px; font-weight:700; cursor:pointer;
  flex-shrink:0;
}

/* Empty section */
.ppv-empty-section {
  display:flex; flex-direction:column; align-items:center; padding:32px 0; gap:8px;
  color:var(--fx-muted); font-size:14px;
}

/* ── Reviews ────────────────────────────────────────────────────────────── */
.ppv-score-card {
  display:flex; gap:16px; padding:16px; border-radius:14px;
  background:var(--fx-card); border:1px solid var(--fx-border); margin-bottom:14px;
}
.ppv-score-big { font-size:48px; font-weight:900; line-height:1; }
.ppv-score-right { flex:1; display:flex; flex-direction:column; gap:4px; }
.ppv-score-total { font-size:12px; color:var(--fx-muted); margin-top:2px; }
.ppv-bar-list { margin-top:6px; display:flex; flex-direction:column; gap:3px; }
.ppv-bar-row  { display:flex; align-items:center; gap:6px; }
.ppv-bar-label { font-size:11px; color:var(--fx-muted); width:20px; text-align:right; }
.ppv-bar-track { flex:1; height:5px; border-radius:3px; background:var(--fx-border); overflow:hidden; }
.ppv-bar-fill  { height:100%; border-radius:3px; transition:width .4s; }
.ppv-bar-num   { font-size:11px; color:var(--fx-muted); width:18px; }

.ppv-tag-cloud { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
.ppv-review-tag {
  display:flex; align-items:center; gap:4px;
  padding:5px 12px; border-radius:20px;
  background:rgba(255,102,53,.08); color:#FF6635;
  font-size:12px; font-weight:600; cursor:default;
}
.ppv-tag-count {
  background:#FF6635; color:#fff; font-size:10px; border-radius:10px; padding:0 5px;
}

.ppv-review-list { display:flex; flex-direction:column; gap:14px; }
.ppv-review-card {
  background:var(--fx-card); border-radius:14px; padding:14px;
  border:1px solid var(--fx-border);
}
.ppv-rev-header { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
.ppv-rev-avatar { width:36px; height:36px; border-radius:50%; overflow:hidden; flex-shrink:0; background:rgba(255,102,53,.12); }
.ppv-rev-avatar-img { width:100%; height:100%; object-fit:cover; }
.ppv-rev-avatar-initials {
  width:100%; height:100%; display:flex; align-items:center; justify-content:center;
  font-size:13px; font-weight:700; color:#FF6635;
}
.ppv-rev-meta { flex:1; min-width:0; }
.ppv-rev-name { font-size:13px; font-weight:700; color:var(--fx-text); }
.ppv-rev-date { font-size:11px; color:var(--fx-muted); }
.ppv-rev-stars { flex-shrink:0; }
.ppv-rev-text  { font-size:13px; color:var(--fx-muted); line-height:1.6; }
.ppv-rev-images { display:flex; gap:6px; margin-top:8px; flex-wrap:wrap; }
.ppv-rev-img {
  width:80px; height:80px; border-radius:8px; object-fit:cover; cursor:pointer;
  border:1px solid var(--fx-border);
}

/* ── Info tab ───────────────────────────────────────────────────────────── */
.ppv-info-section { padding:12px 0; border-bottom:1px solid var(--fx-border); }
.ppv-info-section:last-child { border-bottom:none; }
.ppv-info-label { font-size:12px; font-weight:700; color:var(--fx-muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
.ppv-info-text  { font-size:14px; color:var(--fx-text); line-height:1.5; }

/* ── Book bar ───────────────────────────────────────────────────────────── */
.ppv-book-bar {
  position:fixed; bottom:106px; left:12px; right:12px; z-index:20;
  border-radius:20px;
  padding:8px 8px;
  background:transparent;
  display:flex; gap:8px;
}
.ppv-cart-btn {
  width:50px; height:50px; border-radius:14px; border:2px solid var(--fx-accent);
  background:rgba(255,255,255,0.85); color:var(--fx-accent); cursor:pointer;
  display:flex; align-items:center; justify-content:center; flex-shrink:0;
  backdrop-filter:blur(20px);
}
.ppv-cart-btn.saved { background:var(--fx-accent); color:#fff; }
.ppv-book-btn {
  width:100%; padding:14px; border-radius:14px; border:none;
  background:#FF6635; color:#fff; font-size:15px; font-weight:800;
  display:flex; align-items:center; justify-content:center; gap:8px; cursor:pointer;
  box-shadow:0 4px 16px rgba(255,102,53,.35);
}

/* ── Lightbox ───────────────────────────────────────────────────────────── */
.ppv-lightbox {
  position:fixed; inset:0; z-index:300; background:rgba(0,0,0,.88);
  display:flex; align-items:center; justify-content:center; cursor:pointer;
}
.ppv-lightbox-img { max-width:92vw; max-height:88vh; border-radius:12px; object-fit:contain; }

/* ── Edit modal ─────────────────────────────────────────────────────────── */
.ppv-modal-backdrop {
  position:fixed; inset:0; z-index:200; background:rgba(0,0,0,.40);
  backdrop-filter:blur(6px); -webkit-backdrop-filter:blur(6px);
}
.ppv-modal {
  position:fixed; bottom:0; left:0; right:0; z-index:201;
  /* opaque glass panel — var(--fx-card) is translucent, which let the page
     content show through the modal. 0.92 white + blur reads as solid. */
  background:
    radial-gradient(ellipse 60% 40% at 20% 5%, rgba(255,255,255,0.35) 0%, transparent 65%),
    rgba(255,255,255,0.92);
  backdrop-filter:blur(40px) saturate(1.6); -webkit-backdrop-filter:blur(40px) saturate(1.6);
  border-radius:24px 24px 0 0;
  max-height:90vh; display:flex; flex-direction:column;
  box-shadow:0 -8px 40px rgba(0,0,0,.18);
}
.ppv-modal-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:16px 20px; border-bottom:1px solid var(--fx-border);
}
.ppv-modal-title  { font-size:16px; font-weight:800; color:var(--fx-text); }
.ppv-modal-close  { background:none; border:none; cursor:pointer; color:var(--fx-muted); display:flex; }
.ppv-modal-body   { padding:16px 20px; overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:14px; }
.ppv-modal-footer {
  display:flex; gap:10px; padding:12px 20px calc(12px + env(safe-area-inset-bottom));
  border-top:1px solid var(--fx-border);
}
.ppv-modal-cancel {
  flex:1; padding:12px; border-radius:12px; border:1.5px solid var(--fx-border);
  background:var(--fx-glass-bg); color:var(--fx-text); font-weight:600; cursor:pointer;
}
.ppv-modal-save {
  flex:2; padding:12px; border-radius:12px; border:none;
  background:#FF6635; color:#fff; font-weight:700; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
}
.ppv-modal-save:disabled { opacity:.6; cursor:default; }

.ppv-cover-pick { cursor:pointer; display:block; margin-bottom:2px; }
.ppv-cover-pick-img {
  height:120px; background:#222; background-size:cover; background-position:center;
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  gap:4px; opacity:.85;
}
.ppv-field { display:flex; flex-direction:column; gap:5px; }
.ppv-field-label { font-size:12px; font-weight:700; color:var(--fx-muted); text-transform:uppercase; letter-spacing:.4px; }
.ppv-field-input {
  padding:10px 12px; border-radius:10px; border:1.5px solid var(--fx-border);
  background:var(--fx-glass-bg); color:var(--fx-text); font-size:14px;
  outline:none; resize:none; font-family:inherit;
}
.ppv-field-input:focus { border-color:#FF6635; }
.ppv-edit-err { font-size:13px; color:#ef4444; padding:4px 0; }

.ppv-mini-spinner {
  width:18px; height:18px; border:2px solid rgba(255,255,255,.3);
  border-top-color:#fff; border-radius:50%; animation:spin .6s linear infinite; display:inline-block;
}

/* transitions */
.fade-enter-active,.fade-leave-active { transition:opacity .22s; }
.fade-enter-from,.fade-leave-to { opacity:0; }
.slide-up-enter-active,.slide-up-leave-active { transition:transform .28s cubic-bezier(.32,.72,0,1); }
.slide-up-enter-from,.slide-up-leave-to { transform:translateY(100%); }
</style>
