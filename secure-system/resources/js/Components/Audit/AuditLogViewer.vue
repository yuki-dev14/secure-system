<template>
  <div class="audit-viewer">
    <!-- Filter bar -->
    <div class="filter-bar">
      <div class="filter-row">
        <div class="search-wrap">
          <input v-model="filters.search" type="text" placeholder="🔍 Search descriptions, IPs..." class="f-input" @input="debounceSearch" />
        </div>
        <select v-model="filters.activity_type" class="f-select" @change="loadLogs()">
          <option value="">All Types</option>
          <option v-for="t in activityTypes" :key="t" :value="t">{{ t }}</option>
        </select>
        <select v-model="filters.activity_category" class="f-select" @change="loadLogs()">
          <option value="">All Categories</option>
          <option value="data_access">Data Access</option>
          <option value="data_change">Data Change</option>
          <option value="security">Security</option>
          <option value="compliance">Compliance</option>
          <option value="system">System</option>
        </select>
        <select v-model="filters.status" class="f-select" @change="loadLogs()">
          <option value="">All Statuses</option>
          <option value="success">✅ Success</option>
          <option value="failed">❌ Failed</option>
        </select>
        <select v-model="filters.severity" class="f-select" @change="loadLogs()">
          <option value="">All Severities</option>
          <option value="critical">🔴 Critical</option>
          <option value="high">🟠 High</option>
          <option value="medium">🟡 Medium</option>
          <option value="low">🟢 Low</option>
        </select>
      </div>
      <div class="filter-row">
        <div class="date-group">
          <label>From:</label>
          <input v-model="filters.date_from" type="date" class="f-input date-input" @change="loadLogs()" />
        </div>
        <div class="date-group">
          <label>To:</label>
          <input v-model="filters.date_to" type="date" class="f-input date-input" @change="loadLogs()" />
        </div>
        <div class="user-search-wrap">
          <select v-model="filters.user_id" class="f-select" @change="loadLogs()">
            <option value="">All Users</option>
            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <button class="btn-reset" @click="resetFilters">↺ Reset</button>
        <div class="export-btns">
          <button class="btn-export" @click="exportLogs('csv')">📊 CSV</button>
          <button class="btn-export" @click="exportLogs('pdf')">📄 PDF</button>
        </div>
        <button class="btn-refresh" @click="loadLogs()" :class="{ spinning: loading }">⟳</button>
      </div>
    </div>

    <!-- Stats strip -->
    <div v-if="stats" class="stats-strip">
      <div class="stat-chip total">Total: <strong>{{ pagination.total?.toLocaleString() }}</strong></div>
      <div class="stat-chip success">✅ Success: <strong>{{ stats.total_success?.toLocaleString() }}</strong></div>
      <div class="stat-chip failed">❌ Failed: <strong>{{ stats.total_failed?.toLocaleString() }}</strong></div>
      <div class="stat-chip rate">Rate: <strong>{{ stats.success_rate }}%</strong></div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="skeleton-wrap">
      <div v-for="i in 8" :key="i" class="skeleton-row"></div>
    </div>

    <!-- Table -->
    <div v-else class="table-wrap">
      <table class="audit-table">
        <thead>
          <tr>
            <th class="th-sort" @click="sort('timestamp')">Timestamp {{ sortIcon('timestamp') }}</th>
            <th>User</th>
            <th class="th-sort" @click="sort('activity_type')">Type {{ sortIcon('activity_type') }}</th>
            <th>Category</th>
            <th>Description</th>
            <th>Beneficiary</th>
            <th>IP Address</th>
            <th class="th-sort" @click="sort('status')">Status {{ sortIcon('status') }}</th>
            <th>Severity</th>
            <th>Response</th>
            <th>Time(s)</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <template v-if="logs.length">
            <tr
              v-for="log in logs"
              :key="log.id"
              class="log-row"
              :class="{ 'row-failed': log.status === 'failed', 'row-expanded': expandedId === log.id }"
              @click="expandedId = expandedId === log.id ? null : log.id"
            >
              <td class="ts-cell">{{ formatTs(log.timestamp) }}</td>
              <td>
                <span class="user-chip">{{ log.user?.name ?? '—' }}</span>
                <span class="role-chip">{{ log.user?.role ?? '' }}</span>
              </td>
              <td><span class="type-badge" :class="'type-' + log.activity_type">{{ log.activity_type }}</span></td>
              <td><span class="cat-badge" :class="'cat-' + log.activity_category">{{ log.activity_category ?? '—' }}</span></td>
              <td class="desc-cell" :title="log.activity_description">{{ truncate(log.activity_description, 70) }}</td>
              <td>{{ log.beneficiary?.family_head_name ?? '—' }}</td>
              <td class="ip-cell">{{ log.ip_address }}</td>
              <td>
                <span class="status-dot" :class="'dot-' + log.status">
                  {{ log.status === 'success' ? '✅' : '❌' }} {{ log.status }}
                </span>
              </td>
              <td>
                <span v-if="log.severity" class="sev-badge" :class="'sev-' + log.severity">
                  {{ log.severity }}
                </span>
                <span v-else class="dim">—</span>
              </td>
              <td>{{ log.response_status ?? '—' }}</td>
              <td>{{ log.execution_time != null ? log.execution_time.toFixed(3) : '—' }}</td>
              <td>
                <button class="btn-detail" @click.stop="openDetail(log.id)">Details</button>
              </td>
            </tr>
            <!-- Expanded inline view -->
            <transition name="expand">
              <tr v-if="expandedId && logs.find(l => l.id === expandedId)" :key="'exp-' + expandedId">
                <td colspan="12" class="expanded-cell">
                  <div class="expanded-content">
                    <span v-if="expandedLog">{{ expandedLog.activity_description }}</span>
                    <span class="dim" v-else>Loading…</span>
                  </div>
                </td>
              </tr>
            </transition>
          </template>
          <tr v-else>
            <td colspan="12" class="empty-cell">
              <div class="empty-state">
                <div class="empty-icon">📋</div>
                <div class="empty-text">No audit logs found for the selected filters.</div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)" class="pg-btn">← Prev</button>
      <span class="pg-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
      <button :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)" class="pg-btn">Next →</button>
    </div>

    <!-- Detail modal -->
    <AuditLogDetail v-if="detailLog" :log="detailLog" :timeline="detailTimeline" @close="detailLog = null" />
  </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import axios from 'axios'
