<template>
  <div class="activity-timeline">
    <!-- Header controls -->
    <div class="tl-header">
      <div class="tl-title">🕐 Activity Timeline</div>
      <div class="tl-controls">
        <select v-model="filterType" class="tc-select" @change="applyFilter">
          <option value="">All Types</option>
          <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
        </select>
        <button class="btn-pdf" @click="exportPdf" title="Export timeline as PDF">📄 Export</button>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="tl-loading">
      <div v-for="i in 6" :key="i" class="tl-skel"></div>
    </div>

    <!-- Empty -->
    <div v-else-if="!filtered.length" class="tl-empty">
      <div class="te-icon">🕳️</div>
      <div class="te-text">No activities found for the selected filter.</div>
    </div>

    <!-- Timeline list -->
    <div v-else class="tl-body" ref="timelineEl">
      <div
        v-for="(log, idx) in filtered"
        :key="log.id"
        class="tl-item"
        :class="{ 'tl-fail': log.status === 'failed' }"
        @click="openDetail(log)"
      >
        <!-- Connector line -->
        <div class="tl-line-wrap">
          <div class="tl-dot" :style="{ background: typeColor(log.activity_type) }"></div>
          <div class="tl-connector" v-if="idx < filtered.length - 1"></div>
        </div>

        <!-- Event card -->
        <div class="tl-card">
          <div class="tlc-top">
            <div class="tlc-ts">{{ formatTs(log.timestamp) }}</div>
            <span class="tlc-type" :style="{ background: typeColor(log.activity_type) + '22', color: typeColor(log.activity_type) }">
              {{ log.activity_type }}
            </span>
            <span v-if="log.severity" class="tlc-sev" :class="'sev-' + log.severity">{{ log.severity }}</span>
            <span class="tlc-status" :class="log.status === 'success' ? 'sts-ok' : 'sts-fail'">
              {{ log.status === 'success' ? '✅' : '❌' }}
            </span>
          </div>
          <div class="tlc-desc">{{ log.activity_description }}</div>
          <div class="tlc-meta" v-if="log.user?.name || log.beneficiary?.family_head_name || log.ip_address">
            <span v-if="log.user?.name" class="meta-pill user">👤 {{ log.user.name }}</span>
            <span v-if="log.beneficiary?.family_head_name" class="meta-pill bene">📋 {{ log.beneficiary.family_head_name }}</span>
            <span class="meta-pill ip">🌐 {{ log.ip_address }}</span>
            <span v-if="log.execution_time != null" class="meta-pill time">⏱ {{ log.execution_time.toFixed(2) }}s</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail modal -->
    <AuditLogDetail
      v-if="detailLog"
      :log="detailLog"
      :timeline="[]"
      @close="detailLog = null"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AuditLogDetail from './AuditLogDetail.vue'

const props = defineProps({
  userId:        { type: Number, default: null },
  beneficiaryId: { type: Number, default: null },
  limit:         { type: Number, default: 50 },
})

const logs       = ref([])
const loading    = ref(false)
const filterType = ref('')
const detailLog  = ref(null)
const timelineEl = ref(null)

const types = [
  'view', 'edit', 'delete', 'approve', 'reject', 'scan', 'verify',
  'export', 'download', 'distribution', 'security_event', 'login', 'logout',
]

const filtered = computed(() =>
  filterType.value
    ? logs.value.filter(l => l.activity_type === filterType.value)
    : logs.value
)

async function loadLogs () {
  loading.value = true
  try {
    const params = { per_page: props.limit }
    if (props.userId)        params.user_id        = props.userId
    if (props.beneficiaryId) params.beneficiary_id = props.beneficiaryId

    const res = await axios.get('/audit-logs', { params })
    logs.value = res.data.data ?? []
  } catch (e) {
    console.error('ActivityTimeline: load failed', e)
  } finally {
    loading.value = false
  }
}

function applyFilter () { /* computed property reacts */ }

function openDetail (log) { detailLog.value = log }

function typeColor (type) {
  const map = {
    view: '#3b82f6', edit: '#f59e0b', delete: '#dc2626',
    approve: '#10b981', reject: '#ef4444', scan: '#8b5cf6',
    verify: '#6366f1', export: '#0ea5e9', download: '#0284c7',
    distribution: '#059669', security_event: '#dc2626',
    login: '#10b981', logout: '#94a3b8',
  }
  return map[type] ?? '#94a3b8'
}

