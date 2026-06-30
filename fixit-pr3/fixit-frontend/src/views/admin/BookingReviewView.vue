<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useBookingsStore } from '../../stores/bookings'
import * as api from '../../services/api'

const router = useRouter()
const bookingsStore  = useBookingsStore()
const users       = ref([])
const userTotal   = ref(0)
const reviews     = ref([])
const verifyStats = ref({ pending: 0, approved: 0 })
const stripeStats = ref(null)
const loading     = ref(true)
const filterStatus = ref('All')

const reviewTotal = ref(0)
const reviewAvg = ref(null)

onMounted(async () => {
  await Promise.all([
    bookingsStore.load(),
    api.getUsers({ limit: 1 }).then(u => {
      users.value = u.users || []
      userTotal.value = u.counts?.total ?? u.total ?? users.value.length
    }).catch(() => {}),
    api.getReviews({ limit: 25 }).then(r => {
      reviews.value = r.reviews || r
      reviewTotal.value = r.total ?? reviews.value.length
      reviewAvg.value = r.avg_rating ?? null
    }).catch(() => {}),
    api.getVerifyStats().then(v => { verifyStats.value = v }).catch(() => {}),
    api.getStripeStats().then(s => { stripeStats.value = s }).catch(() => {}),
  ])
  loading.value = false
})

const bookings = computed(() => bookingsStore.bookings)
const paid     = computed(() => bookings.value.filter(b => ['completed','reviewed'].includes(b.status)))

// ── Revenue source ────────────────────────────────────────────────────
const stripeRevCents = computed(() => Number(stripeStats.value?.totals?.total_revenue_cents || 0))
const bookingRevenue = computed(() => paid.value.reduce((s, b) => s + Number(b.total || 0), 0))
const revenue        = computed(() => stripeRevCents.value > 0 ? stripeRevCents.value / 100 : bookingRevenue.value)
const usingStripe    = computed(() => stripeRevCents.value > 0)

const kpi = computed(() => ({
  revenue:   revenue.value,
  bookings:  bookings.value.length,
  users:     userTotal.value,
  providers: (verifyStats.value.approved ?? 0) + (verifyStats.value.pending ?? 0),
  pending:   bookings.value.filter(b => b.status === 'requested').length,
  avgRating: reviewAvg.value != null
    ? String(reviewAvg.value)
    : (reviews.value.length
      ? (reviews.value.reduce((s, r) => s + r.rating, 0) / reviews.value.length).toFixed(1) : '—'),
}))

// ── Base months (last 6) ──────────────────────────────────────────────
const baseMonths = computed(() => {
  const now = new Date()
  return Array.from({ length: 6 }, (_, i) => {
    const d = new Date(now.getFullYear(), now.getMonth() - (5 - i), 1)
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    return { label: d.toLocaleString('en', { month: 'short' }), key, year: d.getFullYear(), month: d.getMonth() }
  })
})

// ── Monthly revenue ───────────────────────────────────────────────────
const monthly = computed(() => {
  const months = baseMonths.value.map(m => ({ ...m, total: 0 }))
  if (usingStripe.value && stripeStats.value?.monthly?.length) {
    stripeStats.value.monthly.forEach(row => {
      const m = months.find(x => x.key === row.month)
      if (m) m.total = Number(row.revenue_cents) / 100
    })
  } else {
    paid.value.forEach(b => {
      const raw = b.scheduled_at || b.created_at; if (!raw) return
      const d = new Date(raw)
      const m = months.find(x => x.year === d.getFullYear() && x.month === d.getMonth())
      if (m) m.total += Number(b.total || 0)
    })
  }
  return months
})

const chartMax = computed(() => Math.max(...monthly.value.map(m => m.total), 1))
const barH = t => t > 0 ? Math.max((t / chartMax.value) * 90, 4) : 0
const barY = t => 100 - barH(t)

