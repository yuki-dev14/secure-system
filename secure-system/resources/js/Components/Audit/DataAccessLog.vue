<template>
  <div class="data-access-log">
    <div class="dal-header">
      <div>
        <div class="dal-title">🔍 Data Access Log</div>
        <div class="dal-sub">Compliance-focused view of who accessed what data</div>
      </div>
      <div class="dal-actions">
        <button class="btn-export-dal" @click="exportCsv">📊 Export CSV</button>
      </div>
    </div>

    <!-- Filters -->
    <div class="dal-filters">
      <select v-model="filters.action" class="f-sel" @change="loadLogs()">
        <option value="">All Actions</option>
        <option value="view">👁️ View</option>
        <option value="download">⬇️ Download</option>
        <option value="export">📤 Export</option>
      </select>
      <select v-model="filters.resource_type" class="f-sel" @change="loadLogs()">
        <option value="">All Data Types</option>
        <option value="Beneficiary">👤 Beneficiary</option>
        <option value="SubmittedRequirement">📎 Document</option>
        <option value="CashGrantDistribution">💰 Distribution</option>
        <option value="ComplianceRecord">✅ Compliance</option>
        <option value="PrivacyComplianceReport">🔒 Privacy Report</option>
      </select>
      <div class="date-grp">
        <input v-model="filters.date_from" type="date" class="f-date" placeholder="From" @change="loadLogs()" />
        <span class="date-sep">→</span>
        <input v-model="filters.date_to" type="date" class="f-date" placeholder="To" @change="loadLogs()" />
      </div>
      <button class="btn-reset-dal" @click="resetFilters">↺</button>
    </div>

    <!-- KPI strip -->
    <div class="kpi-strip">
      <div class="kpi-item">
        <div class="kpi-val">{{ totalViews }}</div>
        <div class="kpi-lbl">👁️ Views</div>
      </div>
      <div class="kpi-item">
        <div class="kpi-val">{{ totalDownloads }}</div>
        <div class="kpi-lbl">⬇️ Downloads</div>
      </div>
      <div class="kpi-item">
        <div class="kpi-val">{{ totalExports }}</div>
        <div class="kpi-lbl">📤 Exports</div>
      </div>
      <div class="kpi-item" :class="{ 'kpi-warn': uniqueUsers > 10 }">
        <div class="kpi-val">{{ uniqueUsers }}</div>
        <div class="kpi-lbl">👤 Unique Users</div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="dal-loading">
      <div v-for="i in 6" :key="i" class="dal-skel"></div>
    </div>

    <!-- Table -->
    <div v-else class="dal-table-wrap">
      <table class="dal-table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>User</th>
            <th>Action</th>
            <th>Data Type</th>
            <th>Record ID</th>
            <th>Beneficiary</th>
            <th>IP Address</th>
            <th>Severity</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id"
              class="dal-row"
              :class="{ 'row-download': ['download','export'].includes(log.activity_type) }"
              @click="openDetail(log)"
          >
            <td class="ts-cell">{{ formatTs(log.timestamp) }}</td>
            <td>
              <div class="user-name">{{ log.user?.name ?? '—' }}</div>
              <div class="user-role">{{ log.user?.role }}</div>
            </td>
            <td>
              <span class="action-badge" :class="'ab-' + log.activity_type">
                {{ actionIcon(log.activity_type) }} {{ log.activity_type }}
              </span>
            </td>
            <td>
              <span class="res-type">{{ log.resource_type ?? '—' }}</span>
            </td>
            <td class="mono-cell">{{ log.resource_id ?? '—' }}</td>
            <td>{{ log.beneficiary?.family_head_name ?? '—' }}</td>
            <td class="mono-cell">{{ log.ip_address }}</td>
            <td>
              <span v-if="log.severity" class="sev-dot" :class="'sev-' + log.severity">
                {{ log.severity }}
              </span>
              <span v-else class="dim">—</span>
            </td>
          </tr>
          <tr v-if="!logs.length">
            <td colspan="8" class="empty-cell">
              <div class="empty-state">
                <div>🔍</div>
                <div>No data access records found.</div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="dal-pagination" v-if="pagination.last_page > 1">
      <button :disabled="pagination.current_page <= 1" @click="loadLogs(pagination.current_page - 1)" class="pg-btn">← Prev</button>
      <span class="pg-info">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
      <button :disabled="pagination.current_page >= pagination.last_page" @click="loadLogs(pagination.current_page + 1)" class="pg-btn">Next →</button>
    </div>

    <AuditLogDetail v-if="detailLog" :log="detailLog" :timeline="[]" @close="detailLog = null" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import AuditLogDetail from './AuditLogDetail.vue'

const logs       = ref([])
const loading    = ref(false)
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const detailLog  = ref(null)
const allLogs    = ref([]) // for KPI counting from current page

const filters = ref({ action: '', resource_type: '', date_from: '', date_to: '' })

const totalViews     = computed(() => allLogs.value.filter(l => l.activity_type === 'view').length)
const totalDownloads = computed(() => allLogs.value.filter(l => l.activity_type === 'download').length)
const totalExports   = computed(() => allLogs.value.filter(l => l.activity_type === 'export').length)
const uniqueUsers    = computed(() => new Set(allLogs.value.map(l => l.user_id).filter(Boolean)).size)

async function loadLogs (page = 1) {
  loading.value = true
  try {
    const params = {
      activity_category: 'data_access',
      activity_type:  filters.value.action || undefined,
      resource_type:  filters.value.resource_type || undefined,
      date_from:      filters.value.date_from || undefined,
      date_to:        filters.value.date_to || undefined,
      page, per_page: 30,
    }
    const res     = await axios.get('/audit-logs', { params })
    logs.value    = res.data.data ?? []
    allLogs.value = logs.value
    pagination.value = res.data.pagination ?? {}
  } catch (e) {
    console.error('DataAccessLog: load failed', e)
  } finally {
    loading.value = false
  }
}

