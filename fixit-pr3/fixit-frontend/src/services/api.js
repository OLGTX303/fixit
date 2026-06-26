// PR3: Slim REST API + JWT via fetch (no axios).

const TOKEN_KEY = 'fixit_token'
const USER_KEY = 'fixit_user'
const BASE = import.meta.env.VITE_API_URL || 'https://fixit.olgtx.com/api'

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

// localStorage (not sessionStorage): the JWT must survive across tabs, reloads,
// and app/PWA relaunches. sessionStorage is per-tab, so a context that never ran
// the login (new tab, external link, cold app launch) had no token — public pages
// still rendered, but the authed POST /bookings 401'd and bounced the user to login.
export function getStoredToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function getStoredUser() {
  return safeParseUser(localStorage.getItem(USER_KEY))
}

export function persistSession(token, user) {
  localStorage.setItem(TOKEN_KEY, token)
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

export function clearSession() {
  localStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(USER_KEY)
}

export function setUnauthorizedHandler(fn) {
  onUnauthorized = fn
}

// Extract the first balanced JSON object/array from a string that may have
// extra (non-JSON) content before or after it — e.g. PHP warning HTML.
function salvageJson(text) {
  const start = text.search(/[{[]/)
  if (start === -1) return null
  const open = text[start]
  const close = open === '{' ? '}' : ']'
  const end = text.lastIndexOf(close)
  if (end <= start) return null
  try {
    return JSON.parse(text.slice(start, end + 1))
  } catch {
    return null
  }
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
      // Some PHP hosts append deprecation/notice HTML around the JSON body,
      // which breaks a strict parse. Salvage the embedded JSON object/array
      // so a valid response (e.g. Stripe client_secret) is never dropped.
      data = salvageJson(text) ?? { error: text }
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
export const getUsers = ({ q = '', limit = 25, offset = 0, sort = 'name' } = {}) =>
  get(`/admin/users?q=${encodeURIComponent(q)}&limit=${limit}&offset=${offset}&sort=${sort}`)
export const getCategoryStats = () => get('/admin/category-stats')
export const getVerifyStats = () => get('/admin/verify-stats')
// Admin provider list, paginated. opts: { verified (0|1), limit, offset }
export function getAdminProviders(opts = {}) {
  const p = new URLSearchParams()
  if (opts.verified != null) p.set('verified', opts.verified)
  p.set('limit', opts.limit ?? 20)
  p.set('offset', opts.offset ?? 0)
  return get(`/admin/providers?${p.toString()}`)
}
export const blockUser = (id, blocked) => patch(`/admin/users/${id}/block`, { blocked })
export const getCategories = () => get('/categories')
export const getMapsConfig = () => get('/config/maps')

export function getProviders({ limit = 20, offset = 0 } = {}) {
  const user = getStoredUser()
  if (user?.role === 'admin') {
    return getAdminProviders({ limit, offset })
  }
  return searchProviders({ limit, offset })
}

export const getProvider = (id) => get(`/providers/${id}`)

// Paginated provider list/search. opts: { q, category, sort, priority, lat, lng, limit, offset }
export function searchProviders(opts = {}) {
  const p = new URLSearchParams()
  if (opts.q) p.set('q', opts.q)
  if (opts.category) p.set('category', opts.category)
  if (opts.sort) p.set('sort', opts.sort)
  if (opts.priority) p.set('priority', '1')
  if (opts.region) p.set('region', opts.region)
  if (opts.lat != null && opts.lng != null) { p.set('lat', opts.lat); p.set('lng', opts.lng) }
  p.set('limit', opts.limit ?? 20)
  p.set('offset', opts.offset ?? 0)
  return get(`/providers?${p.toString()}`)
}
export const getRecommendedProviders = (limit = 20, offset = 0) =>
  get(`/providers?sort=recommended&limit=${limit}&offset=${offset}`)
// The logged-in provider's own profile (works even while unverified).
export const getMyProviderProfile = () => get('/me/provider')
export function getBookings({ limit = 0, offset = 0, status } = {}) {
  const p = new URLSearchParams()
  if (limit > 0) { p.set('limit', limit); p.set('offset', offset) }
  if (status) p.set('status', status)
  const qs = p.toString()
  return get(`/bookings${qs ? `?${qs}` : ''}`)
}
export const getReviews = ({ limit = 25, offset = 0 } = {}) =>
  get(`/admin/reviews?limit=${limit}&offset=${offset}`)
export const getStripeStats = () => get('/admin/stripe/stats')
export const getReviewsForProvider = (providerId) => get(`/providers/${providerId}/reviews`)

// ── Provider service catalog (rich, server-persisted) ────────────────────────
export const getProviderServices = (providerId) => get(`/providers/${providerId}/services`)
export const createProviderService = (providerId, payload) => post(`/providers/${providerId}/services`, payload)
export const updateProviderServiceItem = (providerId, sid, payload) => put(`/providers/${providerId}/services/${sid}`, payload)
export const deleteProviderServiceItem = (providerId, sid) => del(`/providers/${providerId}/services/${sid}`)
export const getMessagesForJob = (jobId) => get(`/jobs/${jobId}/messages`)

// ── Auth ────────────────────────────────────────────────────────────────────
export async function register(payload) {
  const data = await post('/auth/register', payload)
  persistSession(data.token, data.user)
  return data
}

export const requestRegisterOtp = (payload) => post('/auth/register/otp', payload)
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
  // The booking form's time chips are 12-hour ("10:00 AM"), which Date can't
  // parse — so handle "YYYY-MM-DD[T ]H:MM AM/PM" explicitly into MySQL DATETIME.
  const ampm = String(value).match(/^(\d{4}-\d{2}-\d{2})[T ](\d{1,2}):(\d{2})\s*(AM|PM)$/i)
  if (ampm) {
    const [, date, hh, mm, ap] = ampm
    let h = Number(hh) % 12
    if (/PM/i.test(ap)) h += 12
    return `${date} ${String(h).padStart(2, '0')}:${mm}:00`
  }
  const d = new Date(value)
  if (Number.isNaN(d.getTime())) return value.replace('T', ' ')
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`
}

export async function createBooking(payload) {
  const scheduled = payload.scheduled_at
    || (payload.date && payload.time ? `${payload.date}T${payload.time}` : null)
  const body = {
    provider_id:          payload.provider_id,
    category_id:          payload.category_id,
    scheduled_at:         toServerDatetime(scheduled),
    address:              payload.address,
    notes:                payload.notes,
    recurrence_type:      payload.recurrence_type || 'none',
    recurrence_end_date:  payload.recurrence_end_date || null,
    provider_service_id:  payload.provider_service_id || undefined,
  }
  if (payload.coupon_code) body.coupon_code = payload.coupon_code
  return post('/bookings', body)
}

export const setProviderVerification = (providerId, isVerified) =>
  patch(`/admin/providers/${providerId}/verify`, { is_verified: isVerified })

export const updateBookingStatus = (bookingId, status) =>
  patch(`/bookings/${bookingId}/status`, { status })

// Pre-order chat: get-or-create an inquiry conversation with a provider.
export const startInquiry = (providerId) => post(`/providers/${providerId}/inquiry`)

export const createReview = (payload) => post('/reviews', payload)
export const sendMessage = (jobId, payload) => post(`/jobs/${jobId}/messages`, payload)

// ── Profile ─────────────────────────────────────────────────────────────────
export const updateProfile = (payload) => patch('/users/me', payload)
export const uploadAvatar = (dataUrl) => post('/users/me/avatar', { image: dataUrl })
export const uploadImage  = (dataUrl) => post('/upload/image', { image: dataUrl })
export const requestEmailOtp = (email) => post('/users/me/email/otp', { email })
export const verifyEmailOtp = (email, otp) => post('/users/me/email/verify', { email, otp })

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

// ── Wallet (real ledger; top-up charges card, withdraw refunds) ──────────────
export const getWallet = () => get('/wallet')
export const walletTopUp = (amountCents) => post('/wallet/topup', { amount_cents: amountCents })
export const walletWithdraw = (amountCents) => post('/wallet/withdraw', { amount_cents: amountCents })

// ── Availability calendar ────────────────────────────────────────────────────
export const getProviderAvailability = (id) => get(`/providers/${id}/availability`)
export const saveProviderAvailability = (id, slots) => put(`/providers/${id}/availability`, { slots })

// ── Admin: priority listing ──────────────────────────────────────────────────
export const setProviderPriority = (id, isPriority) =>
  patch(`/admin/providers/${id}/priority`, { is_priority: isPriority })

// ── Favourites ───────────────────────────────────────────────────────────────
export function getFavorites({ limit = 20, offset = 0 } = {}) {
  const p = new URLSearchParams()
  p.set('limit', limit)
  p.set('offset', offset)
  return get(`/favorites?${p.toString()}`)
}
export const favoriteProvider = (providerId) => post(`/providers/${providerId}/favorite`)
export const unfavoriteProvider = (providerId) => del(`/providers/${providerId}/favorite`)

// ── Coupons ──────────────────────────────────────────────────────────────────
export const validateCoupon = (payload) => post('/coupons/validate', payload)
export function getAvailableCoupons(providerId) {
  // No providerId → system-wide coupons only (customer My Coupons page).
  return get(providerId ? `/coupons/available?provider_id=${providerId}` : '/coupons/available')
}
export const getMyCoupons = () => get('/me/coupons')
export const createMyCoupon = (payload) => post('/me/coupons', payload)
export const updateMyCoupon = (id, payload) => put(`/me/coupons/${id}`, payload)
export const deleteMyCoupon = (id) => del(`/me/coupons/${id}`)
export function getAdminCoupons({ limit = 25, offset = 0 } = {}) {
  const p = new URLSearchParams()
  p.set('limit', limit)
  p.set('offset', offset)
  return get(`/admin/coupons?${p.toString()}`)
}
export const createAdminCoupon = (payload) => post('/admin/coupons', payload)
export const updateAdminCoupon = (id, payload) => put(`/admin/coupons/${id}`, payload)
export const deleteAdminCoupon = (id) => del(`/admin/coupons/${id}`)

// ── Browsing history ─────────────────────────────────────────────────────────
export const recordBrowsingHistory = (providerId) => post('/me/history', { provider_id: providerId })
export function getBrowsingHistory({ limit = 20, offset = 0 } = {}) {
  const p = new URLSearchParams()
  p.set('limit', limit)
  p.set('offset', offset)
  return get(`/me/history?${p.toString()}`)
}
export const clearBrowsingHistory = () => del('/me/history')