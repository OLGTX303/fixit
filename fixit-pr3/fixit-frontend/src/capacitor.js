import { Capacitor } from '@capacitor/core'
import { App } from '@capacitor/app'
import { StatusBar, Style } from '@capacitor/status-bar'

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
}