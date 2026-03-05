<template>
  <div class="req-progress" id="requirement-progress-panel">

    <!-- ── Top Row: Circle + Stats ── -->
    <div class="progress-top">

      <!-- Circular SVG gauge -->
      <div class="circle-wrap" :title="`${completionPct}% complete`">
        <svg class="circle-svg" viewBox="0 0 120 120" aria-hidden="true">
          <!-- Background ring -->
          <circle
            cx="60" cy="60" r="50"
            fill="none"
            stroke="#1e293b"
            stroke-width="10"
          />
          <!-- Progress arc -->
          <circle
            cx="60" cy="60" r="50"
            fill="none"
            :stroke="arcColor"
            stroke-width="10"
            stroke-linecap="round"
            stroke-dasharray="314.16"
            :stroke-dashoffset="arcOffset"
            transform="rotate(-90 60 60)"
            class="progress-arc"
          />
        </svg>
        <div class="circle-label">
          <span class="circle-pct">{{ completionPct }}<sup>%</sup></span>
          <span class="circle-sub">complete</span>
        </div>
      </div>

      <!-- Stat Chips -->
      <div class="stat-grid">
        <div class="stat-block stat-block--approved">
          <span class="stat-block__num">{{ counts.approved }}</span>
          <span class="stat-block__label">Approved</span>
        </div>
        <div class="stat-block stat-block--pending">
          <span class="stat-block__num">{{ counts.pending }}</span>
          <span class="stat-block__label">Pending</span>
        </div>
        <div class="stat-block stat-block--rejected">
          <span class="stat-block__num">{{ counts.rejected }}</span>
          <span class="stat-block__label">Rejected</span>
        </div>
        <div class="stat-block stat-block--missing">
          <span class="stat-block__num">{{ counts.missing + counts.expired }}</span>
          <span class="stat-block__label">Missing/Expired</span>
        </div>
      </div>
    </div>

    <!-- Eligibility Banner -->
    <div :class="['eligibility-banner', eligible ? 'eligibility-banner--ok' : 'eligibility-banner--no']">
      <span class="eligibility-banner__icon">{{ eligible ? '✅' : '⛔' }}</span>
      <div class="eligibility-banner__text">
        <p class="eligibility-banner__title">
          {{ eligible ? 'Eligible for Cash Grant Distribution' : 'Not Yet Eligible for Distribution' }}
        </p>
        <p v-if="!eligible && blockingReasons.length" class="eligibility-banner__reasons">
          {{ blockingReasons[0] }}
          <span v-if="blockingReasons.length > 1"> (+{{ blockingReasons.length - 1 }} more)</span>
        </p>
      </div>
    </div>

    <!-- ── Checklist ── -->
    <div class="checklist" role="list" aria-label="Document Checklist">

      <!-- Required items first, then optional -->
      <template v-for="(item, idx) in sortedItems" :key="`${item.type}-${idx}`">

        <!-- Section divider -->
        <div v-if="idx === firstOptionalIdx && firstOptionalIdx !== sortedItems.length" class="divider">
          <span>Optional Documents</span>
        </div>

        <div
          :class="['checklist-row', rowClass(item.status), { 'checklist-row--optional': !item.is_required }]"
          role="listitem"
          :id="`checklist-row-${item.type}-${idx}`"
        >
          <!-- Status Icon -->
          <div class="row-icon" :title="statusTitle(item.status)">
            <span>{{ statusIcon(item.status) }}</span>
          </div>

          <!-- Info -->
          <div class="row-info">
            <div class="row-info__top">
              <span class="row-info__label">{{ item.label }}</span>
              <span v-if="item.member_name" class="row-info__member">· {{ item.member_name }}</span>
              <span v-if="!item.is_required" class="row-info__optional-tag">Optional</span>
            </div>
            <p class="row-info__desc">{{ item.description }}</p>

            <!-- Rejection reason -->
            <p v-if="item.status === 'rejected' && item.document?.rejection_reason" class="row-info__rejection">
              ✕ {{ item.document.rejection_reason }}
            </p>

            <!-- Expiry warning -->
            <p v-if="item.status === 'expired'" class="row-info__expired">
              ⚠ Document expired on {{ item.document?.expiration_date }}
            </p>
            <p v-else-if="item.document?.is_expired === false && item.document?.expiration_date && item.document?.expiration_date_days_away <= 30" class="row-info__expiring">
              ⏳ Expires in {{ item.document?.expiration_date_days_away }}d
            </p>
          </div>

          <!-- Right: status badge + action -->
          <div class="row-actions">
            <span class="badge" :class="badgeClass(item.status)">
              {{ statusLabel(item.status) }}
            </span>

            <!-- View existing -->
            <button
              v-if="item.document"
              class="action-pill action-pill--view"
              :id="`view-doc-${item.type}-${idx}`"
              @click="$emit('view-document', item)"
              title="View / Preview"
            >👁 View</button>

            <!-- Upload / Re-upload -->
            <button
              v-if="canUpload && ['missing', 'rejected', 'expired'].includes(item.status)"
              class="action-pill action-pill--upload"
              :id="`upload-doc-${item.type}-${idx}`"
              @click="$emit('upload', item.type, item.member_id)"
              title="Upload document"
            >↑ {{ item.status === 'missing' ? 'Upload' : 'Re-upload' }}</button>
          </div>
        </div>
      </template>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="skeleton-list">
      <div v-for="i in 5" :key="i" class="skeleton-row">
        <div class="skeleton-icon"></div>
        <div class="skeleton-body">
          <div class="skeleton-line skeleton-line--title"></div>
          <div class="skeleton-line skeleton-line--sub"></div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  /** items from RequirementTrackingService::getCompletionStatus().items */
  items:           { type: Array,   default: () => [] },
  completionPct:   { type: Number,  default: 0 },
  eligible:        { type: Boolean, default: false },
  blockingReasons: { type: Array,   default: () => [] },
  canUpload:       { type: Boolean, default: false },
  loading:         { type: Boolean, default: false },
})

