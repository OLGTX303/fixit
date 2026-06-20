// Google Maps loader. The API key is NOT hardcoded here — it is fetched from
// the backend (GET /api/config/maps), which reads it from server-side .env.
import * as api from './api'

let loadPromise = null
let cachedKey = null

async function fetchKey() {
  if (cachedKey) return cachedKey
  const cfg = await api.getMapsConfig()
  if (!cfg.configured || !cfg.maps_api_key) {
    throw new Error('Google Maps key is not configured on the server')
  }
  cachedKey = cfg.maps_api_key
  return cachedKey
}

/** Load the Google Maps JS SDK once and resolve with the global `google.maps`. */
export async function loadGoogleMaps() {
  if (window.google?.maps) return window.google.maps
  if (loadPromise) return loadPromise

  loadPromise = (async () => {
    const key = await fetchKey()
    await new Promise((resolve, reject) => {
      const existing = document.getElementById('gmaps-sdk')
      if (existing) {
        existing.addEventListener('load', resolve)
        existing.addEventListener('error', reject)
        return
      }
      const s = document.createElement('script')
      s.id = 'gmaps-sdk'
      s.async = true
      s.defer = true
      s.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}&libraries=marker`
      s.onload = resolve
      s.onerror = () => reject(new Error('Failed to load Google Maps SDK'))
      document.head.appendChild(s)
    })
    return window.google.maps
  })()

  return loadPromise
}
