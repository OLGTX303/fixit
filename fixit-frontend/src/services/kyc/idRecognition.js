/**
 * Production government-ID verification.
 * OCR (Tesseract) + MRZ checksums + anti-spoof forensics + strict scoring.
 */

import { drawToCanvas, fileToImage, imageStats, sha256Hex } from './imageUtils.js'
import { runForensics } from './forensics.js'
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

// Production thresholds — tuned to reject fakes while allowing real phone photos.
const THRESHOLDS = {
  min_confidence: 72,
  min_long_edge: 960,
  min_gov_keywords: 2,
  min_ocr_chars: 24,
  max_fraud_score: 35,
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

async function runOcr(canvas) {
  const { createWorker } = await import('tesseract.js')
  const worker = await createWorker('eng', 1, { logger: () => {} })
  try {
    const { data } = await worker.recognize(canvas)
    const text = data.text || ''
    const conf = data.confidence ?? 0
    return { text, confidence: Math.round(conf) }
  } finally {
    await worker.terminate()
  }
}

function computeDecision(checks, fraudScore, ocrConfidence) {
  const critical = [
    checks.resolution?.pass,
    checks.aspect_ratio?.pass,
    checks.anti_spoof?.pass,
    checks.contrast?.pass,
    checks.document_bounds?.pass,
  ]
  const criticalOk = critical.every(Boolean)

  const textualOk = (checks.gov_keywords?.pass || checks.mrz?.pass)
    && checks.ocr_quality?.pass

  const mrzFailed = checks.mrz?.found && !checks.mrz?.pass

  const rejectionReasons = []
  if (!checks.resolution?.pass) rejectionReasons.push('Image resolution too low — use a sharper photo')
  if (!checks.aspect_ratio?.pass) rejectionReasons.push('Document shape not recognized as a government ID')
  if (!checks.anti_spoof?.pass) rejectionReasons.push('Possible fake: screen photo, printout, or tampered image')
  if (checks.fraud_flags?.length) {
    rejectionReasons.push(`Fraud signals: ${checks.fraud_flags.join(', ')}`)
  }
  if (!textualOk) rejectionReasons.push('Could not read enough official text from the document')
  if (mrzFailed) rejectionReasons.push('MRZ checksum failed — document may be forged')
  if (ocrConfidence < 45) rejectionReasons.push('OCR confidence too low — improve lighting and focus')

  let confidence = 0
  confidence += critical.filter(Boolean).length * 10
  confidence += textualOk ? 20 : 0
  confidence += checks.mrz?.pass ? 25 : 0
  confidence += Math.min(15, (checks.gov_keywords?.count || 0) * 4)
  confidence += Math.min(10, ocrConfidence / 10)
  confidence -= Math.min(30, fraudScore / 2)
  confidence = Math.max(0, Math.min(100, Math.round(confidence)))

  const valid = criticalOk
    && textualOk
    && !mrzFailed
    && confidence >= THRESHOLDS.min_confidence
    && fraudScore <= THRESHOLDS.max_fraud_score
    && ocrConfidence >= 45

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
      pass: longEdge >= THRESHOLDS.min_long_edge && width >= 640 && height >= 400,
    },
    aspect_ratio: { ...aspect, pass: aspect.type !== 'unknown' },
    contrast: { variance: Math.round(stats.variance), pass: stats.variance > 900 },
    edge_density: { value: Number(stats.edgeDensity.toFixed(3)), pass: stats.edgeDensity > 0.07 },
    brightness: { mean: Math.round(stats.mean), pass: stats.mean > 35 && stats.mean < 225 },
    ocr_quality: {
      chars: ocr.text.length,
      confidence: ocr.confidence,
      pass: ocr.text.length >= THRESHOLDS.min_ocr_chars && ocr.confidence >= 45,
    },
    gov_keywords: {
      hits: keywords.hits,
      count: keywords.count,
      pass: keywords.count >= THRESHOLDS.min_gov_keywords,
    },
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

  const { valid, confidence, rejectionReasons } = computeDecision(
    checks,
    forensics.fraud_score,
    ocr.confidence,
  )

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