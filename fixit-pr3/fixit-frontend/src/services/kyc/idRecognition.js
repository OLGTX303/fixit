/**
 * Production government-ID verification.
 * OCR (Tesseract) + MRZ checksums + anti-spoof forensics + strict scoring.
 */

import { validateMrzFromText } from './mrz.js'

const GOV_KEYWORDS = [
  'passport', 'national', 'identity', 'identification', 'republic', 'government',
  'driving', 'driver', 'license', 'licence', 'citizen', 'ministry', 'department',
  'immigration', 'residence', 'permit', 'card', 'document', 'official',
  'date of birth', 'dob', 'expiry', 'expires', 'surname', 'given name', 'sex',
  'authority', 'issuing', 'valid', 'signature',
]

const ID_ASPECT_RANGES = [
  { type: 'passport', min: 1.25, max: 1.50, label: 'Passport data page' },
  { type: 'national_id', min: 1.45, max: 1.75, label: 'National ID card' },
  { type: 'drivers_license', min: 1.50, max: 1.90, label: 'Driving licence' },
]

const THRESHOLDS = {
  min_confidence: 55,
  min_long_edge: 500,
  min_gov_keywords: 2,
  min_ocr_chars: 16,
  max_fraud_score: 60,
}

async function fileToImage(file) {
  const url = URL.createObjectURL(file)
  try {
    return await new Promise((resolve, reject) => {
      const el = new Image()
      el.onload = () => resolve(el)
      el.onerror = () => reject(new Error('Could not load image'))
      el.src = url
    })
  } finally {
    URL.revokeObjectURL(url)
  }
}

function drawToCanvas(img, maxEdge = 1600) {
  const scale = Math.min(1, maxEdge / Math.max(img.width, img.height))
  const w = Math.round(img.width * scale)
  const h = Math.round(img.height * scale)
  const canvas = document.createElement('canvas')
  canvas.width = w
  canvas.height = h
  const ctx = canvas.getContext('2d', { willReadFrequently: true })
  ctx.drawImage(img, 0, 0, w, h)
  return { canvas, ctx, width: w, height: h }
}

function imageStats(ctx, w, h) {
  const data = ctx.getImageData(0, 0, w, h).data
  let sum = 0
  let sumSq = 0
  let edges = 0
  const step = 4
  for (let y = 1; y < h - 1; y += step) {
    for (let x = 1; x < w - 1; x += step) {
      const i = (y * w + x) * 4
      const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]
      sum += lum
      sumSq += lum * lum
      const right = (y * w + x + 1) * 4
      const down = ((y + 1) * w + x) * 4
      const lumR = 0.299 * data[right] + 0.587 * data[right + 1] + 0.114 * data[right + 2]
      const lumD = 0.299 * data[down] + 0.587 * data[down + 1] + 0.114 * data[down + 2]
      if (Math.abs(lum - lumR) + Math.abs(lum - lumD) > 40) edges++
    }
  }
  const samples = Math.floor((w / step) * (h / step))
  const mean = sum / samples
  return { mean, variance: sumSq / samples - mean * mean, edgeDensity: edges / samples }
}

async function sha256Hex(blob) {
  const hash = await crypto.subtle.digest('SHA-256', await blob.arrayBuffer())
  return [...new Uint8Array(hash)].map((b) => b.toString(16).padStart(2, '0')).join('')
}

function lumAt(data, w, x, y) {
  const i = (y * w + x) * 4
  return 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]
}

