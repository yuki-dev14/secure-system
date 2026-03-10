<template>
  <div class="dist-list-page">
    <!-- Header bar -->
    <div class="page-header">
      <div>
        <h2 class="page-title">📋 Distribution Records</h2>
        <p class="page-subtitle">All cash grant distributions</p>
      </div>
      <div class="header-actions">
        <button class="btn-export" @click="exportExcel">
          📥 Export Excel
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-card">
      <div class="filter-row">
        <div class="filter-group">
          <label class="filter-label">Search</label>
          <input
            v-model="filters.search"
            type="text"
            class="filter-input"
            placeholder="Name or BIN..."
            @input="debouncedFetch"
          />
        </div>
        <div class="filter-group">
          <label class="filter-label">From Date</label>
          <input v-model="filters.from" type="date" class="filter-input" @change="fetchData" />
        </div>
        <div class="filter-group">
          <label class="filter-label">To Date</label>
          <input v-model="filters.to" type="date" class="filter-input" @change="fetchData" />
        </div>
        <div class="filter-group">
          <label class="filter-label">Payment Method</label>
          <select v-model="filters.payment_method" class="filter-input" @change="fetchData">
            <option value="">All Methods</option>
            <option value="cash">Cash</option>
            <option value="e-wallet">E-Wallet</option>
            <option value="bank_transfer">Bank Transfer</option>
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label">Month</label>
          <select v-model="filters.month" class="filter-input" @change="fetchData">
            <option value="">All Months</option>
            <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label">Year</label>
          <select v-model="filters.year" class="filter-input" @change="fetchData">
            <option value="">All Years</option>
            <option v-for="y in yearList" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="filter-group filter-reset">
          <button class="btn-reset" @click="resetFilters">↺ Reset</button>
        </div>
      </div>
    </div>

    <!-- Total summary -->
    <div class="summary-bar" v-if="!loading">
      <div class="summary-item">
        <span class="sum-label">Total Records</span>
        <span class="sum-value">{{ pagination.total?.toLocaleString() }}</span>
      </div>
      <div class="summary-item highlight">
        <span class="sum-label">Total Distributed</span>
        <span class="sum-value">₱{{ formatAmount(totalAmount) }}</span>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Loading distributions...</span>
    </div>

    <!-- Table -->
    <div v-else-if="distributions.length" class="table-wrapper">
      <table class="dist-table">
        <thead>
          <tr>
            <th @click="sort('distributed_at')" class="sortable">
              Date <span class="sort-icon">{{ sortIcon('distributed_at') }}</span>
            </th>
            <th>Beneficiary</th>
            <th>BIN</th>
            <th @click="sort('payout_amount')" class="sortable">
              Amount <span class="sort-icon">{{ sortIcon('payout_amount') }}</span>
            </th>
            <th>Period</th>
            <th>Method</th>
            <th>Officer</th>
            <th>Reference</th>
            <th>Receipt</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="dist in distributions"
            :key="dist.id"
            class="dist-row"
            @click="$emit('view', dist)"
          >
            <td class="date-cell">
              <div class="date-main">{{ formatDate(dist.distributed_at) }}</div>
              <div class="date-sub">{{ dist.distributed_at_human }}</div>
            </td>
            <td class="name-cell">
              <div class="name-main">{{ dist.beneficiary?.family_head_name }}</div>
              <div class="name-sub">{{ dist.beneficiary?.barangay }}, {{ dist.beneficiary?.municipality }}</div>
            </td>
            <td><span class="bin-badge">{{ dist.beneficiary?.bin }}</span></td>
            <td class="amount-cell">
              <span class="amount-val">₱{{ formatAmount(dist.payout_amount) }}</span>
            </td>
            <td class="period-cell">{{ dist.payout_period }}</td>
            <td>
              <span class="method-badge" :class="methodClass(dist.payment_method)">
                {{ methodLabel(dist.payment_method) }}
              </span>
            </td>
            <td class="officer-cell">
              <div class="officer-name">{{ dist.distributed_by?.name }}</div>
              <div class="officer-office">{{ dist.distributed_by?.office_location }}</div>
            </td>
            <td class="ref-cell">
              <span class="ref-code">{{ dist.transaction_reference_number }}</span>
            </td>
            <td class="receipt-cell" @click.stop>
              <a
                :href="dist.receipt_url"
                target="_blank"
                class="btn-receipt"
                title="Download Receipt"
              >
                📄 PDF
              </a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Empty state -->
    <div v-else class="empty-state">
      <div class="empty-icon">📭</div>
      <div class="empty-text">No distribution records found</div>
      <div class="empty-sub">Try adjusting your filters</div>
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="pagination.last_page > 1">
      <button
        class="page-btn"
        :disabled="pagination.current_page <= 1"
        @click="goToPage(pagination.current_page - 1)"
      >‹ Prev</button>
      <span class="page-info">
        Page {{ pagination.current_page }} of {{ pagination.last_page }}
        &nbsp;({{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }})
      </span>
      <button
        class="page-btn"
        :disabled="pagination.current_page >= pagination.last_page"
        @click="goToPage(pagination.current_page + 1)"
      >Next ›</button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import axios from 'axios'

