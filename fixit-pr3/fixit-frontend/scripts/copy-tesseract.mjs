import { cpSync, mkdirSync, existsSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import https from 'node:https'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const dest = join(root, 'public', 'tesseract')
const coreSrc = join(root, 'node_modules', 'tesseract.js-core')
const workerSrc = join(root, 'node_modules', 'tesseract.js', 'dist', 'worker.min.js')

if (!existsSync(workerSrc)) {
  console.warn('tesseract.js not installed — run npm install first')
  process.exit(0)
}

mkdirSync(join(dest, 'core'), { recursive: true })
mkdirSync(join(dest, 'lang-data'), { recursive: true })

cpSync(workerSrc, join(dest, 'worker.min.js'))

const coreFiles = [
  'tesseract-core.wasm.js',
  'tesseract-core-simd.wasm.js',
  'tesseract-core-lstm.wasm.js',
  'tesseract-core-simd-lstm.wasm.js',
  'tesseract-core.wasm',
  'tesseract-core-simd.wasm',
  'tesseract-core-lstm.wasm',
  'tesseract-core-simd-lstm.wasm',
]
for (const f of coreFiles) {
  const src = join(coreSrc, f)
  if (existsSync(src)) cpSync(src, join(dest, 'core', f))
}

const langDest = join(dest, 'lang-data', 'eng.traineddata.gz')
if (!existsSync(langDest)) {
  await new Promise((resolve, reject) => {
    https.get('https://tessdata.projectnaptha.com/4.0.0/eng.traineddata.gz', (res) => {
      if (res.statusCode !== 200) {
        reject(new Error(`lang download HTTP ${res.statusCode}`))
        return
      }
      const chunks = []
      res.on('data', (c) => chunks.push(c))
      res.on('end', () => {
        writeFileSync(langDest, Buffer.concat(chunks))
        resolve()
      })
    }).on('error', reject)
  })
}

console.log('Copied tesseract assets to public/tesseract/')