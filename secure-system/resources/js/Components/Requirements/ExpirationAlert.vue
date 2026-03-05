<template>
  <div class="expiration-alert" id="expiration-alert-panel">
    <!-- Summary Badge -->
    <div class="alert-header">
      <div class="alert-header__left">
        <div :class="['alert-badge', hasCritical ? 'alert-badge--critical' : 'alert-badge--warn']">
          <span class="alert-badge__icon">{{ hasCritical ? '🚨' : '⚠️' }}</span>
          <div>
            <p class="alert-badge__title">{{ alertTitle }}</p>
            <p class="alert-badge__sub">{{ alertSub }}</p>
          </div>
        </div>
      </div>
      <!-- Filter Chips -->
      <div class="filter-pills">
        <button
          v-for="f in filters"
          :key="f.key"
          :class="['filter-pill', { 'filter-pill--active': activeFilter === f.key }]"
          @click="activeFilter = f.key"
          :id="`expiry-filter-${f.key}`"
        >{{ f.label }} <span class="filter-pill__count">{{ f.count }}</span></button>
      </div>
    </div>

    <!-- Document List -->
    <div v-if="filteredDocs.length" class="doc-list">
      <div
        v-for="doc in filteredDocs"
        :key="doc.id"
        :class="['doc-card', doc.is_expired ? 'doc-card--expired' : 'doc-card--soon']"
      >
        <div class="doc-card__left">
          <span class="doc-card__icon">{{ typeIcon(doc.requirement_type) }}</span>
          <div>
            <p class="doc-card__type">{{ typeLabel(doc.requirement_type) }}</p>
            <p class="doc-card__name">{{ doc.file_name }}</p>
          </div>
        </div>
        <div class="doc-card__right">
          <!-- Countdown -->
          <div :class="['countdown', doc.is_expired ? 'countdown--expired' : (doc.days_until_expiry <= 7 ? 'countdown--urgent' : 'countdown--warn')]">
            <span class="countdown__num">
              <template v-if="doc.is_expired">Expired</template>
              <template v-else>{{ doc.days_until_expiry }}d</template>
            </span>
            <span class="countdown__label">
              <template v-if="!doc.is_expired">remaining</template>
              <template v-else>{{ doc.expiration_date }}</template>
            </span>
          </div>
          <!-- Action Button -->
          <button
            v-if="canUpload"
            class="resubmit-btn"
            :id="`resubmit-btn-${doc.id}`"
            @click="$emit('resubmit', doc)"
          >↑ Resubmit</button>
        </div>
      </div>
    </div>

    <div v-else class="empty-alert">
      <span>✓</span> No documents {{ activeFilter === 'expired' ? 'expired' : 'expiring soon' }}.
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  documents: { type: Array, default: () => [] }, // array of requirement objects with is_expired, days_until_expiry
  canUpload: { type: Boolean, default: false },
})

defineEmits(['resubmit'])

const activeFilter = ref('all')

const expiredDocs = computed(() => props.documents.filter(d => d.is_expired))
const soonDocs    = computed(() => props.documents.filter(d => !d.is_expired && d.days_until_expiry != null && d.days_until_expiry <= 30))

const filteredDocs = computed(() => {
  if (activeFilter.value === 'expired') return expiredDocs.value
  if (activeFilter.value === 'soon')    return soonDocs.value
  return [...expiredDocs.value, ...soonDocs.value].sort((a, b) =>
    (a.days_until_expiry ?? -1) - (b.days_until_expiry ?? -1)
  )
})

const filters = computed(() => [
  { key: 'all',     label: 'All',     count: expiredDocs.value.length + soonDocs.value.length },
  { key: 'expired', label: 'Expired', count: expiredDocs.value.length },
  { key: 'soon',    label: 'Expiring Soon', count: soonDocs.value.length },
])

const hasCritical = computed(() => expiredDocs.value.length > 0)
const alertTitle  = computed(() => hasCritical.value ? `${expiredDocs.value.length} Document(s) Expired` : `${soonDocs.value.length} Expiring Soon`)
const alertSub    = computed(() => hasCritical.value
  ? 'Action required — expired documents must be resubmitted.'
  : 'Documents will expire within the next 30 days.')