// ── Monthly counts by status (for queue sparklines & volume trend) ────
const STATUS_KEYS  = ['requested','accepted','in_progress','completed','reviewed']
const STATUS_COLOR = { requested:'#f59e0b', accepted:'#3b82f6', in_progress:'#8b5cf6', completed:'#22c55e', reviewed:'#94a3b8' }
const STATUS_ICON  = { requested:'pending', accepted:'handshake', in_progress:'build', completed:'check_circle', reviewed:'rate_review' }

const monthlyByStatus = computed(() => {
  const months = baseMonths.value.map(m => {
    const obj = { ...m, total: 0 }
    STATUS_KEYS.forEach(k => { obj[k] = 0 })
    return obj
  })
  bookings.value.forEach(b => {
    const raw = b.scheduled_at || b.created_at; if (!raw) return
    const d = new Date(raw)
    const m = months.find(x => x.year === d.getFullYear() && x.month === d.getMonth())
    if (m) { if (m[b.status] !== undefined) m[b.status]++; m.total++ }
  })
  return months
})

const statusCounts = computed(() => {
  const c = {}; STATUS_KEYS.forEach(k => { c[k] = 0 })
  bookings.value.forEach(b => { if (c[b.status] !== undefined) c[b.status]++ })
  return c
})

// ── SVG helpers ───────────────────────────────────────────────────────
function sparkline(vals, w = 90, h = 40) {
  const max = Math.max(...vals, 1)
  const n = vals.length
  const pts = vals.map((v, i) => ({
    x: n < 2 ? w / 2 : Math.round((i / (n - 1)) * w),
    y: Math.round(h - 4 - ((v || 0) / max) * (h - 10)),
    v: v || 0,
  }))
  const firstX = pts[0]?.x ?? 0, lastX = pts[n - 1]?.x ?? w
  return {
    points: pts.map(p => `${p.x},${p.y}`).join(' '),
    area:   `M${firstX},${h} ${pts.map(p => `L${p.x},${p.y}`).join(' ')} L${lastX},${h} Z`,
    dots:   pts,
  }
}

// h=70 plot area inside an 80-tall viewBox �?leaves a band below for month
// labels so a flat (zero) baseline never sits on top of the text.
const revTrend = computed(() => sparkline(monthly.value.map(m => m.total), 340, 70))
const volTrend = computed(() => sparkline(monthlyByStatus.value.map(m => m.total), 340, 70))

// ── Queue cards ───────────────────────────────────────────────────────
const queueCards = computed(() => {
  const total = bookings.value.length || 1
  return STATUS_KEYS.map(k => ({
    key:    k,
    label:  k.replace('_', ' '),
    count:  statusCounts.value[k],
    pct:    Math.round((statusCounts.value[k] / total) * 100),
    color:  STATUS_COLOR[k],
    icon:   STATUS_ICON[k],
    spark:  sparkline(monthlyByStatus.value.map(m => m[k]), 90, 38),
    labels: baseMonths.value.map(m => m.label),
  }))
})

// ── Top providers ─────────────────────────────────────────────────────
const topProviders = computed(() => {
  const map = {}
  paid.value.forEach(b => {
    const name = b.provider?.name || 'Unknown'
    map[name] = (map[name] || 0) + Number(b.total || 0)
  })
  return Object.entries(map).sort(([,a],[,b]) => b - a).slice(0, 5)
    .map(([name, rev]) => ({ name, rev }))
})

// ── Filtered bookings ─────────────────────────────────────────────────
const FILTERS = ['All', ...STATUS_KEYS]
const filtered = computed(() =>
  filterStatus.value === 'All'
    ? [...bookings.value].reverse().slice(0, 8)
    : bookings.value.filter(b => b.status === filterStatus.value).reverse().slice(0, 8)
)

const fmtRM = n => `RM ${Number(n || 0).toFixed(2)}`
const label = s => s.replace('_', ' ')
</script>