import AuditLogDetail from './AuditLogDetail.vue'

const props = defineProps({
  users: { type: Array, default: () => [] },
})

const logs       = ref([])
const loading    = ref(false)
const stats      = ref(null)
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const expandedId = ref(null)
const expandedLog = ref(null)
const detailLog  = ref(null)
const detailTimeline = ref([])
const sortField  = ref('timestamp')
const sortDir    = ref('desc')
let searchTimer  = null

const activityTypes = ['view', 'edit', 'delete', 'approve', 'reject', 'scan', 'verify',
                       'export', 'download', 'distribution', 'bulk_distribution', 'security_event', 'login', 'logout']

const filters = reactive({
  search: '', user_id: '', activity_type: '', activity_category: '',
  status: '', severity: '', date_from: '', date_to: '',
})

async function loadLogs (page = 1) {
  loading.value = true
  try {
    const params = { ...filters, page, per_page: 50 }
    const [logsRes, statsRes] = await Promise.all([
      axios.get('/audit-logs', { params }),
      axios.get('/audit-logs/statistics', { params: { from: filters.date_from, to: filters.date_to } }),
    ])
    logs.value       = logsRes.data.data ?? []
    pagination.value = logsRes.data.pagination ?? {}
    stats.value      = statsRes.data
  } catch (e) {
    console.error('AuditLogViewer: load failed', e)
  } finally {
    loading.value = false
  }
}

function debounceSearch () {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadLogs(), 400)
}

function sort (field) {
  if (sortField.value === field) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field; sortDir.value = 'desc'
  }
  logs.value.sort((a, b) => {
    const av = a[field] ?? ''; const bv = b[field] ?? ''
    return sortDir.value === 'asc' ? (av > bv ? 1 : -1) : (av < bv ? 1 : -1)
  })
}

function sortIcon (field) {
  if (sortField.value !== field) return '↕'
  return sortDir.value === 'asc' ? '↑' : '↓'
}

function resetFilters () {
  Object.assign(filters, { search: '', user_id: '', activity_type: '', activity_category: '',
    status: '', severity: '', date_from: '', date_to: '' })
  loadLogs()
}

function goPage (page) {
  loadLogs(page)
}

async function openDetail (id) {
  try {
    const res = await axios.get(`/audit-logs/${id}`)
    detailLog.value      = res.data.log
    detailTimeline.value = res.data.timeline ?? []
  } catch (e) { console.error(e) }
}

async function exportLogs (format) {
  const params = { ...filters, format }
  const res = await axios.post('/audit-logs/export', params, { responseType: 'blob' })
  const url  = URL.createObjectURL(new Blob([res.data]))
  const a    = document.createElement('a'); a.href = url; a.download = `audit_logs.${format}`; a.click()
  URL.revokeObjectURL(url)
}

function formatTs (ts) {
  if (!ts) return '—'
  return new Date(ts).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'medium' })
}

function truncate (str, n) {
  return str?.length > n ? str.slice(0, n) + '…' : str
}

loadLogs()
</script>

<style scoped>
.audit-viewer { display: flex; flex-direction: column; gap: 12px; }

.filter-bar { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 10px; }
.filter-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }

