<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useProvidersStore } from '../../stores/providers'
import { useModalGuard } from '../../composables/useModalGuard'
import * as api from '../../services/api'

const router         = useRouter()
const auth           = useAuthStore()
const providersStore = useProvidersStore()

const myProfile  = computed(() => providersStore.providers.find(p => p.user_id === auth.user?.id))
const storageKey = computed(() => `fixit_svc_${auth.user?.id}`)

// ── Cover photo �?uploaded to R2 and saved on the provider (persists, shows
//    to customers). Same R2 path as avatars: uploadImage �?URL �?cover_url. ──
const coverFileInput = ref(null)
const uploadingCover = ref(false)
const coverPreview   = ref(null)

function loadCover() {
  coverPreview.value = myProfile.value?.cover_url || null
}

onMounted(async () => {
  await providersStore.load()
  loadServices()
  loadCoupons()
  loadCover()
  if (myProfile.value) {
    baseRate.value = myProfile.value.base_rate || 45
    profileForm.value = {
      bio: myProfile.value.bio || '',
      location: myProfile.value.location || '',
      base_rate: myProfile.value.base_rate || 45,
    }
  }
})

function pickCover() { coverFileInput.value?.click() }
async function onCoverSelected(e) {
  const file = e.target.files?.[0]
  if (!file || !myProfile.value) return
  uploadingCover.value = true
  try {
    const dataUrl = await compressImage(file, 1200, 800, 0.8)
    const { url } = await api.uploadImage(dataUrl)          // �?R2
    await api.updateProvider(myProfile.value.id, { cover_url: url })
    coverPreview.value = url
    await providersStore.load()                              // refresh cached profile
  } catch (err) {
    console.error(err)
    alert('Could not save background photo: ' + (err.message || 'upload failed'))
  } finally {
    uploadingCover.value = false
    if (coverFileInput.value) coverFileInput.value.value = ''
  }
}

// ── Edit profile (button + popup, no subtab) ─────────────────────────────
const showProfileForm = ref(false)
const savingProfile   = ref(false)
const profileForm     = ref({ bio: '', location: '', base_rate: 45 })

function openEditProfile() {
  if (myProfile.value) profileForm.value = {
    bio: myProfile.value.bio || '',
    location: myProfile.value.location || '',
    base_rate: myProfile.value.base_rate || 45,
  }
  showProfileForm.value = true
}
async function saveProfile() {
  if (!myProfile.value) return
  savingProfile.value = true
  try {
    await api.updateProvider(myProfile.value.id, {
      bio: profileForm.value.bio,
      location: profileForm.value.location,
      base_rate: parseFloat(profileForm.value.base_rate),
    })
    baseRate.value = profileForm.value.base_rate
    await providersStore.load()
    showProfileForm.value = false
  } catch (err) {
    alert('Could not save profile: ' + (err.message || 'failed'))
  } finally {
    savingProfile.value = false
  }
}

function openPreview() {
  if (myProfile.value) router.push({ name: 'provider-profile', params: { id: myProfile.value.id } })
}

// ── Services ───────────────────────────────────────────────────────────
const services       = ref([])
const showServiceForm = ref(false)
const editingService  = ref(null)
const svcForm         = ref({ name: '', sku: '', price: '', description: '', image_url: '', active: true })
const svcImagePreview = ref(null)
const uploadingImage  = ref(false)
const svcImageInput   = ref(null)

// Services are a real server catalog (ProviderService table) �?full CRUD with
// per-service price, photo (R2), description. No more localStorage.
async function loadServices() {
  if (!myProfile.value) return
  try {
    const rows = await api.getProviderServices(myProfile.value.id)
    services.value = rows.map(r => ({ ...r, active: r.is_active }))
    // One-time seed from legacy services_json names so existing data carries over.
    if (!services.value.length && myProfile.value.services?.length) {
      for (const name of myProfile.value.services) {
        await api.createProviderService(myProfile.value.id, {
          name, price: myProfile.value.base_rate || 45, is_active: true,
        })
      }
      services.value = (await api.getProviderServices(myProfile.value.id)).map(r => ({ ...r, active: r.is_active }))
    }
  } catch (e) { console.error('load services failed', e) }
}