defineEmits(['view-document', 'upload'])

// ── Computed ──────────────────────────────────────────────────────────────────

const counts = computed(() => {
  const c = { approved: 0, pending: 0, rejected: 0, missing: 0, expired: 0 }
  for (const item of props.items) {
    const s = item.status
    if (s === 'approved') c.approved++
    else if (s === 'pending')  c.pending++
    else if (s === 'rejected') c.rejected++
    else if (s === 'expired')  c.expired++
    else                       c.missing++
  }
  return c
})

// Required first, then optional; within each group approved → pending → rejected → expired → missing
const statusOrder = { approved: 0, pending: 1, rejected: 2, expired: 3, missing: 4 }
const sortedItems = computed(() => {
  const required  = props.items.filter(i => i.is_required)
  const optional  = props.items.filter(i => !i.is_required)
  const sorter    = (a, b) => (statusOrder[a.status] ?? 5) - (statusOrder[b.status] ?? 5)
  return [...required.sort(sorter), ...optional.sort(sorter)]
})

const firstOptionalIdx = computed(() =>
  sortedItems.value.findIndex(i => !i.is_required)
)

// SVG circle: circumference = 2π × 50 = 314.16
const arcOffset  = computed(() => 314.16 - (314.16 * props.completionPct) / 100)
const arcColor   = computed(() => {
  if (props.completionPct === 100) return '#22c55e'
  if (props.completionPct >= 60)   return '#6366f1'
  if (props.completionPct >= 30)   return '#f59e0b'
  return '#ef4444'
})

// ── Status helpers ────────────────────────────────────────────────────────────

const statusIcon = (s) => ({ approved: '✅', pending: '⏳', rejected: '❌', expired: '⚠️', missing: '⭕' }[s] ?? '⭕')
const statusTitle = (s) => ({ approved: 'Approved', pending: 'Awaiting review', rejected: 'Rejected', expired: 'Expired', missing: 'Not submitted' }[s] ?? s)
const statusLabel = (s) => ({ approved: 'Approved', pending: 'Pending', rejected: 'Rejected', expired: 'Expired', missing: 'Not Submitted' }[s] ?? 'Unknown')

