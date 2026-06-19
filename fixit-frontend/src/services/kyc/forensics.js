/**
 * Anti-spoof / fake-ID forensics (client-side pre-screen).
 * Flags photos of screens, flat prints, tampered regions, and low-quality captures.
 */

import { drawToCanvas } from './imageUtils.js'

function lumAt(data, w, x, y) {
  const i = (y * w + x) * 4
  return 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]
}

/** Detect moiré / pixel-grid patterns typical of screen photos. */
export function detectScreenPattern(ctx, w, h) {
  const data = ctx.getImageData(0, 0, w, h).data
  let periodicHits = 0
  let samples = 0
  const step = 6
  for (let y = step * 2; y < h - step * 2; y += step) {
    for (let x = step * 2; x < w - step * 2; x += step) {
      const c = lumAt(data, w, x, y)
      const left = lumAt(data, w, x - step, y)
      const right = lumAt(data, w, x + step, y)
      const up = lumAt(data, w, x, y - step)
      const down = lumAt(data, w, x, y + step)
      const lap = Math.abs(4 * c - left - right - up - down)
      if (lap > 18 && Math.abs(left - right) < 4 && Math.abs(up - down) < 4) periodicHits++
      samples++
    }
  }
  const ratio = samples ? periodicHits / samples : 0
  return {
    ratio: Number(ratio.toFixed(4)),
    pass: ratio < 0.12,
    flag: ratio >= 0.12 ? 'possible_screen_capture' : null,
  }
}

/** Flat/uniform lighting suggests a digital copy not a physical card. */
export function detectFlatLighting(ctx, w, h) {
  const regions = [
    [0.05, 0.05, 0.25, 0.25],
    [0.75, 0.05, 0.95, 0.25],
    [0.05, 0.75, 0.25, 0.95],
    [0.75, 0.75, 0.95, 0.95],
  ]
  const data = ctx.getImageData(0, 0, w, h).data
  const variances = []

  for (const [x0, y0, x1, y1] of regions) {
    const sx = Math.floor(w * x0)
    const ex = Math.floor(w * x1)
    const sy = Math.floor(h * y0)
    const ey = Math.floor(h * y1)
    let sum = 0
    let sumSq = 0
    let n = 0
    for (let y = sy; y < ey; y += 3) {
      for (let x = sx; x < ex; x += 3) {
        const lum = lumAt(data, w, x, y)
        sum += lum
        sumSq += lum * lum
        n++
      }
    }
    if (n) {
      const mean = sum / n
      variances.push(sumSq / n - mean * mean)
    }
  }

  const avgVar = variances.length ? variances.reduce((a, b) => a + b, 0) / variances.length : 0
  return {
    corner_variance: Math.round(avgVar),
    pass: avgVar > 120,
    flag: avgVar <= 120 ? 'flat_lighting_suspect' : null,
  }
}

/** Document should have a strong rectangular border (4-edge energy). */
export function detectDocumentBounds(ctx, w, h) {
  const data = ctx.getImageData(0, 0, w, h).data
  const edgeBand = Math.max(4, Math.floor(Math.min(w, h) * 0.04))

  function bandEnergy(horizontal) {
    let edges = 0
    let n = 0
    if (horizontal) {
      for (let y of [edgeBand, h - edgeBand - 1]) {
        for (let x = 1; x < w - 1; x += 2) {
          const a = lumAt(data, w, x, y)
          const b = lumAt(data, w, x, y + (y < h / 2 ? 1 : -1))
          if (Math.abs(a - b) > 25) edges++
          n++
        }
      }
    } else {
      for (let x of [edgeBand, w - edgeBand - 1]) {
        for (let y = 1; y < h - 1; y += 2) {
          const a = lumAt(data, w, x, y)
          const b = lumAt(data, w, x + (x < w / 2 ? 1 : -1), y)
          if (Math.abs(a - b) > 25) edges++
          n++
        }
      }
    }
    return n ? edges / n : 0
  }

  const hEnergy = bandEnergy(true)
  const vEnergy = bandEnergy(false)
  const score = (hEnergy + vEnergy) / 2
  return {
    border_score: Number(score.toFixed(3)),
    pass: score > 0.06,
    flag: score <= 0.06 ? 'no_document_border' : null,
  }
}

/** Recompression delta — screenshots re-JPEG differently from camera originals. */
export function detectRecompression(img, ctx, w, h) {
  const tmp = document.createElement('canvas')
  tmp.width = w
  tmp.height = h
  const tctx = tmp.getContext('2d')
  tctx.drawImage(img, 0, 0, w, h)
  const original = ctx.getImageData(0, 0, w, h).data
  const resaved = tctx.getImageData(0, 0, w, h).data
  // Draw via JPEG round-trip
  const jpegUrl = tmp.toDataURL('image/jpeg', 0.82)
  return new Promise((resolve) => {
    const j = new Image()
    j.onload = () => {
      tctx.drawImage(j, 0, 0, w, h)
      const roundTrip = tctx.getImageData(0, 0, w, h).data
      let diff = 0
      let n = 0
      for (let i = 0; i < original.length; i += 16) {
        diff += Math.abs(original[i] - roundTrip[i])
          + Math.abs(original[i + 1] - roundTrip[i + 1])
          + Math.abs(original[i + 2] - roundTrip[i + 2])
        n++
      }
      const avgDiff = n ? diff / (n * 3) : 0
      resolve({
        recompress_delta: Math.round(avgDiff),
        pass: avgDiff > 2 && avgDiff < 45,
        flag: avgDiff <= 2 ? 'synthetic_flat_image' : avgDiff >= 45 ? 'heavy_manipulation' : null,
      })
    }
    j.onerror = () => resolve({ recompress_delta: 0, pass: true, flag: null })
    j.src = jpegUrl
  })
}

export function checkFileMetadata(file) {
  const ext = (file.name || '').split('.').pop()?.toLowerCase()
  const allowed = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif']
  const sizeOk = file.size >= 50_000 && file.size <= 15_000_000
  return {
    extension: ext,
    size_bytes: file.size,
    pass: allowed.includes(ext || '') && sizeOk,
    flag: !allowed.includes(ext || '') ? 'invalid_file_type'
      : !sizeOk ? (file.size < 50_000 ? 'file_too_small' : 'file_too_large') : null,
  }
}

export async function runForensics(file, img, ctx, w, h) {
  const meta = checkFileMetadata(file)
  const screen = detectScreenPattern(ctx, w, h)
  const flat = detectFlatLighting(ctx, w, h)
  const bounds = detectDocumentBounds(ctx, w, h)
  const recompress = await detectRecompression(img, ctx, w, h)

  const flags = [meta, screen, flat, bounds, recompress]
    .map((c) => c.flag)
    .filter(Boolean)

  const criticalPass = meta.pass && screen.pass && bounds.pass
  const fraudScore = Math.min(100, flags.length * 18 + (flat.pass ? 0 : 15))

  return {
    checks: { metadata: meta, screen_pattern: screen, flat_lighting: flat, document_bounds: bounds, recompression: recompress },
    fraud_flags: flags,
    fraud_score: fraudScore,
    anti_spoof_pass: criticalPass && fraudScore < 40,
  }
}