<script setup>
import { ref, onMounted } from 'vue'
import * as api from '../../services/api'
import AppIcon from '../../components/AppIcon.vue'

const reviews = ref([])
const loading = ref(true)
const notes = ref({})

onMounted(async () => {
  reviews.value = await api.getHarmReviews()
  loading.value = false
})

async function resolve(id, status) {
  await api.reviewHarmMessage(id, { status, admin_notes: notes.value[id] || '' })
  reviews.value = reviews.value.filter(r => r.id !== id)
}

function cats(r) {
  return (r.harm_categories || []).join(', ') || '—'
}
</script>

<template>
  <div class="fx-page">
    <h1 class="fw-bold mb-1" style="font-size:20px">Harm message review</h1>
    <div class="mb-3" style="font-size:13px;color:var(--fx-muted)">
      Flagged E2E messages (metadata only — content is encrypted on server)
    </div>

    <div v-if="loading" class="text-muted">Loading…</div>
    <div v-else-if="!reviews.length" class="text-muted" style="font-size:14px">No pending reviews.</div>

    <div v-for="r in reviews" :key="r.id" class="mb-3 p-3" style="border:1px solid var(--fx-border);border-radius:14px">
      <div class="d-flex justify-content-between mb-2">
        <span class="fw-semibold">Job #{{ r.job_id }}</span>
        <span class="fx-badge" style="background:var(--fx-warn-soft);color:var(--fx-warn)">{{ r.harm_status }}</span>
      </div>
      <div style="font-size:13px;color:var(--fx-muted)">
        Sender: {{ r.sender_name }} · Categories: {{ cats(r) }}
      </div>
      <div style="font-size:11px;color:var(--fx-muted-soft)" class="mt-1">
        Hash: {{ r.content_hash?.slice(0, 16) }}… · {{ r.message_sent_at }}
      </div>
      <input class="fx-input mt-2" v-model="notes[r.id]" placeholder="Admin notes (optional)" />
      <div class="d-flex gap-2 mt-2">
        <button class="btn btn-sm btn-success flex-fill" @click="resolve(r.id, 'reviewed_clear')">Clear</button>
        <button class="btn btn-sm btn-danger flex-fill" @click="resolve(r.id, 'reviewed_action')">Take action</button>
      </div>
    </div>
  </div>
</template>