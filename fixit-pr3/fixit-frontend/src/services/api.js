// PR3: Slim REST API + JWT via fetch (no axios).

const TOKEN_KEY = 'fixit_token'
const USER_KEY = 'fixit_user'
const BASE = import.meta.env.VITE_API_URL || 'http://localhost:8080/api'

let onUnauthorized = null

function safeParseUser(raw) {
  if (!raw) return null
  try {
    const user = JSON.parse(raw)
    if (!user || typeof user !== 'object' || !user.id || !user.role) return null
    return user
  } catch {
    return null
  }
}

export function getStoredToken() {
  return sessionStorage.getItem(TOKEN_KEY)
}

export function getStoredUser() {
  return safeParseUser(sessionStorage.getItem(USER_KEY))
}

export function persistSession(token, user) {
  sessionStorage.setItem(TOKEN_KEY, token)
  sessionStorage.setItem(USER_KEY, JSON.stringify(user))
}

export function clearSession() {
  sessionStorage.removeItem(TOKEN_KEY)
  sessionStorage.removeItem(USER_KEY)
}

export function setUnauthorizedHandler(fn) {
  onUnauthorized = fn
}

async function request(method, path, body) {
  const headers = { 'Content-Type': 'application/json' }
  const token = getStoredToken()
  if (token) headers.Authorization = `Bearer ${token}`

  const res = await fetch(`${BASE}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  let data = null
  const text = await res.text()
  if (text) {
    try {
      data = JSON.parse(text)
    } catch {
      data = { error: text }
    }
  }

  if (res.status === 401 && onUnauthorized) onUnauthorized()
  if (!res.ok) throw new Error(data?.error || res.statusText || 'Request failed')
  return data
}

const get = (path) => request('GET', path)
const post = (path, body) => request('POST', path, body)
const put = (path, body) => request('PUT', path, body)
const patch = (path, body) => request('PATCH', path, body)
const del = (path) => request('DELETE', path)

// ── Reads ───────────────────────────────────────────────────────────────────
export const getUsers = () => get('/admin/users')
export const getCategories = () => get('/categories')

export async function getProviders() {
  return getStoredUser()?.role === 'admin' ? get('/admin/providers') : get('/providers')
}

export const getProvider = (id) => get(`/providers/${id}`)
export const getBookings = () => get('/bookings')
export const getReviews = () => get('/admin/reviews')
export const getReviewsForProvider = (providerId) => get(`/providers/${providerId}/reviews`)
export const getMessagesForJob = (jobId) => get(`/jobs/${jobId}/messages`)

// ── Auth ────────────────────────────────────────────────────────────────────
export async function register(payload) {
  const data = await post('/auth/register', payload)
  persistSession(data.token, data.user)
  return data
}

export const getCaptchaChallenge = () => get('/auth/captcha')
export const verifyCaptcha = (payload) => post('/auth/captcha/verify', payload)

export async function login(email, password) {
  const data = await post('/auth/login', { email, password })
  persistSession(data.token, data.user)
  return data
}

export function logout() {
  clearSession()
}

// ── Mutations ───────────────────────────────────────────────────────────────
function toServerDatetime(value) {
  if (!value) return null
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value.replace('T', ' ')
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`
}

export async function createBooking(payload) {
  const scheduled = payload.scheduled_at
    || (payload.date && payload.time ? `${payload.date}T${payload.time}` : null)
  return post('/bookings', {
    provider_id: payload.provider_id,
    category_id: payload.category_id,
    scheduled_at: toServerDatetime(scheduled),
    address: payload.address,
    total: payload.total,
    notes: payload.notes,
  })
}

export const setProviderVerification = (providerId, isVerified) =>
  patch(`/admin/providers/${providerId}/verify`, { is_verified: isVerified })

export const updateBookingStatus = (bookingId, status) =>
  patch(`/bookings/${bookingId}/status`, { status })

export const createReview = (payload) => post('/reviews', payload)
export const sendMessage = (jobId, payload) => post(`/jobs/${jobId}/messages`, payload)

export const getCryptoStatus = () => get('/crypto/status')
export const fetchPinSalt = () => get('/crypto/pin/salt')
export const getMyPublicKey = () => get('/crypto/public-key')
export const setupPin = (payload) => post('/crypto/pin/setup', payload)
export const verifyPin = (payload) => post('/crypto/pin/verify', payload)
export const getJobPeers = (jobId) => get(`/jobs/${jobId}/crypto/peers`)
export const getUserPublicKey = (userId) => get(`/users/${userId}/crypto/public-key`)
export const getJobCryptoKey = (jobId) => get(`/jobs/${jobId}/crypto/key`)
export const saveJobCryptoKey = (jobId, payload) => put(`/jobs/${jobId}/crypto/key`, payload)
export const getHarmReviews = () => get('/admin/harm-reviews')
export const reviewHarmMessage = (id, payload) => patch(`/admin/harm-reviews/${id}`, payload)
export const updateProvider = (id, payload) => put(`/providers/${id}`, payload)
export const deleteBooking = (bookingId) => del(`/bookings/${bookingId}`)

// ── KYC ─────────────────────────────────────────────────────────────────────
export const getKycStatus = (providerId) => get(`/providers/${providerId}/kyc`)
export const submitKycIdRecognition = (providerId, payload) =>
  post(`/providers/${providerId}/kyc/id-recognition`, payload)
export const submitKycLiveness = (providerId, payload) =>
  post(`/providers/${providerId}/kyc/liveness`, payload)

// ── Stripe test-mode payments ───────────────────────────────────────────────
export const getStripeConfig = () => get('/payments/stripe/config')
export const ensureStripeCustomer = () => post('/payments/stripe/customer')
export const createStripeSetupIntent = () => post('/payments/stripe/setup-intent')
export const saveStripePaymentMethod = (paymentMethodId) =>
  post('/payments/stripe/save-payment-method', { payment_method_id: paymentMethodId })
export const payWithStripeSavedMethod = (payload) =>
  post('/payments/stripe/pay-with-saved-method', payload)
export const removeStripeSavedPaymentMethod = () => del('/payments/stripe/saved-payment-method')