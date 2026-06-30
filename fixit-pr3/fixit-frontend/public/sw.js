// FixIt push service worker — shows chat notifications and routes taps.
/* eslint-env serviceworker */

self.addEventListener('push', (event) => {
  let payload = {}
  try { payload = event.data ? event.data.json() : {} } catch { payload = {} }
  const title = payload.title || 'FixIt'
  const body = payload.body || 'New message'
  const data = payload.data || {}
  event.waitUntil(
    self.registration.showNotification(title, {
      body,
      icon: '/favicon.svg',
      badge: '/favicon.svg',
      tag: data.job_id ? `chat-${data.job_id}` : undefined,
      data,
    })
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()
  const jobId = event.notification.data?.job_id
  const url = jobId ? `/jobs/${jobId}/chat` : '/'
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      for (const c of clients) {
        if ('focus' in c) { c.navigate(url); return c.focus() }
      }
      return self.clients.openWindow(url)
    })
  )
})
