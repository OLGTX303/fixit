# FixIt Frontend — Domain glossary

## Roles

- **customer** — browses verified providers, creates bookings (Jobs), reviews completed work
- **provider** — manages profile, accepts/declines requests, advances job status, chats on jobs
- **admin** — verifies provider KYC, views all users/bookings/reviews

## Core terms

- **Provider** — a `ProviderProfile` joined with user info and categories; only `is_verified` providers appear in customer search
- **Booking / Job** — same entity; status flow: `requested` → `accepted` → `in_progress` → `completed` → `reviewed`
- **Category** — `ServiceCategory` (Plumbing, Electrical, etc.)

## Integration

- All HTTP calls go through `src/services/api.js` (never fetch directly from views)
- JWT stored in `sessionStorage`; attached as `Authorization: Bearer`
- API base URL: `VITE_API_URL` (must point to deployed backend in production)

## Encrypted chat

- **Message** — E2E encrypted with per-job AES key; PIN unlocks RSA private key on new devices
- **Harm review** — client-side screening before send; admin reviews flagged metadata at `/admin/harm-reviews`