const typeLabels = {
  birth_certificate: 'Birth Certificate', school_record: 'School Record',
  health_record: 'Health Record', proof_of_income: 'Proof of Income',
  valid_id: 'Valid ID', other: 'Other',
}
const typeLabel = (v) => typeLabels[v] ?? v
const typeIcon  = (v) => ({
  birth_certificate: '📋', school_record: '🎓', health_record: '💉',
  proof_of_income: '💰', valid_id: '🪪', other: '📄',
}[v] ?? '📄')
</script>

<style scoped>
.expiration-alert { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1rem; }

/* Header */
.alert-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 1rem; }
.alert-badge {
  display: flex; align-items: center; gap: .85rem;
  border-radius: 12px; padding: .85rem 1.1rem; border: 1px solid transparent;
}
.alert-badge--critical { background: rgba(127,29,29,.2); border-color: rgba(239,68,68,.3); }
.alert-badge--warn     { background: rgba(120,83,14,.2); border-color: rgba(251,191,36,.3); }
.alert-badge__icon { font-size: 1.6rem; }
.alert-badge__title { color: #f1f5f9; font-size: .92rem; font-weight: 700; margin: 0; }
.alert-badge__sub   { color: #94a3b8; font-size: .78rem; margin: .15rem 0 0; }

.filter-pills { display: flex; gap: .45rem; flex-wrap: wrap; align-items: center; }
.filter-pill {
  background: rgba(30,41,59,.8); border: 1px solid #334155; color: #64748b;
  border-radius: 9999px; padding: .28rem .75rem; font-size: .77rem; font-weight: 600;
  cursor: pointer; transition: all .15s; display: flex; align-items: center; gap: .35rem;
}
.filter-pill:hover { border-color: #475569; color: #94a3b8; }
.filter-pill--active { background: rgba(99,102,241,.15); border-color: rgba(99,102,241,.4); color: #a5b4fc; }
.filter-pill__count {
  background: rgba(99,102,241,.2); border-radius: 9999px;
  padding: .05rem .45rem; font-size: .7rem;
}

/* Doc List */
.doc-list { display: flex; flex-direction: column; gap: .55rem; }
.doc-card {
  display: flex; align-items: center; justify-content: space-between; gap: 1rem;
  padding: .85rem 1rem; border-radius: 10px; border: 1px solid transparent;
  transition: all .15s;
}
.doc-card--expired { background: rgba(127,29,29,.12); border-color: rgba(239,68,68,.25); }
.doc-card--soon    { background: rgba(120,83,14,.1);  border-color: rgba(251,191,36,.2); }
.doc-card__left { display: flex; align-items: center; gap: .85rem; min-width: 0; }
.doc-card__icon { font-size: 1.4rem; flex-shrink: 0; }
.doc-card__type { color: #e2e8f0; font-size: .86rem; font-weight: 600; margin: 0; }
.doc-card__name { color: #475569; font-size: .76rem; margin: .1rem 0 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
.doc-card__right { display: flex; align-items: center; gap: .85rem; flex-shrink: 0; }

/* Countdown */
.countdown { text-align: center; }
.countdown__num {
  display: block; font-size: 1.1rem; font-weight: 800; line-height: 1;
}
.countdown__label { font-size: .68rem; font-weight: 500; text-transform: uppercase; letter-spacing: .05em; opacity: .7; }
.countdown--expired { color: #f87171; }
.countdown--urgent  { color: #fb923c; }
.countdown--warn    { color: #fbbf24; }

/* Resubmit */
.resubmit-btn {
  background: rgba(99,102,241,.15); border: 1px solid rgba(99,102,241,.3); color: #a5b4fc;
  border-radius: 7px; padding: .35rem .8rem; font-size: .79rem; font-weight: 600;
  cursor: pointer; transition: all .15s;
}
.resubmit-btn:hover { background: rgba(99,102,241,.3); }

/* Empty */
.empty-alert {
  text-align: center; color: #22c55e; padding: 1.5rem;
  background: rgba(20,83,45,.1); border: 1px solid rgba(34,197,94,.2);
  border-radius: 10px; font-size: .9rem;
}
</style>
