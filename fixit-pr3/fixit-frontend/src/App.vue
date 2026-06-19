<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useProvidersStore } from './stores/providers'
import { useBookingsStore } from './stores/bookings'
import AppIcon from './components/AppIcon.vue'
import LegalFooter from './components/LegalFooter.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const NAVS = {
  customer: [
    { icon: 'home', label: 'Home', to: 'home' },
    { icon: 'search', label: 'Explore', to: 'search' },
    { icon: 'calendar', label: 'Bookings', to: 'job-tracker' },
    { icon: 'shield', label: 'Payment', to: 'payment' },
  ],
  provider: [
    { icon: 'grid', label: 'Profile', to: 'pro-profile' },
    { icon: 'shield', label: 'KYC', to: 'pro-kyc' },
    { icon: 'bell', label: 'Requests', to: 'pro-requests' },
    { icon: 'briefcase', label: 'Jobs', to: 'pro-job', jobRoute: true },
    { icon: 'chat', label: 'Chat', to: 'pro-chat', jobRoute: true },
  ],
  admin: [
    { icon: 'shield', label: 'Verify', to: 'admin-verify' },
    { icon: 'user', label: 'Users', to: 'admin-users' },
    { icon: 'calendar', label: 'Bookings', to: 'admin-bookings' },
    { icon: 'shield', label: 'Safety', to: 'admin-harm' },
  ],
}

const isLegalPage = computed(() => route.name === 'legal-terms' || route.name === 'legal-privacy')
const showShell = computed(() => auth.isAuthenticated && !route.meta.public && !isLegalPage.value)
const navItems = computed(() => NAVS[auth.role] || [])

async function go(item) {
  if (item.jobRoute) {
    const bookings = useBookingsStore()
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
function isActive(item) {
  return route.name === item.to
}
function logout() {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="fx-shell">
    <template v-if="showShell">
      <div class="fx-layout">
        <aside class="fx-sidenav">
          <div class="d-flex align-items-center gap-2 mb-4 px-2">
            <div class="d-flex align-items-center justify-content-center"
                 style="width:38px;height:38px;border-radius:11px;background:var(--fx-accent);color:#fff">
              <AppIcon name="tool" :size="20" />
            </div>
            <span style="font-size:22px;font-weight:800;letter-spacing:-0.5px">FixIt</span>
          </div>
          <button v-for="item in navItems" :key="item.label"
                  class="nav-item" :class="{ active: isActive(item) }" @click="go(item)">
            <AppIcon :name="item.icon" :size="20" />
            <span>{{ item.label }}</span>
          </button>
          <button class="nav-item mt-auto" @click="logout">
            <AppIcon name="logout" :size="20" />
            <span>Log out</span>
          </button>
          <div class="px-2 mt-2" style="font-size:12px;color:var(--fx-muted)">
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

      <nav class="fx-bottomnav">
        <button v-for="item in navItems" :key="item.label"
                class="nav-item" :class="{ active: isActive(item) }" @click="go(item)">
          <AppIcon :name="item.icon" :size="22" />
          <span>{{ item.label }}</span>
        </button>
      </nav>
    </template>

    <main v-else class="fx-main" :style="{ paddingBottom: isLegalPage ? 0 : undefined }">
      <router-view />
      <LegalFooter v-if="!isLegalPage" class="fx-page" style="padding-top:0" />
    </main>
  </div>
</template>