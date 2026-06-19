# FixIt Frontend

Vue 3 + Vite SPA (PR3). Talks to `../fixit-backend` via `src/services/api.js`.

## Setup

```bash
npm install
cp .env.example .env
# VITE_API_URL=http://localhost:8080/api
npm run dev
```

## Production build

```bash
VITE_API_URL=https://your-api.example.com/api npm run build
```

Deploy the `dist/` folder to any static host (Render, Netlify, S3, nginx).

## Demo logins

Password for all seed users: `password123` (change in production DB).

| Role     | Email              |
|----------|--------------------|
| Customer | alex@email.com     |
| Provider | marcus@email.com   |
| Admin    | admin@fixit.com    |

## Android app (Capacitor)

```bash
# Set VITE_API_URL for device/emulator (see .env.android.example)
npm run cap:sync
npm run cap:android    # opens Android Studio → Run on emulator/device
```

Full guide: [ANDROID.md](./ANDROID.md)

## Security

- JWT in `sessionStorage` (cleared on tab close)
- No mock data bundled — all data from live API
- E2E encrypted chat with PIN unlock
- See `../SECURITY.md`