<template>
  <div class="fx-page crm">

    <!-- Header -->
    <div class="crm-hdr">
      <div>
        <h1 class="crm-title">CRM Dashboard</h1>
        <p class="crm-sub">{{ new Date().toLocaleDateString('en',{weekday:'long',year:'numeric',month:'long',day:'numeric'}) }}</p>
      </div>
      <div class="crm-badges">
        <span class="crm-live">● LIVE</span>
        <span v-if="usingStripe" class="crm-stripe-badge">✓ Stripe Sandbox</span>
      </div>
    </div>

    <div v-if="loading" class="crm-spin">
      <span class="material-symbols-outlined" style="animation:spin 1s linear infinite;font-size:32px;color:var(--fx-accent)">autorenew</span>
      <span style="color:var(--fx-muted);font-size:13px">Loading CRM data…</span>
    </div>

    <template v-else>

      <!-- ── LIQUID GLASS TOP CARDS ────────────────────────────────── -->
      <div class="crm-top-grid">

        <!-- Revenue hero -->
        <div class="crm-hero glass-card">
          <div class="crm-hero-eyebrow">
            <span class="material-symbols-outlined" style="font-size:16px;font-variation-settings:'FILL' 1">payments</span>
            Total Revenue
          </div>
          <div class="crm-hero-val">{{ fmtRM(kpi.revenue) }}</div>
          <div class="crm-hero-hint">{{ usingStripe ? '✓ Stripe Sandbox · MYR' : 'Completed bookings · RM' }}</div>
          <!-- mini trend line on revenue card -->
          <svg class="crm-hero-spark" viewBox="0 0 340 60" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="hspG" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#fff" stop-opacity="0.30"/>
                <stop offset="100%" stop-color="#fff" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path :d="revTrend.area" fill="url(#hspG)"/>
            <polyline :points="revTrend.points" fill="none" stroke="rgba(255,255,255,0.85)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle v-if="revTrend.dots?.length" :cx="revTrend.dots.at(-1).x" :cy="revTrend.dots.at(-1).y" r="3.5" fill="#fff"/>
          </svg>
        </div>

        <!-- Metric glass cards -->
        <div v-for="mc in [
          { val: kpi.bookings,  label:'Bookings',  icon:'calendar_month', c:'#3b82f6' },
          { val: kpi.users,     label:'Users',     icon:'group',          c:'#22c55e' },
          { val: kpi.avgRating, label:'Rating',    icon:'star',           c:'#8b5cf6' },
          { val: kpi.pending,   label:'Pending',   icon:'pending',        c:'#f59e0b' },
          { val: kpi.providers, label:'Providers', icon:'engineering',    c:'#64748b' },
        ]" :key="mc.label" class="crm-mc glass-card">
          <span class="material-symbols-outlined crm-mc-icon" :style="{ color: mc.c }">{{ mc.icon }}</span>
          <div class="crm-mc-val">{{ mc.val }}</div>
          <div class="crm-mc-label">{{ mc.label }}</div>
        </div>

      </div>

      <!-- ── MAIN 2-COL CHARTS ─────────────────────────────────────── -->
      <div class="crm-grid">

        <!-- Left: bar + income trend + status -->
        <div class="glass-card crm-panel">
          <div class="crm-panel-hdr">
            <span class="crm-panel-title">Monthly Revenue</span>
            <span class="crm-panel-sub">{{ usingStripe ? 'Stripe Sandbox' : 'RM' }}</span>
          </div>

          <!-- Bar chart -->
          <svg class="crm-bar-svg" viewBox="0 0 360 128" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="bG" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#FF8056"/><stop offset="100%" stop-color="#FF6635" stop-opacity="0.7"/>
              </linearGradient>
            </defs>
            <line v-for="n in 4" :key="n" x1="0" :y1="n*22" x2="360" :y2="n*22" stroke="rgba(0,0,0,0.05)" stroke-width="0.5" stroke-dasharray="4 4"/>
            <g v-for="(m,i) in monthly" :key="m.label">
              <rect :x="i*60+10" :y="barY(m.total)" :width="40" :height="barH(m.total)||3" rx="7" :fill="m.total>0?'url(#bG)':'rgba(0,0,0,0.07)'"/>
              <text v-if="m.total>0" :x="i*60+30" :y="barY(m.total)-4" text-anchor="middle" font-size="7.5" fill="#FF6635" font-weight="700">{{ Math.round(m.total) }}</text>
              <text :x="i*60+30" y="120" text-anchor="middle" font-size="10" fill="var(--fx-muted)">{{ m.label }}</text>
            </g>
          </svg>

          <!-- Income trend line -->
          <div class="crm-divider-label">Income Trend</div>
          <svg class="crm-line-svg" style="height:80px" viewBox="0 0 340 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="ltG" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#FF8056" stop-opacity="0.25"/>
                <stop offset="100%" stop-color="#FF8056" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path :d="revTrend.area" fill="url(#ltG)"/>
            <polyline :points="revTrend.points" fill="none" stroke="#FF6635" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <circle v-for="(p,i) in revTrend.dots" :key="i" :cx="p.x" :cy="p.y" r="3" fill="#FF6635" opacity="0.85"/>
            <text v-for="(m,i) in monthly" :key="m.key"
                  :x="Math.round(i/(monthly.length-1)*332)+4" y="78"
                  text-anchor="middle" font-size="9" fill="var(--fx-muted)">{{ m.label }}</text>
          </svg>

          <!-- Status bar -->
          <div class="crm-divider-label">Order Status Mix</div>
          <div class="crm-sbar">
            <div v-for="k in STATUS_KEYS" :key="k"
                 :style="{ width:bookings.length?(statusCounts[k]/bookings.length*100)+'%':0, background:STATUS_COLOR[k] }"
                 class="crm-sbar-seg" :title="`${label(k)}: ${statusCounts[k]}`"/>
          </div>
          <div class="crm-slegend">
            <span v-for="k in STATUS_KEYS" :key="k" class="crm-sleg">
              <span class="crm-sdot" :style="{ background:STATUS_COLOR[k] }"/>{{ label(k) }} <b>{{ statusCounts[k] }}</b>
            </span>
          </div>
        </div>

        <!-- Right col -->
        <div class="crm-right">

          <div class="glass-card crm-panel">
            <div class="crm-panel-hdr">
              <span class="crm-panel-title">Top Providers</span>
              <span class="crm-panel-sub">By revenue</span>
            </div>
            <div v-if="!topProviders.length" class="crm-empty">No completed bookings yet</div>
            <div v-for="(p,i) in topProviders" :key="p.name" class="crm-prow">
              <div class="crm-prank">#{{ i+1 }}</div>
              <div class="crm-pbar-wrap">
                <div class="crm-pname">{{ p.name }}</div>
                <div class="crm-pbar"><div class="crm-pfill" :style="{ width:(p.rev/topProviders[0].rev*100)+'%' }"/></div>
              </div>
              <div class="crm-prev">RM {{ p.rev.toFixed(0) }}</div>
            </div>
          </div>

          <div class="glass-card crm-panel">
            <div class="crm-panel-hdr"><span class="crm-panel-title">Bookings</span></div>
            <div class="crm-chips">
              <span v-for="f in FILTERS" :key="f" class="crm-chip" :class="{active:filterStatus===f}" @click="filterStatus=f">
                {{ f==='All'?'All':label(f) }}
              </span>
            </div>
            <div class="crm-blist">
            <div v-for="b in filtered" :key="b.id" class="crm-brow"
                 role="button" @click="router.push({ name: 'order-detail', params: { id: b.id } })">
              <div class="crm-bdot" :style="{ background:STATUS_COLOR[b.status] }"/>
              <div class="crm-binfo">
                <span class="crm-bname">{{ b.customer?.name||'—' }}</span>
                <span class="crm-bcat"> · {{ b.category?.name }}</span>
              </div>
              <span class="crm-btotal">RM {{ b.total }}</span>
              <span class="material-symbols-outlined" style="font-size:16px;color:var(--fx-muted);margin-left:4px">chevron_right</span>
            </div>
            <div v-if="!filtered.length" class="crm-empty">No bookings</div>
            </div>
          </div>

        </div>
      </div>

      <!-- ── ORDER QUEUE FLOW ──────────────────────────────────────── -->
      <div class="crm-section-hdr">
        <span class="crm-section-title">Order Queue Flow</span>
        <span class="crm-section-sub">Trend per status · last 6 months</span>
      </div>
      <div class="crm-queue-grid">
        <div v-for="q in queueCards" :key="q.key" class="crm-qcard glass-card">
          <div class="crm-qcard-head">
            <span class="material-symbols-outlined crm-qicon" :style="{ color:q.color }">{{ q.icon }}</span>
            <div>
              <div class="crm-qcount" :style="{ color:q.color }">{{ q.count }}</div>
              <div class="crm-qlabel">{{ q.label }}</div>
            </div>
            <div class="crm-qpct" :style="{ color:q.color }">{{ q.pct }}%</div>
          </div>
          <!-- % bar -->
          <div class="crm-qbar-bg"><div class="crm-qbar-fill" :style="{ width:q.pct+'%', background:q.color }"/></div>
          <!-- Sparkline trend for this status -->
          <svg class="crm-qspark" :viewBox="`0 0 90 38`" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient :id="`qg${q.key}`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" :stop-color="q.color" stop-opacity="0.22"/>
                <stop offset="100%" :stop-color="q.color" stop-opacity="0"/>
              </linearGradient>
            </defs>
            <path :d="q.spark.area" :fill="`url(#qg${q.key})`"/>
            <polyline :points="q.spark.points" fill="none" :stroke="q.color" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle v-for="(p,pi) in q.spark.dots" :key="pi" :cx="p.x" :cy="p.y" r="2.2" :fill="q.color"/>
          </svg>
          <div class="crm-qmonths">
            <span v-for="(l,li) in q.labels" :key="li">{{ li===0||li===q.labels.length-1?l:'' }}</span>
          </div>
        </div>
      </div>

      <!-- ── ORDER VOLUME TREND ────────────────────────────────────── -->
      <div class="glass-card crm-panel" style="margin-top:14px">
        <div class="crm-panel-hdr">
          <span class="crm-panel-title">Order Volume Trend</span>
          <span class="crm-panel-sub">Total orders per month</span>
        </div>
        <svg class="crm-line-svg" style="height:80px" viewBox="0 0 340 80" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="vtG" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.22"/>
              <stop offset="100%" stop-color="#3b82f6" stop-opacity="0"/>
            </linearGradient>
          </defs>
          <path :d="volTrend.area" fill="url(#vtG)"/>
          <polyline :points="volTrend.points" fill="none" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          <circle v-for="(p,i) in volTrend.dots" :key="i" :cx="p.x" :cy="p.y" r="3" fill="#3b82f6"/>
          <text v-for="(m,i) in monthly" :key="m.key"
                :x="Math.round(i/(monthly.length-1)*332)+4" y="78"
                text-anchor="middle" font-size="9" fill="var(--fx-muted)">{{ m.label }}</text>
        </svg>
      </div>

    </template>
  </div>
