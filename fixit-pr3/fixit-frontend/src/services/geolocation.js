import { Capacitor } from '@capacitor/core'
import { Geolocation } from '@capacitor/geolocation'

// Johor Bahru city centre — fallback when device location is unavailable.
const DEFAULT_CENTER = [1.4927, 103.7414]

export function isNativeApp() {
  return Capacitor.isNativePlatform()
}

/** @returns {Promise<[number, number]>} [latitude, longitude] */
export async function getUserLocation() {
  try {
    if (Capacitor.isNativePlatform()) {
      const perm = await Geolocation.checkPermissions()
      if (perm.location !== 'granted') {
        const req = await Geolocation.requestPermissions()
        if (req.location !== 'granted') return DEFAULT_CENTER
      }
      const pos = await Geolocation.getCurrentPosition({
        enableHighAccuracy: true,
        timeout: 10000,
      })
      return [pos.coords.latitude, pos.coords.longitude]
    }

    if (navigator.geolocation) {
      const pos = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, {
          enableHighAccuracy: true,
          timeout: 10000,
        })
      })
      return [pos.coords.latitude, pos.coords.longitude]
    }
  } catch {
    /* fall through to default */
  }
  return DEFAULT_CENTER
}

export function distanceKmFrom(lat, lng, p) {
  const R = 6371
  const toRad = (d) => (d * Math.PI) / 180
  const dLat = toRad(p.latitude - lat)
  const dLng = toRad(p.longitude - lng)
  const a = Math.sin(dLat / 2) ** 2
    + Math.cos(toRad(lat)) * Math.cos(toRad(p.latitude)) * Math.sin(dLng / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}