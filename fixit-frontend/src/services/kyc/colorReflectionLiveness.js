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
    await sleep(FLASH_MS)
    const frame = captureFrame(video)
    const sample = frame
      ? sampleFaceRegion(frame.ctx, frame.width, frame.height)
      : baseline

    const expected = expectedDominant(color)
    const delta = channelDelta(baseline, sample, expected)
    const otherDeltas = ['r', 'g', 'b']
      .filter((c) => c !== expected)
      .map((c) => channelDelta(baseline, sample, c))
    const maxOther = Math.max(...otherDeltas, 0)

    const reflected = delta > 8 && delta > maxOther + 3
    flashResults.push({
      color: color.name,
      expected_channel: expected,
      channel_delta: Math.round(delta * 10) / 10,
      reflected,
    })
    onFlash(null)
    await sleep(SETTLE_MS)
  }

  const matchCount = flashResults.filter((f) => f.reflected).length
  const score = Math.round((matchCount / sequence.length) * 100)
  const passed = matchCount >= 6

  return {
    passed,
    score,
    color_sequence_hash: hash,
    checks: {
      method: '8_color_random_reflection',
      colors_tested: sequence.length,
      matches: matchCount,
      threshold: 6,
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