function resetFilters () {
  filters.value = { action: '', resource_type: '', date_from: '', date_to: '' }
  loadLogs()
}

function openDetail (log) { detailLog.value = log }

function actionIcon (type) {
  return { view: '👁️', download: '⬇️', export: '📤' }[type] ?? '🔍'
}

function formatTs (ts) {
  if (!ts) return '—'
  return new Date(ts).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'short' })
}

function exportCsv () {
  const rows = [
    ['Timestamp', 'User', 'Role', 'Action', 'Data Type', 'Record ID', 'Beneficiary', 'IP', 'Severity'],
    ...logs.value.map(l => [
      new Date(l.timestamp).toLocaleString('en-PH'),
      l.user?.name ?? '', l.user?.role ?? '',
      l.activity_type, l.resource_type ?? '', l.resource_id ?? '',
      l.beneficiary?.family_head_name ?? '', l.ip_address, l.severity ?? '',
    ]),
  ]
  const csv  = rows.map(r => r.join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a'); a.href = url; a.download = 'data_access_log.csv'; a.click()
  URL.revokeObjectURL(url)
}

onMounted(loadLogs)
</script>

<style scoped>
.data-access-log { background: white; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; gap: 0; }

.dal-header { display: flex; align-items: flex-start; justify-content: space-between; padding: 14px 16px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
.dal-title  { font-weight: 800; font-size: 0.9rem; color: #0f172a; }
.dal-sub    { font-size: 0.72rem; color: #94a3b8; margin-top: 2px; }
.btn-export-dal { padding: 7px 14px; background: white; border: 1px solid #3b82f6; color: #1d4ed8; border-radius: 8px; font-size: 0.75rem; font-weight: 700; cursor: pointer; }
.btn-export-dal:hover { background: #eff6ff; }

.dal-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; background: white; }
.f-sel, .f-date { padding: 7px 10px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.78rem; background: #f8fafc; }
.f-sel:focus, .f-date:focus { outline: none; border-color: #3b82f6; }
.date-grp { display: flex; align-items: center; gap: 4px; }
.date-sep { font-size: 0.78rem; color: #94a3b8; }
.btn-reset-dal { padding: 7px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 7px; font-size: 0.82rem; cursor: pointer; color: #64748b; }

.kpi-strip { display: flex; padding: 10px 16px; gap: 0; border-bottom: 1px solid #f1f5f9; }
.kpi-item { flex: 1; text-align: center; padding: 6px; }
.kpi-item + .kpi-item { border-left: 1px solid #f1f5f9; }
.kpi-val { font-size: 1.3rem; font-weight: 800; color: #0f172a; }
.kpi-lbl { font-size: 0.68rem; color: #94a3b8; margin-top: 1px; }
.kpi-warn .kpi-val { color: #d97706; }

.dal-loading { padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; }
.dal-skel { height: 44px; background: #f1f5f9; border-radius: 6px; animation: pulse 1.2s infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }

.dal-table-wrap { overflow-x: auto; max-height: 440px; overflow-y: auto; }
.dal-table { width: 100%; border-collapse: collapse; min-width: 700px; }
thead tr { background: #0f172a; }
thead th { padding: 9px 10px; font-size: 0.68rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.4px; text-align: left; }
tbody .dal-row { cursor: pointer; transition: background 0.1s; }
tbody .dal-row:hover { background: #f8fafc; }
tbody .dal-row.row-download { background: #fffbeb; }
tbody .dal-row.row-download:hover { background: #fef3c7; }
tbody td { padding: 8px 10px; font-size: 0.78rem; border-bottom: 1px solid #f1f5f9; }

.ts-cell { font-family: monospace; font-size: 0.72rem; color: #64748b; white-space: nowrap; }
.user-name { font-weight: 600; color: #1e293b; }
.user-role { font-size: 0.68rem; color: #94a3b8; }
.mono-cell { font-family: monospace; font-size: 0.72rem; color: #64748b; }
.res-type  { background: #f1f5f9; padding: 2px 7px; border-radius: 8px; font-size: 0.7rem; font-weight: 600; color: #475569; }

.action-badge { padding: 2px 8px; border-radius: 10px; font-size: 0.68rem; font-weight: 700; white-space: nowrap; }
.ab-view     { background: #eff6ff; color: #1d4ed8; }
.ab-download { background: #fef9c3; color: #92400e; }
.ab-export   { background: #fff7ed; color: #c2410c; }

.sev-dot { padding: 2px 8px; border-radius: 10px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; }
.sev-critical { background: #fee2e2; color: #b91c1c; }
.sev-high     { background: #ffedd5; color: #c2410c; }
.sev-medium   { background: #fef9c3; color: #92400e; }
.sev-low      { background: #f0fdf4; color: #15803d; }
.dim { color: #94a3b8; }

.empty-cell { text-align: center; padding: 32px !important; }
.empty-state { font-size: 0.82rem; color: #94a3b8; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.empty-state > div:first-child { font-size: 1.8rem; }

.dal-pagination { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 10px; border-top: 1px solid #f1f5f9; }
.pg-btn { padding: 6px 14px; background: white; border: 1px solid #e2e8f0; border-radius: 7px; font-size: 0.78rem; font-weight: 600; color: #475569; cursor: pointer; }
.pg-btn:hover:not(:disabled) { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
.pg-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pg-info { font-size: 0.78rem; color: #64748b; }
</style>