function genSku(name) {
  return name.trim().toUpperCase().replace(/\s+/g, '-').slice(0, 8) + '-' + Math.random().toString(36).slice(2, 5).toUpperCase()
}

function openAdd() {
  editingService.value = null
  svcForm.value = { name: '', sku: '', price: '', description: '', image_url: '', active: true }
  svcImagePreview.value = null
  showServiceForm.value = true
}
function openEdit(svc) {
  editingService.value = svc
  svcForm.value = {
    name: svc.name, sku: svc.sku || '', price: svc.price,
    description: svc.description || '', image_url: svc.image_url || '', active: svc.active,
  }
  svcImagePreview.value = svc.image_url || null
  showServiceForm.value = true
}

function pickSvcImage() { svcImageInput.value?.click() }
async function onSvcImageSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  uploadingImage.value = true
  try {
    const dataUrl = await compressImage(file, 800, 800, 0.8)
    svcForm.value.image_url = dataUrl
    svcImagePreview.value = dataUrl
  } catch (err) { console.error(err) }
  finally { uploadingImage.value = false; if (svcImageInput.value) svcImageInput.value.value = '' }
}

// Create / update on the server. A freshly-picked photo (data URL) is uploaded
// to R2 first, then its URL is stored on the service.
async function saveService() {
  if (!svcForm.value.name || !svcForm.value.price || !myProfile.value) return
  uploadingImage.value = true
  try {
    let imageUrl = svcForm.value.image_url || null
    if (imageUrl && imageUrl.startsWith('data:')) {
      imageUrl = (await api.uploadImage(imageUrl)).url
    }
    const payload = {
      name: svcForm.value.name,
      price: parseFloat(svcForm.value.price),
      description: svcForm.value.description || null,
      image_url: imageUrl,
      sku: svcForm.value.sku || genSku(svcForm.value.name),
      is_active: svcForm.value.active !== false,
    }
    if (editingService.value) {
      await api.updateProviderServiceItem(myProfile.value.id, editingService.value.id, payload)
    } else {
      await api.createProviderService(myProfile.value.id, payload)
    }
    await loadServices()
    showServiceForm.value = false
  } catch (e) {
    alert('Could not save service: ' + (e.message || 'failed'))
  } finally { uploadingImage.value = false }
}

async function deleteService(svc) {
  if (!myProfile.value) return
  try { await api.deleteProviderServiceItem(myProfile.value.id, svc.id); await loadServices() }
  catch (e) { alert('Could not delete: ' + (e.message || '')) }
}
async function toggleActive(svc) {
  if (!myProfile.value) return
  try {
    await api.updateProviderServiceItem(myProfile.value.id, svc.id, { ...svc, is_active: !svc.active })
    await loadServices()
  } catch (e) { alert('Could not update: ' + (e.message || '')) }
}

// ── Base rate ──────────────────────────────────────────────────────────
const savingRate = ref(false)
const baseRate   = ref(45)
async function saveRate() {
  if (!myProfile.value) return
  savingRate.value = true
  try { await api.updateProvider(myProfile.value.id, { base_rate: parseFloat(baseRate.value) }) }
  finally { savingRate.value = false }
}

// ── Coupons (server-persisted) ─────────────────────────────────────────
const coupons        = ref([])
const showCouponForm = ref(false)
const editingCoupon  = ref(null)
const savingCoupon   = ref(false)
const cpnForm        = ref({ code: '', discount_type: 'percent', discount_value: '', min_spend: '', expires_at: '', is_active: true })

useModalGuard(showProfileForm)
useModalGuard(showServiceForm)
useModalGuard(showCouponForm)

