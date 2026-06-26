import { registerPlugin } from '@capacitor/core'

export const OtaUpdater = registerPlugin('OtaUpdater', {
  web: () => import('./otaUpdater.web.js').then((m) => new m.OtaUpdaterWeb()),
})