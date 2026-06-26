import { Capacitor } from '@capacitor/core'
import { Geolocation } from '@capacitor/geolocation'

/** West Malaysia service regions — primary: Johor Bahru + Skudai (UTM). */
export const WEST_MALAYSIA_REGIONS = {
  johor_bahru: {
    id: 'johor_bahru',
    label: 'Johor Bahru',
    center: [1.4927, 103.7414],
    minLat: 1.40, maxLat: 1.58,
    minLng: 103.65, maxLng: 103.82,
    primary: true,
  },
  skudai_utm: {
    id: 'skudai_utm',
    label: 'Skudai · UTM',
    center: [1.5595, 103.6383],
    minLat: 1.52, maxLat: 1.58,
    minLng: 103.60, maxLng: 103.67,
    primary: true,
  },
  kul: {
    id: 'kul',
    label: 'Kuala Lumpur',
    center: [3.1390, 101.6869],
    minLat: 2.95, maxLat: 3.35,
    minLng: 101.55, maxLng: 101.85,
    primary: false,
  },
  penang: {
    id: 'penang',
    label: 'Penang',
    center: [5.4141, 100.3288],
    minLat: 5.30, maxLat: 5.50,
    minLng: 100.20, maxLng: 100.45,
    primary: false,
  },
}

export const DEFAULT_REGION = 'johor_bahru'
export const DEFAULT_CENTER = WEST_MALAYSIA_REGIONS[DEFAULT_REGION].center

export const PRIMARY_REGIONS = Object.values(WEST_MALAYSIA_REGIONS).filter(r => r.primary)

export function isNativeApp() {
  return Capacitor.isNativePlatform()
}

export function inBounds(lat, lng, region) {
  return lat >= region.minLat && lat <= region.maxLat
    && lng >= region.minLng && lng <= region.maxLng
}

/** Classify GPS into a West Malaysia region (Skudai checked before JB). */
export function detectRegion(lat, lng) {
  if (inBounds(lat, lng, WEST_MALAYSIA_REGIONS.skudai_utm)) return 'skudai_utm'
  if (inBounds(lat, lng, WEST_MALAYSIA_REGIONS.johor_bahru)) return 'johor_bahru'
  for (const id of ['kul', 'penang']) {
    if (inBounds(lat, lng, WEST_MALAYSIA_REGIONS[id])) return id
  }
  return DEFAULT_REGION
}

export function regionLabel(regionId) {
  return WEST_MALAYSIA_REGIONS[regionId]?.label ?? 'Johor Bahru'
}

export function regionCenter(regionId) {
  return WEST_MALAYSIA_REGIONS[regionId]?.center ?? DEFAULT_CENTER
}

/** Prefer stored user profile, then device GPS, then JB centre. */
export async function resolveUserLocation(authUser) {
  if (authUser?.latitude != null && authUser?.longitude != null) {
    const lat = Number(authUser.latitude)
    const lng = Number(authUser.longitude)
    const region = authUser.region || detectRegion(lat, lng)
    return {
      coords: [lat, lng],
      region,
      label: authUser.location_label || regionLabel(region),
      source: 'profile',
    }
  }
  const coords = await getDeviceLocation()
  const region = detectRegion(coords[0], coords[1])
  return {
    coords,
    region,
    label: regionLabel(region),
    source: 'device',
  }
}

/** @returns {Promise<[number, number]>} [latitude, longitude] */
export async function getDeviceLocation() {
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
    /* fall through */
  }
  return DEFAULT_CENTER
}

/** @deprecated use resolveUserLocation */
export async function getUserLocation() {
  const [lat, lng] = await getDeviceLocation()
  return [lat, lng]
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

export const DEFAULT_ADDRESSES = {
  skudai_utm: 'Jalan Sultanah Aminah, Taman Universiti, 81300 Skudai, Johor',
  johor_bahru: '14, Jalan Setia Tropika 1/5, Taman Setia Tropika, 81200 Johor Bahru',
}