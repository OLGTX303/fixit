# Chat Push Notifications — Setup

The code is scaffolded and gated on credentials. Until you fill these in, chat
still works — it just won't push. Two independent transports:

## 1. Database

```bash
mysql -u fixit -p fixit < fixit-pr3/fixit-backend/migrations/024_push_subscriptions.sql
```

## 2. Backend deps

```bash
cd fixit-pr3/fixit-backend && composer install   # pulls minishlink/web-push
```

## 3. Browser (Web Push / VAPID)

Generate a VAPID keypair once:

```bash
npx web-push generate-vapid-keys
```

Put them in `fixit-pr3/fixit-backend/.env`:

```
VAPID_PUBLIC_KEY=BModl...      # public key
VAPID_PRIVATE_KEY=xxxx...      # private key
VAPID_SUBJECT=mailto:you@yourdomain
```

The browser fetches the public key from `GET /api/push/vapid-public-key`, registers
`/sw.js`, asks for permission, and subscribes automatically after login. Requires
HTTPS (or `localhost`).

## 4. Android (FCM)

1. Create a Firebase project, add an Android app with the package id from
   `android/app/build.gradle` (`applicationId`).
2. Download **google-services.json** → `fixit-pr3/fixit-frontend/android/app/google-services.json`.
3. Add the Google Services Gradle plugin (Firebase console shows the exact lines):
   - project `android/build.gradle`: `classpath 'com.google.gms:google-services:4.4.2'`
   - `android/app/build.gradle` bottom: `apply plugin: 'com.google.gms.google-services'`
4. Firebase Console → Project settings → Cloud Messaging → copy the **Server key**
   into `.env`:
   ```
   FCM_SERVER_KEY=AAAA....
   ```
5. Install the plugin and sync:
   ```bash
   cd fixit-pr3/fixit-frontend
   npm install            # adds @capacitor/push-notifications
   npm run cap:sync
   ```

> Uses the FCM **legacy** HTTP API (`fcm/send`) for minimal setup. If/when Google
> retires it, swap `PushService::sendFcm()` to the HTTP v1 API (OAuth service account).

## How it flows

- After login the client registers its device token (`POST /me/push/subscribe`).
- On a new chat message, `MessageController` calls `PushService::sendToUser()` for the
  recipient(s). Encrypted messages push a generic preview ("Sent you an encrypted
  message") — never plaintext.
- Dead tokens (410 / `NotRegistered`) are pruned automatically.