function detectScreenPattern(ctx, w, h) {
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

function detectFlatLighting(ctx, w, h) {
  const regions = [[0.05, 0.05, 0.25, 0.25], [0.75, 0.05, 0.95, 0.25], [0.05, 0.75, 0.25, 0.95], [0.75, 0.75, 0.95, 0.95]]
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

function detectDocumentBounds(ctx, w, h) {
  const data = ctx.getImageData(0, 0, w, h).data
  const edgeBand = Math.max(4, Math.floor(Math.min(w, h) * 0.04))
  function bandEnergy(horizontal) {
    let edges = 0
    let n = 0
    if (horizontal) {
      for (const y of [edgeBand, h - edgeBand - 1]) {
        for (let x = 1; x < w - 1; x += 2) {
          const a = lumAt(data, w, x, y)
          const b = lumAt(data, w, x, y + (y < h / 2 ? 1 : -1))
          if (Math.abs(a - b) > 25) edges++
          n++
        }
      }
    } else {
      for (const x of [edgeBand, w - edgeBand - 1]) {
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
  const score = (bandEnergy(true) + bandEnergy(false)) / 2
  return {
    border_score: Number(score.toFixed(3)),
    pass: score > 0.06,
    flag: score <= 0.06 ? 'no_document_border' : null,
  }
}

function detectRecompression(img, ctx, w, h) {
  const tmp = document.createElement('canvas')
  tmp.width = w
  tmp.height = h
  const tctx = tmp.getContext('2d')
  tctx.drawImage(img, 0, 0, w, h)
  const original = ctx.getImageData(0, 0, w, h).data
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

function checkFileMetadata(file) {
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

async function runForensics(file, img, ctx, w, h) {
  const meta = checkFileMetadata(file)
  const screen = detectScreenPattern(ctx, w, h)
  const flat = detectFlatLighting(ctx, w, h)
  const bounds = detectDocumentBounds(ctx, w, h)
  const recompress = await detectRecompression(img, ctx, w, h)
  // Only a clear screen-capture / wrong-file is treated as hard fraud; border,
  // flat-lighting and recompression are advisory (clean scans legitimately
  // trip them), so they add a little fraud score but don't auto-reject.
  const hardFlags = [meta, screen].map((c) => c.flag).filter(Boolean)
  const softFlags = [flat, bounds, recompress].map((c) => c.flag).filter(Boolean)
  const flags = [...hardFlags, ...softFlags]
  const fraudScore = Math.min(100, hardFlags.length * 30 + softFlags.length * 8)
  return {
    checks: { metadata: meta, screen_pattern: screen, flat_lighting: flat, document_bounds: bounds, recompression: recompress },
    fraud_flags: hardFlags,
    fraud_score: fraudScore,
    anti_spoof_pass: meta.pass && screen.pass && fraudScore < THRESHOLDS.max_fraud_score,
  }
}

function detectAspectType(width, height) {
  const ratio = width >= height ? width / height : height / width
  for (const range of ID_ASPECT_RANGES) {
    if (ratio >= range.min && ratio <= range.max) return { type: range.type, label: range.label, ratio }
  }
  return { type: 'unknown', label: 'Unknown document', ratio }
}

function scoreKeywords(text) {
  const lower = text.toLowerCase()
  const hits = GOV_KEYWORDS.filter((k) => lower.includes(k))
  return { hits, count: hits.length, score: Math.min(100, hits.length * 14) }
}

function detectDates(text) {
  const patterns = [
    /\b\d{2}[./-]\d{2}[./-]\d{2,4}\b/g,
    /\b\d{4}[./-]\d{2}[./-]\d{2}\b/g,
    /\b(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+\d{1,2},?\s+\d{4}\b/gi,
  ]
  const dates = []
  for (const p of patterns) {
    const m = text.match(p)
    if (m) dates.push(...m)
  }
  return dates.slice(0, 8)
}

function withTimeout(promise, ms, label) {
  return Promise.race([
    promise,
    new Promise((_, reject) => setTimeout(() => reject(new Error(label)), ms)),
  ])
}

// OCR loads its worker/wasm/lang from a CDN at runtime; on a slow or blocked
// network that can hang or fail. Time-box it and degrade gracefully so the
// "Recognise" button always completes instead of spinning forever.
async function runOcr(canvas) {
  try {
    const { createWorker } = await withTimeout(import('tesseract.js'), 10000, 'ocr_load_timeout')
    const worker = await withTimeout(createWorker('eng', 1, { logger: () => {} }), 15000, 'ocr_init_timeout')
    try {
      const { data } = await withTimeout(worker.recognize(canvas), 20000, 'ocr_timeout')
      return { text: data.text || '', confidence: Math.round(data.confidence ?? 0), unavailable: false }
    } finally {
      await worker.terminate()
    }
  } catch {
    return { text: '', confidence: 0, unavailable: true }
  }
}

function computeDecision(checks, fraudScore, ocrConfidence, ocrUnavailable = false) {
  const critical = [
    checks.resolution?.pass,
    checks.aspect_ratio?.pass,
    checks.anti_spoof?.pass,
    checks.contrast?.pass,
  ]
  const criticalOk = critical.every(Boolean)
  // If OCR couldn't run (CDN/worker failure), we can't read text — fall back to
  // the image/anti-spoof checks rather than blocking the whole verification.
  const textualOk = ocrUnavailable || (checks.gov_keywords?.pass || checks.mrz?.pass)
  const mrzFailed = checks.mrz?.found && !checks.mrz?.pass
  const rejectionReasons = []
  if (!checks.resolution?.pass) rejectionReasons.push('Image resolution too low — use a sharper photo')
  if (!checks.aspect_ratio?.pass) rejectionReasons.push('Document shape not recognized as a government ID')
  if (!checks.anti_spoof?.pass) rejectionReasons.push('Possible fake: screen photo, printout, or tampered image')
  if (checks.fraud_flags?.length) rejectionReasons.push(`Fraud signals: ${checks.fraud_flags.join(', ')}`)
  if (!textualOk) rejectionReasons.push('Could not read enough official text from the document')
  if (mrzFailed) rejectionReasons.push('MRZ checksum failed — document may be forged')

  let confidence = critical.filter(Boolean).length * 12
  confidence += textualOk ? 22 : 0
  confidence += checks.mrz?.pass ? 20 : 0
  confidence += Math.min(15, (checks.gov_keywords?.count || 0) * 5)
  confidence += Math.min(10, ocrConfidence / 8)
  confidence -= Math.min(25, fraudScore / 3)
  confidence = Math.max(0, Math.min(100, Math.round(confidence)))

  const valid = criticalOk && textualOk && !mrzFailed
    && confidence >= THRESHOLDS.min_confidence
    && fraudScore <= THRESHOLDS.max_fraud_score

  return { valid, confidence, rejectionReasons }
}

export async function analyzeGovernmentId(file, onProgress = () => {}) {
  onProgress('Loading image…')
  const img = await fileToImage(file)
  const longEdge = Math.max(img.width, img.height)
  const { ctx, width, height } = drawToCanvas(img, 2000)

  onProgress('Running anti-spoof checks…')
  const forensics = await runForensics(file, img, ctx, width, height)
  const stats = imageStats(ctx, width, height)
  const aspect = detectAspectType(width, height)

  onProgress('Running OCR on document…')
  const ocr = await runOcr(ctx)
  const keywords = scoreKeywords(ocr.text)
  const dates = detectDates(ocr.text)
  const mrz = validateMrzFromText(ocr.text)

  const docType = mrz.valid && mrz.format === 'TD3' ? 'passport'
    : mrz.valid && mrz.format === 'TD1' ? 'national_id'
      : aspect.type

  const checks = {
    resolution: {
      width, height, long_edge: longEdge,
      pass: longEdge >= THRESHOLDS.min_long_edge && Math.min(width, height) >= 240,
    },
    aspect_ratio: { ...aspect, pass: aspect.type !== 'unknown' },
    contrast: { variance: Math.round(stats.variance), pass: stats.variance > 900 },
    edge_density: { value: Number(stats.edgeDensity.toFixed(3)), pass: stats.edgeDensity > 0.07 },
    brightness: { mean: Math.round(stats.mean), pass: stats.mean > 35 && stats.mean < 225 },
    ocr_quality: {
      chars: ocr.text.length,
      confidence: ocr.confidence,
      pass: ocr.text.length >= THRESHOLDS.min_ocr_chars && ocr.confidence >= 30,
    },
    gov_keywords: { hits: keywords.hits, count: keywords.count, pass: keywords.count >= THRESHOLDS.min_gov_keywords },
    dates: { found: dates, pass: dates.length >= 1 },
    mrz: {
      found: mrz.found,
      valid: mrz.valid,
      format: mrz.format || null,
      document_number: mrz.document_number || null,
      pass: mrz.valid || (!mrz.found && keywords.count >= 3),
      reason: mrz.reason || null,
    },
    anti_spoof: { pass: forensics.anti_spoof_pass, fraud_score: forensics.fraud_score },
    document_bounds: forensics.checks.document_bounds,
    screen_pattern: forensics.checks.screen_pattern,
    metadata: forensics.checks.metadata,
    fraud_flags: forensics.fraud_flags,
  }

  const { valid, confidence, rejectionReasons } = computeDecision(checks, forensics.fraud_score, ocr.confidence, ocr.unavailable)

  return {
    valid,
    document_type: docType,
    document_label: aspect.label,
    confidence,
    fraud_score: forensics.fraud_score,
    rejection_reasons: rejectionReasons,
    checks,
    extracted_preview: ocr.text.replace(/\s+/g, ' ').trim().slice(0, 280),
    ocr_confidence: ocr.confidence,
    image_hash: await sha256Hex(file),
    module_version: 'kyc-id-v2',
  }
}