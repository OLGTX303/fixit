// E2E chat crypto: RSA keypair (PIN-wrapped private key) + AES-GCM messages.
// Job AES keys are distributed per participant via RSA-OAEP — server sees ciphertext only.

const PBKDF2_ITERATIONS = 310000

export function getDeviceId() {
  let id = localStorage.getItem('fixit_device_id')
  if (!id) {
    id = crypto.randomUUID()
    localStorage.setItem('fixit_device_id', id)
  }
  return id
}

function bytesToHex(bytes) {
  return Array.from(bytes).map((b) => b.toString(16).padStart(2, '0')).join('')
}

function bytesToBase64(bytes) {
  return btoa(String.fromCharCode(...bytes))
}

function base64ToBytes(b64) {
  const bin = atob(b64)
  const bytes = new Uint8Array(bin.length)
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i)
  return bytes
}

export function generateSalt() {
  return bytesToHex(crypto.getRandomValues(new Uint8Array(16)))
}

export async function computePinVerifier(pin, saltHex) {
  const data = new TextEncoder().encode(saltHex + pin)
  const hash = await crypto.subtle.digest('SHA-256', data)
  return bytesToHex(new Uint8Array(hash))
}

export async function derivePinKey(pin, saltHex) {
  const salt = new Uint8Array(saltHex.match(/.{2}/g).map((h) => parseInt(h, 16)))
  const baseKey = await crypto.subtle.importKey(
    'raw',
    new TextEncoder().encode(pin),
    'PBKDF2',
    false,
    ['deriveKey'],
  )
  return crypto.subtle.deriveKey(
    { name: 'PBKDF2', salt, iterations: PBKDF2_ITERATIONS, hash: 'SHA-256' },
    baseKey,
    { name: 'AES-GCM', length: 256 },
    false,
    ['encrypt', 'decrypt', 'wrapKey', 'unwrapKey'],
  )
}

export async function generateUserKeyPair() {
  return crypto.subtle.generateKey(
    { name: 'RSA-OAEP', modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256' },
    true,
    ['encrypt', 'decrypt', 'wrapKey', 'unwrapKey'],
  )
}

export async function exportPublicKeyJwk(publicKey) {
  return crypto.subtle.exportKey('jwk', publicKey)
}

export async function importPublicKeyJwk(jwk) {
  return crypto.subtle.importKey('jwk', jwk, { name: 'RSA-OAEP', hash: 'SHA-256' }, true, ['encrypt'])
}

export async function wrapPrivateKey(pinKey, privateKey) {
  const iv = crypto.getRandomValues(new Uint8Array(12))
  const wrapped = await crypto.subtle.wrapKey('pkcs8', privateKey, pinKey, { name: 'AES-GCM', iv })
  return { wrapped_private_key: bytesToBase64(new Uint8Array(wrapped)), private_key_iv: bytesToBase64(iv) }
}

export async function unwrapPrivateKey(pinKey, wrappedB64, ivB64) {
  const wrapped = base64ToBytes(wrappedB64)
  const iv = base64ToBytes(ivB64)
  const pkcs8 = await crypto.subtle.unwrapKey('pkcs8', wrapped, pinKey, { name: 'AES-GCM', iv }, { name: 'RSA-OAEP', hash: 'SHA-256' }, true, ['decrypt'])
  return pkcs8
}

export async function generateJobKey() {
  return crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt'])
}

export async function encryptJobKeyForUser(publicKey, jobKey) {
  const raw = await crypto.subtle.exportKey('raw', jobKey)
  const enc = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, publicKey, raw)
  return bytesToBase64(new Uint8Array(enc))
}

export async function decryptJobKey(privateKey, encryptedB64) {
  const enc = base64ToBytes(encryptedB64)
  const raw = await crypto.subtle.decrypt({ name: 'RSA-OAEP' }, privateKey, enc)
  return crypto.subtle.importKey('raw', raw, { name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt'])
}

export async function encryptMessage(jobKey, plaintext) {
  const iv = crypto.getRandomValues(new Uint8Array(12))
  const encoded = new TextEncoder().encode(plaintext)
  const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, jobKey, encoded)
  return { ciphertext: bytesToBase64(new Uint8Array(cipher)), iv: bytesToBase64(iv) }
}

export async function decryptMessage(jobKey, ciphertextB64, ivB64) {
  const cipher = base64ToBytes(ciphertextB64)
  const iv = base64ToBytes(ivB64)
  const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, jobKey, cipher)
  return new TextDecoder().decode(plain)
}

export async function contentHash(plaintext) {
  const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(plaintext))
  return bytesToHex(new Uint8Array(hash))
}

export function isUnlockedThisSession() {
  return sessionStorage.getItem('fixit_unlocked_device') === getDeviceId()
}

export function markUnlocked() {
  sessionStorage.setItem('fixit_unlocked_device', getDeviceId())
}

export function clearUnlockSession() {
  sessionStorage.removeItem('fixit_unlocked_device')
}