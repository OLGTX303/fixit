<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useBookingsStore } from '../../stores/bookings'
import * as api from '../../services/api'
import AppIcon from '../../components/AppIcon.vue'

const bookingsStore = useBookingsStore()
const selectedId = ref(null)
const messages = ref([])
const loading = ref(false)
const error = ref('')
const listEl = ref(null)

onMounted(async () => {
  await bookingsStore.load()
  if (bookingsStore.bookings.length) selectId(bookingsStore.bookings[0].id)
})

const selected = computed(() => bookingsStore.byId(selectedId.value))

function senderName(m) {
  const b = selected.value
  if (!b) return 'User'
  return m.sender_id === b.customer_id ? (b.customer?.name || 'Customer') : (b.provider?.name || 'Provider')
}

async function selectId(id) {
  selectedId.value = id
  loading.value = true
  error.value = ''
  messages.value = []
  try {
    messages.value = await api.getMessagesForJob(id)
    nextTick(() => { if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight })
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function bodyOf(m) {
  if (m.is_encrypted) return null
  return m.body || '[empty]'
}
function timeOf(iso) {
  return new Date(iso).toLocaleString('en', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}
</script>

<template>
  <div class="fx-page">
    <h1 class="fw-bold mb-1" style="font-size:20px">Chat monitoring</h1>
    <div class="mb-3" style="font-size:13px;color:var(--fx-muted)">
      Conversations are end-to-end encrypted — admins see metadata and safety flags, not private message text.
    </div>

    <!-- Job selector -->
    <div class="d-flex gap-2 mb-3" style="overflow-x:auto">
      <span v-for="b in bookingsStore.bookings" :key="b.id" class="fx-chip sm"
            :class="{ active: b.id === selectedId }" @click="selectId(b.id)">
        #{{ b.id }} · {{ b.customer?.name?.split(' ')[0] }}↔{{ b.provider?.name?.split(' ')[0] }}
      </span>
    </div>

    <div v-if="error" class="alert alert-danger py-2 mb-2" style="font-size:13px">{{ error }}</div>
    <div v-if="loading" class="text-center py-4" style="color:var(--fx-muted)">Loading…</div>

    <div v-else-if="selected" ref="listEl" class="d-flex flex-column gap-2" style="max-height:60vh;overflow-y:auto">
      <div v-for="m in messages" :key="m.id" class="fx-card" style="padding:10px 12px">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="fw-semibold" style="font-size:12px">{{ senderName(m) }}</span>
          <span style="font-size:10px;color:var(--fx-muted-soft)">{{ timeOf(m.sent_at) }}</span>
        </div>
        <div v-if="bodyOf(m)" style="font-size:13px">{{ bodyOf(m) }}</div>
        <div v-else style="font-size:13px;color:var(--fx-muted);font-style:italic">🔒 Encrypted message</div>
        <div v-if="m.harm_status && m.harm_status !== 'clear'" class="mt-1">
          <span class="fx-badge" style="background:var(--fx-warn-soft);color:var(--fx-warn);font-size:10px">
            <AppIcon name="shield" :size="11" /> <span class="ms-1">{{ m.harm_status }}</span>
          </span>
        </div>
      </div>
      <div v-if="!messages.length" class="text-center py-4" style="color:var(--fx-muted)">No messages in this job yet.</div>
    </div>

    <div v-else class="text-center py-4" style="color:var(--fx-muted)">No bookings to monitor.</div>
  </div>
</template>