function formatTs (ts) {
  if (!ts) return '—'
  const d = new Date(ts)
  return d.toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'medium' })
}

function exportPdf () {
  // Build a simple printable version of the timeline
  const win = window.open('', '_blank')
  win.document.write(`<html><head><title>Activity Timeline</title>
    <style>
      body { font-family: Arial; font-size: 12px; padding: 20px; }
      h2 { color: #1e3a5f; }
      .ev { margin-bottom: 12px; padding: 8px; border-left: 3px solid #3b82f6; background: #f8fafc; }
      .ev-ts { font-size: 10px; color: #94a3b8; }
      .ev-type { font-weight: bold; }
      .ev-desc { margin-top: 2px; }
    </style>
  </head><body>`)
  win.document.write(`<h2>Activity Timeline</h2>`)
  filtered.value.forEach(l => {
    win.document.write(`<div class="ev">
      <div class="ev-ts">${new Date(l.timestamp).toLocaleString('en-PH')}</div>
      <div class="ev-type">${l.activity_type?.toUpperCase()}</div>
      <div class="ev-desc">${l.activity_description}</div>
    </div>`)
  })
  win.document.write('</body></html>')
  win.document.close()
  win.print()
}

onMounted(loadLogs)
</script>

<style scoped>
.activity-timeline { background: white; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; }

.tl-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 13px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0;
}
.tl-title { font-weight: 800; font-size: 0.88rem; color: #0f172a; }
.tl-controls { display: flex; gap: 8px; align-items: center; }
.tc-select { padding: 5px 10px; border: 1px solid #e2e8f0; border-radius: 7px; font-size: 0.75rem; background: white; }
.btn-pdf { padding: 5px 12px; background: white; border: 1px solid #3b82f6; color: #1d4ed8; border-radius: 7px; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
.btn-pdf:hover { background: #eff6ff; }

.tl-loading { padding: 12px 16px; display: flex; flex-direction: column; gap: 12px; }
.tl-skel { height: 64px; background: #f1f5f9; border-radius: 8px; animation: pulse 1.2s infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }

.tl-empty { padding: 36px; text-align: center; }
.te-icon { font-size: 2rem; margin-bottom: 8px; }
.te-text { font-size: 0.82rem; color: #94a3b8; }

.tl-body { padding: 16px; max-height: 600px; overflow-y: auto; }

.tl-item {
  display: flex; gap: 14px; padding-bottom: 4px;
  cursor: pointer;
}
.tl-item:hover .tl-card { background: #f8fafc; }
.tl-item.tl-fail .tl-dot { box-shadow: 0 0 0 3px #fecaca; }

.tl-line-wrap { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; width: 18px; }
.tl-dot { width: 18px; height: 18px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 0 2px #e2e8f0; flex-shrink: 0; }
.tl-connector { flex: 1; width: 2px; background: #e2e8f0; min-height: 24px; margin-top: 2px; }

.tl-card {
  flex: 1; border: 1px solid #f1f5f9; border-radius: 10px;
  padding: 10px 12px; margin-bottom: 10px;
  transition: background 0.1s;
}
.tlc-top { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 4px; }
.tlc-ts { font-size: 0.68rem; color: #94a3b8; font-family: monospace; }
.tlc-type { padding: 2px 8px; border-radius: 10px; font-size: 0.68rem; font-weight: 700; }
.tlc-sev { padding: 2px 7px; border-radius: 10px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
.sev-critical { background: #fee2e2; color: #b91c1c; }
.sev-high     { background: #ffedd5; color: #c2410c; }
.sev-medium   { background: #fef9c3; color: #92400e; }
.sev-low      { background: #f0fdf4; color: #15803d; }
.tlc-status { font-size: 0.78rem; margin-left: auto; }
.sts-ok   { color: #15803d; }
.sts-fail { color: #dc2626; }

.tlc-desc { font-size: 0.78rem; color: #1e293b; line-height: 1.5; margin-bottom: 6px; }
.tlc-meta { display: flex; gap: 6px; flex-wrap: wrap; }
.meta-pill { padding: 2px 8px; border-radius: 10px; font-size: 0.68rem; background: #f8fafc; border: 1px solid #e2e8f0; color: #64748b; }
.meta-pill.user { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.meta-pill.bene { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }
</style>
