// PR3: replace baseURL + paths with the Slim REST API; signatures stay identical.
//
// This is the ONLY place that talks to the data layer. Components never fetch
// directly — they go through Pinia stores, which call these functions. In PR2
// every call resolves from a static JSON file in /public/mock via Axios. In PR3
// we point `http` at the PHP Slim 4 + JWT backend and the rest of the app is
// unchanged because the resolved shapes are identical.

import axios from 'axios'

const http = axios.create({ baseURL: import.meta.env.BASE_URL })

// ── Raw table reads (mirror the DB tables 1:1) ──────────────────────────────
export const getUsers      = () => http.get('mock/users.json').then(r => r.data)
export const getCategories = () => http.get('mock/categories.json').then(r => r.data)
const getProviderRows      = () => http.get('mock/providers.json').then(r => r.data)
export const getReviews    = () => http.get('mock/reviews.json').then(r => r.data)
export const getBookingRows = () => http.get('mock/bookings.json').then(r => r.data)
export const getMessages   = () => http.get('mock/messages.json').then(r => r.data)

// ── Joined / view reads (what the UI actually consumes) ─────────────────────
// A ProviderProfile joined with its User + ServiceCategory rows. PR3 returns
// the same shape from `GET /providers`.
export async function getProviders() {
  const [rows, users, categories, reviews] = await Promise.all([
    getProviderRows(), getUsers(), getCategories(), getReviews(),
  ])
  return rows.map(p => {
    const user = users.find(u => u.id === p.user_id) || {}
    const cats = categories.filter(c => p.category_ids.includes(c.id))
    return {
      ...p,
      name: user.name,
      email: user.email,
      phone: user.phone,
      categories: cats,
      category_names: cats.map(c => c.name),
      // review_count counts reviews on this provider's jobs (computed in PR2)
      review_count: reviews.length ? Math.max(0, Math.round(p.avg_rating * 28)) : 0,
    }
  })
}

export async function getProvider(id) {
  const all = await getProviders()
  return all.find(p => p.id === Number(id))
}

// A Job joined with customer, provider and category. PR3 returns the same.
export async function getBookings() {
  const [rows, users, providers, categories] = await Promise.all([
    getBookingRows(), getUsers(), getProviders(), getCategories(),
  ])
  return rows.map(b => ({
    ...b,
    customer: users.find(u => u.id === b.customer_id) || null,
    provider: providers.find(p => p.id === b.provider_id) || null,
    category: categories.find(c => c.id === b.category_id) || null,
  }))
}

export async function getReviewsForProvider(providerId) {
  const [reviews, bookingRows] = await Promise.all([getReviews(), getBookingRows()])
  const jobIds = bookingRows.filter(b => b.provider_id === Number(providerId)).map(b => b.id)
  return reviews.filter(r => jobIds.includes(r.job_id))
}

export async function getMessagesForJob(jobId) {
  const all = await getMessages()
  return all.filter(m => m.job_id === Number(jobId))
}

// ── Mutations ───────────────────────────────────────────────────────────────
// In PR2 these are resolved no-ops: the Pinia store applies the change to its
// in-memory state (no persistence). In PR3 each becomes a POST/PATCH/DELETE and
// the store commits the server response instead.
export async function login(email, password) {
  const users = await getUsers()
  const user = users.find(u => u.email === email)
  if (!user) throw new Error('No account found for that email.')
  return { token: `mock-jwt-${user.id}`, user }
}

export async function createBooking(payload) {
  // PR3: POST /bookings → returns the persisted Job. PR2: echo with a fresh id.
  return { id: Date.now(), status: 'requested', ...payload }
}

export async function setProviderVerification(providerId, isVerified) {
  // PR3: PATCH /providers/:id { is_verified }. PR2: store mutates locally.
  return { id: Number(providerId), is_verified: isVerified }
}

export async function updateBookingStatus(bookingId, status) {
  // PR3: PATCH /bookings/:id { status }. PR2: store mutates locally.
  return { id: Number(bookingId), status }
}

export async function createReview(payload) {
  // PR3: POST /reviews. PR2: echo with a fresh id + timestamp.
  return { id: Date.now(), created_at: new Date().toISOString(), ...payload }
}
