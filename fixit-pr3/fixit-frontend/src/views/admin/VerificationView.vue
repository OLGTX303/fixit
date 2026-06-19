<script setup>
import { ref, computed, onMounted } from 'vue'
import { useProvidersStore } from '../../stores/providers'
import * as api from '../../services/api'
import AppIcon from '../../components/AppIcon.vue'

const providersStore = useProvidersStore()
const filter = ref('Pending')
const expanded = ref(null)
const decisions = ref({})

onMounted(() => providersStore.load())

function statusOf(p) {
  if (decisions.value[p.id] === 'rejected') return 'Rejected'
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

const stats = computed(() => {
  const approved = providersStore.providers.filter(p => p.is_verified).length
  const rejected = Object.values(decisions.value).filter(d => d === 'rejected').length
  const pending = providersStore.providers.filter(p => !p.is_verified).length - rejected
  return { pending: Math.max(0, pending), approved, rejected }
})

async function approve(id) {
  await api.setProviderVerification(id, true)
  providersStore.setVerified(id, true)
  decisions.value[id] = 'approved'
}

async function reject(id) {
  await api.setProviderVerification(id, false)
  providersStore.setVerified(id, false)
  decisions.value[id] = 'rejected'
}
</script>

<template>
  <div class="fx-page">
    <h1 class="fw-bold mb-1" style="font-size:20px">Verifications</h1>
    <div class="mb-3" style="font-size:13px;color:var(--fx-muted)">Admin Dashboard</div>

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

    <div class="d-flex mb-3">
      <div v-for="t in ['All','Pending','Approved','Rejected']" :key="t" role="button"
           class="flex-fill text-center" style="padding:8px 0;font-size:12px;border-bottom:2px solid"
           :style="{ fontWeight: filter === t ? 700 : 500, color: filter === t ? 'var(--fx-accent)' : 'var(--fx-muted)', borderColor: filter === t ? 'var(--fx-accent)' : 'var(--fx-border)' }"
           @click="filter = t">{{ t }}</div>
    </div>

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

            <button class="btn btn-link p-0 mt-1" style="font-size:12px;text-decoration:none"
                    @click="expanded = expanded === p.id ? null : p.id">
              {{ expanded === p.id ? 'Hide' : 'View' }} KYC details
            </button>
            <div v-if="expanded === p.id" class="fx-card mt-2" style="background:var(--fx-blue-soft);box-shadow:none">
              <div class="d-flex align-items-center gap-2 mb-2" style="font-size:12px;color:var(--fx-blue)">
                <AppIcon name="shield" :size="14" /> Automated KYC results
              </div>
              <div style="font-size:12px;color:var(--fx-muted)">Status: <strong>{{ p.kyc_status || 'none' }}</strong></div>
              <div v-if="p.kyc_id_type" style="font-size:12px;color:var(--fx-muted)">
                ID type: {{ p.kyc_id_type.replace('_', ' ') }} · {{ p.kyc_id_confidence }}% confidence
              </div>
              <div v-if="p.kyc_id_checks?.fraud_score != null" style="font-size:12px;color:var(--fx-muted)">
                Fraud score: {{ p.kyc_id_checks.fraud_score }}
                <span v-if="p.kyc_id_checks.ocr_confidence != null"> · OCR {{ p.kyc_id_checks.ocr_confidence }}%</span>
              </div>
              <div v-if="p.kyc_id_checks?.fraud_flags?.length" style="font-size:11px;color:var(--fx-error);margin-top:4px">
                Flags: {{ p.kyc_id_checks.fraud_flags.join(', ') }}
              </div>
              <div v-if="p.kyc_id_checks?.rejection_reasons?.length" style="font-size:11px;color:var(--fx-error);margin-top:4px">
                Rejected: {{ p.kyc_id_checks.rejection_reasons.join('; ') }}
              </div>
              <div v-if="p.kyc_id_checks?.extracted_preview" style="font-size:11px;color:var(--fx-muted);margin-top:4px">
                OCR: {{ p.kyc_id_checks.extracted_preview }}
              </div>
              <div v-if="p.kyc_liveness_passed" style="font-size:12px;color:var(--fx-success);margin-top:4px">
                ✓ 8-colour face liveness passed ({{ p.kyc_liveness_score }}%)
              </div>
              <div style="font-size:12px;color:var(--fx-muted);margin-top:4px">{{ p.email }} · {{ p.phone }}</div>
            </div>

            <div v-if="statusOf(p) === 'Pending'" class="d-flex gap-2 mt-2">
              <button class="btn btn-outline-danger flex-fill" style="border-radius:9px;font-size:12px;padding:7px 0"
                      @click="reject(p.id)">Reject</button>
              <button class="btn btn-primary" style="flex:2;border-radius:9px;font-size:12px;padding:7px 0"
                      @click="approve(p.id)">Review &amp; Approve</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="!shown.length" class="text-center py-4" style="color:var(--fx-muted)">No providers in this filter.</div>
    </div>
  </div>
</template>