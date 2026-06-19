<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'
import AppIcon from './components/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

// Nav definitions per role: each item maps a tab to a route name.
const NAVS = {
  customer: [
    { icon: 'home', label: 'Home', to: 'home' },
    { icon: 'search', label: 'Explore', to: 'search' },
    { icon: 'calendar', label: 'Bookings', to: 'job-tracker' },
    { icon: 'user', label: 'Profile', to: 'home' },
  ],
  provider: [
    { icon: 'grid', label: 'Profile', to: 'pro-profile' },
    { icon: 'shield', label: 'KYC', to: 'pro-kyc' },
    { icon: 'bell', label: 'Requests', to: 'pro-requests' },
    { icon: 'briefcase', label: 'Jobs', to: 'pro-job', param: 2847 },
    { icon: 'chat', label: 'Chat', to: 'pro-chat', param: 2847 },
  ],
  admin: [
    { icon: 'shield', label: 'Verify', to: 'admin-verify' },
    { icon: 'user', label: 'Users', to: 'admin-users' },
    { icon: 'calendar', label: 'Bookings', to: 'admin-bookings' },
    { icon: 'shield', label: 'Safety', to: 'admin-harm' },
  ],
}

const showShell = computed(() => auth.isAuthenticated && !route.meta.public)
const navItems = computed(() => NAVS[auth.role] || [])

function go(item) {
  router.push(item.param ? { name: item.to, params: { id: item.param } } : { name: item.to })
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
    <!-- Authenticated layout: side nav on desktop, bottom nav on mobile -->
    <template v-if="showShell">
      <div class="fx-layout">
        <!-- Desktop side nav -->
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
          </main>
        </div>
      </div>

      <!-- Mobile bottom nav -->
      <nav class="fx-bottomnav">
        <button v-for="item in navItems" :key="item.label"
                class="nav-item" :class="{ active: isActive(item) }" @click="go(item)">
          <AppIcon :name="item.icon" :size="22" />
          <span>{{ item.label }}</span>
        </button>
      </nav>
    </template>

    <!-- Public (login/register) -->
    <main v-else class="fx-main" style="padding-bottom:0">
      <router-view />
    </main>
  </div>
</template>
