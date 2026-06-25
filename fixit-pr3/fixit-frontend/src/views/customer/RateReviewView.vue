<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useProvidersStore } from '../../stores/providers'
import * as api from '../../services/api'
import RatingStars from '../../components/RatingStars.vue'
import AppIcon from '../../components/AppIcon.vue'
import { useModalGuard } from '../../composables/useModalGuard'

useModalGuard()

const route = useRoute()
const router = useRouter()
const bookingsStore = useBookingsStore()
const providersStore = useProvidersStore()
const error = ref('')

const booking = computed(() => bookingsStore.byId(route.params.id))

// Review form (v-model bound).
const rating = ref(5)
const tags = ['On Time', 'Professional', 'Quality Work', 'Great Value', 'Friendly', 'Clean']
const selectedTags = ref(['On Time', 'Professional', 'Quality Work'])
const comment = ref('')
const tipAmount = ref('')
const submitting = ref(false)

// Photo upload
const photos = ref([])   // [{ preview: dataUrl, uploading: false, url: null }]
const maxPhotos = 5

function onPhotoPick(e) {
  const files = Array.from(e.target.files || [])
  for (const file of files) {
    if (photos.value.length >= maxPhotos) break
    const reader = new FileReader()
    reader.onload = ev => photos.value.push({ preview: ev.target.result, uploading: false, url: null })
    reader.readAsDataURL(file)
  }
  e.target.value = ''
}
function removePhoto(i) { photos.value.splice(i, 1) }

async function uploadPhotos() {
  await Promise.all(photos.value.map(async (p) => {
    if (p.url) return
    p.uploading = true
    try {
      const res = await api.uploadImage(p.preview)
      p.url = res.url
    } catch { p.url = p.preview }  // fallback: skip on R2 error
    finally { p.uploading = false }
  }))
}

onMounted(() => bookingsStore.load())

function toggleTag(t) {
  const i = selectedTags.value.indexOf(t)
  i === -1 ? selectedTags.value.push(t) : selectedTags.value.splice(i, 1)
}

async function submit() {
  submitting.value = true
  error.value = ''
  try {
    await uploadPhotos()
    const imageUrls = photos.value.map(p => p.url).filter(Boolean)
    await api.createReview({
      job_id:     booking.value.id,
      rating:     rating.value,
      comment:    comment.value || selectedTags.value.join(', '),
      tip_amount: tipAmount.value !== '' ? parseFloat(tipAmount.value) : null,
      image_urls: imageUrls,
    })
    // The backend marks the job 'reviewed' on review create — don't PATCH the
    // status again (reviewed→reviewed is rejected and used to abort navigation).
    const b = bookingsStore.byId(booking.value.id)
    if (b) b.status = 'reviewed'
    // Refresh the provider directory so the new rating shows without re-login.
    try { await providersStore.reload() } catch { /* non-fatal */ }
    router.push({ name: 'job-tracker', query: { id: booking.value.id } })
  } catch (e) {
    error.value = e.message || 'Could not submit review. Please try again.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div v-if="booking" class="fx-page" style="max-width:480px">
    <div class="d-flex align-items-center gap-2 mb-4">
      <button class="btn btn-light rounded-circle" style="width:34px;height:34px;padding:0" @click="router.back()">
        <AppIcon name="back" :size="16" />
      </button>
      <h1 class="fw-bold m-0" style="font-size:20px">Rate Experience</h1>
    </div>

    <div class="text-center mb-4">
      <div class="fx-avatar mx-auto mb-2" style="width:64px;height:64px;font-size:24px">
        {{ (booking.provider?.name || '?').split(' ').map(w => w[0]).join('') }}
      </div>
      <div class="fw-bold" style="font-size:18px">{{ booking.provider?.name }}</div>
      <div style="font-size:13px;color:var(--fx-muted)">{{ booking.category?.name }} · #{{ booking.id }}</div>
    </div>

    <div class="text-center mb-4">
      <div class="mb-2" style="font-size:15px;font-weight:600;color:var(--fx-muted)">How was the service?</div>
      <div class="d-flex justify-content-center">
        <RatingStars interactive v-model="rating" :size="38" />
      </div>
    </div>

    <div class="fw-semibold mb-2" style="font-size:14px">What stood out?</div>
    <div class="d-flex flex-wrap gap-2 mb-4">
      <span v-for="t in tags" :key="t" class="fx-chip sm" :class="{ active: selectedTags.includes(t) }"
            @click="toggleTag(t)">{{ t }}</span>
    </div>

    <div class="fw-semibold mb-2" style="font-size:14px">Write a Review</div>
    <textarea class="fx-input mb-4" rows="3" v-model="comment"
              placeholder="Tell others about your experience…" style="resize:none"></textarea>

    <!-- Photo upload -->
    <div class="fw-semibold mb-2" style="font-size:14px">Add Photos <span style="font-weight:400;color:var(--fx-muted)">(up to 5)</span></div>
    <div class="d-flex gap-2 flex-wrap mb-4">
      <div v-for="(p,i) in photos" :key="i" style="position:relative;width:72px;height:72px">
        <img :src="p.preview" style="width:72px;height:72px;border-radius:10px;object-fit:cover;border:1px solid var(--fx-border)" />
        <button @click="removePhoto(i)"
                style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;border:none;background:#ef4444;color:#fff;font-size:13px;display:flex;align-items:center;justify-content:center;cursor:pointer;line-height:1">✕</button>
      </div>
      <label v-if="photos.length < maxPhotos"
             style="width:72px;height:72px;border-radius:10px;border:2px dashed var(--fx-border);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;cursor:pointer;color:var(--fx-muted)">
        <span class="material-symbols-outlined" style="font-size:22px">add_photo_alternate</span>
        <span style="font-size:10px;font-weight:600">Photo</span>
        <input type="file" accept="image/*" multiple style="display:none" @change="onPhotoPick">
      </label>
    </div>

    <div class="fw-semibold mb-2" style="font-size:14px">Add a Tip <span style="font-weight:400;color:var(--fx-muted)">(optional)</span></div>
    <div class="d-flex align-items-center gap-2 mb-3">
      <div class="d-flex gap-2">
        <button v-for="amt in [2, 5, 10]" :key="amt"
          class="fx-chip sm" :class="{ active: tipAmount == amt }"
          @click="tipAmount = tipAmount == amt ? '' : amt">
          ${{ amt }}
        </button>
      </div>
      <input class="fx-input" type="number" min="0" step="0.5" v-model="tipAmount"
             placeholder="Custom" style="width:90px;text-align:center" />
    </div>

    <div v-if="error" class="alert alert-danger py-2 mb-2" style="font-size:13px">{{ error }}</div>
    <button class="btn btn-primary w-100" :disabled="submitting" @click="submit">
      {{ submitting ? 'Submitting…' : 'Submit Review' }}
    </button>
    <div class="text-center mt-3">
      <span role="button" style="font-size:13px;color:var(--fx-muted)" @click="router.back()">Skip for now</span>
    </div>
  </div>

  <div v-else class="fx-page text-center py-5" style="color:var(--fx-muted)">Loading…</div>
</template>
