<script setup>
import { ref, computed } from 'vue'
import { secureDebug } from '../services/secureTransport'

// Floating dev capsule: shows the per-interaction encryption payloads for the
// most recent sensitive request — plaintext↔ciphertext, both directions.
const open = ref(false)
const idx = ref(0)
const events = computed(() => secureDebug.events)
const cur = computed(() => events.value[idx.value] || null)
const trunc = (s, n = 600) => (s && s.length > n ? s.slice(0, n) + '… (' + s.length + ' chars)' : (s || ''))
</script>

<template>
  <Teleport to="body">
    <div class="dbg" :class="{ open }">
      <button class="dbg-toggle" @click="open = !open" :title="'Encryption debug'">
        <span class="material-symbols-outlined" style="font-size:18px">{{ open ? 'close' : 'lock' }}</span>
        <span v-if="!open" class="dbg-count">{{ events.length }}</span>
      </button>

      <div v-if="open" class="dbg-panel">
        <div class="dbg-head">
          <span>Per-interaction encryption</span>
          <span class="dbg-meta">{{ events.length }} captured</span>
        </div>

        <div v-if="!cur" class="dbg-empty">
          No encrypted request yet. Trigger a sensitive action (wallet top-up, booking, payment, KYC…).
        </div>

        <template v-else>
          <div class="dbg-nav">
            <button :disabled="idx >= events.length - 1" @click="idx++">‹ older</button>
            <span class="dbg-route">{{ cur.method }} {{ cur.path }} <em :class="cur.encrypted ? 'ok' : 'warn'">{{ cur.encrypted ? 'resp 🔒' : 'resp plain' }}</em></span>
            <button :disabled="idx <= 0" @click="idx--">newer ›</button>
          </div>

          <div class="dbg-sec">
            <div class="dbg-lbl up">↑ encrypt — before (plaintext payload)</div>
            <pre class="plain">{{ trunc(cur.encBefore) }}</pre>
            <div class="dbg-lbl up">↑ encrypt — after (AES-256-GCM ciphertext, base64)</div>
            <pre class="cipher">{{ trunc(cur.encAfter) }}</pre>
          </div>
          <div class="dbg-sec">
            <div class="dbg-lbl down">↓ decrypt — before (response ciphertext)</div>
            <pre class="cipher">{{ trunc(cur.decBefore) }}</pre>
            <div class="dbg-lbl down">↓ decrypt — after (plaintext response)</div>
            <pre class="plain">{{ trunc(cur.decAfter) }}</pre>
          </div>
        </template>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.dbg { position: fixed; left: 14px; bottom: 14px; z-index: 4500; }
.dbg-toggle {
  display: flex; align-items: center; gap: 4px;
  height: 40px; padding: 0 12px; border-radius: 999px; cursor: pointer;
  border: none; color: #fff; font-weight: 700; font-size: 12px;
  background: linear-gradient(135deg, #6d28d9, #4c1d95);
  box-shadow: 0 6px 18px rgba(76, 29, 149, 0.4);
}
.dbg-count { background: rgba(255,255,255,0.25); border-radius: 999px; padding: 0 6px; font-size: 11px; }
.dbg-panel {
  position: absolute; bottom: 48px; left: 0;
  width: min(380px, calc(100vw - 28px));
  max-height: min(70vh, 560px); overflow-y: auto;
  background: #14121c; color: #e7e3f3;
  border: 1px solid #2c2640; border-radius: 14px; padding: 12px;
  box-shadow: 0 18px 50px rgba(0,0,0,0.5);
}
.dbg-head { display: flex; justify-content: space-between; font-size: 13px; font-weight: 700; margin-bottom: 8px; }
.dbg-meta { color: #8b80b3; font-weight: 500; }
.dbg-empty { font-size: 12px; color: #9a90c0; line-height: 1.5; }
.dbg-nav { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 11px; }
.dbg-nav button { background: #251f38; color: #cfc6ec; border: none; border-radius: 8px; padding: 4px 8px; cursor: pointer; }
.dbg-nav button:disabled { opacity: 0.35; cursor: default; }
.dbg-route { color: #cfc6ec; font-weight: 600; flex: 1; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dbg-route em { font-style: normal; font-size: 10px; padding: 1px 5px; border-radius: 6px; margin-left: 4px; }
.dbg-route em.ok { background: rgba(34,197,94,0.2); color: #7cf0a8; }
.dbg-route em.warn { background: rgba(245,158,11,0.2); color: #f5c97a; }
.dbg-sec { border-top: 1px solid #2c2640; padding-top: 8px; margin-top: 8px; }
.dbg-lbl { font-size: 10px; font-weight: 700; letter-spacing: 0.03em; margin: 6px 0 3px; }
.dbg-lbl.up { color: #7cc4f0; }
.dbg-lbl.down { color: #f0a07c; }
.dbg pre {
  margin: 0; padding: 7px 9px; border-radius: 8px; font-size: 11px; line-height: 1.45;
  white-space: pre-wrap; word-break: break-all; font-family: var(--fx-mono, ui-monospace, monospace);
}
.dbg pre.plain { background: #10241a; color: #aef0c8; }
.dbg pre.cipher { background: #221016; color: #f0b9c2; }
</style>