</template>

<style scoped>
@keyframes spin { to { transform: rotate(360deg); } }

.crm { max-width: 1100px; }

/* ── Header ─────────────────────────────────────────────────────────── */
.crm-hdr    { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 10px; }
.crm-title  { font-size: 20px; font-weight: 800; margin: 0; letter-spacing: -0.02em; }
.crm-sub    { font-size: 11px; color: var(--fx-muted); margin: 3px 0 0; }
.crm-badges { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.crm-live         { font-size: 10px; font-weight: 800; letter-spacing: 0.08em; color: var(--fx-success); background: var(--fx-success-soft); padding: 3px 10px; border-radius: 999px; }
.crm-stripe-badge { font-size: 10px; font-weight: 700; color: #635bff; background: rgba(99,91,255,0.10); padding: 3px 10px; border-radius: 999px; }
.crm-spin { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 60px 0; }

/* ── Top grid ─────────────────────────────────────────────────────── */
.crm-top-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 10px;
  margin-bottom: 14px;
}
@media (min-width: 760px) {
  .crm-top-grid { grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr; }
}

/* Revenue hero */
.crm-hero {
  grid-column: 1 / -1;
  padding: 18px 20px 12px;
  background:
    radial-gradient(ellipse 70% 55% at 8% 12%, rgba(255,200,160,0.38) 0%, transparent 58%),
    radial-gradient(ellipse 45% 40% at 92% 82%, rgba(255,100,60,0.22) 0%, transparent 55%),
    rgba(255,102,53,0.20);
  border-color: rgba(255,160,120,0.45);
  box-shadow:
    inset 0 1.5px 0 rgba(255,255,255,0.70),
    0 8px 32px rgba(255,102,53,0.20),
    0 2px 8px rgba(255,102,53,0.12);
}
@media (min-width: 760px) { .crm-hero { grid-column: 1 / 2; } }

.crm-hero-eyebrow { display: flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: rgba(255,255,255,0.75); margin-bottom: 8px; }
.crm-hero-val     { font-size: 30px; font-weight: 900; color: #fff; letter-spacing: -0.03em; line-height: 1; text-shadow: 0 2px 12px rgba(180,60,0,0.30); }
.crm-hero-hint    { font-size: 10px; color: rgba(255,255,255,0.60); margin-top: 5px; margin-bottom: 10px; }
.crm-hero-spark   { width: 100%; height: 50px; display: block; }

/* Metric cards */
.crm-mc {
  padding: 14px 10px;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 3px; text-align: center;
}
.crm-mc-icon  { font-size: 22px; margin-bottom: 3px; font-variation-settings: 'FILL' 1; }
.crm-mc-val   { font-size: 20px; font-weight: 800; color: var(--fx-text); line-height: 1; }
.crm-mc-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--fx-muted); }