async function loadCoupons() {
  try { coupons.value = await api.getMyCoupons() } catch { coupons.value = [] }
}

function defaultExpiry() {
  const d = new Date()
  d.setDate(d.getDate() + 30)
  return d.toISOString().slice(0, 10)
}

function openAddCoupon() {
  editingCoupon.value = null
  cpnForm.value = { code: '', discount_type: 'percent', discount_value: '', min_spend: '', expires_at: defaultExpiry(), is_active: true }
  showCouponForm.value = true
}
function openEditCoupon(c) {
  editingCoupon.value = c
  cpnForm.value = {
    code: c.code,
    discount_type: c.discount_type,
    discount_value: c.discount_value,
    min_spend: c.min_spend || '',
    expires_at: (c.expires_at || '').slice(0, 10),
    is_active: c.is_active,
  }
  showCouponForm.value = true
}
async function saveCoupon() {
  if (!cpnForm.value.code || !cpnForm.value.discount_value) return
  savingCoupon.value = true
  try {
    const payload = {
      code: cpnForm.value.code,
      discount_type: cpnForm.value.discount_type,
      discount_value: parseFloat(cpnForm.value.discount_value),
      min_spend: cpnForm.value.min_spend ? parseFloat(cpnForm.value.min_spend) : 0,
      expires_at: cpnForm.value.expires_at,
      is_active: cpnForm.value.is_active,
    }
    if (editingCoupon.value) await api.updateMyCoupon(editingCoupon.value.id, payload)
    else await api.createMyCoupon(payload)
    await loadCoupons()
    showCouponForm.value = false
  } catch (e) {
    alert(e.message || 'Could not save coupon')
  } finally { savingCoupon.value = false }
}
async function deleteCoupon(c) {
  if (!confirm(`Delete coupon ${c.code}?`)) return
  try { await api.deleteMyCoupon(c.id); await loadCoupons() }
  catch (e) { alert(e.message || 'Could not delete') }
}
async function toggleCoupon(c) {
  try {
    await api.updateMyCoupon(c.id, { ...c, is_active: !c.is_active })
    c.is_active = !c.is_active
  } catch (e) { alert(e.message || 'Could not update') }
}
function discountLabel(c) {
  return c.discount_type === 'percent' ? `${c.discount_value}% off` : `RM${c.discount_value} off`
}

// ── Util ───────────────────────────────────────────────────────────────
function readFile(file) {
  return new Promise((res, rej) => {
    const r = new FileReader(); r.onload = () => res(r.result); r.onerror = rej; r.readAsDataURL(file)
  })
}

// Resize + compress before upload so we stay well under PHP post_max_size.
// maxW/maxH: max pixel dimension; quality: JPEG quality 0-1.
function compressImage(file, maxW = 1200, maxH = 900, quality = 0.82) {
  return new Promise((res, rej) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    img.onload = () => {
      URL.revokeObjectURL(url)
      let { width: w, height: h } = img
      const ratio = Math.min(maxW / w, maxH / h, 1)
      w = Math.round(w * ratio); h = Math.round(h * ratio)
      const canvas = document.createElement('canvas')
      canvas.width = w; canvas.height = h
      canvas.getContext('2d').drawImage(img, 0, 0, w, h)
      res(canvas.toDataURL('image/jpeg', quality))
    }
    img.onerror = rej
    img.src = url
  })
}
</script>

