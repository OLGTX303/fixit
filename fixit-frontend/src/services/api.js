// PR3: Slim REST API + JWT. Signatures unchanged so stores/views need no rewrites.

import axios from 'axios'

const TOKEN_KEY = 'fixit_token'
const USER_KEY = 'fixit_user'

const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8080/api',
  headers: { 'Content-Type': 'application/json' },
  timeout: 15000,
  withCredentials: false,
})

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
  http.defaults.headers.common.Authorization = `Bearer ${token}`
}

export function clearSession() {
  sessionStorage.removeItem(TOKEN_KEY)
  sessionStorage.removeItem(USER_KEY)
  delete http.defaults.headers.common.Authorization
}

const token = getStoredToken()
if (token) {
  http.defaults.headers.common.Authorization = `Bearer ${token}`
}

let onUnauthorized = null
export function setUnauthorizedHandler(fn) {
  onUnauthorized = fn
}

http.interceptors.response.use(
  (r) => r,
  (err) => {
    if (err.response?.status === 401 && onUnauthorized) {
      onUnauthorized()
    }
    const message = err.response?.data?.error || err.message
    return Promise.reject(new Error(message))
  },
)

function unwrap(promise) {
  return promise.then((r) => r.data)
}

// ── Reads ───────────────────────────────────────────────────────────────────
export const getUsers = () => unwrap(http.get('/admin/users'))

export const getCategories = () => unwrap(http.get('/categories'))

export async function getProviders() {
  const role = getStoredUser()?.role
  if (role === 'admin') {
    return unwrap(http.get('/admin/providers'))
  }
  return unwrap(http.get('/providers'))
}

export async function getProvider(id) {
  return unwrap(http.get(`/providers/${id}`))
}

export const getBookings = () => unwrap(http.get('/bookings'))

export const getReviews = () => unwrap(http.get('/admin/reviews'))

export async function getReviewsForProvider(providerId) {
  return unwrap(http.get(`/providers/${providerId}/reviews`))
}

export async function getMessagesForJob(jobId) {
  return unwrap(http.get(`/jobs/${jobId}/messages`))
}

// ── Auth ────────────────────────────────────────────────────────────────────
export async function register(payload) {
  const { data } = await http.post('/auth/register', payload)
  persistSession(data.token, data.user)
  return data
}

export async function login(email, password) {
  const { data } = await http.post('/auth/login', { email, password })
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
  return unwrap(http.post('/bookings', {
    provider_id: payload.provider_id,
    category_id: payload.category_id,
    scheduled_at: toServerDatetime(scheduled),
    address: payload.address,
    total: payload.total,
    notes: payload.notes,
  }))
}

export async function setProviderVerification(providerId, isVerified) {
  return unwrap(http.patch(`/admin/providers/${providerId}/verify`, { is_verified: isVerified }))
}

export async function updateBookingStatus(bookingId, status) {
  return unwrap(http.patch(`/bookings/${bookingId}/status`, { status }))
}

export async function createReview(payload) {
  return unwrap(http.post('/reviews', payload))
}

export async function sendMessage(jobId, payload) {
  return unwrap(http.post(`/jobs/${jobId}/messages`, payload))
}

export const getCryptoStatus = () => unwrap(http.get('/crypto/status'))
export const fetchPinSalt = () => unwrap(http.get('/crypto/pin/salt'))
export const getMyPublicKey = () => unwrap(http.get('/crypto/public-key'))
export const setupPin = (payload) => unwrap(http.post('/crypto/pin/setup', payload))
export const verifyPin = (payload) => unwrap(http.post('/crypto/pin/verify', payload))
export const getJobPeers = (jobId) => unwrap(http.get(`/jobs/${jobId}/crypto/peers`))
export const getUserPublicKey = (userId) => unwrap(http.get(`/users/${userId}/crypto/public-key`))
export const getJobCryptoKey = (jobId) => unwrap(http.get(`/jobs/${jobId}/crypto/key`))
export const saveJobCryptoKey = (jobId, payload) =>
  unwrap(http.put(`/jobs/${jobId}/crypto/key`, payload))
export const getHarmReviews = () => unwrap(http.get('/admin/harm-reviews'))
export const reviewHarmMessage = (id, payload) =>
  unwrap(http.patch(`/admin/harm-reviews/${id}`, payload))

export async function updateProvider(id, payload) {
  return unwrap(http.put(`/providers/${id}`, payload))
}

export async function deleteBooking(bookingId) {
  return unwrap(http.delete(`/bookings/${bookingId}`))
}