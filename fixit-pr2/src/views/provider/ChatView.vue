<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import { useAuthStore } from '../../stores/auth'
import * as api from '../../services/api'
import AppIcon from '../../components/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const bookingsStore = useBookingsStore()
const auth = useAuthStore()

const messages = ref([])
const draft = ref('')
const listEl = ref(null)

const booking = computed(() => bookingsStore.byId(route.params.id))

onMounted(async () => {
  await bookingsStore.load()
  messages.value = await api.getMessagesForJob(route.params.id)
  scrollToEnd()
})

function scrollToEnd() {
  nextTick(() => { if (listEl.value) listEl.value.scrollTop = listEl.value.scrollHeight })
}
function isMine(m) { return m.sender_id === auth.user.id }
function send() {
  if (!draft.value.trim()) return
  messages.value.push({
    id: Date.now(), job_id: Number(route.params.id),
    sender_id: auth.user.id, body: draft.value.trim(),
    sent_at: new Date().toISOString(),
  })
  draft.value = ''
  scrollToEnd()
}
function timeOf(iso) {
  return new Date(iso).toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' })
}
</script>

<template>
  <div class="d-flex flex-column" style="height:calc(100vh - 88px)">
    <!-- Header -->
    <div class="d-flex align-items-center gap-3 px-3 py-2" style="border-bottom:1px solid var(--fx-border)">
      <button class="btn btn-light rounded-circle" style="width:32px;height:32px;padding:0" @click="router.back()">
        <AppIcon name="back" :size="15" />
      </button>
      <div class="fx-avatar" style="width:40px;height:40px;background:var(--fx-blue-soft);color:var(--fx-blue)">
        {{ (booking?.customer?.name || '?').split(' ').map(w => w[0]).join('') }}
      </div>
      <div class="flex-grow-1">
        <div class="fw-bold" style="font-size:15px">{{ booking?.customer?.name || 'Chat' }}</div>
        <div style="font-size:12px;color:var(--fx-success);font-weight:500">Online · #{{ route.params.id }}</div>
      </div>
    </div>

    <!-- Messages -->
    <div ref="listEl" class="flex-grow-1 d-flex flex-column gap-2 px-3 py-3" style="overflow-y:auto">
      <div v-for="m in messages" :key="m.id" class="d-flex"
           :style="{ justifyContent: isMine(m) ? 'flex-end' : 'flex-start' }">
        <div style="max-width:72%">
          <div :style="{
            padding: '10px 13px', borderRadius: '16px', fontSize: '13px', lineHeight: 1.5,
            background: isMine(m) ? 'var(--fx-accent)' : 'var(--fx-border-soft)',
            color: isMine(m) ? '#fff' : 'var(--fx-text)',
            borderBottomRightRadius: isMine(m) ? '4px' : '16px',
            borderBottomLeftRadius: isMine(m) ? '16px' : '4px',
          }">{{ m.body }}</div>
          <div style="font-size:10px;color:var(--fx-muted-soft);margin-top:3px"
               :style="{ textAlign: isMine(m) ? 'right' : 'left' }">{{ timeOf(m.sent_at) }}</div>
        </div>
      </div>
    </div>

    <!-- Input -->
    <form class="d-flex gap-2 align-items-center px-3 py-2" style="border-top:1px solid var(--fx-border)" @submit.prevent="send">
      <input class="fx-input" style="border-radius:22px" v-model="draft" placeholder="Type a message…" />
      <button type="submit" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center"
              style="width:40px;height:40px;padding:0;flex-shrink:0">
        <AppIcon name="send" :size="18" />
      </button>
    </form>
  </div>
</template>
