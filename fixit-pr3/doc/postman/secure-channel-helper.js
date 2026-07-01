#!/usr/bin/env node
/**
 * Secure-channel helper for Postman.
 *
 * Postman's sandbox has no native X25519 (elliptic-curve Diffie-Hellman)
 * support, so pre-request scripts can't perform the app's per-interaction
 * encryption on their own. This script reproduces the exact algorithm from
 * fixit-frontend/src/services/secureTransport.js using Node's built-in
 * `crypto` module (which natively supports X25519, HKDF, and AES-256-GCM),
 * so you can compute real request headers/body and paste them into Postman
 * — or just let it make the live call directly and show you the decrypted
 * response.
 *
 * Usage:
 *   node secure-channel-helper.js --token <bearer_jwt> --method GET --path /bookings/1
 *   node secure-channel-helper.js --token <bearer_jwt> --method POST --path /bookings \
 *     --body '{"provider_id":1,"category_id":1,"scheduled_at":"2026-07-10 10:00:00","address":"123 Test St"}'
 *
 * Options:
 *   --base-url   API base (default: https://fixit.olgtx.com/api)
 *   --token      Bearer JWT (get one from POST /auth/login)
 *   --method     HTTP method (default: GET)
 *   --path       Path after the base URL, e.g. /bookings/1 (no query string)
 *   --body       JSON body as a string (default: {})
 *   --no-send    Only print the computed headers/body; don't call the API
 */

const crypto = require('crypto');

function parseArgs(argv) {
  const out = { baseUrl: 'https://fixit.olgtx.com/api', method: 'GET', body: '{}', send: true };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--base-url') out.baseUrl = argv[++i];
    else if (a === '--token') out.token = argv[++i];
    else if (a === '--method') out.method = argv[++i].toUpperCase();
    else if (a === '--path') out.path = argv[++i];
    else if (a === '--body') out.body = argv[++i];
    else if (a === '--no-send') out.send = false;
  }
  if (!out.token || !out.path) {
    console.error('Usage: node secure-channel-helper.js --token <jwt> --method GET --path /bookings/1 [--body \'{"...":...}\'] [--base-url ...] [--no-send]');
    process.exit(1);
  }
  return out;
}

// ── X25519 raw-key <-> Node KeyObject conversion ────────────────────────────
// Node's crypto module exports/imports X25519 keys as SPKI/PKCS8 DER, not raw
// 32-byte points. The DER wrapper for X25519 SPKI is a fixed 12-byte ASN.1
// header followed by the 32 raw key bytes — the browser's
// crypto.subtle.exportKey('raw', ...) / importKey('raw', ...) work with just
// those 32 bytes, so we convert on both ends.
const X25519_SPKI_PREFIX = Buffer.from('302a300506032b656e032100', 'hex');

function rawPublicKeyFromKeyObject(publicKey) {
  const der = publicKey.export({ type: 'spki', format: 'der' });
  return der.subarray(der.length - 32);
}

function keyObjectFromRawPublicKey(raw32) {
  const der = Buffer.concat([X25519_SPKI_PREFIX, raw32]);
  return crypto.createPublicKey({ key: der, format: 'der', type: 'spki' });
}

// ── Primitives mirroring secureTransport.js exactly ─────────────────────────
function hkdf(ikm, salt, info, length = 32) {
  return Buffer.from(crypto.hkdfSync('sha256', ikm, salt, Buffer.from(info, 'utf8'), length));
}

function hmacHex(macKey, msg) {
  return crypto.createHmac('sha256', macKey).update(msg, 'utf8').digest('hex');
}

function sha256hex(str) {
  return crypto.createHash('sha256').update(str, 'utf8').digest('hex');
}

// Length-prefixed canonical string — must match secureTransport.js's canonical().
function canonical(pairs) {
  return Object.keys(pairs).sort().map((k) => {
    const v = String(pairs[k]);
    return `${Buffer.byteLength(k, 'utf8')}|${k}|${Buffer.byteLength(v, 'utf8')}|${v}|`;
  }).join('');
}