const rowClass = (s) => ({
  'checklist-row--approved': s === 'approved',
  'checklist-row--pending':  s === 'pending',
  'checklist-row--rejected': s === 'rejected',
  'checklist-row--expired':  s === 'expired',
  'checklist-row--missing':  s === 'missing',
})

const badgeClass = (s) => ({
  'badge--approved': s === 'approved',
  'badge--pending':  s === 'pending',
  'badge--rejected': s === 'rejected',
  'badge--expired':  s === 'expired',
  'badge--missing':  s === 'missing',
})
</script>

<style scoped>
.req-progress { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1.25rem; }

/* ── Top Row ── */
.progress-top {
  display: flex; align-items: center; gap: 1.75rem; flex-wrap: wrap;
}

/* Circle */
.circle-wrap {
  position: relative; width: 120px; height: 120px; flex-shrink: 0;
}
.circle-svg { width: 100%; height: 100%; }
.progress-arc { transition: stroke-dashoffset .6s cubic-bezier(.4,0,.2,1), stroke .4s; }
.circle-label {
  position: absolute; inset: 0;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
}
.circle-pct {
  font-size: 1.6rem; font-weight: 800; color: #f1f5f9; line-height: 1;
}
.circle-pct sup { font-size: .75rem; font-weight: 600; vertical-align: super; }
.circle-sub { font-size: .65rem; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-top: .15rem; }

