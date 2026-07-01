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
see [the root README's "Deployment credentials & secrets" section](../README.md#deployment-credentials--secrets)
for the full list of required GitHub Actions secrets and backend `.env` variables, and how to
*prove* they're configured (GitHub's masked Secrets page, a green Actions run, or the live site
itself) without ever exposing a value.

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