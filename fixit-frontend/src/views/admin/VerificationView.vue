<script setup>
import { ref, computed, onMounted } from 'vue'
import { useProvidersStore } from '../../stores/providers'
import { useAdminStore } from '../../stores/admin'
import AppIcon from '../../components/AppIcon.vue'

const providersStore = useProvidersStore()
const adminStore = useAdminStore()

const filter = ref('Pending')
const expanded = ref(null)

onMounted(() => providersStore.load())

// Decision-aware status for each provider (workflow #3).
function statusOf(p) {
  const decision = adminStore.decisions[p.id]
  if (decision === 'rejected') return 'Rejected'
  if (p.is_verified) return 'Approved'
  return 'Pending'
}
const STATUS_STYLE = {
  Pending: { c: 'var(--fx-warn)', bg: 'var(--fx-warn-soft)' },
  Approved: { c: 'var(--fx-success)', bg: 'var(--fx-success-soft)' },
  Rejected: { c: 'var(--fx-error)', bg: 'var(--fx-error-soft)' },
}

const shown = computed(() =>
  providersStore.providers.filter(p => filter.value === 'All' || statusOf(p) === filter.value))
const stats = computed(() => adminStore.stats)
</script>

<template>
  <div class="fx-page">
    <h1 class="fw-bold mb-1" style="font-size:20px">Verifications</h1>
    <div class="mb-3" style="font-size:13px;color:var(--fx-muted)">Admin Dashboard</div>

    <!-- Stats -->
    <div class="d-flex gap-2 mb-3">
      <div class="flex-fill text-center" style="background:var(--fx-warn-soft);border-radius:14px;padding:12px 10px">
        <div style="font-size:24px;font-weight:800;color:var(--fx-warn)">{{ stats.pending }}</div>
        <div style="font-size:11px;color:var(--fx-warn);font-weight:500">Pending</div>
      </div>
      <div class="flex-fill text-center" style="background:var(--fx-success-soft);border-radius:14px;padding:12px 10px">
        <div style="font-size:24px;font-weight:800;color:var(--fx-success)">{{ stats.approved }}</div>
        <div style="font-size:11px;color:var(--fx-success);font-weight:500">Approved</div>
      </div>
      <div class="flex-fill text-center" style="background:var(--fx-error-soft);border-radius:14px;padding:12px 10px">
        <div style="font-size:24px;font-weight:800;color:var(--fx-error)">{{ stats.rejected }}</div>
        <div style="font-size:11px;color:var(--fx-error);font-weight:500">Rejected</div>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="d-flex mb-3">
      <div v-for="t in ['All','Pending','Approved','Rejected']" :key="t" role="button"
           class="flex-fill text-center" style="padding:8px 0;font-size:12px;border-bottom:2px solid"
           :style="{ fontWeight: filter === t ? 700 : 500, color: filter === t ? 'var(--fx-accent)' : 'var(--fx-muted)', borderColor: filter === t ? 'var(--fx-accent)' : 'var(--fx-border)' }"
           @click="filter = t">{{ t }}</div>
    </div>

    <!-- Provider list -->
    <div class="d-flex flex-column gap-2">
      <div v-for="p in shown" :key="p.id" class="fx-card" style="padding:12px">
        <div class="d-flex align-items-start gap-2">
          <div class="fx-avatar" style="width:42px;height:42px;background:var(--fx-border-soft);color:var(--fx-muted)">
            {{ p.name.split(' ').map(w => w[0]).join('') }}
          </div>
          <div class="flex-grow-1" style="min-width:0">
            <div class="d-flex justify-content-between align-items-center">
              <span class="fw-semibold" style="font-size:14px">{{ p.name }}</span>
              <span class="fx-badge" :style="{ color: STATUS_STYLE[statusOf(p)].c, background: STATUS_STYLE[statusOf(p)].bg }">
                {{ statusOf(p) }}
              </span>
            </div>
            <div style="font-size:12px;color:var(--fx-muted);margin-top:2px">{{ p.category_names.join(', ') }} · {{ p.location }}</div>

            <!-- KYC reference (mock) -->
            <button class="btn btn-link p-0 mt-1" style="font-size:12px;text-decoration:none"
                    @click="expanded = expanded === p.id ? null : p.id">
              {{ expanded === p.id ? 'Hide' : 'View' }} KYC reference
            </button>
            <div v-if="expanded === p.id" class="fx-card mt-2" style="background:var(--fx-blue-soft);box-shadow:none">
              <div class="d-flex align-items-center gap-2 mb-1" style="font-size:12px;color:var(--fx-blue)">
                <AppIcon name="shield" :size="14" /> Mock KYC document
              </div>
              <div style="font-size:12px;color:var(--fx-muted)">{{ p.kyc_doc_url }}</div>
              <div style="font-size:12px;color:var(--fx-muted)">{{ p.email }} · {{ p.phone }}</div>
            </div>

            <div v-if="statusOf(p) === 'Pending'" class="d-flex gap-2 mt-2">
              <button class="btn btn-outline-danger flex-fill" style="border-radius:9px;font-size:12px;padding:7px 0"
                      @click="adminStore.reject(p.id)">Reject</button>
              <button class="btn btn-primary" style="flex:2;border-radius:9px;font-size:12px;padding:7px 0"
                      @click="adminStore.approve(p.id)">Review &amp; Approve</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="!shown.length" class="text-center py-4" style="color:var(--fx-muted)">No providers in this filter.</div>
    </div>
  </div>
</template>
