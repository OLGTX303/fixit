// Direct chat notifications — no FCM, no server push, no device tokens.
// The client polls its own booking list (which already carries latest_message)
// and, when a newer incoming message appears, fires a notification locally:
//   - Browser: Web Notifications API.
//   - Native (Android): @capacitor/local-notifications.
// The sender's avatar is used as the notification icon.
//
// Trade-off vs FCM: only fires while the app/tab is alive (the poll runs).
// True closed-app push needs a push service — intentionally out of scope here.

import { Capacitor } from '@capacitor/core'
import * as api from './api'
import router from '../router'

const POLL_MS = 15000
let timer = null
let primed = false
let lastSeen = {}      // jobId -> last notified sent_at (ms)
let localNotif = null  // Capacitor LocalNotifications, lazily loaded on native
let notifId = 1

export async function initPush() {
  try {
    if (Capacitor.isNativePlatform()) {
      const mod = await import('@capacitor/local-notifications')
      localNotif = mod.LocalNotifications
      await localNotif.requestPermissions()
      localNotif.addListener('localNotificationActionPerformed', (e) => {
        openChat(e?.notification?.extra?.job_id)
      })
    } else if ('Notification' in window && Notification.permission === 'default') {
      await Notification.requestPermission()
    }
  } catch { /* permission denied / plugin missing — degrade silently */ }

  start()
}

export function stopPush() {
  if (timer) { clearInterval(timer); timer = null }
  primed = false
  lastSeen = {}
}

function start() {
  if (timer) return
  poll()
  timer = setInterval(poll, POLL_MS)
}

async function poll() {
  const me = api.getStoredUser()
  if (!me) return
  let bookings
  try {
    bookings = await api.getBookings({ limit: 50 })
  } catch {
    return
  }

  for (const b of bookings) {
    const lm = b.latest_message
    if (!lm || !lm.sent_at) continue
    const t = new Date(lm.sent_at).getTime()
    if (!(t > (lastSeen[b.id] || 0))) continue
    lastSeen[b.id] = t
    // First pass only seeds the baseline so we don't notify for old history.
    if (primed && lm.sender_id !== me.id && !lm.is_system && !viewingChat(b.id)) {
      notify(b, lm)
    }
  }
  primed = true
}

function viewingChat(jobId) {
  const r = router.currentRoute.value
  return ['chat', 'pro-chat', 'admin-chat'].includes(r.name) && Number(r.params.id) === Number(jobId)
}

function senderOf(b, lm) {
  if (lm.sender_id === b.customer?.id) return { name: b.customer?.name, avatar: b.customer?.avatar_url }
  if (lm.sender_id === b.provider?.user_id) return { name: b.provider?.name, avatar: b.provider?.avatar_url }
  return { name: 'Customer Service', avatar: null }
}

function notify(b, lm) {
  const s = senderOf(b, lm)
  const title = s.name || 'New message'
  const body = lm.is_encrypted ? 'Sent you an encrypted message' : (lm.body || 'New message')
  const icon = s.avatar || '/favicon.svg'

  if (localNotif) {
    localNotif.schedule({
      notifications: [{
        id: notifId++,
        title,
        body,
        largeIcon: s.avatar || undefined,
        smallIcon: 'ic_stat_icon',
        extra: { job_id: b.id },
      }],
    }).catch(() => {})
    return
  }

  if ('Notification' in window && Notification.permission === 'granted') {
    const n = new Notification(title, { body, icon, tag: `chat-${b.id}` })
    n.onclick = () => { window.focus(); openChat(b.id); n.close() }
  }
}

function openChat(jobId) {
  if (!jobId) return
  router.push({ name: 'chat', params: { id: jobId } }).catch(() => {})
}
