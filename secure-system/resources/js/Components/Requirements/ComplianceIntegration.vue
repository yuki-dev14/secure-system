<template>
  <div class="compliance-integration" id="compliance-integration-panel">

    <!-- ── Header with overall score ── -->
    <div class="header-row">
      <div>
        <h3 class="section-title">Compliance Overview</h3>
        <p class="section-sub">How submitted documents affect overall compliance standing</p>
      </div>
      <div :class="['overall-badge', overallClass]">
        {{ overallLabel }}
      </div>
    </div>

    <!-- ── Score Cards ── -->
    <div class="score-grid">
      <div
        v-for="dim in dimensions"
        :key="dim.key"
        :class="['score-card', dim.compliant ? 'score-card--ok' : 'score-card--no']"
        :id="`score-card-${dim.key}`"
      >
        <div class="score-card__top">
          <span class="score-card__icon">{{ dim.icon }}</span>
          <span class="score-card__label">{{ dim.label }}</span>
          <span :class="['score-card__status', dim.compliant ? 'text-green' : 'text-red']">
            {{ dim.compliant ? '✓' : '✗' }}
          </span>
        </div>

        <!-- Score bar -->
        <div class="score-bar-wrap">
          <div class="score-bar-track">
            <div
              class="score-bar-fill"
              :class="dim.compliant ? 'score-bar-fill--ok' : 'score-bar-fill--no'"
              :style="{ width: dim.percentage + '%' }"
            ></div>
          </div>
          <span class="score-bar-pct">{{ dim.percentage }}%</span>
        </div>

        <p class="score-card__desc">{{ dim.description }}</p>
      </div>
    </div>

    <!-- ── Document-to-Compliance Linkage ── -->
    <div class="link-section">
      <h4 class="link-section__title">📎 Document → Compliance Impact</h4>
      <p class="link-section__sub">
        Each required document contributes to a specific compliance dimension.
        Documents marked <em>Pending</em> or <em>Missing</em> directly block full compliance.
      </p>

      <div class="link-table">
        <div class="link-table__header">
          <span>Document Type</span>
          <span>Compliance Dimension</span>
          <span>Current Status</span>
          <span>Impact</span>
        </div>

        <div
          v-for="(link, idx) in documentLinks"
          :key="idx"
          :class="['link-table__row', link.blocking ? 'link-row--blocking' : '']"
          :id="`link-row-${link.type}-${idx}`"
        >
          <!-- Document type -->
          <div class="link-cell">
            <span class="link-doc-icon">{{ link.icon }}</span>
            <div>
              <p class="link-doc-name">{{ link.label }}</p>
              <p v-if="link.member_name" class="link-doc-member">{{ link.member_name }}</p>
            </div>
          </div>

          <!-- Compliance dimension -->
          <div class="link-cell">
            <span :class="['dim-tag', `dim-tag--${link.dimension}`]">{{ link.dimensionLabel }}</span>
          </div>

          <!-- Document status -->
          <div class="link-cell">
            <span class="badge" :class="badgeClass(link.docStatus)">{{ statusLabel(link.docStatus) }}</span>
          </div>

          <!-- Impact -->
          <div class="link-cell link-cell--impact">
            <span :class="['impact-chip', link.blocking ? 'impact-chip--blocking' : 'impact-chip--ok']">
              {{ link.blocking ? '🔴 Blocking' : '🟢 Satisfied' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Required Actions ── -->
    <div v-if="requiredActions.length" class="actions-panel">
      <h4 class="actions-panel__title">⚡ Required Actions to Achieve Compliance</h4>
      <div class="action-list">
        <div
          v-for="(action, idx) in requiredActions"
          :key="idx"
          :class="['action-item', `action-item--${action.priority}`]"
        >
          <span class="action-item__priority-dot"></span>
          <div class="action-item__body">
            <p class="action-item__title">{{ action.title }}</p>
            <p class="action-item__desc">{{ action.description }}</p>
          </div>
          <button
            v-if="action.canUpload && canUpload"
            class="action-btn"
            :id="`action-upload-${idx}`"
            @click="$emit('upload', action.type)"
          >↑ Upload</button>
          <span v-else-if="action.canApprove && canApprove" class="action-tag">
            Awaiting Approval
          </span>
        </div>
      </div>
    </div>

    <!-- ── Compliance History Sparkline ── -->
    <div v-if="history.length > 1" class="history-section">
      <h4 class="history-section__title">📈 Compliance Score History</h4>
      <div class="sparkline-wrap">
        <svg class="sparkline" :viewBox="`0 0 ${sparkW} 50`" preserveAspectRatio="none" aria-hidden="true">
          <polyline
            :points="sparklinePoints"
            fill="none"
            stroke="#6366f1"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
          />
          <!-- Area fill -->
          <polygon
            :points="sparklineArea"
            fill="url(#spark-grad)"
            opacity="0.25"
          />
          <defs>
            <linearGradient id="spark-grad" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#6366f1" />
              <stop offset="100%" stop-color="#6366f1" stop-opacity="0" />
            </linearGradient>
          </defs>
          <!-- Data dots -->
          <circle
            v-for="(pt, i) in sparkPoints"
            :key="i"
            :cx="pt.x" :cy="pt.y" r="3"
            fill="#6366f1"
            :title="`${history[i].label}: ${history[i].score}%`"
          />
        </svg>
        <!-- Labels -->
        <div class="spark-labels">
          <span v-for="(h, i) in history" :key="i" class="spark-label">{{ h.label }}</span>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  /** Overall compliance status — 'compliant' | 'partial' | 'non_compliant' */
  overallStatus: { type: String, default: 'non_compliant' },

  /** Per-dimension scores from compliance cache */
  educationPct:  { type: Number, default: 0 },
  healthPct:     { type: Number, default: 0 },
  fdsPct:        { type: Number, default: 0 },

  /** Items from RequirementTrackingService::getCompletionStatus().items */
  items: { type: Array, default: () => [] },

  /** Missing requirements from RequirementTrackingService::getMissingRequirements() */
  missingRequirements: { type: Array, default: () => [] },

  /** Blocking reasons from eligibility check */
  blockingReasons: { type: Array, default: () => [] },

  /** Score history: [{label: 'Jan', score: 45}, ...] — optional */
  history: { type: Array, default: () => [] },

  canUpload:  { type: Boolean, default: false },
  canApprove: { type: Boolean, default: false },
})

