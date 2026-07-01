# FixIt - PR3 Full-Stack Build

<p align="center">
  <img src="fixit-frontend/public/fixit-logo.svg" alt="FixIt project logo" width="240">
</p>

On-demand local home-services marketplace. This is the **PR3 milestone**:
Vue 3 frontend + PHP Slim 4 API + MySQL, with production KYC, Stripe test payments,
slider captcha registration, legal policies, E2E encrypted chat, and Capacitor Android.

**Live:** [https://fixit.olgtx.com](https://fixit.olgtx.com) · API base: `https://fixit.olgtx.com/api`

## Layout

```
fixit-pr3/
├── fixit-frontend/   Vue 3 + Vite SPA + Capacitor Android
└── fixit-backend/    PHP Slim 4 REST API + MySQL
```

## Quick start

### Database

```bash
mysql -u root -p < fixit-backend/schema.sql
mysql -u root -p < fixit-backend/seed.sql
for f in fixit-backend/migrations/*.sql; do mysql -u root -p < "$f"; done
```

### Backend

```bash
cd fixit-backend
cp .env.example .env
composer install
composer start
# → http://localhost:8080/api/health
```

### Frontend

```bash
cd fixit-frontend
npm install
cp .env.example .env
npm run dev
# → http://localhost:5173
```

### Android (optional)

```bash
cd fixit-frontend
cp .env.android.example .env.production.local
npm run cap:sync && npm run cap:android
```

See [fixit-frontend/ANDROID.md](fixit-frontend/ANDROID.md) and [fixit-backend/README.md](fixit-backend/README.md) (includes **aaPanel / nginx production deploy**).

### Deployment credentials

Real credential values are **never** committed, documented, or shown in screenshots/recordings —
rotate anything ever shown in a screen share or recording. See
[the root README](../README.md#deployment-credentials--secrets) for the GitHub Actions secrets
(`SSH_*`, `ANDROID_*`) required by the CI/CD pipeline, and how to *prove* credentials are
configured without exposing a value (GitHub's masked Secrets page, a green Actions run, or the
live site itself).

Backend secrets live in `fixit-backend/.env` (git-ignored — template at
[`.env.example`](fixit-backend/.env.example)):

| Variable | Purpose |
|----------|---------|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | MySQL connection |
| `JWT_SECRET` | Signs/verifies auth tokens — ≥32 random characters |
| `CORS_ORIGIN` | Comma-separated allow-list of origins permitted to call the API |
| `APP_DEBUG` | Must be `false` in production |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME` | Outgoing mail for the email-OTP flow |
| `GOOGLE_MAPS_API_KEY` | Maps JS key, served via `GET /api/config/maps` (restrict by HTTP referrer + API in Google Cloud Console) |
| `STRIPE_MODE`, `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET` | Stripe test-mode payments — never use live keys here |
| `FACE_MATCH_URL`, `FACE_MATCH_API_KEY`, `FACE_MATCH_MIN_SCORE` | LAN face-match gateway used by KYC |

## Demo accounts

Password: `password123`

| Role | Email |
|------|-------|
| Customer | alex@email.com |
| Provider | marcus@email.com |
| Admin | admin@fixit.com |

## Features

| Area | Details |
|------|---------|
| Roles | Customer, provider, admin — JWT + role guards |
| KYC | OCR + MRZ + anti-spoof + 8-colour face liveness |
| Payments | Stripe test mode (SetupIntent + saved card) |
| Auth | Slider puzzle captcha + Terms/Privacy acceptance |
| Chat | E2E AES-256-GCM; PIN-wrapped RSA keys; auto-refreshes every 3s |
| Order history | Order Details page (customer/provider/admin): submit → paid → accepted → in-progress → completed timeline, synced avatars |
| Per-interaction encryption | X25519 + HKDF + AES-256-GCM + HMAC on payments, chat, and order-detail requests — same channel, visible in the Encryption Debug capsule |
| Chat notifications | Client-side only (Web Notifications / Capacitor Local Notifications) — no FCM, no push server |
| Security | Rate limits, CORS, prepared statements, security headers |