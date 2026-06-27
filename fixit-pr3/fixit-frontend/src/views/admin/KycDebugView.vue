<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { analyzeGovernmentId } from '../../services/kyc/idRecognition'
import { runColorReflectionCheck, openFrontCamera, REFLECTION_COLORS } from '../../services/kyc/colorReflectionLiveness'
import AppIcon from '../../components/AppIcon.vue'

// Admin debug harness: runs the SAME pipeline providers use — government-ID
// recognition then 8-colour reflection liveness — purely client-side, no submit.

const router = useRouter()

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

const STEPS = ['Gov ID', 'Face check', 'Done']
const progressPct = computed(() => `${((step.value - 1) / (STEPS.length - 1)) * 100}%`)

function onIdSelected(e) {
  const file = e.target.files?.[0]
  if (!file) return
  idFile.value = file
  idPreview.value = URL.createObjectURL(file)
  idResult.value = null
  error.value = ''
}

function clearId() {
  idFile.value = null
  idPreview.value = ''
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
    if (!result.valid) error.value = formatRejection(result)
  } catch (e) {
    error.value = e.message || 'ID recognition failed'
  } finally {
    busy.value = false
    statusMsg.value = ''
  }
}

// Brightness/variance probe so the admin can confirm the round frame holds a
// face. ponytail: heuristic, swap for a real detector only if it must be exact.
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

function goLiveness() { error.value = ''; step.value = 2 }
function backToId() { stopCamera(); error.value = ''; step.value = 1 }

function retry() {
  stopCamera()
  step.value = 1
  clearId()
  livenessResult.value = null
}

onUnmounted(stopCamera)
</script>