const emit = defineEmits(['view'])

const distributions = ref([])
const totalAmount   = ref(0)
const loading       = ref(false)
const pagination    = reactive({ total: 0, per_page: 20, current_page: 1, last_page: 1, from: 0, to: 0 })

const sortBy  = ref('distributed_at')
const sortDir = ref('desc')

const currentYear = new Date().getFullYear()
const yearList = computed(() => Array.from({ length: 6 }, (_, i) => currentYear - i))

const filters = reactive({
  search: '', from: '', to: '',
  payment_method: '', month: '', year: '',
})

let debounceTimer = null

function debouncedFetch () {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(fetchData, 350)
}

async function fetchData (page = 1) {
  loading.value = true
  try {
    const params = { ...filters, page, per_page: 20 }
    // Remove falsy values
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })

    const res = await axios.get('/distribution', { params })
    distributions.value = res.data.data
    totalAmount.value    = res.data.total_amount
    Object.assign(pagination, res.data.pagination)
  } catch (e) {
    console.error('Distribution fetch error:', e)
  } finally {
    loading.value = false
  }
}

function goToPage (page) { fetchData(page) }

function sort (col) {
  if (sortBy.value === col) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value  = col
    sortDir.value = 'desc'
  }
  fetchData()
}

function sortIcon (col) {
  if (sortBy.value !== col) return '⇅'
  return sortDir.value === 'asc' ? '↑' : '↓'
}

function resetFilters () {
  Object.assign(filters, { search: '', from: '', to: '', payment_method: '', month: '', year: '' })
  fetchData()
}

