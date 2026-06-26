import { Capacitor } from '@capacitor/core'
import { App } from '@capacitor/app'
import { StatusBar, Style } from '@capacitor/status-bar'
import { OtaUpdater } from './plugins/otaUpdater'

const OTA_URL = `${import.meta.env.VITE_API_URL || 'https://fixit.olgtx.com/api'}/app/latest`
const OTA_ACTIVE_KEY = 'fixit_ota_active'

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

let otaInflight = null

async function runSilentOta(latest) {
  const key = releaseKey(latest)
  try {
    if (localStorage.getItem(OTA_ACTIVE_KEY) === key) {
      return { status: 'installing', version: latest.version }
    }
  } catch { /* ignore */ }

  if (otaInflight) return otaInflight

  otaInflight = (async () => {
    try {
      localStorage.setItem(OTA_ACTIVE_KEY, key)
      await OtaUpdater.downloadAndInstall({
        url: latest.apk_url,
        versionCode: String(latest.version_code || latest.version),
      })
      return { status: 'installing', version: latest.version }
    } catch (err) {
      try { localStorage.removeItem(OTA_ACTIVE_KEY) } catch { /* ignore */ }
      console.warn('[ota] silent update failed', err)
      return { status: 'error', message: err?.message || 'update failed' }
    } finally {
      otaInflight = null
    }
  })()

  return otaInflight
}

/**
 * Silent OTA: background APK download + in-app install prompt (no browser).
 */
export async function checkForUpdate({ force = false } = {}) {
  if (!Capacitor.isNativePlatform()) return { status: 'skipped' }

  if (force) {
    try { localStorage.removeItem(OTA_ACTIVE_KEY) } catch { /* ignore */ }
  }

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
      try { localStorage.removeItem(OTA_ACTIVE_KEY) } catch { /* ignore */ }
      return { status: 'current', version: appInfo.version, build: appInfo.build }
    }

    if (!force) {
      const active = (() => { try { return localStorage.getItem(OTA_ACTIVE_KEY) } catch { return null } })()
      if (active === releaseKey(latest)) {
        return runSilentOta(latest)
      }
    }

    return runSilentOta(latest)
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