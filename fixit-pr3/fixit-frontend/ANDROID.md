# FixIt Android App (Capacitor)

## Prerequisites

- [Android Studio](https://developer.android.com/studio) (includes SDK + emulator)
- Java 17+ (bundled with Android Studio)
- Backend API running and reachable from the device/emulator

## API URL for mobile

Vite inlines `VITE_API_URL` at build time. Before building:

```bash
# Android emulator → host machine localhost
cp .env.android.example .env.production.local
# Edit VITE_API_URL:
#   Emulator:  http://10.0.2.2:8080/api
#   Real device: http://YOUR_LAN_IP:8080/api
#   Production:  https://fixit.olgtx.com/api
```

Capacitor Android serves the WebView from **`https://localhost`**. The backend must allow that origin or login/API calls fail with a CORS preflight error.

Update server `fixit-backend/.env` `CORS_ORIGIN` (comma-separated, no spaces):

```
CORS_ORIGIN=https://fixit.olgtx.com,http://fixit.olgtx.com,https://localhost,capacitor://localhost,http://localhost,http://localhost:5173
```

After changing `.env` on the server, restart PHP-FPM:

```bash
/etc/init.d/php-fpm-85 restart
```

## Build workflow

```bash
cd fixit-frontend

# 1. Set API URL (see above)
# 2. Build + sync web assets into Android project
npm run cap:sync

# 3. Open in Android Studio
npm run cap:android
```

In Android Studio: **Run ▶** on an emulator or connected device.

## CLI build (without Android Studio UI)

```bash
cd fixit-frontend
npm run android:release
# APK: android/app/build/outputs/apk/release/app-release-unsigned.apk
```

Release signing is intentionally not committed. To create a Play Store-ready APK,
sign the release artifact with your private keystore or configure local Gradle
signing credentials outside version control.

## Features enabled

| Plugin | Use |
|--------|-----|
| `@capacitor/geolocation` | Auto-detect customer location on Search map |
| `@capacitor/app` | Android back button handling |
| `@capacitor/status-bar` | Brand-colored status bar (#FF6635) |

## E2E encrypted chat on mobile

Chat PIN + RSA/AES encryption works the same in the WebView. PIN is required on each new app install.

## Troubleshooting

- **Blank screen**: run `npm run cap:sync` after every frontend change
- **API 401/CORS**: check `VITE_API_URL` and backend `CORS_ORIGIN`
- **Location denied**: grant location permission in Android Settings → Apps → FixIt
- **HTTP blocked**: `capacitor.config.json` has `cleartext: true` for local dev; use HTTPS in production
