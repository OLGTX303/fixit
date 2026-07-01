/**
 * 8-color random reflection liveness check.
 * Flashes random colours on screen; verifies the face region reflects them
 * (anti-spoof: printed photos / screens fail to match subsurface scattering).
 */

export const REFLECTION_COLORS = [
  { name: 'red', hex: '#FF0000', rgb: [255, 0, 0] },
  { name: 'green', hex: '#00FF00', rgb: [0, 255, 0] },
  { name: 'blue', hex: '#0000FF', rgb: [0, 0, 255] },
  { name: 'cyan', hex: '#00FFFF', rgb: [0, 255, 255] },
  { name: 'magenta', hex: '#FF00FF', rgb: [255, 0, 255] },
  { name: 'yellow', hex: '#FFFF00', rgb: [255, 255, 0] },
  { name: 'orange', hex: '#FF8800', rgb: [255, 136, 0] },
  { name: 'purple', hex: '#8800FF', rgb: [136, 0, 255] },
]

const FLASH_MS = 450
const SETTLE_MS = 120

export function generateColorSequence() {
  const pool = [...REFLECTION_COLORS]
  for (let i = pool.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [pool[i], pool[j]] = [pool[j], pool[i]]
  }
  return pool
}

export async function hashColorSequence(sequence) {
  const payload = sequence.map((c) => c.name).join(',')
  const buf = new TextEncoder().encode(payload)
  const hash = await crypto.subtle.digest('SHA-256', buf)
  return [...new Uint8Array(hash)].map((b) => b.toString(16).padStart(2, '0')).join('')
}

function captureFrame(video) {
  const w = video.videoWidth
  const h = video.videoHeight
  if (!w || !h) return null
  const canvas = document.createElement('canvas')
  canvas.width = w
  canvas.height = h
  const ctx = canvas.getContext('2d', { willReadFrequently: true })
  ctx.drawImage(video, 0, 0, w, h)
  return { canvas, ctx, width: w, height: h }
}

/** Sample pixels inside central face oval (user aligns face to guide). */
function sampleFaceRegion(ctx, w, h) {
  const cx = w / 2
  const cy = h * 0.42
  const rx = w * 0.22
  const ry = h * 0.28
  const data = ctx.getImageData(0, 0, w, h).data
  let r = 0
  let g = 0
  let b = 0
  let n = 0
  const step = 3
  for (let y = 0; y < h; y += step) {
    for (let x = 0; x < w; x += step) {
      const dx = (x - cx) / rx
      const dy = (y - cy) / ry
      if (dx * dx + dy * dy <= 1) {
        const i = (y * w + x) * 4
        r += data[i]
        g += data[i + 1]
        b += data[i + 2]
        n++
      }
    }
  }
  if (!n) return { r: 0, g: 0, b: 0 }
  return { r: r / n, g: g / n, b: b / n }
}

function dominantChannel(rgb) {
  const [r, g, b] = rgb
  if (r >= g && r >= b) return 'r'
  if (g >= r && g >= b) return 'g'
  return 'b'
}

function expectedDominant(color) {
  const [r, g, b] = color.rgb
  if (color.name === 'cyan') return 'g'
  if (color.name === 'magenta') return 'r'
  if (color.name === 'yellow') return 'r'
  if (color.name === 'orange') return 'r'
  if (color.name === 'purple') return 'b'
  return dominantChannel(color.rgb)
}

function channelDelta(baseline, sample, channel) {
  const idx = channel === 'r' ? 0 : channel === 'g' ? 1 : 2
  const base = [baseline.r, baseline.g, baseline.b][idx]
  const val = [sample.r, sample.g, sample.b][idx]
  return val - base
}

function channelValue(rgb, channel) {
  return channel === 'r' ? rgb.r : channel === 'g' ? rgb.g : rgb.b
}

/**
 * In bright ambient light (daytime), the camera's auto-exposure compresses the
 * sensor response and the baseline channel often already sits close to 255 —
 * so the same physical reflection produces a much smaller absolute delta than
 * it would indoors, even though the face is genuinely reflecting the flash. A
 * single fixed absolute threshold (e.g. "delta > 5") that works well in dim
 * light rejects real users in daylight. Scale the required delta by the
 * remaining headroom to 255, so a small delta still counts when the channel
 * was already near-saturated pre-flash.
 */
