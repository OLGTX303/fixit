// Dynamic per-interaction encryption client (v2 skill).
//
// Establishes an X25519 session (PFS), then for each sensitive write it derives
// a unique per-interaction key (HKDF), encrypts the body (AES-256-GCM), and
// signs the request metadata (HMAC over a length-prefixed canonical string).
// The server enforces timestamp window + nonce replay + signature before
// decrypting. Response bodies come back encrypted under a distinct key.
//
// Standalone (reads the JWT from localStorage) to avoid a cycle with api.js.

import { reactive } from 'vue'

const BASE = import.meta.env.VITE_API_URL || 'https://fixit.olgtx.com/api'
const te = new TextEncoder()
const td = new TextDecoder()

// Debug capsule feed: the most recent sensitive requests' payloads, both ways.
export const secureDebug = reactive({ events: [] })
function pushDebug(e) {
  secureDebug.events.unshift(e)
  if (secureDebug.events.length > 25) secureDebug.events.pop()
}

let session = null // { id, master, mac, salt, counter, expiresAt }

const b64 = (bytes) => btoa(String.fromCharCode(...bytes))
const unb64 = (s) => Uint8Array.from(atob(s), (c) => c.charCodeAt(0))

function token() {
  return localStorage.getItem('fixit_token')
}

function authHeaders(extra = {}) {
  const h = { ...extra }
  const t = token()
  if (t) h.Authorization = `Bearer ${t}`
  return h
}

async function hkdf(ikm, salt, info) {
  const key = await crypto.subtle.importKey('raw', ikm, 'HKDF', false, ['deriveBits'])
  const bits = await crypto.subtle.deriveBits(
    { name: 'HKDF', hash: 'SHA-256', salt, info: te.encode(info) }, key, 256)
  return new Uint8Array(bits)
}

