export async function fileToImage(file) {
  const url = URL.createObjectURL(file)
  try {
    return await new Promise((resolve, reject) => {
      const el = new Image()
      el.onload = () => resolve(el)
      el.onerror = () => reject(new Error('Could not load image'))
      el.src = url
    })
  } finally {
    URL.revokeObjectURL(url)
  }
}

export function drawToCanvas(img, maxEdge = 1600) {
  const scale = Math.min(1, maxEdge / Math.max(img.width, img.height))
  const w = Math.round(img.width * scale)
  const h = Math.round(img.height * scale)
  const canvas = document.createElement('canvas')
  canvas.width = w
  canvas.height = h
  const ctx = canvas.getContext('2d', { willReadFrequently: true })
  ctx.drawImage(img, 0, 0, w, h)
  return { canvas, ctx, width: w, height: h }
}

export function imageStats(ctx, w, h) {
  const data = ctx.getImageData(0, 0, w, h).data
  let sum = 0
  let sumSq = 0
  let edges = 0
  const step = 4
  for (let y = 1; y < h - 1; y += step) {
    for (let x = 1; x < w - 1; x += step) {
      const i = (y * w + x) * 4
      const lum = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2]
      sum += lum
      sumSq += lum * lum
      const right = (y * w + x + 1) * 4
      const down = ((y + 1) * w + x) * 4
      const lumR = 0.299 * data[right] + 0.587 * data[right + 1] + 0.114 * data[right + 2]
      const lumD = 0.299 * data[down] + 0.587 * data[down + 1] + 0.114 * data[down + 2]
      if (Math.abs(lum - lumR) + Math.abs(lum - lumD) > 40) edges++
    }
  }
  const samples = Math.floor((w / step) * (h / step))
  const mean = sum / samples
  return { mean, variance: sumSq / samples - mean * mean, edgeDensity: edges / samples }
}

export async function sha256Hex(blob) {
  const hash = await crypto.subtle.digest('SHA-256', await blob.arrayBuffer())
  return [...new Uint8Array(hash)].map((b) => b.toString(16).padStart(2, '0')).join('')
}