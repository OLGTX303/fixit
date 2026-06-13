<script setup>
import { ref, computed, onMounted } from 'vue'
import * as api from '../../services/api'
import { useProvidersStore } from '../../stores/providers'

const providersStore = useProvidersStore()
const users = ref([])
const tab = ref('users')
const query = ref('')

onMounted(async () => {
  users.value = await api.getUsers()
  await providersStore.load()
})

const filtered = computed(() =>
  users.value.filter(u => u.name.toLowerCase().includes(query.value.toLowerCase())))

const summary = computed(() => ({
  total: users.value.length,
  customers: users.value.filter(u => u.role === 'customer').length,
  providers: users.value.filter(u => u.role === 'provider').length,
}))

// Derive a status per user: providers reflect verification, others Active.
function statusOf(u) {
  if (u.role !== 'provider') return 'Active'
  const p = providersStore.providers.find(x => x.user_id === u.id)
  return p?.is_verified ? 'Active' : 'Pending'
}
const STATUS_STYLE = {
  Active: { c: 'var(--fx-success)', bg: 'var(--fx-success-soft)' },
  Pending: { c: 'var(--fx-warn)', bg: 'var(--fx-warn-soft)' },
  Inactive: { c: 'var(--fx-muted)', bg: 'var(--fx-border-soft)' },
}

const categoriesWithCounts = computed(() =>
  providersStore.categories.map(c => ({
    ...c,
    count: providersStore.providers.filter(p => p.category_ids.includes(c.id)).length,
  })))
</script>

<template>
  <div class="fx-page">
    <h1 class="fw-bold mb-3" style="font-size:20px">Management</h1>

    <div class="d-flex mb-3" style="background:var(--fx-border-soft);border-radius:12px;padding:3px">
      <div v-for="t in [['users','Users'],['categories','Categories']]" :key="t[0]" role="button"
           class="flex-fill text-center" style="padding:8px 0;border-radius:9px;font-size:13px;font-weight:600"
           :style="{ background: tab === t[0] ? '#fff' : 'transparent', color: tab === t[0] ? 'var(--fx-text)' : 'var(--fx-muted)', boxShadow: tab === t[0] ? '0 1px 4px rgba(0,0,0,.08)' : 'none' }"
           @click="tab = t[0]">{{ t[1] }}</div>
    </div>

    <!-- Users tab -->
    <template v-if="tab === 'users'">
      <input class="fx-input mb-3" v-model="query" placeholder="Search users…" />

      <div class="d-flex gap-2 mb-3">
        <div v-for="s in [[summary.total,'Total'],[summary.customers,'Customers'],[summary.providers,'Providers']]" :key="s[1]"
             class="flex-fill text-center fx-card" style="padding:10px 8px">
          <div style="font-size:18px;font-weight:800">{{ s[0] }}</div>
          <div style="font-size:10px;color:var(--fx-muted)">{{ s[1] }}</div>
        </div>
      </div>

      <div class="d-flex flex-column gap-2">
        <div v-for="u in filtered" :key="u.id" class="fx-card d-flex align-items-center gap-2" style="padding:11px 13px">
          <div class="fx-avatar" style="width:38px;height:38px;font-size:13px"
               :style="{ background: u.role === 'provider' ? 'var(--fx-accent-soft)' : 'var(--fx-blue-soft)', color: u.role === 'provider' ? 'var(--fx-accent)' : 'var(--fx-blue)' }">
            {{ u.name.split(' ').map(w => w[0]).join('') }}
          </div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="fw-semibold" style="font-size:13px">{{ u.name }}</div>
            <div style="font-size:11px;color:var(--fx-muted);text-transform:capitalize">{{ u.role }}</div>
          </div>
          <span class="fx-badge" :style="{ color: STATUS_STYLE[statusOf(u)].c, background: STATUS_STYLE[statusOf(u)].bg }">
            {{ statusOf(u) }}
          </span>
        </div>
      </div>
    </template>

    <!-- Categories tab -->
    <template v-else>
      <div class="d-flex flex-column gap-2">
        <div v-for="c in categoriesWithCounts" :key="c.id" class="fx-card d-flex align-items-center gap-3">
          <span style="font-size:24px">{{ c.icon_url }}</span>
          <div class="flex-grow-1">
            <div class="fw-semibold" style="font-size:14px">{{ c.name }}</div>
            <div style="font-size:12px;color:var(--fx-muted)">{{ c.description }}</div>
          </div>
          <span class="fx-badge text-accent bg-accent-soft">{{ c.count }} pros</span>
        </div>
      </div>
    </template>
  </div>
</template>
