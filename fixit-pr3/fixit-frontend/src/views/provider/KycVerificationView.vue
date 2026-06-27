<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useProvidersStore } from '../../stores/providers'
import { useAuthStore } from '../../stores/auth'
import { analyzeGovernmentId } from '../../services/kyc/idRecognition'
import { runColorReflectionCheck, openFrontCamera } from '../../services/kyc/colorReflectionLiveness'
import * as api from '../../services/api'
import AppIcon from '../../components/AppIcon.vue'

const providersStore = useProvidersStore()
const auth = useAuthStore()

const step = ref(1)
const busy = ref(false)
const error = ref('')
const statusMsg = ref('')

const idFile = ref(null)
const idPreview = ref('')
const idResult = ref(null)

const videoRef = ref(null)
const streamRef = ref(null)
const flashColor = ref(null)
const livenessProgress = ref({ current: 0, total: 8 })
const livenessResult = ref(null)
const kycStatus = ref(null)

// Own profile �?fetched directly so it resolves even while unverified (the
// public providers list is verified-only, which left this null and made the
// "Recognise government ID" button silently no-op).
const myProfile = ref(null)

const statusLabel = computed(() => {
  const s = kycStatus.value?.kyc_status || myProfile.value?.kyc_status || 'none'
  const map = {
    none: 'Not started',
    id_pending: 'ID pending',
    id_passed: 'ID verified — complete face check',
    liveness_pending: 'Face check pending',
    submitted: 'Submitted for admin review',
    failed: 'Verification failed — retry',
  }
  return map[s] || s
})

onMounted(async () => {
  try {
    myProfile.value = await api.getMyProviderProfile()
  } catch {
    // Fallback: maybe the profile is in the (verified-only) store.
    await providersStore.load()
    myProfile.value = providersStore.providers.find((p) => p.user_id === auth.user?.id) || null
  }
  if (!myProfile.value) {
    error.value = 'No provider profile found. Set up your provider profile first.'
    return
  }
  try {
    kycStatus.value = await api.getKycStatus(myProfile.value.id)
    if (kycStatus.value.kyc_status === 'id_passed') step.value = 2
    if (kycStatus.value.kyc_status === 'submitted') step.value = 3
  } catch {
    /* offline / mock */
  }
})

onUnmounted(() => stopCamera())

function onIdSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  idFile.value = file
  idPreview.value = URL.createObjectURL(file)
  idResult.value = null
  error.value = ''
}

function formatRejection(result) {
  const reasons = result?.rejection_reasons?.length
    ? result.rejection_reasons
    : ['Could not verify as a government ID. Use a clear photo with all edges visible.']
  return reasons.join(' ')
}

async function runIdRecognition() {
  if (!idFile.value || !myProfile.value) return
  busy.value = true
  error.value = ''
  statusMsg.value = ''
  try {
    const result = await analyzeGovernmentId(idFile.value, (m) => { statusMsg.value = m })
    idResult.value = result

    const payload = {
      valid: result.valid,
      document_type: result.document_type,
      confidence: result.confidence,
      fraud_score: result.fraud_score,
      ocr_confidence: result.ocr_confidence,
      checks: result.checks,
      filename: idFile.value.name,
      id_image: await fileToDataUrl(idFile.value),
      image_hash: result.image_hash,
      extracted_preview: result.extracted_preview,
      module_version: result.module_version,
    }

    if (!result.valid) {
      error.value = formatRejection(result)
      return
    }

    kycStatus.value = await api.submitKycIdRecognition(myProfile.value.id, payload)
    step.value = 2
  } catch (e) {
    error.value = e.message || 'ID recognition failed'
  } finally {
    busy.value = false
    statusMsg.value = ''
  }
}

async function startCamera() {
  error.value = ''
  try {
    stopCamera()
    const stream = await openFrontCamera()
    streamRef.value = stream
    if (videoRef.value) {
      videoRef.value.srcObject = stream
      await videoRef.value.play()
    }
  } catch {
    error.value = 'Camera access required for face liveness check.'
  }
}

function stopCamera() {
  if (streamRef.value) {
    streamRef.value.getTracks().forEach((t) => t.stop())
    streamRef.value = null
  }
  if (videoRef.value) videoRef.value.srcObject = null
}

function captureVideoFrame(video) {
  if (!video || !video.videoWidth) return null
  const c = document.createElement('canvas')
  c.width = video.videoWidth
  c.height = video.videoHeight
  c.getContext('2d').drawImage(video, 0, 0)
  return c.toDataURL('image/jpeg', 0.9)
}