function isReflected(baseline, sample, expected) {
  const delta = channelDelta(baseline, sample, expected)
  const otherDeltas = ['r', 'g', 'b']
    .filter((c) => c !== expected)
    .map((c) => channelDelta(baseline, sample, c))
  const maxOther = Math.max(...otherDeltas, 0)

  const headroom = Math.max(20, 255 - channelValue(baseline, expected))
  const relativeDelta = delta / headroom

  // Two regimes, OR'd: the original absolute check (works in normal/dim
  // lighting) and a relative check scaled to available headroom (kicks in
  // when ambient light has already pushed the baseline near saturation).
  const passesAbsolute = delta > 5 && delta > maxOther + 1
  const passesRelative = delta > 1.5 && delta > maxOther && relativeDelta > 0.06
  return { reflected: passesAbsolute || passesRelative, delta, maxOther }
}

/** Best-effort: lock exposure/white-balance so auto-exposure can't fight the
 * flash signal mid-check. Unsupported on most browsers — silently no-ops. */
async function tryLockExposure(video) {
  try {
    const track = video.srcObject?.getVideoTracks?.()[0]
    if (!track) return
    const caps = track.getCapabilities?.()
    const constraints = {}
    if (caps?.exposureMode?.includes('manual')) constraints.exposureMode = 'manual'
    if (caps?.whiteBalanceMode?.includes('manual')) constraints.whiteBalanceMode = 'manual'
    if (Object.keys(constraints).length) await track.applyConstraints({ advanced: [constraints] })
  } catch {
    /* not supported — the relative threshold below covers this case anyway */
  }
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms))
}

/**
 * @param {HTMLVideoElement} video
 * @param {{ onFlash: (color) => void, onProgress: (i, total, color) => void }} callbacks
 */
export async function runColorReflectionCheck(video, { onFlash, onProgress }) {
  const sequence = generateColorSequence()
  const hash = await hashColorSequence(sequence)

  await tryLockExposure(video)
  await sleep(300)
  const baselineFrame = captureFrame(video)
  if (!baselineFrame) {
    return { passed: false, score: 0, color_sequence_hash: hash, checks: { error: 'no_video_frame' } }
  }
  const baseline = sampleFaceRegion(baselineFrame.ctx, baselineFrame.width, baselineFrame.height)

  const flashResults = []

  for (let i = 0; i < sequence.length; i++) {
    const color = sequence[i]
    onProgress(i + 1, sequence.length, color)
    onFlash(color)
    const expected = expectedDominant(color)

    // Sample twice: early (before auto-exposure has time to compensate the
    // extra screen light) and at the original timing. Auto-exposure reacts
    // faster in bright ambient conditions, so a late-only sample under-reads
    // the real signal in daylight — take whichever sample shows it best.
    await sleep(FLASH_MS * 0.35)
    const earlyFrame = captureFrame(video)
    const earlySample = earlyFrame ? sampleFaceRegion(earlyFrame.ctx, earlyFrame.width, earlyFrame.height) : baseline
    await sleep(FLASH_MS * 0.65)
    const lateFrame = captureFrame(video)
    const lateSample = lateFrame ? sampleFaceRegion(lateFrame.ctx, lateFrame.width, lateFrame.height) : baseline

    const early = isReflected(baseline, earlySample, expected)
    const late = isReflected(baseline, lateSample, expected)
    const best = Math.abs(early.delta) >= Math.abs(late.delta) ? early : late

    flashResults.push({
      color: color.name,
      expected_channel: expected,
      channel_delta: Math.round(best.delta * 10) / 10,
      reflected: early.reflected || late.reflected,
    })
    onFlash(null)
    await sleep(SETTLE_MS)
  }

  const matchCount = flashResults.filter((f) => f.reflected).length
  const score = Math.round((matchCount / sequence.length) * 100)
  const passed = matchCount >= 4

  return {
    passed,
    score,
    color_sequence_hash: hash,
    checks: {
      method: '8_color_random_reflection',
      colors_tested: sequence.length,
      matches: matchCount,
      threshold: 4,
      flash_results: flashResults,
      baseline_rgb: {
        r: Math.round(baseline.r),
        g: Math.round(baseline.g),
        b: Math.round(baseline.b),
      },
    },
  }
}

export async function openFrontCamera() {
  const stream = await navigator.mediaDevices.getUserMedia({
    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
    audio: false,
  })
  return stream
}