/* ── 2-col main grid ─────────────────────────────────────────────── */
.crm-grid  { display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px; }
@media (min-width: 760px) { .crm-grid { flex-direction: row; align-items: flex-start; gap: 14px; } }

.crm-panel { padding: 16px; }
@media (min-width: 760px) { .crm-panel { flex: 1.5; min-width: 0; } }
.crm-right { display: flex; flex-direction: column; gap: 12px; }
@media (min-width: 760px) { .crm-right { flex: 1; min-width: 0; } }
@media (min-width: 760px) { .crm-blist { max-height: 200px; overflow-y: auto; } }

.crm-panel-hdr   { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.crm-panel-title { font-size: 13px; font-weight: 700; }
.crm-panel-sub   { font-size: 11px; color: var(--fx-muted); }
.crm-empty       { font-size: 12px; color: var(--fx-muted); padding: 16px 0; text-align: center; }

.crm-divider-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
                     color: var(--fx-muted); margin: 14px 0 8px;
                     padding-top: 14px; border-top: 0.5px solid rgba(255,255,255,0.40); }

/* SVG charts */
.crm-bar-svg  { width: 100%; height: 128px; display: block; overflow: visible; }
.crm-line-svg { width: 100%; height: 60px; display: block; overflow: visible; }

/* Status bar */
.crm-sbar      { height: 7px; border-radius: 999px; display: flex; overflow: hidden; background: rgba(0,0,0,0.06); margin-top: 0; }
.crm-sbar-seg  { transition: width 0.5s ease; }
.crm-slegend   { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.crm-sleg      { display: flex; align-items: center; gap: 3px; font-size: 10px; color: var(--fx-muted); text-transform: capitalize; }
.crm-sleg b    { color: var(--fx-text); font-weight: 700; }
.crm-sdot      { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

/* Provider rows */
.crm-prow      { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 0.5px solid rgba(255,255,255,0.35); }
.crm-prow:last-child { border-bottom: none; }
.crm-prank     { width: 18px; font-size: 11px; font-weight: 800; color: var(--fx-muted); flex-shrink: 0; }
.crm-pbar-wrap { flex: 1; min-width: 0; }
.crm-pname     { font-size: 12px; font-weight: 600; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.crm-pbar      { height: 5px; border-radius: 999px; background: rgba(0,0,0,0.06); overflow: hidden; }
.crm-pfill     { height: 100%; background: linear-gradient(to right, #FF8056, #FF6635); border-radius: 999px; }
.crm-prev      { font-size: 12px; font-weight: 700; color: var(--fx-accent); white-space: nowrap; flex-shrink: 0; }

/* Chips */
/* Single scrollable row �?6 chips (incl. "In Progress") don't fit the panel
   width; wrapping orphaned the last chip ("Reviewed") on its own line. */
.crm-chips { display: flex; flex-wrap: nowrap; gap: 4px; margin-bottom: 10px;
             overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
.crm-chips::-webkit-scrollbar { display: none; }
.crm-chip  { font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 999px;
             white-space: nowrap; flex: 0 0 auto;
             background: rgba(255,255,255,0.22); border: 0.5px solid rgba(255,255,255,0.45);
             color: var(--fx-muted); cursor: pointer; text-transform: capitalize; transition: all 0.15s; }
.crm-chip.active { background: var(--fx-accent); border-color: transparent; color: #fff; }

/* Booking rows */
.crm-brow  { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 0.5px solid rgba(255,255,255,0.30); cursor: pointer; }
.crm-brow:hover { opacity: 0.7; }
.crm-brow:last-child { border-bottom: none; }
.crm-bdot  { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.crm-binfo { flex: 1; min-width: 0; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.crm-bname { font-weight: 600; }
.crm-bcat  { color: var(--fx-muted); }
.crm-btotal { font-size: 12px; font-weight: 700; flex-shrink: 0; }

/* ── Queue flow ─────────────────────────────────────────────────────── */
.crm-section-hdr  { display: flex; align-items: baseline; gap: 8px; margin-bottom: 10px; }
.crm-section-title { font-size: 13px; font-weight: 700; }
.crm-section-sub   { font-size: 11px; color: var(--fx-muted); }

.crm-queue-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
@media (min-width: 580px) { .crm-queue-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 880px) { .crm-queue-grid { grid-template-columns: repeat(5, 1fr); } }

.crm-qcard { padding: 14px 12px 8px; }

.crm-qcard-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.crm-qicon  { font-size: 20px; font-variation-settings: 'FILL' 1; flex-shrink: 0; }
.crm-qcount { font-size: 22px; font-weight: 900; line-height: 1; }
.crm-qlabel { font-size: 9px; font-weight: 700; text-transform: capitalize; color: var(--fx-muted); letter-spacing: 0.04em; }
.crm-qpct   { font-size: 14px; font-weight: 800; margin-left: auto; flex-shrink: 0; }

.crm-qbar-bg   { height: 4px; border-radius: 999px; background: rgba(0,0,0,0.07); overflow: hidden; margin-bottom: 10px; }
.crm-qbar-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; min-width: 2px; }

.crm-qspark  { width: 100%; height: 38px; display: block; overflow: visible; }
.crm-qmonths { display: flex; justify-content: space-between; margin-top: 3px; }
.crm-qmonths span { font-size: 8px; color: var(--fx-muted); opacity: 0.6; min-width: 12px; }
</style>