function fileToDataUrl(file) {
  return new Promise((resolve, reject) => {
    const r = new FileReader()
    r.onload = () => resolve(r.result)
    r.onerror = reject
    r.readAsDataURL(file)
  })
}

async function runLiveness() {
  if (!videoRef.value || !myProfile.value) return
  busy.value = true
  error.value = ''
  try {
    const result = await runColorReflectionCheck(videoRef.value, {
      onFlash: (c) => { flashColor.value = c?.hex || null },
      onProgress: (current, total) => { livenessProgress.value = { current, total } },
    })
    livenessResult.value = result
    flashColor.value = null

    // Capture a live selfie + the ID image so the server can face-match them
    // against each other via the local gateway.
    const selfieImage = captureVideoFrame(videoRef.value)
    const idImage = idFile.value ? await fileToDataUrl(idFile.value) : null

    kycStatus.value = await api.submitKycLiveness(myProfile.value.id, {
      passed: result.passed,
      score: result.score,
      color_sequence_hash: result.color_sequence_hash,
      checks: result.checks,
      selfie_image: selfieImage,
      id_image: idImage,
    })

    if (!result.passed) {
      error.value = `Face liveness failed (${result.score}%). Keep your face in the oval and look at the screen during colour flashes.`
      return
    }
    stopCamera()
    step.value = 3
    await providersStore.load()
  } catch (e) {
    error.value = e.message || 'Liveness check failed'
  } finally {
    busy.value = false
    flashColor.value = null
  }
}

function retry() {
  step.value = 1
  idFile.value = null
  idPreview.value = ''
  idResult.value = null
  livenessResult.value = null
  error.value = ''
}
</script>

