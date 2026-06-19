# FixIt — PR3 Full-Stack Build

On-demand local home-services marketplace. This is the **PR3 milestone**:
Vue 3 frontend + PHP Slim 4 API + MySQL, with production KYC, Stripe test payments,
slider captcha registration, legal policies, E2E encrypted chat, and Capacitor Android.

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
mysql -u root -p < fixit-backend/migrations/002_e2e_crypto_harm.sql
mysql -u root -p < fixit-backend/migrations/003_kyc_verification.sql
mysql -u root -p < fixit-backend/migrations/004_stripe_payments.sql
mysql -u root -p < fixit-backend/migrations/005_legal_acceptance.sql
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
| Chat | E2E AES-256-GCM; PIN-wrapped RSA keys |
| Security | Rate limits, CORS, prepared statements, security headers |