<template>
  <div class="smv-root fx-view-root">

    <!-- Header with action buttons (no subtabs) -->
    <div class="smv-header">
      <span class="smv-title">Service Management</span>
      <div class="smv-header-actions">
        <button class="smv-hbtn" :disabled="!myProfile" @click="openEditProfile">
          <span class="material-symbols-outlined" style="font-size:16px">edit</span> Edit
        </button>
        <button class="smv-hbtn primary" :disabled="!myProfile" @click="openPreview">
          <span class="material-symbols-outlined" style="font-size:16px">visibility</span> Preview
        </button>
      </div>
    </div>

    <!-- One scrolling page �?every section visible, actions via buttons + popups. -->

      <!-- Cover photo -->
      <div class="smv-section-label">Page Background Photo</div>
      <div class="smv-cover-wrap" @click="pickCover">
        <img v-if="coverPreview" :src="coverPreview" class="smv-cover-img" />
        <div v-else class="smv-cover-placeholder">
          <span class="material-symbols-outlined" style="font-size:28px;color:rgba(255,255,255,0.7)">add_photo_alternate</span>
          <span>Tap to set background photo</span>
        </div>
        <div v-if="uploadingCover" class="smv-cover-uploading">
          <span class="material-symbols-outlined smv-spin" style="font-size:28px;color:#fff">autorenew</span>
        </div>
        <input ref="coverFileInput" type="file" accept="image/*" style="display:none" @change="onCoverSelected" />
      </div>

      <!-- Base rate -->
      <div class="smv-section-label">Hourly Base Rate</div>
      <div class="smv-card fx-card smv-rate-row">
        <span class="smv-rate-prefix">RM</span>
        <input class="smv-rate-input" v-model="baseRate" type="number" min="1" step="1" placeholder="45" />
        <span class="smv-rate-suffix">/ hr</span>
        <button class="smv-rate-save" :disabled="savingRate" @click="saveRate">
          {{ savingRate ? '✓' : 'Save' }}
        </button>
      </div>

      <!-- Service items -->
      <div class="smv-section-label">Service Items</div>
      <div v-if="services.length" class="smv-service-list">
        <div v-for="svc in services" :key="svc.sku" class="smv-card fx-card smv-svc-row">
          <img v-if="svc.image_url" :src="svc.image_url" class="smv-svc-img" />
          <div class="smv-svc-info">
            <div class="smv-svc-name">{{ svc.name }}</div>
            <div class="smv-svc-meta">
              <span class="smv-sku-badge">{{ svc.sku }}</span>
              <span class="smv-svc-price">RM{{ svc.price }}</span>
            </div>
            <div v-if="svc.description" class="smv-svc-desc">{{ svc.description }}</div>
          </div>
          <div class="smv-svc-actions">
            <label class="smv-toggle smv-toggle-sm">
              <input type="checkbox" :checked="svc.active" @change="toggleActive(svc)" />
              <span class="smv-slider"></span>
            </label>
            <button class="smv-icon-btn" @click="openEdit(svc)">
              <span class="material-symbols-outlined" style="font-size:18px">edit</span>
            </button>
            <button class="smv-icon-btn danger" @click="deleteService(svc)">
              <span class="material-symbols-outlined" style="font-size:18px">delete</span>
            </button>
          </div>
        </div>
      </div>
      <div v-else class="smv-empty">
        <span class="material-symbols-outlined" style="font-size:40px;color:var(--fx-muted)">build_circle</span>
        <p>No service items yet</p>
      </div>

      <button class="smv-add-btn" @click="openAdd">
        <span class="material-symbols-outlined">add</span> Add Service
      </button>

      <!-- Coupons -->
      <div class="smv-section-label">Active Coupons</div>

      <div v-if="coupons.length" class="smv-service-list">
        <div v-for="c in coupons" :key="c.id" class="smv-card fx-card smv-cpn-row">
          <div class="smv-cpn-left">
            <div class="smv-cpn-code" :class="{ inactive: !c.is_active }">{{ c.code }}</div>
            <div class="smv-cpn-meta">
              {{ discountLabel(c) }}
              <span v-if="c.min_spend"> · Min RM{{ c.min_spend }}</span>
              <span v-if="c.expires_at"> · Expires {{ (c.expires_at || '').slice(0, 10) }}</span>
            </div>
          </div>
          <div class="smv-svc-actions">
            <label class="smv-toggle smv-toggle-sm">
              <input type="checkbox" :checked="c.is_active" @change="toggleCoupon(c)" />
              <span class="smv-slider"></span>
            </label>
            <button class="smv-icon-btn" @click="openEditCoupon(c)">
              <span class="material-symbols-outlined" style="font-size:18px">edit</span>
            </button>
            <button class="smv-icon-btn danger" @click="deleteCoupon(c)">
              <span class="material-symbols-outlined" style="font-size:18px">delete</span>
            </button>
          </div>
        </div>
      </div>
      <div v-else class="smv-empty">
        <span class="material-symbols-outlined" style="font-size:40px;color:var(--fx-muted)">local_offer</span>
        <p>No coupons yet</p>
      </div>

      <button class="smv-add-btn" @click="openAddCoupon">
        <span class="material-symbols-outlined">add</span> Add Coupon
      </button>

    <!-- ══ EDIT PROFILE MODAL ══ -->
    <div v-if="showProfileForm" class="lg-overlay-center" @click.self="showProfileForm = false">
      <div class="lg-modal liquid-glass-high smv-modal">
        <div class="smv-modal-header">
          <span class="smv-modal-title">Edit Profile</span>
          <button class="smv-modal-close" @click="showProfileForm = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="smv-field-group">
          <label class="smv-field-lbl">Bio</label>
          <textarea class="smv-field-input smv-textarea" v-model="profileForm.bio" rows="3" placeholder="Tell customers about your service…"></textarea>
        </div>
        <div class="smv-field-row">
          <div class="smv-field-group" style="flex:2">
            <label class="smv-field-lbl">Location</label>
            <input class="smv-field-input" v-model="profileForm.location" placeholder="e.g. Johor Bahru" />
          </div>
          <div class="smv-field-group" style="flex:1">
            <label class="smv-field-lbl">Rate (RM/hr)</label>
            <input class="smv-field-input" v-model="profileForm.base_rate" type="number" min="1" step="1" placeholder="45" />
          </div>
        </div>
        <div class="smv-modal-footer">
          <button class="smv-modal-cancel" @click="showProfileForm = false">Cancel</button>
          <button class="smv-modal-save" :disabled="savingProfile" @click="saveProfile">{{ savingProfile ? 'Saving…' : 'Save' }}</button>
        </div>
      </div>
    </div>

    <!-- ══ SERVICE FORM MODAL ══ -->
    <div v-if="showServiceForm" class="lg-overlay-center" @click.self="showServiceForm = false">
      <div class="lg-modal liquid-glass-high smv-modal">
        <div class="smv-modal-header">
          <span class="smv-modal-title">{{ editingService ? 'Edit Service' : 'Add Service' }}</span>
          <button class="smv-modal-close" @click="showServiceForm = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="smv-field-group">
          <label class="smv-field-lbl">Service Name *</label>
          <input class="smv-field-input" v-model="svcForm.name" placeholder="e.g. Pipe Repair" />
        </div>
        <div class="smv-field-row">
          <div class="smv-field-group" style="flex:1">
            <label class="smv-field-lbl">SKU</label>
            <input class="smv-field-input" v-model="svcForm.sku" placeholder="Auto-generated" />
          </div>
          <div class="smv-field-group" style="flex:1">
            <label class="smv-field-lbl">Price (RM) *</label>
            <input class="smv-field-input" v-model="svcForm.price" type="number" min="0" step="0.5" placeholder="50" />
          </div>
        </div>
        <div class="smv-field-group">
          <label class="smv-field-lbl">Description</label>
          <textarea class="smv-field-input smv-textarea" v-model="svcForm.description" rows="2" placeholder="Short description…"></textarea>
        </div>
        <div class="smv-field-group">
          <label class="smv-field-lbl">Product Photo</label>
          <div class="smv-img-pick" @click="pickSvcImage">
            <img v-if="svcImagePreview" :src="svcImagePreview" class="smv-img-preview" />
            <div v-else class="smv-img-placeholder">
              <span class="material-symbols-outlined" style="font-size:24px">add_photo_alternate</span>
              <span>{{ uploadingImage ? 'Uploading…' : 'Add photo' }}</span>
            </div>
          </div>
          <input ref="svcImageInput" type="file" accept="image/*" style="display:none" @change="onSvcImageSelected" />
        </div>
        <div class="smv-modal-footer">
          <button class="smv-modal-cancel" @click="showServiceForm = false">Cancel</button>
          <button class="smv-modal-save" @click="saveService" :disabled="!svcForm.name || !svcForm.price">Save</button>
        </div>
      </div>
    </div>

    <!-- ══ COUPON FORM MODAL ══ -->
    <div v-if="showCouponForm" class="lg-overlay-center" @click.self="showCouponForm = false">
      <div class="lg-modal liquid-glass-high smv-modal">
        <div class="smv-modal-header">
          <span class="smv-modal-title">{{ editingCoupon ? 'Edit Coupon' : 'Add Coupon' }}</span>
          <button class="smv-modal-close" @click="showCouponForm = false">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="smv-field-group">
          <label class="smv-field-lbl">Coupon Code *</label>
          <input class="smv-field-input" v-model="cpnForm.code" placeholder="e.g. SAVE20" style="text-transform:uppercase" />
        </div>
        <div class="smv-field-row">
          <div class="smv-field-group" style="flex:1">
            <label class="smv-field-lbl">Discount Type</label>
            <select class="smv-field-input" v-model="cpnForm.discount_type">
              <option value="percent">Percent (%)</option>
              <option value="fixed">Fixed (RM)</option>
            </select>
          </div>
          <div class="smv-field-group" style="flex:1">
            <label class="smv-field-lbl">Value *</label>
            <input class="smv-field-input" v-model="cpnForm.discount_value" type="number" min="0" :placeholder="cpnForm.discount_type === 'percent' ? '10' : '5'" />
          </div>
        </div>
        <div class="smv-field-row">
          <div class="smv-field-group" style="flex:1">
            <label class="smv-field-lbl">Min Order (RM)</label>
            <input class="smv-field-input" v-model="cpnForm.min_spend" type="number" min="0" placeholder="0" />
          </div>
          <div class="smv-field-group" style="flex:1">
            <label class="smv-field-lbl">Expiry Date</label>
            <input class="smv-field-input" v-model="cpnForm.expires_at" type="date" />
          </div>
        </div>
        <div class="smv-modal-footer">
          <button class="smv-modal-cancel" @click="showCouponForm = false">Cancel</button>
          <button class="smv-modal-save" @click="saveCoupon" :disabled="savingCoupon || !cpnForm.code || !cpnForm.discount_value">{{ savingCoupon ? 'Saving…' : 'Save' }}</button>
        </div>
      </div>
    </div>

    <div style="height: calc(88px + env(safe-area-inset-bottom))"></div>
  </div>