<template>
  <div class="fx-page kyc-dbg" style="max-width:560px">
    <!-- Commercial header -->
    <div class="kyc-hero mb-3">
      <div class="kyc-hero-icon"><AppIcon name="shield" :size="22" /></div>
      <div>
        <div class="fw-bold" style="font-size:18px;line-height:1.2">Identity Verification</div>
        <div style="font-size:12px;opacity:.92">Debug harness · gov-ID + 8-colour liveness · nothing saved</div>
      </div>
    </div>

    <!-- Animated step rail -->
    <div class="kyc-rail mb-4">
      <div class="kyc-rail-track"><div class="kyc-rail-fill" :style="{ width: progressPct }"></div></div>
      <div class="d-flex justify-content-between">
        <div v-for="(label, i) in STEPS" :key="label" class="kyc-rail-step"
             :class="{ active: step === i + 1, done: step > i + 1 }">
          <span class="kyc-rail-dot">{{ step > i + 1 ? '✓' : i + 1 }}</span>
          <span style="font-size:11px">{{ label }}</span>
        </div>
      </div>
    </div>

    <Transition name="fx-fade"><div v-if="error" class="alert alert-danger py-2" style="font-size:13px">{{ error }}</div></Transition>
    <Transition name="fx-fade"><div v-if="statusMsg" class="mb-2 d-flex align-items-center gap-2" style="font-size:12px;color:var(--fx-muted)">
      <span class="kyc-spin"></span>{{ statusMsg }}</div></Transition>

    <Transition name="fx-step" mode="out-in">
      <div :key="step">
        <!-- Step 1: Government ID -->
        <div v-if="step === 1">
          <p style="font-size:13px;color:var(--fx-muted)">
            Upload or capture a passport, national ID, or driving licence. OCR + MRZ + anti-spoof checks run client-side.
          </p>
          <label class="kyc-drop mb-3" :class="{ filled: idPreview }">
            <input type="file" accept="image/*" capture="environment" class="d-none" @change="onIdSelected" />
            <img v-if="idPreview" :src="idPreview" alt="ID preview" class="kyc-drop-img" />
            <template v-else>
              <AppIcon name="shield" :size="34" />
              <div class="mt-2 fw-semibold" style="font-size:14px">Upload or capture ID photo</div>
              <div style="font-size:11px;color:var(--fx-muted)">JPG / PNG · physical card, not a screen</div>
            </template>
          </label>

          <Transition name="fx-fade">
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
                <span>Fraud {{ idResult.fraud_score }}</span>
                <span v-if="idResult.checks?.mrz?.found">MRZ {{ idResult.checks.mrz.valid ? 'valid' : 'invalid' }}</span>
              </div>
              <div style="color:var(--fx-muted)">{{ idResult.extracted_preview || 'No text extracted' }}</div>
              <ul v-if="!idResult.valid && idResult.rejection_reasons?.length" class="mt-2 mb-0 ps-3" style="color:var(--fx-error)">
                <li v-for="(r, i) in idResult.rejection_reasons" :key="i">{{ r }}</li>
              </ul>
            </div>
          </Transition>

          <div class="kyc-actions">
            <button v-if="idFile" class="btn btn-ghost" :disabled="busy" @click="clearId">Clear</button>
            <button v-if="!idResult?.valid" class="btn btn-primary flex-fill" :disabled="!idFile || busy" @click="runIdRecognition">
              <span v-if="busy" class="kyc-spin me-2"></span>{{ busy ? 'Analysing ID…' : 'Recognise government ID' }}
            </button>
            <button v-else class="btn btn-primary flex-fill" @click="goLiveness">Continue to face check →</button>
          </div>
        </div>

        <!-- Step 2: 8-colour liveness -->
        <div v-else-if="step === 2">
          <p style="font-size:13px;color:var(--fx-muted)">
            Centre your face in the circle. The screen flashes <strong>8 random colours</strong> — your skin should reflect each one.
          </p>
          <div class="d-flex flex-column align-items-center mb-3">
            <div class="kyc-cam-ring" :class="{ 'is-face': faceInFrame, 'is-empty': streamRef && !faceInFrame, 'is-busy': busy }">
              <video ref="videoRef" autoplay playsinline muted class="kyc-cam-video" />
              <div v-if="busy" class="kyc-cam-badge">Colour {{ progress.current }} / {{ progress.total }}</div>
            </div>
            <Transition name="fx-fade">
              <div v-if="streamRef" class="kyc-face-tag mt-2" :class="faceInFrame ? 'ok' : 'warn'">
                <AppIcon :name="faceInFrame ? 'shield' : 'user'" :size="14" />
                {{ faceInFrame ? 'Face in frame' : 'Move your face into the circle' }}
              </div>
            </Transition>
          </div>

          <Transition name="fx-fade">
            <div v-if="livenessResult" class="fx-card mb-3" style="font-size:13px">
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
                  {{ f.color }} {{ f.reflected ? '✓' : '✗' }}
                </span>
              </div>
            </div>
          </Transition>

          <div class="kyc-actions">
            <button class="btn btn-ghost" :disabled="busy" @click="backToId">← Back</button>
            <button v-if="!streamRef" class="btn btn-outline-primary flex-fill" @click="startCamera">Enable camera</button>
            <button v-else class="btn btn-primary flex-fill" :disabled="busy" @click="runLiveness">
              <span v-if="busy" class="kyc-spin me-2"></span>{{ busy ? 'Running…' : 'Start 8-colour check' }}
            </button>
          </div>
        </div>

        <!-- Step 3: Done -->
        <div v-else class="text-center py-3">
          <div class="kyc-check">✓</div>
          <h2 class="fw-bold mt-2" style="font-size:18px">Both checks passed</h2>
          <p style="font-size:13px;color:var(--fx-muted)">
            Government ID and 8-colour face liveness both passed (debug — nothing submitted).
          </p>
          <div class="fx-card text-start mt-3" style="font-size:12px">
            <div v-if="idResult" class="mb-1">ID: {{ idResult.document_label }} · {{ idResult.confidence }}% · fraud {{ idResult.fraud_score }}</div>
            <div v-if="livenessResult">Liveness: {{ livenessResult.score }}% ({{ livenessResult.checks?.matches }}/{{ livenessResult.checks?.colors_tested }})</div>
          </div>
          <div class="kyc-actions mt-3">
            <button class="btn btn-outline-primary flex-fill" @click="retry">↻ Run again</button>
            <button class="btn btn-primary flex-fill" @click="router.push({ name: 'admin-verify' })">Back to Verifications</button>
          </div>
        </div>
      </div>
    </Transition>

    <div class="fx-card mt-3" style="font-size:11px;color:var(--fx-muted)">
      Palette: {{ REFLECTION_COLORS.map(c => c.name).join(', ') }} — shuffled each run.
    </div>
  </div>

  <!-- Colour flash: the screen is the light source. Stops above the floating
       dock so the bottom nav stays visible during the check. -->
  <Teleport to="body">
    <div v-if="flashColor" class="kyc-flash" :style="{ background: flashColor }"></div>
  </Teleport>
</template>

<style scoped>
.kyc-dbg { padding-bottom: 120px; }

.kyc-hero {
  display: flex; align-items: center; gap: 12px;
  padding: 16px;
  border-radius: var(--fx-radius);
  background: linear-gradient(135deg, var(--fx-accent) 0%, var(--fx-accent-dark) 100%);
  color: #fff;
  box-shadow: 0 10px 28px rgba(255, 102, 53, 0.28);
}
.kyc-hero-icon {
  width: 44px; height: 44px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  border-radius: 12px; background: rgba(255, 255, 255, 0.2); color: #fff;
}

