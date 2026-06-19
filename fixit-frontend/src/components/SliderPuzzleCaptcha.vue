<script setup>
import { ref, onMounted } from 'vue'
import * as api from '../services/api'
import AppIcon from './AppIcon.vue'

const emit = defineEmits(['verified', 'reset'])

const loading = ref(true)
const error = ref('')
const verifying = ref(false)
const verified = ref(false)

const challenge = ref(null)
const sliderX = ref(0)
const maxSlide = ref(0)
const dragging = ref(false)
const dragStartX = ref(0)
const dragStartOffset = ref(0)
const dragStartedAt = ref(0)
const dragMs = ref(0)

const passToken = ref('')
const captchaId = ref('')

async function loadChallenge() {
  loading.value = true
  error.value = ''
  verified.value = false
  passToken.value = ''
  captchaId.value = ''
  sliderX.value = 0
  emit('reset')

  try {
    const data = await api.getCaptchaChallenge()
    challenge.value = data
    captchaId.value = data.captcha_id
    maxSlide.value = data.width - data.piece_size
  } catch (e) {
    error.value = e.message || 'Could not load verification puzzle'
    challenge.value = null
  } finally {
    loading.value = false
  }
}

function pointerX(e) {
  return e.clientX ?? e.touches?.[0]?.clientX ?? 0
}

function bindDragListeners() {
  window.addEventListener('pointermove', onPointerMove)
  window.addEventListener('pointerup', onPointerUp)
  window.addEventListener('pointercancel', onPointerUp)
}

function unbindDragListeners() {
  window.removeEventListener('pointermove', onPointerMove)
  window.removeEventListener('pointerup', onPointerUp)
  window.removeEventListener('pointercancel', onPointerUp)
}

function onPointerDown(e) {
  if (verified.value || loading.value || verifying.value) return
  dragging.value = true
  dragStartX.value = pointerX(e)
  dragStartOffset.value = sliderX.value
  dragStartedAt.value = Date.now()
  bindDragListeners()
  e.preventDefault()
}

function onPointerMove(e) {
  if (!dragging.value) return
  const delta = pointerX(e) - dragStartX.value
  sliderX.value = Math.max(0, Math.min(maxSlide.value, dragStartOffset.value + delta))
}

async function onPointerUp() {
  if (!dragging.value) return
  dragging.value = false
  unbindDragListeners()
  dragMs.value = Date.now() - dragStartedAt.value
  await submitSlide()
}

async function submitSlide() {
  if (!challenge.value || verified.value) return
  verifying.value = true
  error.value = ''
  try {
    const result = await api.verifyCaptcha({
      captcha_id: captchaId.value,
      captcha_x: Math.round(sliderX.value),
      drag_ms: dragMs.value,
    })
    verified.value = true
    passToken.value = result.captcha_pass_token
    emit('verified', {
      captcha_id: captchaId.value,
      captcha_pass_token: result.captcha_pass_token,
    })
  } catch (e) {
    error.value = e.message || 'Verification failed'
    sliderX.value = 0
    dragMs.value = 0
    await loadChallenge()
  } finally {
    verifying.value = false
  }
}

onMounted(loadChallenge)

defineExpose({ reload: loadChallenge })
</script>

<template>
  <div class="fx-captcha">
    <div class="fx-captcha-head">
      <span class="fw-semibold" style="font-size:13px">Human verification</span>
      <button type="button" class="fx-captcha-refresh" :disabled="loading || verifying" @click="loadChallenge">
        <span aria-hidden="true">↻</span>
        <span>Refresh</span>
      </button>
    </div>

    <p style="font-size:12px;color:var(--fx-muted);margin:0 0 10px">
      Slide the puzzle piece to fit the gap — helps us block automated sign-ups.
    </p>

    <div v-if="loading" class="fx-captcha-stage fx-card" style="min-height:200px;display:flex;align-items:center;justify-content:center">
      <span style="font-size:13px;color:var(--fx-muted)">Loading puzzle…</span>
    </div>

    <div v-else-if="challenge" class="fx-captcha-stage">
      <img :src="challenge.background" alt="" class="fx-captcha-bg" draggable="false" />
      <img
        :src="challenge.piece"
        alt=""
        class="fx-captcha-piece"
        :style="{ transform: `translateX(${sliderX}px)` }"
        draggable="false"
      />
      <div v-if="verified" class="fx-captcha-ok">
        <AppIcon name="check" :size="18" />
        <span>Verified</span>
      </div>
    </div>

    <div v-if="challenge && !loading" class="fx-captcha-track">
      <div class="fx-captcha-track-fill" :style="{ width: `${sliderX + challenge.piece_size / 2}px` }"></div>
      <button
        type="button"
        class="fx-captcha-knob"
        :class="{ verified, dragging }"
        :style="{ transform: `translateX(${sliderX}px)` }"
        :disabled="verified || verifying"
        @pointerdown="onPointerDown"
        @touchstart.prevent="onPointerDown"
      >
        <span v-if="verified" class="fx-captcha-knob-icon"><AppIcon name="check" :size="16" /></span>
        <span v-else class="fx-captcha-knob-icon fx-captcha-knob-arrow">››</span>
      </button>
      <span v-if="!verified && !verifying" class="fx-captcha-hint">Slide to fit the puzzle</span>
      <span v-else-if="verifying" class="fx-captcha-hint">Checking…</span>
    </div>

    <div v-if="error" class="alert alert-danger py-2 mt-2" style="font-size:12px">{{ error }}</div>
  </div>
</template>

<style scoped>
.fx-captcha {
  width: 100%;
}
.fx-captcha-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 6px;
}
.fx-captcha-refresh {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: none;
  background: none;
  color: var(--fx-muted);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 4px 0;
}
.fx-captcha-refresh:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.fx-captcha-stage {
  position: relative;
  width: 100%;
  max-width: 300px;
  margin: 0 auto 12px;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid var(--fx-border);
  background: #0f172a;
  user-select: none;
  touch-action: none;
}
.fx-captcha-bg {
  display: block;
  width: 100%;
  height: auto;
}
.fx-captcha-piece {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  will-change: transform;
}
.fx-captcha-ok {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: rgba(34, 197, 94, 0.82);
  color: #fff;
  font-weight: 700;
  font-size: 14px;
}
.fx-captcha-track {
  position: relative;
  height: 44px;
  border-radius: 22px;
  background: var(--fx-border-soft);
  border: 1px solid var(--fx-border);
  overflow: hidden;
  touch-action: none;
}
.fx-captcha-track-fill {
  position: absolute;
  top: 0;
  left: 0;
  bottom: 0;
  background: var(--fx-accent-soft);
  pointer-events: none;
}
.fx-captcha-knob {
  position: absolute;
  top: 3px;
  left: 3px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  border: none;
  background: var(--fx-accent);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: grab;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
  z-index: 2;
}
.fx-captcha-knob.dragging {
  cursor: grabbing;
}
.fx-captcha-knob.verified {
  background: var(--fx-success);
}
.fx-captcha-knob:disabled {
  cursor: default;
}
.fx-captcha-knob-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  line-height: 1;
}
.fx-captcha-knob-arrow {
  font-size: 14px;
  font-weight: 800;
  letter-spacing: -2px;
}
.fx-captcha-hint {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: var(--fx-muted);
  pointer-events: none;
  padding-left: 36px;
}
</style>