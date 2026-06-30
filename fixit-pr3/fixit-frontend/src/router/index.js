import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { isDesktop } from '../composables/useViewport.js'
import { loginSheetOpen } from '../composables/useLoginPrompt.js'

const routes = [
  { path: '/', redirect: '/home' },
  { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { public: true } },
  { path: '/register', name: 'register', component: () => import('../views/RegisterView.vue'), meta: { public: true } },
  { path: '/legal/terms', name: 'legal-terms', component: () => import('../views/legal/LegalDocumentView.vue'), meta: { public: true, legalKey: 'terms' } },
  { path: '/legal/privacy', name: 'legal-privacy', component: () => import('../views/legal/LegalDocumentView.vue'), meta: { public: true, legalKey: 'privacy' } },

  // Account / profile — available to every authenticated role
  { path: '/account', name: 'account', component: () => import('../views/AccountView.vue') },
  { path: '/account/settings', name: 'account-settings', component: () => import('../views/SettingsView.vue') },
  { path: '/account/personal', name: 'account-personal', component: () => import('../views/PersonalInfoView.vue') },
  { path: '/account/privacy', name: 'account-privacy', component: () => import('../views/PrivacySettingsView.vue') },
  { path: '/account/email', name: 'account-email', component: () => import('../views/EmailEditView.vue') },
  { path: '/account/billing', name: 'account-billing', component: () => import('../views/BillingView.vue') },

  // Order history detail — shared by customer, provider, and admin (backend authorizes).
  { path: '/orders/:id', name: 'order-detail', component: () => import('../views/OrderDetailView.vue') },

  // Customer
  { path: '/home', name: 'home', component: () => import('../views/customer/HomeView.vue'), meta: { role: 'customer' } },
  { path: '/search', name: 'search', component: () => import('../views/customer/SearchView.vue'), meta: { role: 'customer' } },
  { path: '/provider/:id', name: 'provider-profile', component: () => import('../views/customer/ProviderProfileView.vue') },
  { path: '/book/:id', name: 'booking-form', component: () => import('../views/customer/BookingFormView.vue'), meta: { role: 'customer' } },
  { path: '/jobs', name: 'job-tracker', component: () => import('../views/customer/JobTrackerView.vue'), meta: { role: 'customer' } },
  { path: '/favorites', name: 'favorites', component: () => import('../views/customer/FavouritesView.vue'), meta: { role: 'customer' } },
  { path: '/history', name: 'browsing-history', component: () => import('../views/customer/BrowsingHistoryView.vue'), meta: { role: 'customer' } },
  { path: '/coupons', name: 'coupons', component: () => import('../views/customer/MyCouponsView.vue'), meta: { role: 'customer' } },
  { path: '/cart', name: 'cart', component: () => import('../views/customer/CartView.vue'), meta: { role: 'customer' } },
  { path: '/jobs/:id', name: 'job-detail', component: () => import('../views/customer/JobDetailView.vue'), meta: { role: 'customer' } },
  { path: '/jobs/:id/review', name: 'rate-review', component: () => import('../views/customer/RateReviewView.vue'), meta: { role: 'customer' } },
  { path: '/jobs/:id/chat', name: 'chat', component: () => import('../views/provider/ChatView.vue'), meta: { role: 'customer' } },
  { path: '/messages', name: 'messages', component: () => import('../views/customer/MessagesView.vue'), meta: { role: 'customer' } },
  { path: '/payment', name: 'payment', component: () => import('../views/customer/PaymentView.vue'), meta: { role: 'customer' } },
  { path: '/wallet', name: 'wallet', component: () => import('../views/WalletView.vue') },

  // Provider
  { path: '/pro/profile', name: 'pro-profile', component: () => import('../views/provider/ProviderHubView.vue'), meta: { role: 'provider' } },
  { path: '/pro/services', name: 'pro-services', component: () => import('../views/provider/ServiceManagementView.vue'), meta: { role: 'provider' } },
  { path: '/pro/profile/edit', name: 'pro-profile-edit', component: () => import('../views/provider/ProfileSetupView.vue'), meta: { role: 'provider' } },
  { path: '/pro/kyc', name: 'pro-kyc', component: () => import('../views/provider/KycVerificationView.vue'), meta: { role: 'provider' } },
  { path: '/pro/requests', name: 'pro-requests', component: () => import('../views/provider/BookingRequestsView.vue'), meta: { role: 'provider' } },
  { path: '/pro/jobs/:id', name: 'pro-job', component: () => import('../views/provider/JobStatusView.vue'), meta: { role: 'provider' } },
  { path: '/pro/chat/:id', name: 'pro-chat', component: () => import('../views/provider/ChatView.vue'), meta: { role: 'provider' } },
  { path: '/pro/chats', name: 'pro-chats', component: () => import('../views/provider/ConversationsView.vue'), meta: { role: 'provider' } },

  // Admin
  { path: '/admin/verify', name: 'admin-verify', component: () => import('../views/admin/VerificationView.vue'), meta: { role: 'admin' } },
  { path: '/admin/users', name: 'admin-users', component: () => import('../views/admin/UserManagementView.vue'), meta: { role: 'admin' } },
  { path: '/admin/bookings', name: 'admin-bookings', component: () => import('../views/admin/BookingReviewView.vue'), meta: { role: 'admin' } },
  { path: '/admin/harm-reviews', name: 'admin-harm', component: () => import('../views/admin/HarmReviewView.vue'), meta: { role: 'admin' } },
  { path: '/admin/coupons', name: 'admin-coupons', component: () => import('../views/admin/AdminCouponsView.vue'), meta: { role: 'admin' } },
  { path: '/admin/chats', name: 'admin-chats', component: () => import('../views/admin/AdminChatView.vue'), meta: { role: 'admin' } },
  { path: '/admin/chat/:id', name: 'admin-chat', component: () => import('../views/provider/ChatView.vue'), meta: { role: 'admin' } },
  { path: '/admin/kyc-debug', name: 'admin-kyc-debug', component: () => import('../views/admin/KycDebugView.vue'), meta: { role: 'admin' } },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

// Guests may browse home + their (empty) account hub; everything else is gated.
// Desktop opens the login bottom-sheet over home; mobile goes to the /login page.
const GUEST_OK = new Set(['home', 'account'])

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (to.meta.public) return true
  if (!auth.isAuthenticated) {
    if (GUEST_OK.has(to.name)) return true
    if (isDesktop.value) { loginSheetOpen.value = true; return { name: 'home' } }
    return { name: 'login' }
  }
  if (to.meta.role && to.meta.role !== auth.role) {
    const landing = { customer: 'home', provider: 'pro-requests', admin: 'admin-verify' }
    return { name: landing[auth.role] || 'login' }
  }
  return true
})

export default router