defineEmits(['upload'])

// ── Overall status ────────────────────────────────────────────────────────────
const overallLabel = computed(() => ({
  compliant:     '✅ Compliant',
  partial:       '⚠ Partial',
  non_compliant: '⛔ Non-Compliant',
}[props.overallStatus] ?? '—'))

const overallClass = computed(() => ({
  'overall-badge--compliant': props.overallStatus === 'compliant',
  'overall-badge--partial':   props.overallStatus === 'partial',
  'overall-badge--no':        props.overallStatus === 'non_compliant',
}))

// ── Dimensions ────────────────────────────────────────────────────────────────
const dimensions = computed(() => [
  {
    key:         'education',
    label:       'Education',
    icon:        '🎓',
    percentage:  props.educationPct,
    compliant:   props.educationPct >= 100,
    description: 'School enrollment records and attendance for school-age children.',
  },
  {
    key:         'health',
    label:       'Health',
    icon:        '💊',
    percentage:  props.healthPct,
    compliant:   props.healthPct >= 100,
    description: 'Health check-up and immunization records for children under 5.',
  },
  {
    key:         'fds',
    label:       'FDS',
    icon:        '👨‍👩‍👧',
    percentage:  props.fdsPct,
    compliant:   props.fdsPct >= 100,
    description: 'Family Development Session attendance compliance.',
  },
  {
    key:         'documents',
    label:       'Documents',
    icon:        '📄',
    percentage:  Math.round(
      props.items.length
        ? (props.items.filter(i => i.status === 'approved' && i.is_required).length /
           Math.max(1, props.items.filter(i => i.is_required).length)) * 100
        : 0
    ),
    compliant: !props.missingRequirements.some(m => m.required_for !== 'compliance'),
    description: 'All required 4Ps program documents submitted and approved.',
  },
])