async function hmacHex(macKey, msg) {
  const k = await crypto.subtle.importKey('raw', macKey, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign'])
  const sig = new Uint8Array(await crypto.subtle.sign('HMAC', k, te.encode(msg)))
  return [...sig].map((b) => b.toString(16).padStart(2, '0')).join('')
}

async function sha256hex(str) {
  const d = new Uint8Array(await crypto.subtle.digest('SHA-256', te.encode(str)))
  return [...d].map((b) => b.toString(16).padStart(2, '0')).join('')
}

// Length-prefixed canonical string (§2) — byte-wise key sort, no boundary forgery.
function canonical(pairs) {
  return Object.keys(pairs).sort().map((k) => {
    const v = String(pairs[k])
    return `${te.encode(k).length}|${k}|${te.encode(v).length}|${v}|`
  }).join('')
}

async function aesEncrypt(keyBytes, plaintext, aad) {
  const k = await crypto.subtle.importKey('raw', keyBytes, 'AES-GCM', false, ['encrypt'])
  const iv = crypto.getRandomValues(new Uint8Array(12))
  const ct = new Uint8Array(await crypto.subtle.encrypt(
    { name: 'AES-GCM', iv, additionalData: te.encode(aad) }, k, te.encode(plaintext)))
  const out = new Uint8Array(iv.length + ct.length)
  out.set(iv); out.set(ct, iv.length) // iv || ciphertext || tag
  return out
}

async function aesDecrypt(keyBytes, blob, aad) {
  const k = await crypto.subtle.importKey('raw', keyBytes, 'AES-GCM', false, ['decrypt'])
  const pt = await crypto.subtle.decrypt(
    { name: 'AES-GCM', iv: blob.slice(0, 12), additionalData: te.encode(aad) }, k, blob.slice(12))
  return td.decode(pt)
}

export function resetSecureSession() { session = null }

async function ensureSession() {
  if (session && Date.now() < session.expiresAt - 5000) return session

  const kp = await crypto.subtle.generateKey({ name: 'X25519' }, true, ['deriveBits'])
  const clientPub = new Uint8Array(await crypto.subtle.exportKey('raw', kp.publicKey))
  const res = await fetch(`${BASE}/secure/handshake`, {
    method: 'POST',
    headers: authHeaders({ 'Content-Type': 'application/json' }),
    body: JSON.stringify({ client_pub: b64(clientPub) }),
  })
  const j = await res.json()
  if (!res.ok) throw new Error(j?.error || 'Secure handshake failed')

  const serverPub = await crypto.subtle.importKey('raw', unb64(j.server_pub), { name: 'X25519' }, false, [])
  const z = new Uint8Array(await crypto.subtle.deriveBits({ name: 'X25519', public: serverPub }, kp.privateKey, 256))
  const salt = unb64(j.salt)
  const master = await hkdf(z, salt, 'fixit/v2/master')
  const mac = await hkdf(master, salt, 'fixit/v2/mac')
  session = { id: j.session_id, master, mac, salt, counter: 0, expiresAt: Date.now() + j.ttl * 1000 }
  return session
}

// Whether a request must go through the encrypted channel. Must mirror the
// routes that have SecureChannelMiddleware on the server.
const SENSITIVE = [
  { m: 'POST', re: /^\/wallet\/topup$/ },
  { m: 'POST', re: /^\/wallet\/withdraw$/ },
  { m: 'PATCH', re: /^\/users\/me$/ },
  { m: 'POST', re: /^\/payments\/stripe\/customer$/ },
  { m: 'POST', re: /^\/payments\/stripe\/setup-intent$/ },
  { m: 'POST', re: /^\/payments\/stripe\/save-payment-method$/ },
  { m: 'POST', re: /^\/payments\/stripe\/pay-with-saved-method$/ },
  { m: 'POST', re: /^\/payments\/booking\/pay$/ },
  { m: 'DELETE', re: /^\/payments\/stripe\/saved-payment-method$/ },
  { m: 'POST', re: /^\/providers\/\d+\/kyc\/id-recognition$/ },
  { m: 'POST', re: /^\/providers\/\d+\/kyc\/liveness$/ },
  { m: 'POST', re: /^\/bookings$/ },
  { m: 'PATCH', re: /^\/bookings\/\d+\/status$/ },
  { m: 'DELETE', re: /^\/bookings\/\d+$/ },
]

export function isSensitive(method, path) {
  const clean = path.split('?')[0]
  return SENSITIVE.some((s) => s.m === method && s.re.test(clean))
}

/** Encrypt+sign a sensitive write. Returns { ok, status, data }. */
export async function secureRequest(method, path, body) {
  const s = await ensureSession()
  const counter = ++s.counter
  const nonce = crypto.randomUUID()
  const ts = Date.now().toString()
  const fullPath = `/api${path.split('?')[0]}`
  const extra = `${method} ${fullPath}`

  const reqKey = await hkdf(s.master, s.salt, `fixit/v2/request/${counter}/${nonce}`)
  const aad = `fixit/v2|request|${s.id}|${counter}|${nonce}|${ts}|${extra}`
  const blob = await aesEncrypt(reqKey, JSON.stringify(body ?? {}), aad)
  const bodyB64 = b64(blob)
  const sign = await hmacHex(s.mac, canonical({
    session: s.id, counter: String(counter), nonce, ts,
    method, path: fullPath, body_hash: await sha256hex(bodyB64),
  }))

  const res = await fetch(`${BASE}${path}`, {
    method,
    headers: authHeaders({
      'Content-Type': 'application/octet-stream',
      'X-Sec-Session': s.id, 'X-Sec-Counter': String(counter), 'X-Sec-Nonce': nonce,
      'X-Sec-Ts': ts, 'X-Sec-Sign': sign,
    }),
    body: bodyB64,
  })

  const text = await res.text()
  const encryptedResp = res.headers.get('X-Sec-Enc') === '1'
  let data = null
  let respPlain = text
  if (encryptedResp) {
    const respKey = await hkdf(s.master, s.salt, `fixit/v2/response/${counter}/${nonce}`)
    const aadResp = `fixit/v2|response|${s.id}|${counter}|${nonce}|${ts}|${extra}`
    respPlain = await aesDecrypt(respKey, unb64(text), aadResp)
    data = respPlain ? JSON.parse(respPlain) : null
  } else if (text) {
    try { data = JSON.parse(text) } catch { data = { error: text } }
  }

  pushDebug({
    method, path: fullPath, encrypted: encryptedResp,
    encBefore: JSON.stringify(body ?? {}),
    encAfter: bodyB64,
    decBefore: text,
    decAfter: respPlain,
  })

  return { ok: res.ok, status: res.status, data }
}