// AES-256-GCM: output = iv(12) || ciphertext || tag(16), matching WebCrypto's
// combined-output convention (Node keeps ciphertext/tag separate natively).
function aesEncrypt(keyBytes, plaintext, aad) {
  const iv = crypto.randomBytes(12);
  const cipher = crypto.createCipheriv('aes-256-gcm', keyBytes, iv);
  cipher.setAAD(Buffer.from(aad, 'utf8'));
  const ct = Buffer.concat([cipher.update(plaintext, 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();
  return Buffer.concat([iv, ct, tag]);
}

function aesDecrypt(keyBytes, blob, aad) {
  const iv = blob.subarray(0, 12);
  const tag = blob.subarray(blob.length - 16);
  const ct = blob.subarray(12, blob.length - 16);
  const decipher = crypto.createDecipheriv('aes-256-gcm', keyBytes, iv);
  decipher.setAAD(Buffer.from(aad, 'utf8'));
  decipher.setAuthTag(tag);
  return Buffer.concat([decipher.update(ct), decipher.final()]).toString('utf8');
}

async function main() {
  const opt = parseArgs(process.argv.slice(2));
  const authHeaders = { Authorization: `Bearer ${opt.token}` };

  // 1) Handshake — establishes the X25519 session (same as ensureSession()).
  const { publicKey, privateKey } = crypto.generateKeyPairSync('x25519');
  const clientPubB64 = rawPublicKeyFromKeyObject(publicKey).toString('base64');

  const hsRes = await fetch(`${opt.baseUrl}/secure/handshake`, {
    method: 'POST',
    headers: { ...authHeaders, 'Content-Type': 'application/json' },
    body: JSON.stringify({ client_pub: clientPubB64 }),
  });
  const hs = await hsRes.json();
  if (!hsRes.ok) {
    console.error('Handshake failed:', hs);
    process.exit(1);
  }

  const serverPubKeyObj = keyObjectFromRawPublicKey(Buffer.from(hs.server_pub, 'base64'));
  const salt = Buffer.from(hs.salt, 'base64');
  const z = crypto.diffieHellman({ privateKey, publicKey: serverPubKeyObj });
  const master = hkdf(z, salt, 'fixit/v2/master');
  const mac = hkdf(master, salt, 'fixit/v2/mac');
  const sessionId = hs.session_id;

  console.log('=== Session established ===');
  console.log('session_id:', sessionId, ' ttl:', hs.ttl, 's');

  // 2) Per-request encryption + signature (same as secureRequest()).
  const counter = 1;
  const nonce = crypto.randomUUID();
  const ts = Date.now().toString();
  const fullPath = `/api${opt.path.split('?')[0]}`;
  const extra = `${opt.method} ${fullPath}`;

  const reqKey = hkdf(master, salt, `fixit/v2/request/${counter}/${nonce}`);
  const aad = `fixit/v2|request|${sessionId}|${counter}|${nonce}|${ts}|${extra}`;
  const blob = aesEncrypt(reqKey, opt.body, aad);
  const bodyB64 = blob.toString('base64');
  const sign = hmacHex(mac, canonical({
    session: sessionId, counter: String(counter), nonce, ts,
    method: opt.method, path: fullPath, body_hash: sha256hex(bodyB64),
  }));

  const noBody = opt.method === 'GET' || opt.method === 'HEAD';
  const headers = {
    ...authHeaders,
    'X-Sec-Session': sessionId, 'X-Sec-Counter': String(counter), 'X-Sec-Nonce': nonce,
    'X-Sec-Ts': ts, 'X-Sec-Sign': sign,
  };
  if (noBody) headers['X-Sec-Body'] = bodyB64;
  else headers['Content-Type'] = 'application/octet-stream';

  console.log('\n=== Paste into Postman ===');
  console.log(`${opt.method} ${opt.baseUrl}${opt.path}`);
  console.log('Headers:');
  for (const [k, v] of Object.entries(headers)) console.log(`  ${k}: ${v}`);
  if (!noBody) console.log('Body (raw text):', bodyB64);

  if (!opt.send) return;

  // 3) Optional: actually perform the call and decrypt the response, so you
  // can confirm the computed values above are correct before pasting them.
  console.log('\n=== Live call ===');
  const res = await fetch(`${opt.baseUrl}${opt.path}`, {
    method: opt.method, headers, body: noBody ? undefined : bodyB64,
  });
  const text = await res.text();
  console.log('status:', res.status);

  if (res.headers.get('x-sec-enc') === '1') {
    const respKey = hkdf(master, salt, `fixit/v2/response/${counter}/${nonce}`);
    const aadResp = `fixit/v2|response|${sessionId}|${counter}|${nonce}|${ts}|${extra}`;
    const plain = aesDecrypt(respKey, Buffer.from(text, 'base64'), aadResp);
    console.log('decrypted response:', plain);
  } else {
    console.log('response (not encrypted):', text);
  }
}

main().catch((e) => { console.error(e); process.exit(1); });