// ── Document → Compliance links ───────────────────────────────────────────────
const DIMENSION_MAP = {
  birth_certificate: { dimension: 'documents', dimensionLabel: 'Documents' },
  school_record:     { dimension: 'education', dimensionLabel: 'Education' },
  health_record:     { dimension: 'health',    dimensionLabel: 'Health' },
  proof_of_income:   { dimension: 'documents', dimensionLabel: 'Documents' },
  valid_id:          { dimension: 'documents', dimensionLabel: 'Documents' },
  other:             { dimension: 'documents', dimensionLabel: 'Documents' },
}

const DOC_ICONS = {
  birth_certificate: '📋', school_record: '🎓', health_record: '💉',
  proof_of_income: '💰', valid_id: '🪪', other: '📄',
}

const documentLinks = computed(() =>
  props.items.filter(i => i.is_required).map(item => {
    const dim = DIMENSION_MAP[item.type] ?? { dimension: 'documents', dimensionLabel: 'Documents' }
    return {
      type:           item.type,
      label:          item.label,
      icon:           DOC_ICONS[item.type] ?? '📄',
      member_name:    item.member_name,
      dimension:      dim.dimension,
      dimensionLabel: dim.dimensionLabel,
      docStatus:      item.status,
      blocking:       !['approved'].includes(item.status),
    }
  })
)

// ── Required Actions ──────────────────────────────────────────────────────────
const requiredActions = computed(() => {
  const actions = []

  // Document-based actions
  for (const item of props.items) {
    if (!item.is_required) continue
    if (item.status === 'missing') {
      actions.push({
        priority:    'high',
        type:        item.type,
        title:       `Submit ${item.label}`,
        description: item.description,
        canUpload:   true,
        canApprove:  false,
      })
    } else if (item.status === 'rejected') {
      actions.push({
        priority:    'high',
        type:        item.type,
        title:       `Resubmit ${item.label}`,
        description: `Document was rejected: ${item.document?.rejection_reason ?? 'see document details.'}`,
        canUpload:   true,
        canApprove:  false,
      })
    } else if (item.status === 'expired') {
      actions.push({
        priority:    'high',
        type:        item.type,
        title:       `Renew ${item.label}`,
        description: `Document expired on ${item.document?.expiration_date}. Please upload a current copy.`,
        canUpload:   true,
        canApprove:  false,
      })
    } else if (item.status === 'pending') {
      actions.push({
        priority:    'medium',
        type:        item.type,
        title:       `Approve ${item.label}`,
        description: 'Document has been submitted and is awaiting compliance verifier review.',
        canUpload:   false,
        canApprove:  true,
      })
    }
  }

  // Any additional blocking reasons from compliance (non-document)
  for (const reason of props.blockingReasons) {
    if (!reason.startsWith('[Compliance]')) continue
    actions.push({
      priority:    'medium',
      type:        null,
      title:       'Resolve Compliance Issue',
      description: reason.replace('[Compliance] ', ''),
      canUpload:   false,
      canApprove:  false,
    })
  }

  // Sort: high first
  return actions.sort((a, b) => (a.priority === 'high' ? -1 : 1))
})

// ── Sparkline ─────────────────────────────────────────────────────────────────
const sparkW = computed(() => Math.max(200, props.history.length * 40))

const sparkPoints = computed(() => {
  if (!props.history.length) return []
  const max = 100
  return props.history.map((h, i) => ({
    x: (i / (props.history.length - 1)) * (sparkW.value - 10) + 5,
    y: 50 - (h.score / max) * 44,
  }))
})

const sparklinePoints = computed(() =>
  sparkPoints.value.map(p => `${p.x},${p.y}`).join(' ')
)

const sparklineArea = computed(() => {
  if (!sparkPoints.value.length) return ''
  const pts = sparkPoints.value
  const first = pts[0]
  const last  = pts[pts.length - 1]
  return `${pts.map(p => `${p.x},${p.y}`).join(' ')} ${last.x},50 ${first.x},50`
})

// ── Helpers ───────────────────────────────────────────────────────────────────
const statusLabel = (s) => ({ approved: 'Approved', pending: 'Pending', rejected: 'Rejected', expired: 'Expired', missing: 'Missing' }[s] ?? 'Unknown')

const badgeClass = (s) => ({
  'badge--approved': s === 'approved',
  'badge--pending':  s === 'pending',
  'badge--rejected': s === 'rejected',
  'badge--expired':  s === 'expired',
  'badge--missing':  s === 'missing',
})
</script>

<style scoped>
.compliance-integration { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1.4rem; }

