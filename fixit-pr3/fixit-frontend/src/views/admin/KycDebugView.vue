<script setup>
import { ref, onUnmounted } from 'vue'
import { runColorReflectionCheck, openFrontCamera, REFLECTION_COLORS } from '../../services/kyc/colorReflectionLiveness'
import AppIcon from '../../components/AppIcon.vue'

// Admin debug tool: exercise the 8-colour liveness engine without a provider
// account or server submit. Pure client test — nothing is persisted.

const videoRef = ref(null)
const streamRef = ref(null)
const flashColor = ref(null)
const busy = ref(false)
const error = ref('')
const progress = ref({ current: 0, total: 8 })
const result = ref(null)
const faceInFrame = ref(false)  // live "is a face actually visible" indicator

let faceTimer = null

// Sample the central face region's brightness/colour variation so the admin can
// confirm the round frame actually contains a face (not a covered/dark camera).
// ponytail: brightness+variance heuristic, swap for a face detector if needed.
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
  const variance = sumSq / n - mean * mean
  // Lit + textured region → a face is present; dark or flat → empty/covered.
  faceInFrame.value = mean > 45 && variance > 120
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

async function runTest() {
  if (!videoRef.value) return
  busy.value = true
  error.value = ''
  result.value = null
  try {
    result.value = await runColorReflectionCheck(videoRef.value, {
      onFlash: (c) => { flashColor.value = c?.hex || null },
      onProgress: (current, total) => { progress.value = { current, total } },
    })
  } catch (e) {
    error.value = e.message || 'Liveness test failed'
  } finally {
    busy.value = false
    flashColor.value = null
  }
}

onUnmounted(stopCamera)
</script>

<template>
  <div class="fx-page" style="max-width:560px">
    <h1 class="fw-bold mb-1" style="font-size:20px">KYC Debug · 8-colour liveness</h1>
    <p class="mb-3" style="font-size:13px;color:var(--fx-muted)">
      Admin test harness — runs the same reflection engine providers use. Nothing is submitted or saved.
    </p>

    <div v-if="error" class="alert alert-danger py-2" style="font-size:13px">{{ error }}</div>

    <!-- Round camera frame: the live feed is clipped to a circle so the face is
         clearly visible inside the frame the check samples. -->
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
    <button v-else class="btn btn-primary w-100" :disabled="busy" @click="runTest">
      {{ busy ? 'Running reflection check…' : 'Run 8-colour face check' }}
    </button>

    <!-- Result -->
    <div v-if="result" class="fx-card mt-3" style="font-size:13px">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="fw-semibold">Result</span>
        <span class="fx-badge" :style="{
          color: result.passed ? 'var(--fx-success)' : 'var(--fx-error)',
          background: result.passed ? 'var(--fx-success-soft)' : 'var(--fx-error-soft)' }">
          {{ result.passed ? 'PASSED' : 'FAILED' }} · {{ result.score }}%
        </span>
      </div>
      <div style="font-size:12px;color:var(--fx-muted)">
        {{ result.checks?.matches }}/{{ result.checks?.colors_tested }} colours reflected (threshold {{ result.checks?.threshold }})
      </div>
      <div v-if="result.checks?.baseline_rgb" style="font-size:11px;color:var(--fx-muted)">
        baseline rgb({{ result.checks.baseline_rgb.r }}, {{ result.checks.baseline_rgb.g }}, {{ result.checks.baseline_rgb.b }})
      </div>
      <div class="d-flex flex-wrap gap-1 mt-2">
        <span v-for="(f, i) in result.checks?.flash_results || []" :key="i"
              class="fx-badge" style="font-size:11px"
              :style="{
                color: f.reflected ? 'var(--fx-success)' : 'var(--fx-error)',
                background: f.reflected ? 'var(--fx-success-soft)' : 'var(--fx-error-soft)' }">
          {{ f.color }} {{ f.reflected ? '✓' : '✗' }} ({{ f.channel_delta }})
        </span>
      </div>
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
  transform: scaleX(-1); /* mirror for a natural selfie view */
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
