/**
 * Government ID photo recognition — client-side validation.
 * Uses image structure checks + OCR keyword/MRZ detection.
 */

const GOV_KEYWORDS = [
  'passport', 'national', 'identity', 'identification', 'republic', 'government',
  'driving', 'driver', 'license', 'licence', 'citizen', 'ministry', 'department',
  'immigration', 'residence', 'permit', 'card', 'document', 'official',
  'date of birth', 'dob', 'expiry', 'expires', 'surname', 'given name',
]

const ID_ASPECT_RANGES = [
  { type: 'national_id', min: 1.45, max: 1.75, label: 'National ID card' },
  { type: 'passport', min: 1.25, max: 1.50, label: 'Passport data page' },
  { type: 'drivers_license', min: 1.50, max: 1.90, label: 'Driving licence' },
]

export async function fileToImage(file) {
  const url = URL.createObjectURL(file)
  try {
    const img = await new Promise((resolve, reject) => {
      const el = new Image()
      el.onload = () => resolve(el)
      el.onerror = () => reject(new Error('Could not load image'))
      el.src = url
    })
    return img
  } finally {
    URL.revokeObjectURL(url)
  }
}

export function drawToCanvas(img, maxEdge = 1600) {
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
  const variance = sumSq / samples - mean * mean
  const edgeDensity = edges / samples
  return { mean, variance, edgeDensity }
}

function detectAspectType(width, height) {
  const ratio = width >= height ? width / height : height / width
  for (const range of ID_ASPECT_RANGES) {
    if (ratio >= range.min && ratio <= range.max) {
      return { type: range.type, label: range.label, ratio }
    }
  }
  return { type: 'unknown', label: 'Unknown document', ratio }
}

function scoreKeywords(text) {
  const lower = text.toLowerCase()
  const hits = GOV_KEYWORDS.filter((k) => lower.includes(k))
  return { hits, score: Math.min(100, hits.length * 12) }
}

function detectMrz(text) {
  const lines = text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean)
  const mrzLines = lines.filter((l) => l.length >= 28 && (l.match(/</g) || []).length >= 2)
  const hasMrzPattern = mrzLines.some((l) => /^[A-Z0-9<]{28,}$/.test(l.replace(/\s/g, '')))
  return { mrzLines: mrzLines.length, hasMrz: hasMrzPattern || mrzLines.length >= 2 }
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
  return dates.slice(0, 6)
}

async function runOcr(canvas) {
  try {
    const { createWorker } = await import('tesseract.js')
    const worker = await createWorker('eng', 1, { logger: () => {} })
    try {
      const { data } = await worker.recognize(canvas)
      return data.text || ''
    } finally {
      await worker.terminate()
    }
  } catch {
    return ''
  }
}

export async function sha256Hex(blob) {
  const buf = await blob.arrayBuffer()
  const hash = await crypto.subtle.digest('SHA-256', buf)
  return [...new Uint8Array(hash)].map((b) => b.toString(16).padStart(2, '0')).join('')
}

/**
 * @param {File|Blob} file — government ID photo
 * @param {(msg: string) => void} [onProgress]
 */
export async function analyzeGovernmentId(file, onProgress = () => {}) {
  onProgress('Loading image…')
  const img = await fileToImage(file)
  const { canvas, ctx, width, height } = drawToCanvas(img)

  onProgress('Checking document structure…')
  const stats = imageStats(ctx, width, height)
  const aspect = detectAspectType(width, height)

  const checks = {
    resolution: { width, height, pass: width >= 640 && height >= 400 },
    aspect_ratio: { ...aspect, pass: aspect.type !== 'unknown' },
    contrast: { variance: Math.round(stats.variance), pass: stats.variance > 800 },
    edge_density: { value: stats.edgeDensity.toFixed(3), pass: stats.edgeDensity > 0.08 },
    brightness: { mean: Math.round(stats.mean), pass: stats.mean > 40 && stats.mean < 220 },
  }

  onProgress('Running OCR on ID text…')
  const ocrText = await runOcr(canvas)
  const keywords = scoreKeywords(ocrText)
  const mrz = detectMrz(ocrText)
  const dates = detectDates(ocrText)

  checks.ocr_text_length = { chars: ocrText.length, pass: ocrText.length >= 20 }
  checks.gov_keywords = { hits: keywords.hits, pass: keywords.hits.length >= 2 }
  checks.mrz = { lines: mrz.mrzLines, pass: mrz.hasMrz }
  checks.dates = { found: dates, pass: dates.length >= 1 }

  const structural = ['resolution', 'aspect_ratio', 'contrast', 'edge_density', 'brightness']
    .filter((k) => checks[k].pass).length
  const textual = ['ocr_text_length', 'gov_keywords', 'mrz', 'dates']
    .filter((k) => checks[k].pass).length

  let confidence = structural * 10 + textual * 12 + keywords.score * 0.3
  if (mrz.hasMrz) confidence += 15
  confidence = Math.min(100, Math.round(confidence))

  const valid = structural >= 4 && textual >= 2 && confidence >= 55

  return {
    valid,
    document_type: aspect.type,
    document_label: aspect.label,
    confidence,
    checks,
    extracted_preview: ocrText.replace(/\s+/g, ' ').trim().slice(0, 200),
    image_hash: await sha256Hex(file),
  }
}