<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useProvidersStore } from '../../stores/providers'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/AppIcon.vue'

const router = useRouter()
const bookingsStore = useBookingsStore()
const providersStore = useProvidersStore()
const auth = useAuthStore()

const tab = ref('new')

// The logged-in provider's ProviderProfile id.
const myProviderId = computed(() =>
  providersStore.providers.find(p => p.user_id === auth.user?.id)?.id)

onMounted(async () => {
  await Promise.all([bookingsStore.load(), providersStore.load()])
})

const all = computed(() => bookingsStore.forProvider(myProviderId.value))
const shown = computed(() => {
  if (tab.value === 'new') return all.value.filter(b => b.status === 'requested')
  if (tab.value === 'upcoming') return all.value.filter(b => ['accepted', 'in_progress'].includes(b.status))
  return all.value.filter(b => ['completed', 'reviewed'].includes(b.status))
})
const newCount = computed(() => all.value.filter(b => b.status === 'requested').length)

async function accept(b) {
  await bookingsStore.advanceStatus(b.id, 'accepted')
  router.push({ name: 'pro-job', params: { id: b.id } })
}
function decline(b) {
  bookingsStore.advanceStatus(b.id, 'requested') // stays unlisted in PR2 mock
}
</script>

<template>
  <div class="fx-page">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="fw-bold m-0" style="font-size:20px">Requests</h1>
      <span class="fx-badge text-accent bg-accent-soft">{{ newCount }} new</span>
    </div>

    <div class="d-flex mb-3">
      <div v-for="t in [['new','New'],['upcoming','Upcoming'],['past','Past']]" :key="t[0]"
           role="button" class="flex-fill text-center"
           style="padding:9px 0;font-size:13px;border-bottom:2px solid"
           :style="{ fontWeight: tab === t[0] ? 700 : 500, color: tab === t[0] ? 'var(--fx-accent)' : 'var(--fx-muted)', borderColor: tab === t[0] ? 'var(--fx-accent)' : 'var(--fx-border)' }"
           @click="tab = t[0]">{{ t[1] }}</div>
    </div>

    <div class="d-flex flex-column gap-3">
      <div v-for="b in shown" :key="b.id" class="fx-card" style="padding:0;overflow:hidden">
        <div class="d-flex justify-content-between align-items-start p-3 pb-2">
          <div class="d-flex gap-2">
            <div class="fx-avatar" style="width:40px;height:40px;background:var(--fx-border-soft);color:var(--fx-muted)">
              {{ (b.customer?.name || '?').split(' ').map(w => w[0]).join('') }}
            </div>
            <div>
              <div class="fw-semibold" style="font-size:14px">{{ b.customer?.name || 'Customer' }}</div>
              <div style="font-size:12px;color:var(--fx-muted)">{{ b.category?.name }}</div>
            </div>
          </div>
          <div class="fw-bold text-accent" style="font-size:16px">${{ b.total }}</div>
        </div>
        <div class="d-flex gap-3 px-3 pb-3" style="font-size:12px;color:var(--fx-muted)">
          <span class="d-flex align-items-center gap-1"><AppIcon name="calendar" :size="14" />{{ new Date(b.scheduled_at).toLocaleString('en', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) }}</span>
          <span class="d-flex align-items-center gap-1"><AppIcon name="location" :size="14" />{{ b.address }}</span>
        </div>
        <div v-if="b.status === 'requested'" class="d-flex gap-2 px-3 pb-3">
          <button class="btn btn-outline-danger flex-fill" style="border-radius:10px" @click="decline(b)">Decline</button>
          <button class="btn btn-primary" style="flex:2;border-radius:10px" @click="accept(b)">Accept Request</button>
        </div>
        <div v-else-if="['accepted','in_progress'].includes(b.status)" class="px-3 pb-3">
          <button class="btn btn-primary w-100" style="border-radius:10px" @click="router.push({ name: 'pro-job', params: { id: b.id } })">
            Open Job
          </button>
        </div>
      </div>

      <div v-if="!shown.length" class="text-center py-4" style="color:var(--fx-muted)">Nothing here.</div>
    </div>
  </div>
</template>
