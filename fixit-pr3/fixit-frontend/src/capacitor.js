import { Capacitor } from '@capacitor/core'
import { App } from '@capacitor/app'
import { StatusBar, Style } from '@capacitor/status-bar'

const OTA_URL = `${import.meta.env.VITE_API_URL || 'https://fixit.olgtx.com/api'}/app/latest`

function isNewer(remote, current) {
  const a = String(remote).split('.').map(n => parseInt(n, 10) || 0)
  const b = String(current).split('.').map(n => parseInt(n, 10) || 0)
  for (let i = 0; i < Math.max(a.length, b.length); i++) {
    if ((a[i] || 0) !== (b[i] || 0)) return (a[i] || 0) > (b[i] || 0)
  }
  return false
}

// OTA: compare the installed version with the latest GitHub release; if newer,
// offer to download the signed APK (the user taps the download to install).
async function checkForUpdate() {
  try {
    const [{ version }, latest] = await Promise.all([
      App.getInfo(),
      fetch(OTA_URL).then(r => r.json()),
    ])
    if (!latest?.version || !latest?.apk_url) return
    if (isNewer(latest.version, version)) {
      const msg = `A new version (${latest.version}) is available.\n\n${latest.notes || ''}\n\nDownload and install now?`.trim()
      if (window.confirm(msg)) window.open(latest.apk_url, '_blank')
    }
  } catch { /* offline / no release yet — ignore */ }
}

export async function initCapacitor() {
  if (!Capacitor.isNativePlatform()) return

  try {
    // Plain white status bar with dark icons (no orange bar). Keep the WebView
    // below the status bar where the OS honours it; on Android 15+ edge-to-edge
    // the CSS safe-area padding handles the inset.
    await StatusBar.setOverlaysWebView({ overlay: false })
    // Style.Light = dark icons on a light background (Capacitor's naming).
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

  checkForUpdate()                                  // OTA check on launch
  App.addListener('resume', checkForUpdate)         // and when reopened
}