/* ── Header ── */
.header-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.section-title { color: #f1f5f9; font-size: 1rem; font-weight: 700; margin: 0; }
.section-sub   { color: #64748b; font-size: .8rem; margin: .2rem 0 0; }

.overall-badge {
  padding: .4rem 1rem; border-radius: 9999px; font-size: .82rem; font-weight: 700;
  border: 1px solid transparent; white-space: nowrap; flex-shrink: 0;
}
.overall-badge--compliant { background: rgba(20,83,45,.2);  border-color: rgba(34,197,94,.3);  color: #4ade80; }
.overall-badge--partial   { background: rgba(120,83,14,.2); border-color: rgba(251,191,36,.3); color: #fbbf24; }
.overall-badge--no        { background: rgba(127,29,29,.2); border-color: rgba(239,68,68,.3);  color: #f87171; }

/* ── Score Grid ── */
.score-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: .75rem; }
@media (min-width: 700px) { .score-grid { grid-template-columns: repeat(4, 1fr); } }

.score-card {
  padding: 1rem; border-radius: 12px; border: 1px solid transparent;
  display: flex; flex-direction: column; gap: .6rem;
  transition: border-color .2s;
}
.score-card--ok { background: rgba(20,83,45,.12);  border-color: rgba(34,197,94,.2); }
.score-card--no { background: rgba(30,41,59,.5);   border-color: rgba(71,85,105,.2); }

.score-card__top { display: flex; align-items: center; gap: .5rem; }
.score-card__icon  { font-size: 1.2rem; }
.score-card__label { color: #cbd5e1; font-size: .82rem; font-weight: 600; flex: 1; }
.score-card__status { font-size: 1rem; font-weight: 800; }
.text-green { color: #4ade80; }
.text-red   { color: #f87171; }

.score-bar-wrap { display: flex; align-items: center; gap: .5rem; }
.score-bar-track { flex: 1; background: #1e293b; border-radius: 9999px; height: 6px; overflow: hidden; }
.score-bar-fill  { height: 100%; border-radius: 9999px; transition: width .5s ease; }
.score-bar-fill--ok { background: linear-gradient(90deg, #22c55e, #4ade80); }
.score-bar-fill--no { background: #334155; }
.score-bar-pct { color: #94a3b8; font-size: .75rem; font-weight: 700; white-space: nowrap; min-width: 2.5rem; text-align: right; }
.score-card__desc { color: #475569; font-size: .75rem; line-height: 1.4; margin: 0; }

/* ── Link Table ── */
.link-section { display: flex; flex-direction: column; }
.link-section__title { color: #e2e8f0; font-size: .9rem; font-weight: 700; margin: 0 0 .35rem; }
.link-section__sub   { color: #64748b; font-size: .78rem; margin: 0 0 .85rem; line-height: 1.5; }
.link-section__sub em { color: #94a3b8; font-style: normal; font-weight: 600; }

.link-table { border: 1px solid #1e293b; border-radius: 10px; overflow: hidden; }

.link-table__header {
  display: grid; grid-template-columns: 1.8fr 1fr 1fr 1fr;
  background: rgba(15,23,42,.9); padding: .55rem 1rem;
  font-size: .7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em;
  gap: .5rem;
}
.link-table__row {
  display: grid; grid-template-columns: 1.8fr 1fr 1fr 1fr;
  padding: .65rem 1rem; border-top: 1px solid #1e293b; gap: .5rem;
  transition: background .12s; align-items: center;
}
.link-table__row:hover { background: rgba(30,41,59,.4); }
.link-row--blocking { background: rgba(127,29,29,.06); }

.link-cell { display: flex; align-items: center; gap: .5rem; min-width: 0; }
.link-cell--impact { justify-content: flex-start; }
.link-doc-icon { font-size: 1.1rem; flex-shrink: 0; }
.link-doc-name   { color: #cbd5e1; font-size: .82rem; font-weight: 600; margin: 0; }
.link-doc-member { color: #475569; font-size: .72rem; margin: .1rem 0 0; }

.dim-tag {
  padding: .2rem .6rem; border-radius: 9999px; font-size: .72rem; font-weight: 700;
  text-transform: uppercase; letter-spacing: .04em; white-space: nowrap;
}
.dim-tag--education { background: rgba(99,102,241,.15); color: #a5b4fc; border: 1px solid rgba(99,102,241,.3); }
.dim-tag--health    { background: rgba(34,197,94,.12);  color: #4ade80; border: 1px solid rgba(34,197,94,.25); }
.dim-tag--fds       { background: rgba(6,182,212,.12);  color: #22d3ee; border: 1px solid rgba(6,182,212,.25); }
.dim-tag--documents { background: rgba(71,85,105,.2);   color: #94a3b8; border: 1px solid #334155; }

.impact-chip {
  padding: .2rem .65rem; border-radius: 9999px; font-size: .73rem; font-weight: 700; white-space: nowrap;
}
.impact-chip--ok       { color: #4ade80; }
.impact-chip--blocking { color: #f87171; }

/* ── Actions Panel ── */
.actions-panel { display: flex; flex-direction: column; }
.actions-panel__title { color: #e2e8f0; font-size: .9rem; font-weight: 700; margin: 0 0 .75rem; }
.action-list { display: flex; flex-direction: column; gap: .5rem; }

.action-item {
  display: flex; align-items: center; gap: .85rem;
  padding: .85rem 1rem; border-radius: 10px; border: 1px solid transparent;
}
.action-item--high   { background: rgba(127,29,29,.1); border-color: rgba(239,68,68,.2); }
.action-item--medium { background: rgba(120,83,14,.1); border-color: rgba(251,191,36,.2); }
.action-item--low    { background: rgba(30,41,59,.4);  border-color: #1e293b; }

.action-item__priority-dot {
  width: .6rem; height: .6rem; border-radius: 50%; flex-shrink: 0;
}
.action-item--high   .action-item__priority-dot { background: #ef4444; box-shadow: 0 0 6px #ef4444; }
.action-item--medium .action-item__priority-dot { background: #f59e0b; box-shadow: 0 0 6px #f59e0b; }
.action-item--low    .action-item__priority-dot { background: #475569; }

.action-item__body { flex: 1; min-width: 0; }
.action-item__title { color: #e2e8f0; font-size: .87rem; font-weight: 600; margin: 0; }
.action-item__desc  { color: #64748b; font-size: .76rem; margin: .15rem 0 0; line-height: 1.4; }

.action-btn {
  background: rgba(99,102,241,.15); border: 1px solid rgba(99,102,241,.3); color: #a5b4fc;
  border-radius: 7px; padding: .35rem .8rem; font-size: .78rem; font-weight: 600;
  cursor: pointer; transition: all .15s; flex-shrink: 0;
}
.action-btn:hover { background: rgba(99,102,241,.3); }
.action-tag {
  background: rgba(251,191,36,.1); border: 1px solid rgba(251,191,36,.25); color: #fbbf24;
  border-radius: 7px; padding: .3rem .7rem; font-size: .73rem; font-weight: 600; white-space: nowrap;
}

/* ── Sparkline ── */
.history-section { display: flex; flex-direction: column; }
.history-section__title { color: #e2e8f0; font-size: .9rem; font-weight: 700; margin: 0 0 .75rem; }
.sparkline-wrap {
  background: rgba(15,23,42,.6); border: 1px solid #1e293b;
  border-radius: 10px; padding: 1rem 1rem .5rem; overflow: hidden;
}
.sparkline { width: 100%; height: 50px; display: block; }
.spark-labels {
  display: flex; justify-content: space-between;
  margin-top: .4rem; padding: 0 5px;
}
.spark-label { color: #475569; font-size: .68rem; }

/* ── Badges ── */
.badge {
  padding: .2rem .55rem; border-radius: 9999px; font-size: .7rem;
  font-weight: 700; text-transform: capitalize; white-space: nowrap;
}
.badge--approved { background: #14532d22; color: #4ade80; border: 1px solid #14532d55; }
.badge--pending  { background: #78350f22; color: #fbbf24; border: 1px solid #78350f55; }
.badge--rejected { background: #7f1d1d22; color: #f87171; border: 1px solid #7f1d1d55; }
.badge--expired  { background: #7f1d1d22; color: #fca5a5; border: 1px solid #7f1d1d55; }
.badge--missing  { background: #1e293b;   color: #475569; border: 1px solid #334155;   }
</style>
