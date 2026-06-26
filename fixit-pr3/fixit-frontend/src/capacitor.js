import { Capacitor } from '@capacitor/core'
import { App } from '@capacitor/app'
import { Browser } from '@capacitor/browser'
import { StatusBar, Style } from '@capacitor/status-bar'

const OTA_URL = `${import.meta.env.VITE_API_URL || 'https://fixit.olgtx.com/api'}/app/latest`
const OTA_DISMISS_KEY = 'fixit_ota_dismiss'

function isNewer(remote, current) {
  const a = String(remote).split('.').map(n => parseInt(n, 10) || 0)
  const b = String(current).split('.').map(n => parseInt(n, 10) || 0)
  for (let i = 0; i < Math.max(a.length, b.length); i++) {
    if ((a[i] || 0) !== (b[i] || 0)) return (a[i] || 0) > (b[i] || 0)
  }
  return false
}

function releaseKey(latest) {
  return `${latest.version}:${latest.version_code ?? ''}`
}

function isDismissed(latest) {
  try {
    return localStorage.getItem(OTA_DISMISS_KEY) === releaseKey(latest)
  } catch {
    return false
  }
}

function dismissRelease(latest) {
  try {
    localStorage.setItem(OTA_DISMISS_KEY, releaseKey(latest))
  } catch { /* private mode */ }
}

function hasUpdate(latest, appInfo) {
  if (!latest?.apk_url) return false
  const remoteCode = parseInt(latest.version_code, 10)
  const currentCode = parseInt(appInfo.build, 10) || 0
  if (Number.isFinite(remoteCode) && remoteCode > 0 && currentCode > 0) {
    return remoteCode > currentCode
  }
  if (latest.version && appInfo.version) {
    return isNewer(latest.version, appInfo.version)
  }
  return false
}

async function openApkUrl(url) {
  await Browser.open({ url })
}

/**
 * OTA: compare installed build/version with /api/app/latest (GitHub release).
 * @param {{ force?: boolean }} opts — force=true ignores per-version dismiss
 */
export async function checkForUpdate({ force = false } = {}) {
  if (!Capacitor.isNativePlatform()) return { status: 'skipped' }

  try {
    const [appInfo, res] = await Promise.all([
      App.getInfo(),
      fetch(OTA_URL),
    ])
    if (!res.ok) {
      console.warn('[ota] API returned', res.status)
      return { status: 'error', message: `API ${res.status}` }
    }

    const latest = await res.json()
    if (!latest?.version || !latest?.apk_url) {
      return { status: 'none' }
    }

    if (!hasUpdate(latest, appInfo)) {
      return { status: 'current', version: appInfo.version, build: appInfo.build }
    }

    if (!force && isDismissed(latest)) {
      return { status: 'dismissed' }
    }

    const buildHint = latest.version_code ? ` (build ${latest.version_code})` : ''
    const notes = latest.notes ? `\n\n${String(latest.notes).slice(0, 400)}` : ''
    const msg = `A new version (${latest.version}${buildHint}) is available.${notes}\n\nDownload and install now?`.trim()

    if (window.confirm(msg)) {
      await openApkUrl(latest.apk_url)
      return { status: 'opened', version: latest.version }
    }

    dismissRelease(latest)
    return { status: 'declined' }
  } catch (err) {
    console.warn('[ota] check failed', err)
    return { status: 'error', message: err?.message || 'check failed' }
  }
}

export async function initCapacitor() {
  if (!Capacitor.isNativePlatform()) return

  try {
    await StatusBar.setOverlaysWebView({ overlay: false })
    await StatusBar.setStyle({ style: Style.Light })
    await StatusBar.setBackgroundColor({ color: '#FFFFFF' })
  } catch {
    /* status bar not available on all devices */
  }

  App.addListener('backButton', ({ canGoBack }) => {
    if (canGoBack) {
      window.history.back()
    } else {
      App.exitApp()
    }
  })

  checkForUpdate()
  App.addListener('resume', () => checkForUpdate())
}