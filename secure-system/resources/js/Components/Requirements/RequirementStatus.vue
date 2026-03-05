<template>
  <div class="requirement-status" id="requirement-status-panel">
    <!-- Header Summary -->
    <div class="status-summary">
      <div class="progress-wrap">
        <div class="progress-header">
          <span class="progress-label">Completion</span>
          <span class="progress-pct">{{ completionPct }}%</span>
        </div>
        <div class="progress-track">
          <div
            class="progress-fill"
            :style="{ width: completionPct + '%' }"
            :class="progressClass"
          ></div>
        </div>
        <p class="progress-sub">{{ approvedCount }} of {{ totalRequired }} required documents approved</p>
      </div>

      <div class="stat-chips">
        <div class="stat-chip stat-chip--approved">
          <span class="stat-chip__num">{{ approvedCount }}</span>Approved
        </div>
        <div class="stat-chip stat-chip--pending">
          <span class="stat-chip__num">{{ pendingCount }}</span>Pending
        </div>
        <div class="stat-chip stat-chip--missing">
          <span class="stat-chip__num">{{ missingCount }}</span>Missing
        </div>
        <div class="stat-chip stat-chip--expired" v-if="expiredCount">
          <span class="stat-chip__num">{{ expiredCount }}</span>Expired
        </div>
      </div>
    </div>

    <!-- Per-Type List -->
    <div class="type-list">
      <div
        v-for="type in allTypes"
        :key="type.value"
        :class="['type-row', typeRowClass(type.value)]"
      >
        <div class="type-row__icon">{{ typeIcon(type.value) }}</div>
        <div class="type-row__info">
          <p class="type-row__name">{{ type.label }}</p>
          <p class="type-row__sub">{{ typeStatus(type.value) }}</p>
        </div>
        <div class="type-row__right">
          <span class="badge" :class="statusBadgeClass(typeStatusKey(type.value))">
            {{ typeStatusLabel(type.value) }}
          </span>
          <button
            v-if="isMissing(type.value) && canUpload"
            class="quick-upload-btn"
            :id="`quick-upload-${type.value}`"
            @click="$emit('quick-upload', type.value)"
          >+ Upload</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  grouped:      { type: Array, default: () => [] },  // from index API: [{type, count, documents:[]}]
  missingTypes: { type: Array, default: () => [] },
  canUpload:    { type: Boolean, default: false },
})

defineEmits(['quick-upload'])

const allTypes = [
  { value: 'birth_certificate', label: 'Birth Certificate' },
  { value: 'school_record',     label: 'School Record' },
  { value: 'health_record',     label: 'Health Record' },
  { value: 'proof_of_income',   label: 'Proof of Income' },
  { value: 'valid_id',          label: 'Valid ID' },
  { value: 'other',             label: 'Other Document' },
]

const totalRequired = allTypes.length

const groupMap = computed(() => {
  const m = {}
  for (const g of props.grouped) { m[g.type] = g }
  return m
})

// Best doc per type = latest approved, or latest pending
const bestDoc = (typeValue) => {
  const g = groupMap.value[typeValue]
  if (!g) return null
  const approved = g.documents.filter(d => d.approval_status === 'approved')
  if (approved.length) return approved[0]
  return g.documents[0] ?? null
}

const approvedCount = computed(() =>
  allTypes.filter(t => {
    const doc = bestDoc(t.value)
    return doc?.approval_status === 'approved' && !doc?.is_expired
  }).length
)
const pendingCount = computed(() =>
  allTypes.filter(t => bestDoc(t.value)?.approval_status === 'pending').length
)
const missingCount = computed(() => props.missingTypes.length)
const expiredCount = computed(() =>
  allTypes.filter(t => bestDoc(t.value)?.is_expired).length
)
const completionPct = computed(() => Math.round((approvedCount.value / totalRequired) * 100))

const progressClass = computed(() => {
  if (completionPct.value === 100) return 'progress-fill--complete'
  if (completionPct.value >= 60)   return 'progress-fill--good'
  return 'progress-fill--low'
})

const isMissing = (typeValue) => props.missingTypes.includes(typeValue)

const typeStatusKey = (typeValue) => {
  if (isMissing(typeValue)) return 'missing'
  const doc = bestDoc(typeValue)
  if (!doc) return 'missing'
  if (doc.is_expired) return 'expired'
  return doc.approval_status
}

const typeStatusLabel = (typeValue) => {
  const key = typeStatusKey(typeValue)
  return { missing: 'Missing', pending: 'Pending', approved: 'Approved', rejected: 'Rejected', expired: 'Expired' }[key] ?? key
}

const typeStatus = (typeValue) => {
  const g = groupMap.value[typeValue]
  if (!g) return 'No document submitted'
  const doc = bestDoc(typeValue)
  const count = g.count
  if (doc?.is_expired) return `${count} file(s) — latest expired`
  if (doc?.approval_status === 'approved') return `${count} file(s) — latest approved`
  if (doc?.approval_status === 'pending')  return `${count} file(s) — awaiting review`
  return `${count} file(s) — rejected`
}

