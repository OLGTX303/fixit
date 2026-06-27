<script setup>
import { ref, onUnmounted } from 'vue'
import { analyzeGovernmentId } from '../../services/kyc/idRecognition'
import { runColorReflectionCheck, openFrontCamera, REFLECTION_COLORS } from '../../services/kyc/colorReflectionLiveness'
import AppIcon from '../../components/AppIcon.vue'

// Admin debug harness: runs the SAME pipeline providers use — government-ID
// recognition then 8-colour reflection liveness — but purely client-side, with
// no server submit. Mirrors KycVerificationView step-for-step.

const step = ref(1)
const busy = ref(false)
const error = ref('')
const statusMsg = ref('')

// Step 1 — government ID
const idFile = ref(null)
const idPreview = ref('')
const idResult = ref(null)

// Step 2 — liveness
const videoRef = ref(null)
const streamRef = ref(null)
const flashColor = ref(null)
const progress = ref({ current: 0, total: 8 })
const livenessResult = ref(null)
const faceInFrame = ref(false)
let faceTimer = null

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
  if (!idFile.value) return
  busy.value = true
  error.value = ''
  statusMsg.value = ''
  try {
    const result = await analyzeGovernmentId(idFile.value, (m) => { statusMsg.value = m })
    idResult.value = result
    if (!result.valid) {
      error.value = formatRejection(result)
      return
    }
    step.value = 2  // debug: advance locally, no server submit
  } catch (e) {
    error.value = e.message || 'ID recognition failed'
  } finally {
    busy.value = false
    statusMsg.value = ''
  }
}

// Sample the central face region's brightness/variance so the admin can confirm
// the round frame actually contains a face. ponytail: heuristic, swap for a
// face detector if it ever needs to be precise.
function probeFace() {
  const v = videoRef.value
  if (!v || !v.videoWidth) { faceInFrame.value = false; return }
  const w = v.videoWidth, h = v.videoHeight
  const c = document.createElement('canvas')
  c.width = w; c.height = h
  const ctx = c.getContext('2d', { willReadFrequently: true })
  ctx.drawImage(v, 0, 0, w, h)
  const cx = w / 2, cy = h * 0.42, rx = w * 0.22, ry = h * 0.28
  const data = ctx.getImageData(0, 0, w, h).data
  let sum = 0, sumSq = 0, n = 0
  for (let y = 0; y < h; y += 6) {
    for (let x = 0; x < w; x += 6) {
      const dx = (x - cx) / rx, dy = (y - cy) / ry
      if (dx * dx + dy * dy <= 1) {
        const i = (y * w + x) * 4
        const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]
        sum += lum; sumSq += lum * lum; n++
      }
    }
  }
  if (!n) { faceInFrame.value = false; return }
  const mean = sum / n
  faceInFrame.value = mean > 45 && (sumSq / n - mean * mean) > 120
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
    faceTimer = setInterval(probeFace, 300)
  } catch {
    error.value = 'Camera access required for the liveness test.'
  }
}

function stopCamera() {
  if (faceTimer) { clearInterval(faceTimer); faceTimer = null }
  if (streamRef.value) {
    streamRef.value.getTracks().forEach((t) => t.stop())
    streamRef.value = null
  }
  if (videoRef.value) videoRef.value.srcObject = null
  faceInFrame.value = false
}

async function runLiveness() {
  if (!videoRef.value) return
  busy.value = true
  error.value = ''
  livenessResult.value = null
  try {
    const result = await runColorReflectionCheck(videoRef.value, {
      onFlash: (c) => { flashColor.value = c?.hex || null },
      onProgress: (current, total) => { progress.value = { current, total } },
    })
    livenessResult.value = result
    flashColor.value = null
    if (!result.passed) {
      error.value = `Face liveness failed (${result.score}%). Keep your face in the circle and look at the screen during colour flashes.`
      return
    }
    stopCamera()
    step.value = 3
  } catch (e) {
    error.value = e.message || 'Liveness check failed'
  } finally {
    busy.value = false
    flashColor.value = null
  }
}

function retry() {
  stopCamera()
  step.value = 1
  idFile.value = null
  idPreview.value = ''
  idResult.value = null
  livenessResult.value = null
  error.value = ''
}

onUnmounted(stopCamera)
</script>

