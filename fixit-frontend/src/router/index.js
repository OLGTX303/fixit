import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/', redirect: '/login' },
  { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { public: true } },
  { path: '/register', name: 'register', component: () => import('../views/RegisterView.vue'), meta: { public: true } },

  // Customer
  { path: '/home', name: 'home', component: () => import('../views/customer/HomeView.vue'), meta: { role: 'customer' } },
  { path: '/search', name: 'search', component: () => import('../views/customer/SearchView.vue'), meta: { role: 'customer' } },
  { path: '/provider/:id', name: 'provider-profile', component: () => import('../views/customer/ProviderProfileView.vue'), meta: { role: 'customer' } },
  { path: '/book/:id', name: 'booking-form', component: () => import('../views/customer/BookingFormView.vue'), meta: { role: 'customer' } },
  { path: '/jobs', name: 'job-tracker', component: () => import('../views/customer/JobTrackerView.vue'), meta: { role: 'customer' } },
  { path: '/jobs/:id/review', name: 'rate-review', component: () => import('../views/customer/RateReviewView.vue'), meta: { role: 'customer' } },
  { path: '/payment', name: 'payment', component: () => import('../views/customer/PaymentView.vue'), meta: { role: 'customer' } },

  // Provider
  { path: '/pro/profile', name: 'pro-profile', component: () => import('../views/provider/ProfileSetupView.vue'), meta: { role: 'provider' } },
  { path: '/pro/kyc', name: 'pro-kyc', component: () => import('../views/provider/KycVerificationView.vue'), meta: { role: 'provider' } },
  { path: '/pro/requests', name: 'pro-requests', component: () => import('../views/provider/BookingRequestsView.vue'), meta: { role: 'provider' } },
  { path: '/pro/jobs/:id', name: 'pro-job', component: () => import('../views/provider/JobStatusView.vue'), meta: { role: 'provider' } },
  { path: '/pro/chat/:id', name: 'pro-chat', component: () => import('../views/provider/ChatView.vue'), meta: { role: 'provider' } },

  // Admin
  { path: '/admin/verify', name: 'admin-verify', component: () => import('../views/admin/VerificationView.vue'), meta: { role: 'admin' } },
  { path: '/admin/users', name: 'admin-users', component: () => import('../views/admin/UserManagementView.vue'), meta: { role: 'admin' } },
  { path: '/admin/bookings', name: 'admin-bookings', component: () => import('../views/admin/BookingReviewView.vue'), meta: { role: 'admin' } },
  { path: '/admin/harm-reviews', name: 'admin-harm', component: () => import('../views/admin/HarmReviewView.vue'), meta: { role: 'admin' } },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

// Role-aware guard: must be logged in for non-public routes, and the route's
// role (if any) must match the session role.
router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.public) return true
  if (!auth.isAuthenticated) return { name: 'login' }
  if (to.meta.role && to.meta.role !== auth.role) {
    const landing = { customer: 'home', provider: 'pro-requests', admin: 'admin-verify' }
    return { name: landing[auth.role] || 'login' }
  }
  return true
})

export default router