/* Stat Grid */
.stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; flex: 1; }
.stat-block {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: .6rem .5rem; border-radius: 10px; border: 1px solid transparent; text-align: center;
}
.stat-block__num   { font-size: 1.5rem; font-weight: 800; line-height: 1; }
.stat-block__label { font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-top: .15rem; opacity: .8; }
.stat-block--approved { background: rgba(20,83,45,.2);  border-color: rgba(34,197,94,.2);  color: #4ade80; }
.stat-block--pending  { background: rgba(120,83,14,.2); border-color: rgba(251,191,36,.2); color: #fbbf24; }
.stat-block--rejected { background: rgba(127,29,29,.2); border-color: rgba(239,68,68,.2);  color: #f87171; }
.stat-block--missing  { background: rgba(30,41,59,.4);  border-color: rgba(71,85,105,.3);  color: #64748b; }

/* ── Eligibility Banner ── */
.eligibility-banner {
  display: flex; align-items: flex-start; gap: .85rem;
  border-radius: 12px; padding: .9rem 1.1rem; border: 1px solid transparent;
}
.eligibility-banner--ok  { background: rgba(20,83,45,.2);  border-color: rgba(34,197,94,.3); }
.eligibility-banner--no  { background: rgba(127,29,29,.15); border-color: rgba(239,68,68,.2); }
.eligibility-banner__icon { font-size: 1.5rem; flex-shrink: 0; }
.eligibility-banner__title {
  font-size: .9rem; font-weight: 700; margin: 0;
  color: v-bind("eligible ? '#4ade80' : '#f87171'");
}
.eligibility-banner__reasons { font-size: .78rem; color: #94a3b8; margin: .25rem 0 0; }

/* ── Checklist ── */
.checklist { display: flex; flex-direction: column; gap: .45rem; }

.divider {
  display: flex; align-items: center; gap: .75rem; padding: .35rem 0;
  font-size: .72rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: .06em;
}
.divider::before, .divider::after {
  content: ''; flex: 1; height: 1px; background: #1e293b;
}

.checklist-row {
  display: flex; align-items: flex-start; gap: .9rem;
  background: rgba(15,23,42,.5); border: 1px solid #1e293b;
  border-radius: 11px; padding: .85rem 1rem;
  border-left-width: 3px; transition: background .15s;
}
.checklist-row:hover { background: rgba(30,41,59,.5); }
.checklist-row--optional { opacity: .75; }

.checklist-row--approved { border-left-color: #22c55e; }
.checklist-row--pending  { border-left-color: #f59e0b; }
.checklist-row--rejected { border-left-color: #ef4444; background: rgba(127,29,29,.08); }
.checklist-row--expired  { border-left-color: #ef4444; background: rgba(127,29,29,.06); }
.checklist-row--missing  { border-left-color: #334155; }

.row-icon { font-size: 1.3rem; flex-shrink: 0; padding-top: .05rem; }
.row-info { flex: 1; min-width: 0; }
.row-info__top { display: flex; align-items: baseline; gap: .4rem; flex-wrap: wrap; margin-bottom: .2rem; }
.row-info__label  { color: #e2e8f0; font-size: .9rem; font-weight: 600; }
.row-info__member { color: #64748b; font-size: .78rem; }
.row-info__optional-tag {
  background: rgba(71,85,105,.2); border: 1px solid #334155; color: #64748b;
  border-radius: 9999px; padding: .1rem .5rem; font-size: .67rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
}
.row-info__desc      { color: #475569; font-size: .78rem; margin: 0; line-height: 1.4; }
.row-info__rejection { color: #f87171; font-size: .76rem; margin: .3rem 0 0; }
.row-info__expired   { color: #ef4444; font-size: .76rem; margin: .3rem 0 0; font-weight: 600; }
.row-info__expiring  { color: #f59e0b; font-size: .76rem; margin: .3rem 0 0; }

.row-actions { display: flex; flex-direction: column; align-items: flex-end; gap: .45rem; flex-shrink: 0; }

/* Action pills */
.action-pill {
  display: inline-flex; align-items: center; gap: .3rem;
  padding: .28rem .7rem; border-radius: 9999px; font-size: .75rem; font-weight: 600;
  cursor: pointer; border: 1px solid transparent; transition: all .15s; white-space: nowrap;
}
.action-pill--view {
  background: rgba(99,102,241,.12); border-color: rgba(99,102,241,.25); color: #a5b4fc;
}
.action-pill--view:hover { background: rgba(99,102,241,.25); }
.action-pill--upload {
  background: rgba(34,197,94,.12); border-color: rgba(34,197,94,.25); color: #4ade80;
}
.action-pill--upload:hover { background: rgba(34,197,94,.25); }

/* Badges */
.badge {
  padding: .2rem .6rem; border-radius: 9999px; font-size: .7rem;
  font-weight: 700; text-transform: capitalize; white-space: nowrap;
}
.badge--approved { background: #14532d22; color: #4ade80; border: 1px solid #14532d55; }
.badge--pending  { background: #78350f22; color: #fbbf24; border: 1px solid #78350f55; }
.badge--rejected { background: #7f1d1d22; color: #f87171; border: 1px solid #7f1d1d55; }
.badge--expired  { background: #7f1d1d22; color: #fca5a5; border: 1px solid #7f1d1d55; }
.badge--missing  { background: #1e293b;   color: #475569; border: 1px solid #334155;   }

/* Loading Skeleton */
.skeleton-list { display: flex; flex-direction: column; gap: .5rem; }
.skeleton-row  { display: flex; align-items: center; gap: .85rem; padding: .85rem 1rem; background: rgba(15,23,42,.5); border: 1px solid #1e293b; border-radius: 11px; }
.skeleton-icon { width: 2rem; height: 2rem; border-radius: 50%; background: #1e293b; flex-shrink: 0; animation: pulse 1.5s ease-in-out infinite; }
.skeleton-body { flex: 1; display: flex; flex-direction: column; gap: .4rem; }
.skeleton-line { height: .75rem; background: #1e293b; border-radius: 4px; animation: pulse 1.5s ease-in-out infinite; }
.skeleton-line--title { width: 55%; }
.skeleton-line--sub   { width: 80%; }

@keyframes pulse {
  0%, 100% { opacity: .4; }
  50%       { opacity: .9; }
}
</style>