<template>
  <div class="fx-page" style="max-width:560px">
    <h1 class="fw-bold mb-1" style="font-size:20px">KYC Debug · full provider flow</h1>
    <p class="mb-3" style="font-size:13px;color:var(--fx-muted)">
      Admin test harness — same gov-ID recognition + 8-colour liveness providers run. Nothing is submitted or saved.
    </p>

    <!-- Step indicators (mirror provider KYC) -->
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

    <!-- Step 1: Government ID upload (same engine as provider side) -->
    <div v-if="step === 1">
      <p style="font-size:13px;color:var(--fx-muted)">
        Upload or capture a passport, national ID, or driving licence. OCR + MRZ + anti-spoof checks run client-side.
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
            background: idResult.valid ? 'var(--fx-success-soft)' : 'var(--fx-error-soft)' }">
            {{ idResult.valid ? 'Passed' : 'Rejected' }}</span>
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

    <!-- Step 2: 8-colour liveness in a round camera frame -->
    <div v-if="step === 2">
      <p style="font-size:13px;color:var(--fx-muted)">
        Centre your face in the circle. The screen flashes <strong>8 random colours</strong> — your skin should reflect each one.
      </p>
      <div class="d-flex flex-column align-items-center mb-3">
        <div class="kyc-cam-ring" :class="{ 'is-face': faceInFrame, 'is-empty': streamRef && !faceInFrame }">
          <video ref="videoRef" autoplay playsinline muted class="kyc-cam-video" />
          <div v-if="busy" class="kyc-cam-badge">Colour {{ progress.current }} / {{ progress.total }}</div>
        </div>
        <div v-if="streamRef" class="mt-2 d-flex align-items-center gap-1" style="font-size:12px"
             :style="{ color: faceInFrame ? 'var(--fx-success)' : 'var(--fx-warn)' }">
          <AppIcon :name="faceInFrame ? 'shield' : 'user'" :size="14" />
          {{ faceInFrame ? 'Face in frame' : 'Move your face into the circle' }}
        </div>
      </div>

      <button v-if="!streamRef" class="btn btn-outline-primary w-100 mb-2" @click="startCamera">Enable camera</button>
      <button class="btn btn-primary w-100" :disabled="!streamRef || busy" @click="runLiveness">
        {{ busy ? 'Running reflection check…' : 'Start 8-colour face check' }}
      </button>

      <div v-if="livenessResult" class="fx-card mt-3" style="font-size:13px">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="fw-semibold">Liveness result</span>
          <span class="fx-badge" :style="{
            color: livenessResult.passed ? 'var(--fx-success)' : 'var(--fx-error)',
            background: livenessResult.passed ? 'var(--fx-success-soft)' : 'var(--fx-error-soft)' }">
            {{ livenessResult.passed ? 'PASSED' : 'FAILED' }} · {{ livenessResult.score }}%
          </span>
        </div>
        <div style="font-size:12px;color:var(--fx-muted)">
          {{ livenessResult.checks?.matches }}/{{ livenessResult.checks?.colors_tested }} colours reflected (threshold {{ livenessResult.checks?.threshold }})
        </div>
        <div class="d-flex flex-wrap gap-1 mt-2">
          <span v-for="(f, i) in livenessResult.checks?.flash_results || []" :key="i"
                class="fx-badge" style="font-size:11px"
                :style="{
                  color: f.reflected ? 'var(--fx-success)' : 'var(--fx-error)',
                  background: f.reflected ? 'var(--fx-success-soft)' : 'var(--fx-error-soft)' }">
            {{ f.color }} {{ f.reflected ? '✓' : '✗' }} ({{ f.channel_delta }})
          </span>
        </div>
      </div>
    </div>

    <!-- Step 3: Complete -->
    <div v-if="step === 3" class="text-center py-4">
      <div class="mb-3" style="font-size:48px">✓</div>
      <h2 class="fw-bold" style="font-size:18px">Both checks passed</h2>
      <p style="font-size:13px;color:var(--fx-muted)">
        Government ID and 8-colour face liveness both passed automated checks (debug — nothing submitted).
      </p>
      <div class="fx-card text-start mt-3" style="font-size:12px">
        <div v-if="idResult">ID: {{ idResult.document_label }} · {{ idResult.confidence }}% · fraud {{ idResult.fraud_score }}</div>
        <div v-if="livenessResult">Liveness: {{ livenessResult.score }}% ({{ livenessResult.checks?.matches }}/{{ livenessResult.checks?.colors_tested }})</div>
      </div>
      <button class="btn btn-outline-primary mt-3" @click="retry">Run again</button>
    </div>

    <div class="fx-card mt-3" style="font-size:11px;color:var(--fx-muted)">
      Palette: {{ REFLECTION_COLORS.map(c => c.name).join(', ') }} — shuffled each run.
    </div>
  </div>

  <!-- Full-screen colour flash: the screen becomes the light source so the face
       reflects each colour (required for the reflection check to register). -->
  <Teleport to="body">
    <div v-if="flashColor" class="kyc-flash" :style="{ background: flashColor }"></div>
  </Teleport>
</template>

<style scoped>
.kyc-cam-ring {
  position: relative;
  width: 280px;
  height: 280px;
  border-radius: 50%;
  overflow: hidden;
  background: #111;
  border: 4px solid rgba(255, 255, 255, 0.6);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
  transition: border-color 0.2s ease;
}
.kyc-cam-ring.is-face { border-color: var(--fx-success); }
.kyc-cam-ring.is-empty { border-color: var(--fx-warn); }
.kyc-cam-video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scaleX(-1);
}
.kyc-cam-badge {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  text-align: center;
  padding: 6px;
  font-size: 12px;
  color: #fff;
  background: rgba(0, 0, 0, 0.5);
}
.kyc-flash {
  position: fixed;
  inset: 0;
  z-index: 3000;
  pointer-events: none;
}
</style>
