<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useInfiniteList } from '../../composables/useInfiniteList'
import { useModalGuard } from '../../composables/useModalGuard'
import * as api from '../../services/api'

const router = useRouter()
const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)
const form = ref(emptyForm())

useModalGuard(showForm)

function emptyForm() {
  const d = new Date()
  d.setDate(d.getDate() + 30)
  return {
    code: '',
    discount_type: 'percent',
    discount_value: '',
    min_spend: '',
    max_discount: '',
    usage_limit: '',
    per_user_limit: 1,
    expires_at: d.toISOString().slice(0, 10),
    is_active: true,
  }
}

const { items, loading, done, sentinel, reset } = useInfiniteList(async (offset, size) => {
  const res = await api.getAdminCoupons({ limit: size, offset })
  return res.items || []
}, 20)

function openAdd() {
  editing.value = null
  form.value = emptyForm()
  showForm.value = true
}

function openEdit(c) {
  editing.value = c
  form.value = {
    code: c.code,
    discount_type: c.discount_type,
    discount_value: c.discount_value,
    min_spend: c.min_spend || '',
    max_discount: c.max_discount ?? '',
    usage_limit: c.usage_limit ?? '',
    per_user_limit: c.per_user_limit ?? 1,
    expires_at: (c.expires_at || '').slice(0, 10),
    is_active: c.is_active,
  }
  showForm.value = true
}

function discountLabel(c) {
  return c.discount_type === 'percent'
    ? `${c.discount_value}% off`
    : `RM${c.discount_value} off`
}

async function save() {
  if (!form.value.code || !form.value.discount_value) return
  saving.value = true
  try {
    const payload = {
      code: form.value.code,
      discount_type: form.value.discount_type,
      discount_value: parseFloat(form.value.discount_value),
      min_spend: form.value.min_spend ? parseFloat(form.value.min_spend) : 0,
      max_discount: form.value.max_discount !== '' ? parseFloat(form.value.max_discount) : null,
      usage_limit: form.value.usage_limit !== '' ? parseInt(form.value.usage_limit, 10) : null,
      per_user_limit: parseInt(form.value.per_user_limit, 10) || 1,
      expires_at: form.value.expires_at,
      is_active: form.value.is_active,
    }
    if (editing.value) await api.updateAdminCoupon(editing.value.id, payload)
    else await api.createAdminCoupon(payload)
    showForm.value = false
    reset()
  } catch (e) {
    alert(e.message || 'Could not save coupon')
  } finally {
    saving.value = false
  }
}

async function toggleActive(c) {
  try {
    await api.updateAdminCoupon(c.id, { ...c, is_active: !c.is_active })
    c.is_active = !c.is_active
  } catch (e) {
    alert(e.message || 'Could not update coupon')
  }
}

async function remove(c) {
  if (!confirm(`Delete coupon ${c.code}?`)) return
  try {
    await api.deleteAdminCoupon(c.id)
    reset()
  } catch (e) {
    alert(e.message || 'Could not delete coupon')
  }
}
</script>

