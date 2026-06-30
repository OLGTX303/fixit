// Chat push notifications. Two backends behind one initPush():
//   - Native (Capacitor/Android): FCM via @capacitor/push-notifications.
//   - Browser: Web Push via the service worker + VAPID.
// Both register the device token with the API; the server sends on new messages.
// Safe to call when unconfigured (no VAPID key / plugin) — it no-ops.

import { Capacitor } from '@capacitor/core'
import * as api from './api'
import router from '../router'

let started = false

export async function initPush() {
  if (started) return
  started = true
  try {
    if (Capacitor.isNativePlatform()) {
      await initNativePush()
    } else {
      await initWebPush()
    }
  } catch (e) {
    console.warn('[push] init failed', e)
    started = false
  }
}

// ── Native (Android / iOS) ────────────────────────────────────────────────────
async function initNativePush() {
  // @vite-ignore so the web build doesn't require the native-only plugin.
  const { PushNotifications } = await import(/* @vite-ignore */ '@capacitor/push-notifications')

  const perm = await PushNotifications.requestPermissions()
  if (perm.receive !== 'granted') return

  PushNotifications.addListener('registration', (token) => {
    api.subscribePush({ platform: 'android', fcm_token: token.value }).catch(() => {})
  })
  PushNotifications.addListener('registrationError', (err) => console.warn('[push] fcm reg error', err))
  PushNotifications.addListener('pushNotificationActionPerformed', (action) => {
    const jobId = action?.notification?.data?.job_id
    if (jobId) router.push({ name: 'chat', params: { id: jobId } }).catch(() => {})
  })

  await PushNotifications.register()
}

// ── Browser (Web Push) ────────────────────────────────────────────────────────
async function initWebPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return

  const { public_key: vapid } = await api.getVapidPublicKey().catch(() => ({ public_key: '' }))
  if (!vapid) return // server has no VAPID key configured yet

  const permission = await Notification.requestPermission()
  if (permission !== 'granted') return

  const reg = await navigator.serviceWorker.register('/sw.js')
  const sub = await reg.pushManager.getSubscription()
    || await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(vapid),
    })

  const json = sub.toJSON()
  await api.subscribePush({
    platform: 'web',
    endpoint: json.endpoint,
    p256dh: json.keys?.p256dh,
    auth: json.keys?.auth,
  })
}

function urlBase64ToUint8Array(base64) {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(b64)
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)))
}