.kyc-rail-track { height: 4px; border-radius: 2px; background: var(--fx-border); margin-bottom: 10px; overflow: hidden; }
.kyc-rail-fill {
  height: 100%; border-radius: 2px;
  background: linear-gradient(90deg, var(--fx-accent), var(--fx-accent-light));
  transition: width 0.45s cubic-bezier(0.32, 0.72, 0, 1);
}
.kyc-rail-step { display: flex; flex-direction: column; align-items: center; gap: 4px; color: var(--fx-muted); font-weight: 500; }
.kyc-rail-step.active, .kyc-rail-step.done { color: var(--fx-accent); }
.kyc-rail-dot {
  width: 24px; height: 24px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700;
  background: var(--fx-border); color: var(--fx-muted);
  transition: all 0.35s ease;
}
.kyc-rail-step.active .kyc-rail-dot { background: var(--fx-accent); color: #fff; transform: scale(1.12); box-shadow: 0 4px 12px rgba(255,102,53,0.4); }
.kyc-rail-step.done .kyc-rail-dot { background: var(--fx-success); color: #fff; }

.kyc-drop {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  min-height: 150px; padding: 20px; cursor: pointer;
  border: 2px dashed var(--fx-border); border-radius: var(--fx-radius);
  background: var(--fx-accent-soft);
  transition: border-color 0.2s ease, transform 0.15s ease;
}
.kyc-drop:hover { border-color: var(--fx-accent); transform: translateY(-1px); }
.kyc-drop.filled { padding: 6px; background: transparent; }
.kyc-drop-img { width: 100%; max-height: 220px; object-fit: contain; border-radius: 12px; }

.kyc-actions { display: flex; gap: 10px; align-items: center; }
.btn-ghost { background: transparent; border: 1px solid var(--fx-border); color: var(--fx-muted); }
.btn-ghost:hover { border-color: var(--fx-accent); color: var(--fx-accent); }
.btn { transition: transform 0.12s ease, box-shadow 0.2s ease, background 0.2s ease, color 0.2s ease, border-color 0.2s ease; }
.btn:active:not(:disabled) { transform: scale(0.97); }

.kyc-cam-ring {
  position: relative; width: 280px; height: 280px;
  border-radius: 50%; overflow: hidden; background: #111;
  border: 4px solid var(--fx-border);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
  transition: border-color 0.25s ease, box-shadow 0.25s ease;
}
.kyc-cam-ring.is-face { border-color: var(--fx-success); box-shadow: 0 0 0 4px var(--fx-success-soft), 0 8px 30px rgba(0,0,0,0.25); animation: kyc-pulse 1.8s ease-in-out infinite; }
.kyc-cam-ring.is-empty { border-color: var(--fx-warn); }
.kyc-cam-ring.is-busy { border-color: var(--fx-accent); }
.kyc-cam-video { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
.kyc-cam-badge { position: absolute; bottom: 0; left: 0; right: 0; text-align: center; padding: 6px; font-size: 12px; color: #fff; background: rgba(0,0,0,0.5); }

.kyc-face-tag { display: flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 500; padding: 4px 12px; border-radius: 999px; }
.kyc-face-tag.ok { color: var(--fx-success); background: var(--fx-success-soft); }
.kyc-face-tag.warn { color: var(--fx-warn); background: var(--fx-warn-soft); }

.kyc-check {
  width: 72px; height: 72px; margin: 0 auto;
  display: flex; align-items: center; justify-content: center;
  border-radius: 50%; background: var(--fx-success); color: #fff; font-size: 38px;
  animation: kyc-pop 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.kyc-spin { display: inline-block; width: 14px; height: 14px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: kyc-rotate 0.7s linear infinite; vertical-align: -2px; }

/* Flash stops short of the floating dock so the nav stays visible. */
.kyc-flash { position: fixed; top: 0; left: 0; right: 0; bottom: 110px; z-index: 2400; pointer-events: none; }

.fx-step-enter-active, .fx-step-leave-active { transition: opacity 0.3s ease, transform 0.3s cubic-bezier(0.32,0.72,0,1); }
.fx-step-enter-from { opacity: 0; transform: translateX(18px); }
.fx-step-leave-to { opacity: 0; transform: translateX(-18px); }
.fx-fade-enter-active, .fx-fade-leave-active { transition: opacity 0.25s ease; }
.fx-fade-enter-from, .fx-fade-leave-to { opacity: 0; }

@keyframes kyc-pulse { 0%,100% { box-shadow: 0 0 0 4px var(--fx-success-soft), 0 8px 30px rgba(0,0,0,0.25); } 50% { box-shadow: 0 0 0 10px rgba(34,197,94,0.06), 0 8px 30px rgba(0,0,0,0.25); } }
@keyframes kyc-pop { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
@keyframes kyc-rotate { to { transform: rotate(360deg); } }

@media (prefers-reduced-motion: reduce) {
  .kyc-cam-ring.is-face { animation: none; }
  .fx-step-enter-active, .fx-step-leave-active, .btn { transition: none; }
}
</style>
