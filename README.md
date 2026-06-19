# FixIt

On-demand local home-services marketplace — full-stack PR3 build with Vue 3 frontend, PHP Slim 4 API, MySQL, E2E encrypted chat, harm-message review, and Capacitor Android app.

## Repository layout

```
├── fixit-frontend/     PR3 — Vue 3 + Vite SPA + Capacitor Android (live API)
├── fixit-backend/      PR3 — PHP Slim 4 REST API + MySQL
├── fixit-pr2/          PR2 — Vue 3 interim build (mock JSON, no backend)
├── fixit/              PR1 — interactive UI mockup (React/JSX design canvas)
├── docs/               Architecture decisions & agent docs
├── AGENTS.md           AI agent instructions
├── CONTEXT-MAP.md      Monorepo context index
└── SECURITY.md         Security audit & production checklist
```

**PR3** (`fixit-frontend` + `fixit-backend`) is the current full-stack app. **PR2** and **PR1** folders are kept as earlier milestones for reference.

Frontend and backend deploy separately. No Docker required.

## Features

| Area | Details |
|------|---------|
| **Roles** | Customer, provider, admin with JWT + role guards |
| **Marketplace** | Browse providers by category, map search, bookings, reviews |
| **Provider KYC** | Upload flow with admin verification queue |
| **E2E chat** | AES-256-GCM messages; server stores ciphertext only |
| **PIN unlock** | RSA-2048 keypair; private key wrapped with PIN (PBKDF2) for new devices |
| **Harm review** | Client-side screening; flagged metadata queued for admin |
| **Android** | Capacitor app with geolocation, status bar, back-button handling |
| **Security** | Prepared statements, rate limiting, CORS lockdown, security headers |

## Quick start

### Prerequisites

- **Backend:** PHP 8.1+, Composer, MySQL 8.0+
- **Frontend:** Node.js 18+, npm
- **Android (optional):** Android Studio, Java 17+

### 1. Database

```bash
mysql -u root -p < fixit-backend/schema.sql
mysql -u root -p < fixit-backend/seed.sql
mysql -u root -p < fixit-backend/migrations/002_e2e_crypto_harm.sql
```

Create a least-privilege MySQL user (see [fixit-backend/README.md](fixit-backend/README.md)).

### 2. Backend

```bash
cd fixit-backend
cp .env.example .env
# Edit DB_* and JWT_SECRET (≥32 characters)
composer install
composer start
# → http://localhost:8080/api/health
```

### 3. Frontend (web)

```bash
cd fixit-frontend
npm install
cp .env.example .env
# VITE_API_URL=http://localhost:8080/api
npm run dev
# → http://localhost:5173
```

### 4. Android app (optional)

```bash
cd fixit-frontend
cp .env.android.example .env.production.local
# Set VITE_API_URL (emulator: http://10.0.2.2:8080/api)
npm run cap:sync
npm run cap:android
```

Full mobile guide: [fixit-frontend/ANDROID.md](fixit-frontend/ANDROID.md)

## Demo accounts

Seed password for all users: `password123` (change before production).

| Role | Email |
|------|-------|
| Customer | alex@email.com |
| Provider | marcus@email.com |
| Admin | admin@fixit.com |

## API

- **Base URL:** `/api`
- **Auth:** `Authorization: Bearer <token>`
- **Health:** `GET /api/health`

Import [fixit-frontend/fixit.postman_collection.json](fixit-frontend/fixit.postman_collection.json) for full endpoint coverage.

### Main route groups

| Group | Endpoints |
|-------|-----------|
| Auth | `POST /auth/register`, `POST /auth/login` |
| Catalog | `GET /categories`, `GET /providers`, `GET /providers/{id}` |
| Bookings | CRUD + status updates (customer/provider) |
| Reviews | Create + list per provider |
| Crypto | PIN setup/verify, RSA keys, per-job AES key exchange |
| Messages | Encrypted job chat (`GET/POST /jobs/{id}/messages`) |
| Admin | Provider verification, users, reviews, harm-review queue |

## Architecture

```mermaid
flowchart LR
  subgraph clients [Clients]
    Web[Vue 3 SPA]
    Android[Capacitor Android]
  end
  subgraph api [fixit-backend]
    Slim[Slim 4 API]
    JWT[JWT Auth]
    MySQL[(MySQL)]
  end
  Web --> Slim
  Android --> Slim
  Slim --> JWT
  JWT --> MySQL
```

- **Frontend** calls the API via `fixit-frontend/src/services/api.js`
- **E2E crypto** runs in the browser/app (`crypto.js`, `chatCrypto.js`); backend stores wrapped keys and ciphertext
- **Harm screening** runs client-side before encryption (`harmReview.js`)

## Production deployment

1. Read [SECURITY.md](SECURITY.md) and complete both checklists (backend + frontend).
2. Set `APP_DEBUG=false`, strong `JWT_SECRET`, and exact `CORS_ORIGIN`.
3. Build frontend with production API URL:
   ```bash
   VITE_API_URL=https://your-api.example.com/api npm run build
   ```
4. Serve `fixit-frontend/dist/` from any static host (nginx, Netlify, Render, S3).
5. Run backend behind HTTPS with `composer install --no-dev`.

## Earlier milestones

| Folder | Milestone | Run |
|--------|-----------|-----|
| [fixit/](fixit/) | PR1 UI mockup | Open `fixit/FixIt.html` or run `node fixit/server.js` |
| [fixit-pr2/](fixit-pr2/) | PR2 Vue interim (mock data) | `cd fixit-pr2 && npm install && npm run dev` |

## Development docs

| Document | Purpose |
|----------|---------|
| [fixit-frontend/README.md](fixit-frontend/README.md) | SPA setup, build, demo logins |
| [fixit-backend/README.md](fixit-backend/README.md) | API setup, MySQL, Composer |
| [fixit-pr2/README.md](fixit-pr2/README.md) | PR2 mock-data architecture & migration notes |
| [SECURITY.md](SECURITY.md) | Audit findings, E2E crypto notes, CSP |
| [docs/adr/0001-separate-frontend-backend.md](docs/adr/0001-separate-frontend-backend.md) | ADR: split deployment |
| [AGENTS.md](AGENTS.md) | Agent / contributor conventions |

## License

Private project — all rights reserved unless otherwise specified by the repository owner.