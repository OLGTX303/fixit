// Client-side harmful message screening before E2E encrypt + send.

const BLOCK_PATTERNS = [
  { category: 'threats', re: /\b(kill|murder|bomb|shoot|stab)\b/i },
  { category: 'harassment', re: /\b(stupid|idiot|worthless|die)\b/i },
  { category: 'scam', re: /\b(wire transfer|bitcoin|gift card|send money now)\b/i },
  { category: 'hate', re: /\b(hate you|go back to)\b/i },
]

const FLAG_PATTERNS = [
  { category: 'profanity', re: /\b(damn|hell|bastard)\b/i },
  { category: 'aggressive', re: /\b(shut up|get lost|you're useless)\b/i },
]

export function reviewMessage(text) {
  const trimmed = text.trim()
  if (!trimmed) {
    return { allowed: false, status: 'blocked', categories: ['empty'], message: 'Message cannot be empty.' }
  }

  const categories = []

  for (const p of BLOCK_PATTERNS) {
    if (p.re.test(trimmed)) categories.push(p.category)
  }
  if (categories.length) {
    return {
      allowed: false,
      status: 'blocked',
      categories,
      message: 'This message was blocked by safety review. Please revise respectful language.',
    }
  }

  for (const p of FLAG_PATTERNS) {
    if (p.re.test(trimmed)) categories.push(p.category)
  }

  if (categories.length) {
    return {
      allowed: true,
      status: 'flagged',
      categories,
      message: 'Message will be sent but flagged for moderator review.',
    }
  }

  return { allowed: true, status: 'clear', categories: [], message: null }
}