const typeRowClass = (typeValue) => {
  const key = typeStatusKey(typeValue)
  return {
    'type-row--missing':  key === 'missing',
    'type-row--approved': key === 'approved',
    'type-row--pending':  key === 'pending',
    'type-row--rejected': key === 'rejected',
    'type-row--expired':  key === 'expired',
  }
}

const typeIcon = (v) => ({
  birth_certificate: '📋', school_record: '🎓', health_record: '💉',
  proof_of_income: '💰', valid_id: '🪪', other: '📄',
}[v] ?? '📄')

const statusBadgeClass = (key) => ({
  'badge--missing':  key === 'missing',
  'badge--pending':  key === 'pending',
  'badge--approved': key === 'approved',
  'badge--rejected': key === 'rejected',
  'badge--expired':  key === 'expired',
})
</script>

<style scoped>
.requirement-status { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1.25rem; }

/* Summary */
.status-summary {
  background: rgba(15,23,42,.6); border: 1px solid #1e293b;
  border-radius: 12px; padding: 1.25rem;
}
.progress-wrap { margin-bottom: 1.1rem; }
.progress-header { display: flex; justify-content: space-between; margin-bottom: .4rem; }
.progress-label { color: #64748b; font-size: .82rem; font-weight: 500; }
.progress-pct   { color: #f1f5f9; font-size: .9rem; font-weight: 700; }
.progress-track {
  background: #1e293b; border-radius: 9999px; height: 10px; overflow: hidden;
}
.progress-fill { height: 100%; border-radius: 9999px; transition: width .5s ease; }
.progress-fill--complete { background: linear-gradient(90deg, #4ade80, #22c55e); }
.progress-fill--good     { background: linear-gradient(90deg, #38bdf8, #6366f1); }
.progress-fill--low      { background: linear-gradient(90deg, #f59e0b, #ef4444); }
.progress-sub { color: #475569; font-size: .78rem; margin: .4rem 0 0; }

.stat-chips { display: flex; gap: .6rem; flex-wrap: wrap; }
.stat-chip {
  display: flex; flex-direction: column; align-items: center;
  padding: .5rem .9rem; border-radius: 10px; border: 1px solid transparent;
  font-size: .73rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
}
.stat-chip__num { font-size: 1.4rem; font-weight: 800; line-height: 1; margin-bottom: .15rem; }
.stat-chip--approved { background: rgba(20,83,45,.2);  border-color: rgba(34,197,94,.2);  color: #4ade80; }
.stat-chip--pending  { background: rgba(120,83,14,.2); border-color: rgba(251,191,36,.2);  color: #fbbf24; }
.stat-chip--missing  { background: rgba(30,41,59,.4);  border-color: rgba(71,85,105,.3);   color: #64748b; }
.stat-chip--expired  { background: rgba(127,29,29,.2); border-color: rgba(239,68,68,.2);   color: #f87171; }

/* Type List */
.type-list { display: flex; flex-direction: column; gap: .5rem; }
.type-row {
  display: flex; align-items: center; gap: .85rem;
  background: rgba(15,23,42,.5); border: 1px solid #1e293b;
  border-radius: 10px; padding: .75rem 1rem; transition: border-color .2s;
}
.type-row--approved { border-left: 3px solid #22c55e; }
.type-row--pending  { border-left: 3px solid #f59e0b; }
.type-row--missing  { border-left: 3px solid #334155; opacity: .75; }
.type-row--rejected { border-left: 3px solid #ef4444; }
.type-row--expired  { border-left: 3px solid #ef4444; background: rgba(127,29,29,.08); }

.type-row__icon { font-size: 1.5rem; flex-shrink: 0; }
.type-row__info { flex: 1; min-width: 0; }
.type-row__name { color: #e2e8f0; font-size: .88rem; font-weight: 600; margin: 0; }
.type-row__sub  { color: #475569; font-size: .76rem; margin: .15rem 0 0; }
.type-row__right { display: flex; align-items: center; gap: .6rem; flex-shrink: 0; }

.quick-upload-btn {
  background: rgba(99,102,241,.15); border: 1px solid rgba(99,102,241,.3); color: #a5b4fc;
  border-radius: 6px; padding: .25rem .65rem; font-size: .75rem; font-weight: 600;
  cursor: pointer; transition: all .15s;
}
.quick-upload-btn:hover { background: rgba(99,102,241,.3); }

/* Badges */
.badge { padding: .2rem .6rem; border-radius: 9999px; font-size: .72rem; font-weight: 600; text-transform: capitalize; white-space: nowrap; }
.badge--approved { background: #14532d22; color: #4ade80; border: 1px solid #14532d55; }
.badge--pending  { background: #78350f22; color: #fbbf24; border: 1px solid #78350f55; }
.badge--rejected { background: #7f1d1d22; color: #f87171; border: 1px solid #7f1d1d55; }
.badge--missing  { background: #1e293b;   color: #475569; border: 1px solid #334155;   }
.badge--expired  { background: #7f1d1d22; color: #fca5a5; border: 1px solid #7f1d1d55; }
</style>