<template>
  <div class="fx-page" style="max-width:560px">
    <h1 class="fw-bold mb-1" style="font-size:20px">Identity Verification</h1>
    <p class="mb-3" style="font-size:13px;color:var(--fx-muted)">
      OCR + MRZ + anti-spoof on your ID, then 8-colour liveness plus a server-side 1:1 face match against your ID photo
    </p>

    <div class="fx-card mb-3 d-flex align-items-center gap-2" style="padding:12px">
      <AppIcon name="shield" :size="18" />
      <div>
        <div class="fw-semibold" style="font-size:13px">Status: {{ statusLabel }}</div>
        <div v-if="myProfile?.kyc_id_confidence" style="font-size:12px;color:var(--fx-muted)">
          ID confidence {{ myProfile.kyc_id_confidence }}%
          <span v-if="myProfile.kyc_liveness_score"> · Liveness {{ myProfile.kyc_liveness_score }}%</span>
        </div>
      </div>
    </div>

    <!-- Step indicators -->
    <div class="d-flex gap-2 mb-4">
      <div v-for="n in 3" :key="n" class="flex-fill text-center" style="font-size:11px"
           :style="{ fontWeight: step === n ? 700 : 500, color: step >= n ? 'var(--fx-accent)' : 'var(--fx-muted)' }">
        <div style="height:4px;border-radius:2px;margin-bottom:6px"
             :style="{ background: step >= n ? 'var(--fx-accent)' : 'var(--fx-border)' }"></div>
        {{ n === 1 ? 'Gov ID' : n === 2 ? 'Face check' : 'Done' }}
      </div>
    </div>

    <div v-if="error" class="alert alert-danger py-2" style="font-size:13px">{{ error }}</div>
    <div v-if="statusMsg" class="mb-2" style="font-size:12px;color:var(--fx-muted)">{{ statusMsg }}</div>

    <!-- Step 1: Government ID -->
    <div v-if="step === 1">
      <p style="font-size:13px;color:var(--fx-muted)">
        Photograph your passport, national ID, or driving licence. Use a physical card �?photos of screens, printouts, or edited images are rejected.
      </p>
      <label class="fx-card d-block text-center mb-3" style="padding:24px;cursor:pointer;border:2px dashed var(--fx-border)">
        <input type="file" accept="image/*" capture="environment" class="d-none" @change="onIdSelected" />
        <AppIcon name="shield" :size="32" />
        <div class="mt-2 fw-semibold" style="font-size:14px">{{ idFile ? idFile.name : 'Upload or capture ID photo' }}</div>
      </label>
      <img v-if="idPreview" :src="idPreview" alt="ID preview" class="w-100 mb-3" style="border-radius:12px;max-height:220px;object-fit:contain" />

      <div v-if="idResult" class="fx-card mb-3" style="font-size:12px">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="fw-semibold">{{ idResult.document_label }} · {{ idResult.confidence }}% confidence</span>
          <span class="fx-badge" :style="{
            color: idResult.valid ? 'var(--fx-success)' : 'var(--fx-error)',
            background: idResult.valid ? 'var(--fx-success-soft)' : 'var(--fx-error-soft)',
          }">{{ idResult.valid ? 'Passed' : 'Rejected' }}</span>
        </div>
        <div class="d-flex gap-3 mb-2" style="color:var(--fx-muted)">
          <span>OCR {{ idResult.ocr_confidence }}%</span>
          <span>Fraud score {{ idResult.fraud_score }}</span>
          <span v-if="idResult.checks?.mrz?.found">MRZ {{ idResult.checks.mrz.valid ? 'valid' : 'invalid' }}</span>
        </div>
        <div style="color:var(--fx-muted)">{{ idResult.extracted_preview || 'No text extracted' }}</div>
        <ul v-if="!idResult.valid && idResult.rejection_reasons?.length" class="mt-2 mb-0 ps-3" style="color:var(--fx-error)">
          <li v-for="(r, i) in idResult.rejection_reasons" :key="i">{{ r }}</li>
        </ul>
      </div>

      <button class="btn btn-primary w-100" :disabled="!idFile || busy" @click="runIdRecognition">
        {{ busy ? 'Analysing ID…' : 'Recognise government ID' }}
      </button>
    </div>

    <!-- Step 2: 8-color liveness -->
    <div v-if="step === 2">
      <p style="font-size:13px;color:var(--fx-muted)">
        Centre your face in the oval. The screen flashes <strong>8 random colours</strong> (liveness), then the server compares your live selfie <strong>1:1</strong> with the face on your government ID.
      </p>

      <div class="position-relative mb-3" style="border-radius:16px;overflow:hidden;background:#111">
        <video ref="videoRef" autoplay playsinline muted class="w-100" style="transform:scaleX(-1);min-height:280px;object-fit:cover" />
        <div class="position-absolute top-50 start-50 translate-middle pointer-events-none"
             style="width:55%;height:70%;border:3px solid rgba(255,255,255,0.7);border-radius:50%"></div>
        <div v-if="flashColor" class="position-absolute top-0 start-0 w-100 h-100"
             :style="{ background: flashColor, opacity: 0.55, mixBlendMode: 'screen' }"></div>
        <div v-if="busy" class="position-absolute bottom-0 start-0 w-100 text-center text-white py-2"
             style="background:rgba(0,0,0,0.5);font-size:12px">
          Colour {{ livenessProgress.current }} / {{ livenessProgress.total }}
        </div>
      </div>

      <button v-if="!streamRef" class="btn btn-outline-primary w-100 mb-2" @click="startCamera">Enable camera</button>
      <button class="btn btn-primary w-100" :disabled="!streamRef || busy" @click="runLiveness">
        {{ busy ? 'Running reflection check…' : 'Start 8-colour face check' }}
      </button>
    </div>

    <!-- Step 3: Complete -->
    <div v-if="step === 3" class="text-center py-4">
      <div class="mb-3" style="font-size:48px">✓</div>
      <h2 class="fw-bold" style="font-size:18px">Verification submitted</h2>
      <p style="font-size:13px;color:var(--fx-muted)">
        Your ID, liveness, and ID-to-face match passed automated checks. An admin will review and approve your provider account.
      </p>
      <div v-if="livenessResult || kycStatus" class="fx-card text-start mt-3" style="font-size:12px">
        <div v-if="myProfile?.kyc_id_type">Document: {{ myProfile.kyc_id_type.replace('_', ' ') }}</div>
        <div v-if="myProfile?.kyc_liveness_score">Liveness score: {{ myProfile.kyc_liveness_score }}%</div>
      </div>
      <button v-if="kycStatus?.kyc_status === 'failed'" class="btn btn-outline-primary mt-3" @click="retry">Try again</button>
    </div>
  </div>

  <!-- Full-screen colour flash: the phone screen becomes the light source so the
       face actually reflects each colour (essential for liveness to work on H5). -->
  <Teleport to="body">
    <div v-if="flashColor" class="kyc-flash" :style="{ background: flashColor }"></div>
  </Teleport>
</template>

<style scoped>
.kyc-flash {
  position: fixed;
  inset: 0;
  z-index: 3000;
  pointer-events: none;
}
</style>