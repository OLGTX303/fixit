<script setup>
import { ref, computed, watch } from 'vue'
import * as api from '../../services/api'
import { useInfiniteList } from '../../composables/useInfiniteList'

const tab = ref('users')
const query = ref('')
const sortBy = ref('name')
const blocking = ref(new Set())

// ── Users: infinite scroll (load 20, more on scroll) ─────────────────────────
const total = ref(0)
const counts = ref({ total: 0, customers: 0, providers: 0, blocked: 0 })
const { items: users, loading: loadingUsers, done, sentinel, reset } = useInfiniteList(async (offset, size) => {
  const res = await api.getUsers({ q: query.value.trim(), limit: size, offset, sort: sortBy.value })
  total.value = res.total || 0
  counts.value = res.counts || counts.value
  return res.users || []
}, 20)

let searchTimer = null
watch(query, () => { clearTimeout(searchTimer); searchTimer = setTimeout(reset, 350) })
watch(sortBy, reset)

const summary = computed(() => counts.value)

function statusOf(u) {
  if (u.is_blocked) return 'Blocked'
  if (u.role !== 'provider') return 'Active'
  return u.provider_verified ? 'Active' : 'Pending'
}
const STATUS_STYLE = {
  Active:   { c: 'var(--fx-success)', bg: 'var(--fx-success-soft)' },
  Pending:  { c: 'var(--fx-warn)',    bg: 'var(--fx-warn-soft)' },
  Blocked:  { c: '#ef4444',           bg: 'rgba(239,68,68,0.10)' },
}

async function toggleBlock(u) {
  blocking.value.add(u.id)
  const next = !u.is_blocked
  await api.blockUser(u.id, next)
  u.is_blocked = next ? 1 : 0
  counts.value.blocked += next ? 1 : -1
  blocking.value.delete(u.id)
}

// ── Categories tab (lazy; counts via a cheap aggregate, not the full list) ───
const categories = ref([])
const catCounts = ref({})
const categoriesWithCounts = computed(() =>
  categories.value.map(c => ({ ...c, count: catCounts.value[c.id] || 0 })))
async function loadCategories() {
  if (categories.value.length) return
  const [cats, stats] = await Promise.all([api.getCategories(), api.getCategoryStats()])
  categories.value = cats
  catCounts.value = stats || {}
}
watch(tab, (t) => { if (t === 'categories') loadCategories() })
</script>

