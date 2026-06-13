# Claude Code Prompt — FixIt PR2 Interim Build

> Paste everything below the line into Claude Code, running from an empty project folder.

---

You are building the **PR2 Interim Build** for a university project called **FixIt**, an on-demand local home-services marketplace. This is a graded interim milestone, so follow the constraints exactly.

## Hard constraints (do not deviate)

- **Stack for PR2:** Vue 3 + Vite, Vue Router, Pinia, Axios, Bootstrap 5, Leaflet + OpenStreetMap. **No backend in this milestone** — all data comes from **local JSON files served via Axios**.
- **Mock data lives in `public/mock/*.json`** and is fetched through a **single Axios service module** (`src/services/api.js`). Every component reads data through Pinia stores, which call this service. Do not fetch JSON directly in components.
- The mock JSON shapes **must mirror this database schema** (so PR3 can swap to a real PHP Slim 4 + MySQL + JWT backend by editing only `api.js`).
- **Mobile-first responsive** design, verified at 375px / 768px / 1280px.
- Use real Vue 3 features visibly: SFCs, `v-for`, `v-model`, props/emit, `onMounted` lifecycle, computed properties.

## Database schema to mirror in mock JSON

```
User(id, name, email, password_hash, role[customer|provider|admin], phone)
ServiceCategory(id, name, description, icon_url)
ProviderProfile(id, user_id, bio, location, base_rate, is_verified,
                kyc_doc_url, avg_rating, latitude, longitude)
ProviderCategory(id, provider_id, category_id)
Job(id, customer_id, provider_id, category_id,
    status[requested|accepted|in_progress|completed|reviewed],
    scheduled_at, address, total)
Review(id, job_id, rating, comment, created_at)
Message(id, job_id, sender_id, body, sent_at)
```

## Three roles, three end-to-end workflows (all required)

1. **Customer Booking** — browse categories → search providers (list + Leaflet map) → open provider profile (rate, services, reviews) → fill booking form (date, time slot, address, notes via `v-model`) → submit → see Job Tracker with status timeline (requested → accepted → in_progress → completed → reviewed).
2. **Provider Search & Filter** — filter providers by category, distance, price range, minimum rating using reactive computed properties; only `is_verified === true` providers show; map markers update with the filtered set.
3. **Admin Provider Verification** — dashboard lists providers with pending KYC → admin views details + mock KYC reference → Approve sets `is_verified = true` / Reject keeps unlisted → approved providers immediately appear in the customer Search view (shared Pinia state).

## Project structure to create

```
fixit/
  public/mock/
    users.json categories.json providers.json bookings.json reviews.json messages.json
  src/
    main.js
    App.vue
    router/index.js
    services/api.js          # ALL Axios calls; reads /mock/*.json
    stores/
      auth.js providers.js bookings.js admin.js
    components/
      ProviderCard.vue RatingStars.vue StatusTimeline.vue FilterBar.vue
      CategoryGrid.vue ProviderMap.vue   # ProviderMap uses Leaflet
    views/
      LoginView.vue RegisterView.vue
      customer/ HomeView.vue SearchView.vue ProviderProfileView.vue
                BookingFormView.vue JobTrackerView.vue RateReviewView.vue
      provider/ ProfileSetupView.vue BookingRequestsView.vue
                JobStatusView.vue ChatView.vue
      admin/    VerificationView.vue UserManagementView.vue BookingReviewView.vue
  index.html  vite.config.js  package.json  README.md
```

## Service layer requirement

`src/services/api.js` must expose named async functions, each currently resolving from a JSON file via Axios, e.g.:

```js
import axios from 'axios'
const http = axios.create({ baseURL: import.meta.env.BASE_URL })
export const getCategories   = () => http.get('mock/categories.json').then(r => r.data)
export const getProviders    = () => http.get('mock/providers.json').then(r => r.data)
export const getProvider     = async (id) => (await getProviders()).find(p => p.id === id)
export const getReviews      = () => http.get('mock/reviews.json').then(r => r.data)
export const getBookings     = () => http.get('mock/bookings.json').then(r => r.data)
// createBooking / approveProvider mutate the Pinia store in PR2 (no persistence yet)
```
Add a top-of-file comment: `// PR3: replace baseURL + paths with the Slim REST API; signatures stay identical.`

## Seed data

Generate ~6 providers across categories (plumbing, electrical, cleaning, gardening, AC), each with realistic lat/long clustered around one city, varied `base_rate`, `avg_rating`, and `is_verified` (leave at least 2 pending for the admin demo). Add ~5 reviews, ~4 bookings spanning different statuses, ~3 users per role.

## Styling

Brand colour `#E8632A` (orange). Clean card-based UI, bottom tab nav on mobile, side nav on desktop. Match the PR1 mockups in spirit: rounded cards, prominent "Book Now" / "Confirm Booking" / "Review & Approve" CTAs.

## Build order

1. Scaffold Vite + Vue 3, install deps (`vue-router pinia axios bootstrap leaflet`).
2. Create all mock JSON with seed data.
3. Build `api.js`, then the four Pinia stores.
4. Build shared components, then views per role.
5. Wire router with role-aware routes.
6. Verify all three workflows run end-to-end against the mock data.
7. Confirm responsiveness at 375/768/1280px.
8. Write a README documenting how to run (`npm install && npm run dev`) and how PR3 will swap the backend.

After building, give me a short summary of which files implement each of the three workflows, and run the dev server so I can verify.
