/**
 * Government ID validation — structural checks only (no OCR dep).
 * ponytail: tesseract dropped; edge/aspect/contrast heuristics suffice for demo KYC.
 */

import { drawToCanvas, fileToImage, imageStats, sha256Hex } from './imageUtils.js'

const ID_ASPECT_RANGES = [
  { type: 'national_id', min: 1.45, max: 1.75, label: 'National ID card' },
  { type: 'passport', min: 1.25, max: 1.50, label: 'Passport data page' },
  { type: 'drivers_license', min: 1.50, max: 1.90, label: 'Driving licence' },
]

function detectAspectType(width, height) {
  const ratio = width >= height ? width / height : height / width
  for (const range of ID_ASPECT_RANGES) {
    if (ratio >= range.min && ratio <= range.max) {
      return { type: range.type, label: range.label, ratio }
    }
  }
  return { type: 'unknown', label: 'Unknown document', ratio }
}

/** Bottom-band edge density proxy for MRZ / machine-readable text zones. */
function detectTextBand(ctx, w, h) {
  const bandTop = Math.floor(h * 0.72)
  const data = ctx.getImageData(0, bandTop, w, h - bandTop).data
  let edges = 0
  let n = 0
  const bw = w
  const bh = h - bandTop
  for (let y = 1; y < bh - 1; y += 3) {
    for (let x = 1; x < bw - 1; x += 3) {
      const i = (y * bw + x) * 4
      const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]
      const r = (y * bw + x + 1) * 4
      if (Math.abs(lum - (0.299 * data[r] + 0.587 * data[r + 1] + 0.114 * data[r + 2])) > 35) edges++
      n++
    }
  }
  return { density: n ? edges / n : 0, pass: n > 0 && edges / n > 0.1 }
}

export async function analyzeGovernmentId(file, onProgress = () => {}) {
  onProgress('Loading image…')
  const img = await fileToImage(file)
  const { ctx, width, height } = drawToCanvas(img)

  onProgress('Checking document structure…')
  const stats = imageStats(ctx, width, height)
  const aspect = detectAspectType(width, height)
  const textBand = detectTextBand(ctx, width, height)

  const checks = {
    resolution: { width, height, pass: width >= 640 && height >= 400 },
    aspect_ratio: { ...aspect, pass: aspect.type !== 'unknown' },
    contrast: { variance: Math.round(stats.variance), pass: stats.variance > 800 },
    edge_density: { value: stats.edgeDensity.toFixed(3), pass: stats.edgeDensity > 0.08 },
    brightness: { mean: Math.round(stats.mean), pass: stats.mean > 40 && stats.mean < 220 },
    text_band: { density: textBand.density.toFixed(3), pass: textBand.pass },
  }

  const passed = Object.values(checks).filter((c) => c.pass).length
  const confidence = Math.min(100, passed * 14 + (aspect.type !== 'unknown' ? 10 : 0))
  const valid = passed >= 5 && confidence >= 55

  return {
    valid,
    document_type: aspect.type,
    document_label: aspect.label,
    confidence,
    checks,
    extracted_preview: valid ? `Structural ID check passed (${aspect.label})` : '',
    image_hash: await sha256Hex(file),
  }
}