<template>
  <div class="fx-page" style="max-width:600px">
    <h1 class="fw-bold mb-3" style="font-size:20px">Management</h1>

    <!-- Tab switcher -->
    <div class="um-tabs mb-3">
      <button v-for="t in [['users','Users'],['categories','Categories']]" :key="t[0]"
              class="um-tab" :class="{active: tab===t[0]}" @click="tab=t[0]">{{ t[1] }}</button>
    </div>

    <!-- ── Users tab ── -->
    <template v-if="tab==='users'">
      <div class="d-flex gap-2 mb-3">
        <input class="fx-input" style="flex:1" v-model="query" placeholder="Search users…" />
        <select v-model="sortBy" class="um-sort" aria-label="Sort users">
          <option value="name">Name A–Z</option>
          <option value="role">Role</option>
          <option value="blocked">Blocked first</option>
          <option value="recent">Newest</option>
        </select>
      </div>

      <!-- Stats -->
      <div class="um-stats mb-3">
        <div v-for="s in [[summary.total,'Total'],[summary.customers,'Customers'],[summary.providers,'Providers'],[summary.blocked,'Blocked']]"
             :key="s[1]" class="um-stat fx-card">
          <div style="font-size:18px;font-weight:800" :style="s[1]==='Blocked'&&s[0]?{color:'#ef4444'}:{}">{{ s[0] }}</div>
          <div style="font-size:10px;color:var(--fx-muted)">{{ s[1] }}</div>
        </div>
      </div>

      <div v-if="loadingUsers && !users.length" style="text-align:center;padding:20px;color:var(--fx-muted);font-size:13px">Loading…</div>
      <div v-else-if="done && !users.length" style="text-align:center;padding:20px;color:var(--fx-muted);font-size:13px">No users found.</div>

      <div class="d-flex flex-column gap-2">
        <div v-for="u in users" :key="u.id"
             class="fx-card d-flex align-items-center gap-3"
             style="padding:12px 14px"
             :style="u.is_blocked ? 'opacity:0.65' : ''">
          <img v-if="u.avatar_url" :src="u.avatar_url" :alt="u.name"
               class="fx-avatar" style="width:40px;height:40px;flex-shrink:0;object-fit:cover" />
          <div v-else class="fx-avatar" style="width:40px;height:40px;font-size:13px;flex-shrink:0"
               :style="{ background: u.role==='provider' ? 'var(--fx-accent-soft)' : 'var(--fx-blue-soft)',
                         color: u.role==='provider' ? 'var(--fx-accent)' : 'var(--fx-blue)' }">
            {{ u.name.split(' ').map(w=>w[0]).join('') }}
          </div>
          <div style="flex:1;min-width:0">
            <div style="font-size:14px;font-weight:600;display:flex;align-items:center;gap:6px">
              {{ u.name }}
              <span v-if="u.is_blocked" style="font-size:10px;color:#ef4444">🚫 Blocked</span>
            </div>
            <div style="font-size:11px;color:var(--fx-muted);text-transform:capitalize">{{ u.role }} · {{ u.email }}</div>
          </div>
          <span class="fx-badge" :style="{ color: STATUS_STYLE[statusOf(u)].c, background: STATUS_STYLE[statusOf(u)].bg }">
            {{ statusOf(u) }}
          </span>
          <button class="um-block-btn" :class="u.is_blocked ? 'unblock' : 'block'"
                  :disabled="blocking.has(u.id)"
                  @click="toggleBlock(u)">
            {{ u.is_blocked ? 'Unblock' : 'Block' }}
          </button>
        </div>
      </div>

      <!-- infinite-scroll sentinel + states -->
      <div ref="sentinel" style="height:1px"></div>
      <div v-if="loadingUsers && users.length" class="um-page-info" style="text-align:center;padding:14px">Loading…</div>
      <div v-else-if="users.length" class="um-page-info" style="text-align:center;padding:14px">
        {{ users.length }} of {{ total }} shown<span v-if="done"> · end</span>
      </div>
    </template>

    <!-- ── Categories tab ── -->
    <template v-else>
      <div class="d-flex flex-column gap-2">
        <div v-for="c in categoriesWithCounts" :key="c.id" class="fx-card d-flex align-items-center gap-3">
          <span style="font-size:24px">{{ c.icon_url }}</span>
          <div style="flex:1">
            <div style="font-size:14px;font-weight:600">{{ c.name }}</div>
            <div style="font-size:12px;color:var(--fx-muted)">{{ c.description }}</div>
          </div>
          <span class="fx-badge" style="color:var(--fx-accent);background:var(--fx-accent-soft)">{{ c.count }} pros</span>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.um-sort { font-family:inherit; font-size:13px; font-weight:600; color:var(--fx-text);
  border:1.5px solid var(--fx-border); border-radius:10px; padding:0 12px; background:#fff; cursor:pointer; }
.um-pager { display:flex; align-items:center; justify-content:center; gap:14px; margin-top:16px; }
.um-page-info { font-size:13px; color:var(--fx-muted); font-weight:600; }
.um-page-btn { font-family:inherit; font-size:13px; font-weight:700; color:var(--fx-accent);
  border:1.5px solid var(--fx-accent); border-radius:999px; padding:6px 16px; background:#fff; cursor:pointer; }
.um-page-btn:disabled { opacity:0.4; cursor:not-allowed; }
.um-tabs { display:flex; background:var(--fx-border-soft); border-radius:12px; padding:3px; gap:2px; }
.um-tab  { flex:1; padding:8px 0; border:none; border-radius:9px; font-size:13px; font-weight:600;
           background:transparent; color:var(--fx-muted); cursor:pointer; }
.um-tab.active { background:#fff; color:var(--fx-text); box-shadow:0 1px 4px rgba(0,0,0,.08); }

.um-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; }
.um-stat  { text-align:center; padding:10px 4px; }

.um-block-btn {
  padding:5px 12px; border-radius:8px; font-size:12px; font-weight:700;
  border:1.5px solid; cursor:pointer; white-space:nowrap; flex-shrink:0;
}
.um-block-btn.block   { color:#ef4444; border-color:#ef4444; background:transparent; }
.um-block-btn.unblock { color:var(--fx-success); border-color:var(--fx-success); background:transparent; }
.um-block-btn:disabled { opacity:0.5; cursor:wait; }
</style>
