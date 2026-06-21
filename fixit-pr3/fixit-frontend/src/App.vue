<script setup>
import { computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useProvidersStore } from './stores/providers'
import { useBookingsStore } from './stores/bookings'
import AppIcon from './components/AppIcon.vue'
import LegalFooter from './components/LegalFooter.vue'

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
    { icon: 'search',   msIcon: 'search',          label: 'Explore',  to: 'search' },
    { icon: 'calendar', msIcon: 'calendar_month',  label: 'Bookings', to: 'job-tracker' },
    { icon: 'chat',     msIcon: 'chat',            label: 'Messages', to: 'messages' },
  ],
  provider: [
    { icon: 'grid',      msIcon: 'dashboard',     label: 'Dashboard', to: 'pro-profile' },
    { icon: 'bell',      msIcon: 'notifications', label: 'Requests',  to: 'pro-requests' },
    { icon: 'briefcase', msIcon: 'work',          label: 'Jobs',      to: 'pro-job',   jobRoute: true },
    { icon: 'chat',      msIcon: 'chat',          label: 'Messages',  to: 'pro-chats' },
    { icon: 'user',      msIcon: 'person',        label: 'Profile',   to: 'account' },
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

async function go(item) {
  if (item.jobRoute) {
    const bookings  = useBookingsStore()
    const providers = useProvidersStore()
    await Promise.all([bookings.load(), providers.load()])
    const profile = providers.providers.find((p) => p.user_id === auth.user?.id)
    const job = profile
      ? bookings.forProvider(profile.id).find((b) => b.status !== 'reviewed')
      : null
    router.push(job ? { name: item.to, params: { id: job.id } } : { name: 'pro-requests' })
    return
  }
  router.push({ name: item.to })
}
const CHAT_CHILD_ROUTES = { messages: ['chat'], 'pro-chats': ['pro-chat'] }
function isActive(item) {
  if (route.name === item.to) return true
  const children = CHAT_CHILD_ROUTES[item.to]
  return children ? children.includes(route.name) : false
}
function logout() { auth.logout(); router.push({ name: 'login' }) }
</script>

<template>
  <div class="fx-shell">
    <!-- Animated gradient mesh — sits behind everything -->
    <div class="lg-mesh" aria-hidden="true"></div>

    <!-- Liquid cursor blob (spring-follows mouse, creates liquid merge effect near glass surfaces) -->
    <div id="lg-cursor-blob" aria-hidden="true"></div>

    <!-- Status-bar tint strip (Android edge-to-edge) -->
    <div class="fx-statusbar-bg" aria-hidden="true"></div>

    <template v-if="showShell">
      <div class="fx-layout">
        <!-- Glass side-nav (desktop ≥ 992px) -->
        <aside class="fx-sidenav">
          <div class="d-flex align-items-center gap-2 mb-4" style="padding:0 4px">
            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(180deg,#FF7D54,#FF6635);display:flex;align-items:center;justify-content:center;box-shadow:inset 0 1px 0 rgba(255,255,255,.4),0 4px 10px rgba(255,102,53,.28)">
              <span class="material-symbols-outlined" style="color:#fff;font-size:20px;font-variation-settings:'FILL' 1">build</span>
            </div>
            <span style="font-size:22px;font-weight:800;letter-spacing:-0.5px;color:var(--fx-text)">FixIt</span>
          </div>
          <button v-for="item in navItems" :key="item.label"
                  class="nav-item" :class="{ active: isActive(item) }" @click="go(item)">
            <span class="material-symbols-outlined" style="font-size:20px">{{ item.msIcon }}</span>
            <span>{{ item.label }}</span>
          </button>
          <button class="nav-item mt-auto" @click="logout">
            <span class="material-symbols-outlined" style="font-size:20px">logout</span>
            <span>Log out</span>
          </button>
          <div style="padding:0 4px;margin-top:8px;font-size:12px;color:var(--fx-muted)">
            {{ auth.user?.name }} · {{ auth.role }}
          </div>
        </aside>

        <div class="fx-content">
          <main class="fx-main">
            <router-view />
            <LegalFooter class="fx-app-legal" />
          </main>
        </div>
      </div>

      <!-- Floating glass dock — Teleported to body so it's never trapped inside
           any ancestor stacking context (e.g. the Google Maps z-index layer). -->
      <Teleport to="body">
        <nav class="fx-bottomnav">
          <button v-for="item in navItems" :key="item.label"
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
    </template>

    <main v-else class="fx-main" :style="{ paddingBottom: isLegalPage ? 0 : undefined }">
      <router-view />
      <LegalFooter v-if="!isLegalPage" class="fx-page" style="padding-top:0" />
    </main>
  </div>
</template>
