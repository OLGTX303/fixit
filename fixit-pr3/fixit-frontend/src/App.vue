<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useProvidersStore } from './stores/providers'
import { useBookingsStore } from './stores/bookings'
import AppIcon from './components/AppIcon.vue'
import FxAuroraBg from './components/FxAuroraBg.vue'
import LegalFooter from './components/LegalFooter.vue'
// Single viewport condition for ALL pages (self-syncs body.fx-desktop/fx-mobile).
	import { isDesktop } from './composables/useViewport.js'

// ── Liquid cursor blob (mirrors the studio's mouse-spring follower) ──────
let blobEl = null
let raf = null
let bx = window.innerWidth / 2, by = window.innerHeight / 2  // blob position
let tx = bx, ty = by                                          // target (mouse)
const SPRING = 0.10

function onMouseMove(e) { tx = e.clientX; ty = e.clientY }

function tick() {
  bx += (tx - bx) * SPRING
  by += (ty - by) * SPRING
  if (blobEl) blobEl.style.transform = `translate(${bx}px,${by}px) translate(-50%,-50%)`
  raf = requestAnimationFrame(tick)
}

onMounted(() => {
  blobEl = document.getElementById('lg-cursor-blob')
  window.addEventListener('mousemove', onMouseMove, { passive: true })
  raf = requestAnimationFrame(tick)
})
onUnmounted(() => {
  window.removeEventListener('mousemove', onMouseMove)
  cancelAnimationFrame(raf)
})

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const NAVS = {
  customer: [
    { icon: 'home',     msIcon: 'home',           label: 'Home',     to: 'home' },
    { icon: 'shopping_cart', msIcon: 'shopping_cart', label: 'Cart', to: 'cart' },
    { icon: 'chat',     msIcon: 'chat',            label: 'Messages', to: 'messages' },
    { icon: 'user',     msIcon: 'person',          label: 'My',       to: 'account' },
  ],
  provider: [
    { icon: 'bell',   msIcon: 'notifications',          label: 'Requests', to: 'pro-requests' },
    { icon: 'build',  msIcon: 'build',                  label: 'Services', to: 'pro-services' },
    { icon: 'chat',   msIcon: 'chat',                   label: 'Messages', to: 'pro-chats' },
    { icon: 'user',   msIcon: 'person',                 label: 'Profile',  to: 'pro-profile' },
  ],
  admin: [
    { icon: 'shield',   msIcon: 'verified_user',  label: 'Verify',   to: 'admin-verify' },
    { icon: 'grid',     msIcon: 'group',          label: 'Users',    to: 'admin-users' },
    { icon: 'calendar', msIcon: 'calendar_month', label: 'Bookings', to: 'admin-bookings' },
    { icon: 'chat',     msIcon: 'forum',          label: 'Chats',    to: 'admin-chats' },
    { icon: 'user',     msIcon: 'person',         label: 'Profile',  to: 'account' },
  ],
}

	const isLegalPage = computed(() => route.name === 'legal-terms' || route.name === 'legal-privacy')
	const showShell   = computed(() => auth.isAuthenticated && !route.meta.public && !isLegalPage.value)
	const navItems    = computed(() => NAVS[auth.role] || [])
	const SPLIT_ROUTES = ['messages', 'cart', 'pro-chats', 'admin-chats']
	const isSplitRoute = computed(() => SPLIT_ROUTES.includes(route.name))
	const mainFull = computed(() => isDesktop.value && isSplitRoute.value)

async function go(item) {
  if (item.jobRoute) {
    const bookings = useBookingsStore()
    await bookings.load()
    let providerId = null
    if (auth.role === 'provider') {
      const { getMyProviderProfile } = await import('./services/api')
      const profile = await getMyProviderProfile().catch(() => null)
      providerId = profile?.id ?? null
    }
    const job = providerId
      ? bookings.forProvider(providerId).find((b) => b.status !== 'reviewed')
      : null
    router.push(job ? { name: item.to, params: { id: job.id } } : { name: 'pro-requests' })
    return
  }
  router.push({ name: item.to })
}
const CHAT_CHILD_ROUTES = { messages: ['chat'], 'pro-chats': ['pro-chat'], 'admin-chats': ['admin-chat'] }
function isActive(item) {
  if (route.name === item.to) return true
  const children = CHAT_CHILD_ROUTES[item.to]
  return children ? children.includes(route.name) : false
}
</script>

<template>
  <div class="fx-shell">
    <!-- Flowing aurora (vue-bits) — brand orange, replaces static mesh -->
    <FxAuroraBg />

    <!-- Liquid cursor blob (spring-follows mouse, creates liquid merge effect near glass surfaces) -->
    <div id="lg-cursor-blob" aria-hidden="true"></div>

    <!-- Status-bar tint strip (Android edge-to-edge) -->
    <div class="fx-statusbar-bg" aria-hidden="true"></div>

    <template v-if="showShell">
	      <main class="fx-main" :class="{ 'fx-main--full': mainFull }">
	        <router-view />
	        <LegalFooter v-if="!mainFull" class="fx-app-legal" />
	      </main>

      <!-- Mobile: floating liquid-glass bottom dock -->
      <Teleport to="body">
        <nav class="fx-bottomnav" aria-label="Main navigation">
          <button v-for="item in navItems" :key="'dock-' + item.label"
                  class="nav-item" :class="{ active: isActive(item) }" @click="go(item)">
            <div class="nav-icon-wrap">
              <span class="material-symbols-outlined"
                    :style="{ fontSize: '22px', fontVariationSettings: isActive(item) ? `'FILL' 1` : `'FILL' 0` }">
                {{ item.msIcon }}
              </span>
            </div>
            <span>{{ item.label }}</span>
          </button>
        </nav>
      </Teleport>

      <!-- Desktop: right vertical liquid-glass rail -->
      <Teleport to="body">
        <nav class="fx-rightnav" aria-label="Main navigation">
          <button v-for="item in navItems" :key="'rail-' + item.label"
                  class="nav-item" :class="{ active: isActive(item) }" @click="go(item)"
                  :title="item.label">
            <div class="nav-icon-wrap">
              <span class="material-symbols-outlined"
                    :style="{ fontSize: '22px', fontVariationSettings: isActive(item) ? `'FILL' 1` : `'FILL' 0` }">
                {{ item.msIcon }}
              </span>
            </div>
            <span class="nav-label">{{ item.label }}</span>
          </button>
        </nav>
      </Teleport>
    </template>

    <main v-else class="fx-main" :style="{ paddingBottom: isLegalPage ? 0 : undefined }">
      <router-view />
      <LegalFooter v-if="!isLegalPage" class="fx-page" style="padding-top:0" />
    </main>
  </div>
</template>
