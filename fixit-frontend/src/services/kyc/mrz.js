/**
 * ICAO 9303 MRZ parsing + check-digit validation.
 * Rejects forged IDs with invalid machine-readable zones.
 */

const CHAR_VALUES = {
  ...Object.fromEntries('0123456789'.split('').map((c, i) => [c, i])),
  ...Object.fromEntries('ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').map((c, i) => [c, 10 + i])),
  '<': 0,
}

const WEIGHTS = [7, 3, 1]

function mrzCharValue(ch) {
  return CHAR_VALUES[ch] ?? 0
}

function mrzCheckDigit(data) {
  let sum = 0
  for (let i = 0; i < data.length; i++) {
    sum += mrzCharValue(data[i]) * WEIGHTS[i % 3]
  }
  return sum % 10
}

function normalizeMrzLine(line) {
  return line.replace(/\s/g, '').toUpperCase().replace(/[^A-Z0-9<]/g, '<')
}

/** Extract TD1/TD2/TD3 style MRZ line candidates from OCR text. */
function extractMrzLines(text) {
  const lines = text.split(/\r?\n/).map(normalizeMrzLine).filter((l) => l.length >= 28)
  const mrz = lines.filter((l) => (l.match(/</g) || []).length >= 2 && /^[A-Z0-9<]+$/.test(l))

  // TD3 passport: 2 lines × 44 chars
  const td3 = []
  for (let i = 0; i < mrz.length - 1; i++) {
    if (mrz[i].length >= 42 && mrz[i + 1].length >= 42) {
      td3.push([mrz[i].padEnd(44, '<').slice(0, 44), mrz[i + 1].padEnd(44, '<').slice(0, 44)])
    }
  }

  // TD1 ID card: 3 lines × 30 chars
  const td1 = []
  for (let i = 0; i < mrz.length - 2; i++) {
    if (mrz[i].length >= 28 && mrz[i + 1].length >= 28 && mrz[i + 2].length >= 28) {
      td1.push([
        mrz[i].padEnd(30, '<').slice(0, 30),
        mrz[i + 1].padEnd(30, '<').slice(0, 30),
        mrz[i + 2].padEnd(30, '<').slice(0, 30),
      ])
    }
  }

  return { td1, td3, raw: mrz }
}

function validateCheckField(data, checkChar) {
  if (checkChar === '<') return true
  const expected = mrzCheckDigit(data)
  const actual = parseInt(checkChar, 10)
  return !Number.isNaN(actual) && expected === actual
}

/** Validate TD3 (passport) MRZ pair. */
function validateTd3(lines) {
  const [l1, l2] = lines
  if (!l1?.startsWith('P')) return { valid: false, reason: 'not_passport_format' }

  const checks = {
    doc_number: validateCheckField(l2.slice(0, 9), l2[9]),
    birth_date: validateCheckField(l2.slice(13, 19), l2[19]),
    expiry_date: validateCheckField(l2.slice(21, 27), l2[27]),
    composite: validateCheckField(
      l2.slice(0, 10) + l2.slice(13, 20) + l2.slice(21, 28),
      l2[43],
    ),
  }

  const valid = Object.values(checks).every(Boolean)
  return {
    valid,
    format: 'TD3',
    document_number: l2.slice(0, 9).replace(/</g, ''),
    checks,
    lines: [l1, l2],
  }
}

/** Validate TD1 (ID card) MRZ triple. */
function validateTd1(lines) {
  const [l1, l2, l3] = lines
  const checks = {
    doc_number: validateCheckField(l1.slice(5, 14), l1[14]),
    birth_date: validateCheckField(l2.slice(0, 6), l2[6]),
    expiry_date: validateCheckField(l2.slice(8, 14), l2[14]),
    composite: validateCheckField(l1.slice(5, 15) + l2.slice(0, 7) + l2.slice(8, 15), l2[29]),
  }
  const valid = Object.values(checks).every(Boolean)
  return {
    valid,
    format: 'TD1',
    document_number: l1.slice(5, 14).replace(/</g, ''),
    checks,
    lines: [l1, l2, l3],
  }
}

export function validateMrzFromText(text) {
  const { td1, td3 } = extractMrzLines(text)

  for (const pair of td3) {
    const r = validateTd3(pair)
    if (r.valid) return { ...r, found: true }
  }
  for (const triple of td1) {
    const r = validateTd1(triple)
    if (r.valid) return { ...r, found: true }
  }

  const hasMrzShape = td1.length > 0 || td3.length > 0
  return {
    found: hasMrzShape,
    valid: false,
    reason: hasMrzShape ? 'mrz_checksum_failed' : 'no_mrz_detected',
    candidates: { td1: td1.length, td3: td3.length },
  }
}