.f-input, .f-select {
  padding: 7px 10px; border: 1px solid #e2e8f0; border-radius: 8px;
  font-size: 0.8rem; background: #f8fafc; color: #1e293b;
  transition: border-color 0.15s;
}
.f-input:focus, .f-select:focus { outline: none; border-color: #3b82f6; background: white; }
.search-wrap { flex: 2; min-width: 200px; }
.search-wrap .f-input { width: 100%; }
.date-group { display: flex; align-items: center; gap: 6px; font-size: 0.78rem; color: #64748b; }
.date-input { width: 130px; }
.user-search-wrap { flex: 1; min-width: 150px; }
.user-search-wrap .f-select { width: 100%; }

.btn-reset { padding: 7px 14px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.78rem; color: #64748b; cursor: pointer; font-weight: 600; transition: all 0.15s; }
.btn-reset:hover { background: #e2e8f0; }
.export-btns { display: flex; gap: 6px; }
.btn-export { padding: 7px 12px; background: white; border: 1px solid #3b82f6; border-radius: 8px; font-size: 0.75rem; color: #1d4ed8; cursor: pointer; font-weight: 700; transition: all 0.15s; }
.btn-export:hover { background: #eff6ff; }
.btn-refresh { width: 34px; height: 34px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; cursor: pointer; font-size: 1rem; transition: all 0.3s; }
.btn-refresh.spinning { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

.stats-strip { display: flex; gap: 8px; flex-wrap: wrap; }
.stat-chip { padding: 5px 14px; border-radius: 20px; font-size: 0.78rem; }
.stat-chip.total   { background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; }
.stat-chip.success { background: #f0fdf4; border: 1px solid #86efac; color: #15803d; }
.stat-chip.failed  { background: #fff5f5; border: 1px solid #fecaca; color: #dc2626; }
.stat-chip.rate    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }

.table-wrap { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; overflow-x: auto; }
.audit-table { width: 100%; border-collapse: collapse; min-width: 900px; }
thead tr { background: #0f172a; }
thead th { padding: 10px 10px; font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
.th-sort { cursor: pointer; user-select: none; }
.th-sort:hover { color: white; }

tbody .log-row { cursor: pointer; transition: background 0.1s; }
tbody .log-row:hover { background: #f8fafc; }
tbody .log-row.row-failed { background: #fff8f8; }
tbody .log-row.row-failed:hover { background: #fee2e2; }
tbody td { padding: 8px 10px; font-size: 0.78rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }

.skeleton-wrap { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; }
.skeleton-row  { height: 32px; background: #f1f5f9; border-radius: 6px; margin-bottom: 8px; animation: pulse 1.2s ease-in-out infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }

.ts-cell { white-space: nowrap; font-family: monospace; font-size: 0.72rem; color: #64748b; }
.user-chip { font-weight: 600; font-size: 0.78rem; color: #1e293b; }
.role-chip { font-size: 0.66rem; color: #94a3b8; display: block; }
.ip-cell   { font-family: monospace; font-size: 0.72rem; color: #64748b; }
.desc-cell { max-width: 240px; }

.type-badge { padding: 2px 8px; border-radius: 10px; font-size: 0.68rem; font-weight: 700; background: #f1f5f9; color: #475569; }
.type-delete { background: #fff5f5; color: #dc2626; }
.type-edit   { background: #fff7ed; color: #c2410c; }
.type-approve { background: #f0fdf4; color: #15803d; }
.type-reject  { background: #fef2f2; color: #dc2626; }
.type-export, .type-download { background: #eff6ff; color: #1d4ed8; }

.cat-badge { padding: 2px 7px; border-radius: 10px; font-size: 0.67rem; font-weight: 700; }
.cat-data_access  { background: #eff6ff; color: #1d4ed8; }
.cat-data_change  { background: #fff7ed; color: #c2410c; }
.cat-security     { background: #fff5f5; color: #dc2626; }
.cat-compliance   { background: #f0fdf4; color: #15803d; }
.cat-system       { background: #f8fafc; color: #64748b; }

.status-dot { font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
.dot-success { color: #15803d; }
.dot-failed  { color: #dc2626; }

.sev-badge { padding: 2px 8px; border-radius: 10px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; }
.sev-critical { background: #fee2e2; color: #b91c1c; }
.sev-high     { background: #ffedd5; color: #c2410c; }
.sev-medium   { background: #fef9c3; color: #92400e; }
.sev-low      { background: #f0fdf4; color: #15803d; }

.dim { color: #94a3b8; }

.expanded-cell { padding: 0 !important; }
.expanded-content { padding: 10px 14px; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 0.8rem; color: #475569; }
.expand-enter-active, .expand-leave-active { transition: max-height 0.2s ease; overflow: hidden; }
.expand-enter-from, .expand-leave-to { max-height: 0; }
.expand-enter-to, .expand-leave-from { max-height: 200px; }

.btn-detail { padding: 4px 10px; background: white; border: 1px solid #3b82f6; color: #1d4ed8; border-radius: 6px; font-size: 0.72rem; font-weight: 700; cursor: pointer; transition: all 0.15s; }
.btn-detail:hover { background: #eff6ff; }

.empty-cell { text-align: center; padding: 40px !important; }
.empty-state .empty-icon { font-size: 2.5rem; margin-bottom: 8px; }
.empty-state .empty-text { font-size: 0.85rem; color: #94a3b8; }

.pagination { display: flex; align-items: center; gap: 10px; justify-content: center; }
.pg-btn { padding: 8px 18px; background: white; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.8rem; font-weight: 600; color: #475569; cursor: pointer; }
.pg-btn:hover:not(:disabled) { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
.pg-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pg-info { font-size: 0.8rem; color: #64748b; }
</style>