function formatDate (iso) {
  return new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function formatAmount (val) {
  return Number(val || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function monthName (m) {
  return new Date(2000, m - 1, 1).toLocaleString('en', { month: 'long' })
}

function methodLabel (m) {
  return { cash: '💵 Cash', 'e-wallet': '📱 E-Wallet', bank_transfer: '🏦 Bank' }[m] ?? m
}

function methodClass (m) {
  return { cash: 'method-cash', 'e-wallet': 'method-ewallet', bank_transfer: 'method-bank' }[m] ?? ''
}

async function exportExcel () {
  const params = new URLSearchParams({ ...filters, export: 'excel' })
  window.location.href = `/distribution?${params}`
}

onMounted(() => fetchData())
</script>

<style scoped>
.dist-list-page { display: flex; flex-direction: column; gap: 16px; }

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
}

.page-title { font-size: 1.3rem; font-weight: 800; color: #0f172a; margin: 0; }
.page-subtitle { font-size: 0.8rem; color: #94a3b8; margin-top: 2px; }

.btn-export {
  padding: 8px 16px;
  background: linear-gradient(135deg, #059669, #047857);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-export:hover { opacity: 0.9; transform: translateY(-1px); }

.filters-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
}

.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 130px; }
.filter-label { font-size: 0.72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.filter-input {
  padding: 7px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.82rem;
  color: #1e293b;
  background: #f8fafc;
  transition: border-color 0.15s;
  width: 100%;
}
.filter-input:focus { outline: none; border-color: #3b82f6; background: white; }

.filter-reset { flex: none; }
.btn-reset {
  padding: 7px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #64748b;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}
.btn-reset:hover { background: #f1f5f9; }

.summary-bar {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.summary-item {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 10px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.summary-item.highlight {
  background: linear-gradient(135deg, #1e40af, #1565c0);
  border-color: transparent;
  color: white;
}

.sum-label { font-size: 0.72rem; color: #94a3b8; }
.summary-item.highlight .sum-label { color: rgba(255,255,255,0.75); }
.sum-value { font-size: 1.1rem; font-weight: 800; color: #0f172a; }
.summary-item.highlight .sum-value { color: white; }

.loading-state {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 40px;
  justify-content: center;
  color: #64748b;
}

.spinner {
  width: 24px; height: 24px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.table-wrapper {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  overflow-x: auto;
}

.dist-table { width: 100%; border-collapse: collapse; min-width: 900px; }

.dist-table thead tr {
  background: linear-gradient(135deg, #f8fafc, #f1f5f9);
  border-bottom: 2px solid #e2e8f0;
}

.dist-table th {
  padding: 11px 14px;
  text-align: left;
  font-size: 0.73rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.dist-table th.sortable { cursor: pointer; }
.dist-table th.sortable:hover { color: #1d4ed8; }
.sort-icon { font-size: 0.7rem; color: #94a3b8; }

.dist-row {
  border-bottom: 1px solid #f1f5f9;
  transition: background 0.12s;
  cursor: pointer;
}
.dist-row:hover { background: #f8faff; }

.dist-table td { padding: 11px 14px; vertical-align: middle; }

.date-main { font-size: 0.82rem; font-weight: 600; color: #1e293b; }
.date-sub  { font-size: 0.7rem; color: #94a3b8; }
.name-main { font-size: 0.82rem; font-weight: 600; color: #1e293b; }
.name-sub  { font-size: 0.7rem; color: #94a3b8; }

.bin-badge {
  background: #eff6ff;
  color: #1d4ed8;
  padding: 3px 8px;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  font-family: monospace;
}

.amount-val {
  font-weight: 800;
  color: #059669;
  font-size: 0.9rem;
}

.period-cell { font-size: 0.8rem; color: #475569; font-weight: 600; }

.method-badge {
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 0.72rem;
  font-weight: 700;
  white-space: nowrap;
}
.method-cash    { background: #f0fdf4; color: #15803d; }
.method-ewallet { background: #eff6ff; color: #1d4ed8; }
.method-bank    { background: #fff7ed; color: #c2410c; }

.officer-name   { font-size: 0.8rem; font-weight: 600; color: #1e293b; }
.officer-office { font-size: 0.7rem; color: #94a3b8; }

.ref-code {
  font-size: 0.7rem;
  font-family: monospace;
  color: #64748b;
  background: #f8fafc;
  padding: 2px 6px;
  border-radius: 4px;
  border: 1px solid #e2e8f0;
}

.btn-receipt {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  background: linear-gradient(135deg, #dc2626, #b91c1c);
  color: white;
  border-radius: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  text-decoration: none;
  transition: all 0.15s;
}
.btn-receipt:hover { opacity: 0.85; transform: scale(1.05); }

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #94a3b8;
}
.empty-icon { font-size: 3rem; margin-bottom: 10px; }
.empty-text { font-size: 1rem; font-weight: 600; color: #64748b; }
.empty-sub  { font-size: 0.82rem; }

.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
}

.page-btn {
  padding: 7px 14px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #374151;
  font-weight: 600;
  font-size: 0.8rem;
  cursor: pointer;
  transition: all 0.15s;
}
.page-btn:hover:not(:disabled) { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-info { font-size: 0.8rem; color: #64748b; }
</style>