<template>
  <div class="acv-root fx-view-root">
    <header class="acv-header">
      <button class="acv-back" @click="router.back()">
        <span class="material-symbols-outlined" style="font-size:22px">arrow_back</span>
      </button>
      <h1 class="acv-title">System Coupons</h1>
      <button class="acv-add" @click="openAdd">
        <span class="material-symbols-outlined" style="font-size:18px">add</span>
        New
      </button>
    </header>

    <div v-if="loading && !items.length" class="acv-empty">Loading…</div>
    <div v-else-if="!items.length" class="acv-empty">No system coupons yet.</div>

    <div v-else class="acv-list">
      <div v-for="c in items" :key="c.id" class="acv-card liquid-glass">
        <div class="acv-card-top">
          <span class="acv-code">{{ c.code }}</span>
          <span class="acv-badge" :class="{ off: !c.is_active }">{{ c.is_active ? 'Active' : 'Disabled' }}</span>
        </div>
        <div class="acv-meta">{{ discountLabel(c) }} · min RM{{ c.min_spend }} · used {{ c.used_count }}{{ c.usage_limit ? `/${c.usage_limit}` : '' }}</div>
        <div class="acv-meta muted">Expires {{ (c.expires_at || '').slice(0, 10) }}</div>
        <div class="acv-actions">
          <button class="acv-btn" @click="openEdit(c)">Edit</button>
          <button class="acv-btn" @click="toggleActive(c)">{{ c.is_active ? 'Disable' : 'Enable' }}</button>
          <button class="acv-btn danger" @click="remove(c)">Delete</button>
        </div>
      </div>
      <div ref="sentinel" class="acv-sentinel"></div>
    </div>

    <Teleport to="body">
      <div v-if="showForm" class="lg-overlay-center" @click.self="showForm = false">
        <div class="lg-modal liquid-glass-high">
          <div class="acv-modal-head">
            <span>{{ editing ? 'Edit Coupon' : 'New System Coupon' }}</span>
            <button class="acv-close" @click="showForm = false">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>
          <div class="acv-modal-body">
            <label class="acv-lbl">Code *</label>
            <input class="fx-input mb-2" v-model="form.code" placeholder="SAVE10" />
            <label class="acv-lbl">Type</label>
            <select class="fx-input mb-2" v-model="form.discount_type">
              <option value="percent">Percent</option>
              <option value="fixed">Fixed (RM)</option>
            </select>
            <label class="acv-lbl">Value *</label>
            <input class="fx-input mb-2" v-model="form.discount_value" type="number" min="0" step="0.01" />
            <label class="acv-lbl">Min spend (RM)</label>
            <input class="fx-input mb-2" v-model="form.min_spend" type="number" min="0" step="0.01" />
            <label class="acv-lbl">Max discount (RM, percent only)</label>
            <input class="fx-input mb-2" v-model="form.max_discount" type="number" min="0" step="0.01" />
            <label class="acv-lbl">Total usage limit</label>
            <input class="fx-input mb-2" v-model="form.usage_limit" type="number" min="1" placeholder="Unlimited" />
            <label class="acv-lbl">Per-user limit</label>
            <input class="fx-input mb-2" v-model="form.per_user_limit" type="number" min="1" />
            <label class="acv-lbl">Expires</label>
            <input class="fx-input mb-2" v-model="form.expires_at" type="date" />
            <label class="acv-check">
              <input type="checkbox" v-model="form.is_active" /> Active
            </label>
          </div>
          <div class="acv-modal-foot">
            <button class="acv-btn" @click="showForm = false">Cancel</button>
            <button class="acv-btn primary" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save' }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.acv-root { min-height: 100vh; padding-bottom: 80px; }
.acv-header { display: flex; align-items: center; gap: 8px; padding: 16px; }
.acv-back { background: none; border: none; cursor: pointer; color: var(--fx-text); padding: 4px; }
.acv-title { flex: 1; font-size: 20px; font-weight: 800; margin: 0; color: var(--fx-text); }
.acv-add {
  display: flex; align-items: center; gap: 4px;
  padding: 8px 12px; border-radius: 999px; border: none;
  background: var(--fx-accent); color: #fff; font-weight: 700; font-size: 13px; cursor: pointer;
}
.acv-empty { padding: 48px 24px; text-align: center; color: var(--fx-muted); }
.acv-list { display: flex; flex-direction: column; gap: 10px; padding: 0 12px 24px; }
.acv-card { padding: 14px 16px; border-radius: 16px; }
.acv-card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.acv-code { font-size: 16px; font-weight: 800; letter-spacing: 0.04em; }
.acv-badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 999px; background: rgba(34,197,94,0.12); color: #16a34a; }
.acv-badge.off { background: rgba(0,0,0,0.06); color: var(--fx-muted); }
.acv-meta { font-size: 12px; color: var(--fx-text); }
.acv-meta.muted { color: var(--fx-muted); margin-top: 2px; }
.acv-actions { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.acv-btn {
  padding: 6px 12px; border-radius: 8px; border: 1px solid var(--fx-border);
  background: #fff; font-size: 12px; font-weight: 600; cursor: pointer;
}
.acv-btn.primary { background: var(--fx-accent); color: #fff; border-color: transparent; }
.acv-btn.danger { color: #ef4444; border-color: rgba(239,68,68,0.3); }
.acv-sentinel { height: 1px; }

.acv-modal-head {
  display: flex; justify-content: space-between; align-items: center;
  padding: 16px 20px; font-weight: 800; border-bottom: 1px solid rgba(0,0,0,0.06);
}
.acv-close { background: none; border: none; cursor: pointer; color: var(--fx-muted); }
.acv-modal-body { padding: 16px 20px; overflow-y: auto; }
.acv-lbl { display: block; font-size: 12px; font-weight: 600; color: var(--fx-muted); margin-bottom: 4px; }
.acv-check { display: flex; align-items: center; gap: 8px; font-size: 13px; margin-top: 8px; }
.acv-modal-foot { display: flex; gap: 10px; padding: 16px 20px; border-top: 1px solid rgba(0,0,0,0.06); }
</style>