</template>

<style scoped>
.smv-root { min-height: 100vh; }

/* Header */
.smv-header {
  padding: 56px 16px 12px;
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  background: rgba(255,255,255,0.60);
  backdrop-filter: blur(28px) saturate(1.4);
  -webkit-backdrop-filter: blur(28px) saturate(1.4);
  border-bottom: 0.5px solid rgba(255,255,255,0.55);
}
.smv-title { font-size: 20px; font-weight: 800; color: var(--fx-text); }
.smv-header-actions { display: flex; gap: 8px; flex-shrink: 0; }
.smv-hbtn {
  display: flex; align-items: center; gap: 4px;
  padding: 7px 12px; border-radius: 999px; cursor: pointer;
  font-size: 13px; font-weight: 700; font-family: inherit;
  border: 0.5px solid rgba(255,102,53,0.35);
  background: rgba(255,102,53,0.06); color: #FF6635;
}
.smv-hbtn.primary { background: #FF6635; color: #fff; border-color: transparent; }
.smv-hbtn:disabled { opacity: 0.5; cursor: not-allowed; }

/* Cards — material from .fx-card */
.smv-card {
  border-radius: 16px;
  padding: 14px 16px;
  margin: 0 12px 8px;
}

.smv-section-label {
  font-size: 11px; font-weight: 700; color: var(--fx-muted);
  padding: 14px 24px 5px; letter-spacing: 0.06em; text-transform: uppercase;
}

/* Rate row */
.smv-rate-row { display: flex; align-items: center; gap: 8px; }
.smv-rate-prefix { font-size: 16px; font-weight: 700; color: var(--fx-muted); }
.smv-rate-input {
  flex: 1; border: none; background: transparent;
  font-size: 22px; font-weight: 800; color: var(--fx-text);
  outline: none; font-family: inherit; width: 80px;
}
.smv-rate-suffix { font-size: 13px; color: var(--fx-muted); }
.smv-rate-save {
  background: #FF6635; color: #fff; border: none; border-radius: 10px;
  padding: 7px 16px; font-size: 13px; font-weight: 700; cursor: pointer;
}
.smv-rate-save:disabled { opacity: 0.6; }

/* Service list */
.smv-service-list { display: flex; flex-direction: column; }
.smv-svc-row { display: flex; align-items: flex-start; gap: 10px; }
.smv-svc-info { flex: 1; min-width: 0; }
.smv-svc-name { font-size: 15px; font-weight: 700; color: var(--fx-text); }
.smv-svc-meta { display: flex; align-items: center; gap: 8px; margin-top: 4px; }
.smv-sku-badge {
  font-size: 10px; font-weight: 700; letter-spacing: 0.05em;
  background: rgba(255,102,53,0.10); color: #FF6635;
  padding: 2px 7px; border-radius: 6px;
}
.smv-svc-price { font-size: 13px; font-weight: 700; color: var(--fx-text); }
.smv-svc-desc  { font-size: 12px; color: var(--fx-muted); margin-top: 3px; }
.smv-svc-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

/* Coupon row */
.smv-cpn-row { display: flex; align-items: center; gap: 10px; }
.smv-cpn-left { flex: 1; }
.smv-cpn-code { font-size: 16px; font-weight: 800; color: #FF6635; letter-spacing: 0.04em; }
.smv-cpn-code.inactive { color: var(--fx-muted); text-decoration: line-through; }
.smv-cpn-meta { font-size: 12px; color: var(--fx-muted); margin-top: 3px; }

/* Add button */
.smv-add-btn {
  display: flex; align-items: center; justify-content: center; gap: 6px;
  width: calc(100% - 24px); margin: 4px 12px 8px;
  padding: 13px; border-radius: 14px; border: 1.5px dashed rgba(255,102,53,0.35);
  background: rgba(255,102,53,0.05); color: #FF6635;
  font-size: 14px; font-weight: 700; cursor: pointer; transition: background .15s;
}
.smv-add-btn:hover { background: rgba(255,102,53,0.10); }

/* Icon buttons */
.smv-icon-btn {
  width: 32px; height: 32px; border-radius: 8px; border: none; cursor: pointer;
  background: rgba(0,0,0,0.04); color: var(--fx-muted);
  display: flex; align-items: center; justify-content: center;
}
.smv-icon-btn.danger { color: #ef4444; background: rgba(239,68,68,0.08); }

/* Toggle */
.smv-toggle { position: relative; cursor: pointer; flex-shrink: 0; }
.smv-toggle-sm { width: 38px; height: 22px; }
.smv-toggle input { opacity: 0; width: 0; height: 0; }
.smv-slider {
  position: absolute; inset: 0; border-radius: 11px;
  background: rgba(0,0,0,0.15); transition: background .2s;
}
.smv-slider::before {
  content: ''; position: absolute;
  border-radius: 50%; background: #fff;
  box-shadow: 0 1px 3px rgba(0,0,0,0.25);
  transition: transform .2s;
}
.smv-toggle-sm .smv-slider::before { width: 18px; height: 18px; left: 2px; top: 2px; }
.smv-toggle input:checked + .smv-slider { background: #FF6635; }
.smv-toggle-sm input:checked + .smv-slider::before { transform: translateX(16px); }

/* Empty state */
.smv-empty { display: flex; flex-direction: column; align-items: center; padding: 32px 0 8px; gap: 8px; }
.smv-empty p { font-size: 13px; color: var(--fx-muted); margin: 0; }

/* Cover photo upload */
.smv-cover-wrap {
  position: relative; margin: 0 12px 8px; border-radius: 16px; overflow: hidden;
  cursor: pointer; height: 130px;
}
.smv-cover-img { width: 100%; height: 100%; object-fit: cover; display: block; }
.smv-cover-placeholder {
  width: 100%; height: 100%;
  background: linear-gradient(160deg, #1e3a5f, #1e4080);
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px;
  font-size: 13px; color: rgba(255,255,255,0.65); font-weight: 600;
}
.smv-cover-uploading {
  position: absolute; inset: 0; background: rgba(0,0,0,0.4);
  display: flex; align-items: center; justify-content: center;
}
@keyframes smv-spin { to { transform: rotate(360deg); } }
.smv-spin { animation: smv-spin 1s linear infinite; display: inline-block; }

/* Service image thumbnail */
.smv-svc-img { width: 56px; height: 56px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }

/* Service modal image picker */
.smv-img-pick {
  border-radius: 12px; overflow: hidden; cursor: pointer;
  border: 1.5px dashed rgba(255,102,53,0.35); height: 90px;
  display: flex; align-items: center; justify-content: center;
  background: rgba(255,102,53,0.04);
}
.smv-img-preview { width: 100%; height: 100%; object-fit: cover; display: block; }
.smv-img-placeholder {
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  color: #FF6635; font-size: 12px; font-weight: 600;
}

/* Modal — shell from .lg-modal.liquid-glass-high */
.smv-modal {
  padding: 20px 20px calc(28px + env(safe-area-inset-bottom));
}
.smv-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.smv-modal-title  { font-size: 17px; font-weight: 800; color: var(--fx-text); }
.smv-modal-close  { background: rgba(0,0,0,0.06); border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.smv-modal-close .material-symbols-outlined { font-size: 18px; }

.smv-field-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.smv-field-row   { display: flex; gap: 12px; }
.smv-field-lbl   { font-size: 12px; font-weight: 600; color: var(--fx-muted); text-transform: uppercase; letter-spacing: 0.03em; }
.smv-field-input {
  padding: 11px 14px; border-radius: 12px;
  border: 0.5px solid rgba(255,255,255,0.60);
  background: rgba(255,255,255,0.45);
  backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  font-size: 15px; color: var(--fx-text); outline: none;
  font-family: inherit; width: 100%; box-sizing: border-box;
}
.smv-field-input:focus { border-color: rgba(255,102,53,0.45); background: rgba(255,255,255,0.55); }
.smv-textarea { resize: none; }

.smv-modal-footer { display: flex; gap: 10px; margin-top: 4px; }
.smv-modal-cancel {
  flex: 1; padding: 13px; border-radius: 14px;
  border: 0.5px solid rgba(255,255,255,0.55);
  background: rgba(255,255,255,0.35);
  font-size: 15px; font-weight: 600; color: var(--fx-text); cursor: pointer;
}
.smv-modal-save {
  flex: 1; padding: 13px; border-radius: 14px; border: none;
  background: #FF6635; color: #fff;
  font-size: 15px; font-weight: 700; cursor: pointer;
}
.smv-modal-save:disabled { opacity: 0.5; }
</style>
