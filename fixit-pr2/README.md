# FixIt — PR2 Interim Build

On-demand local home-services marketplace. This is the **PR2 interim milestone**:
a fully interactive Vue 3 front end with **no backend** — all data is served from
local JSON files through a single Axios service module.

## Stack

- **Vue 3** (SFCs, `v-for`, `v-model`, props/emit, `onMounted`, computed properties)
- **Vite** build tooling
- **Vue Router** with role-aware route guards
- **Pinia** stores (`auth`, `providers`, `bookings`, `admin`)
- **Axios** — one service module, `src/services/api.js`
- **Bootstrap 5** + custom brand tokens
- **Leaflet + OpenStreetMap** for the provider map

## Run

```bash
npm install
npm run dev
```

Vite prints a local URL (default http://localhost:5173). On the login screen use
the **Customer / Provider / Admin** demo buttons to jump straight into each role,
or sign in with a seeded email (e.g. `alex@email.com`, `marcus@email.com`,
`admin@fixit.com`) — any password works in PR2.

## Architecture

```
public/mock/*.json   →  src/services/api.js  →  Pinia stores  →  components/views
   (DB-shaped data)       (all Axios calls)       (shared state)
```

Components **never** fetch data directly. They read from Pinia stores, which call
`api.js`. The mock JSON shapes mirror the target database schema:

```
User · ServiceCategory · ProviderProfile · ProviderCategory
Job (bookings) · Review · Message
```

## Three required workflows

1. **Customer Booking** — `HomeView` → `SearchView` → `ProviderProfileView` →
   `BookingFormView` → `JobTrackerView` (status timeline) → `RateReviewView`.
2. **Provider Search & Filter** — `SearchView` + `FilterBar` + `ProviderMap`:
   reactive computed filtering by category, distance, price and rating; only
   `is_verified` providers show; map markers update with the filtered set.
3. **Admin Provider Verification** — `VerificationView`: review pending KYC,
   Approve/Reject. Approving flips `is_verified` in the **shared** providers
   store, so the provider appears in the customer Search view immediately.

See [Workflow → file map](#workflow-file-map) below.

## Workflow → file map

| Workflow | Key files |
|---|---|
| Customer Booking | `views/customer/*.vue`, `stores/bookings.js`, `components/StatusTimeline.vue` |
| Provider Search & Filter | `views/customer/SearchView.vue`, `components/FilterBar.vue`, `components/ProviderMap.vue`, `stores/providers.js` |
| Admin Verification | `views/admin/VerificationView.vue`, `stores/admin.js` + `stores/providers.js` (shared state) |

## Responsiveness

Mobile-first; verified at **375 / 768 / 1280 px**. A bottom tab bar is shown on
mobile and a left side-nav on desktop (≥ 992px).

## PR3 migration

The whole data layer is isolated in **`src/services/api.js`**. To swap to the
PHP **Slim 4 + MySQL + JWT** backend, change `baseURL` and the request paths in
that one file — the exported function **signatures stay identical**, so stores,
components and views need no changes. Mutations (`createBooking`,
`setProviderVerification`, `updateBookingStatus`, `createReview`) become real
`POST`/`PATCH` calls and the stores commit the server response instead of mock state.

> Brand accent is `#FF6635` (from the PR1 mockup tokens). The brief also lists
> `#E8632A`; the mockup is treated as the authoritative visual source.
