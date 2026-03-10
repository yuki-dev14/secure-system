<template>
  <div class="recon-dashboard">
    <!-- Header -->
    <div class="recon-header">
      <div>
        <h2 class="recon-title">⚖️ Reconciliation Dashboard</h2>
        <p class="recon-subtitle">Compare expected vs actual distributions</p>
      </div>
      <button class="btn-export" @click="exportReport" :disabled="!result">📥 Export Report</button>
    </div>

    <!-- Filter form -->
    <div class="filter-card">
      <div class="filter-row">
        <div class="filter-group">
          <label class="filter-label">From Date *</label>
          <input v-model="filters.from" type="date" class="filter-input" />
        </div>
        <div class="filter-group">
          <label class="filter-label">To Date *</label>
          <input v-model="filters.to" type="date" class="filter-input" />
        </div>
        <div class="filter-group">
          <label class="filter-label">Office / Location</label>
          <input v-model="filters.office" type="text" class="filter-input" placeholder="All offices" />
        </div>
        <div class="filter-group filter-btn-group">
          <button class="btn-run" @click="runReconciliation" :disabled="loading || !filters.from || !filters.to">
            <span v-if="loading">⏳ Reconciling...</span>
            <span v-else>▶ Run Reconciliation</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <span>Calculating reconciliation...</span>
    </div>

    <!-- Results -->
    <template v-if="result && !loading">
      <!-- KPI Cards -->
      <div class="kpi-grid">
        <div class="kpi-card expected">
          <div class="kpi-icon">🎯</div>
          <div class="kpi-label">Expected Total</div>
          <div class="kpi-val">₱{{ formatAmount(result.expected_total) }}</div>
        </div>
        <div class="kpi-card actual">
          <div class="kpi-icon">✅</div>
          <div class="kpi-label">Actual Distributed</div>
          <div class="kpi-val">₱{{ formatAmount(result.actual_total) }}</div>
        </div>
        <div class="kpi-card" :class="result.flagged ? 'disc-flagged' : 'disc-ok'">
          <div class="kpi-icon">{{ result.flagged ? '⚠️' : '✓' }}</div>
          <div class="kpi-label">Discrepancy</div>
          <div class="kpi-val">
            {{ result.discrepancy >= 0 ? '+' : '' }}₱{{ formatAmount(Math.abs(result.discrepancy)) }}
          </div>
          <div class="kpi-sub">{{ result.discrepancy_pct }}%{{ result.flagged ? ' — FLAGGED' : '' }}</div>
        </div>
        <div class="kpi-card count">
          <div class="kpi-icon">🔢</div>
          <div class="kpi-label">Distribution Count</div>
          <div class="kpi-val">{{ result.count.toLocaleString() }}</div>
        </div>
      </div>

      <!-- Discrepancy bar -->
      <div class="disc-bar-wrapper" v-if="result.expected_total > 0">
        <div class="disc-bar-label">
          Actual as % of Expected:
          <strong>{{ discPct }}%</strong>
        </div>
        <div class="disc-track">
          <div
            class="disc-fill"
            :class="result.flagged ? 'disc-fill-warn' : 'disc-fill-ok'"
            :style="{ width: Math.min(discPct, 100) + '%' }"
          ></div>
          <div class="disc-target" title="Expected (100%)"></div>
        </div>
      </div>

      <!-- Breakdown tables -->
      <div class="breakdown-grid">
        <!-- By Payment Method -->
        <div class="breakdown-table">
          <div class="bt-title">By Payment Method</div>
          <table class="mini-table">
            <thead>
              <tr>
                <th>Method</th>
                <th>Count</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(data, method) in result.by_method" :key="method">
                <td>
                  <span class="method-badge" :class="methodClass(method)">{{ methodLabel(method) }}</span>
                </td>
                <td>{{ data.count }}</td>
                <td class="amount-cell">₱{{ formatAmount(data.amount) }}</td>
              </tr>
              <tr v-if="!Object.keys(result.by_method).length">
                <td colspan="3" class="empty-cell">No data</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- By Office -->
        <div class="breakdown-table">
          <div class="bt-title">By Office / Location</div>
          <table class="mini-table">
            <thead>
              <tr>
                <th>Office</th>
                <th>Count</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(data, office) in result.by_office" :key="office">
                <td class="office-cell">{{ office }}</td>
                <td>{{ data.count }}</td>
                <td class="amount-cell">₱{{ formatAmount(data.amount) }}</td>
              </tr>
              <tr v-if="!Object.keys(result.by_office).length">
                <td colspan="3" class="empty-cell">No data</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Flagged notice -->
      <div v-if="result.flagged" class="flag-notice">
        <div class="flag-icon">⚠️</div>
        <div>
          <div class="flag-title">Discrepancy Flagged</div>
          <div class="flag-body">
            The discrepancy of {{ result.discrepancy_pct }}% exceeds the 0.01% threshold.
            The difference is ₱{{ formatAmount(Math.abs(result.discrepancy)) }}.
            Please review individual distribution records or contact the administrator.
          </div>
        </div>
      </div>
    </template>

    <!-- Empty state -->
    <div v-if="!result && !loading" class="empty-state">
      <div class="empty-icon">📊</div>
      <div class="empty-text">Set date range and click <strong>Run Reconciliation</strong></div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import axios from 'axios'

const filters = reactive({ from: '', to: '', office: '' })
const loading  = ref(false)
const result   = ref(null)

const discPct = computed(() => {
  if (!result.value || !result.value.expected_total) return 100
  return Math.round((result.value.actual_total / result.value.expected_total) * 10000) / 100
})

async function runReconciliation () {
  loading.value = true
  result.value  = null
  try {
    const payload = { from: filters.from, to: filters.to }
    if (filters.office) payload.office = filters.office
    const res    = await axios.post('/distribution/reconcile', payload)
    result.value = res.data
  } catch (e) {
    console.error('Reconciliation error:', e)
  } finally {
    loading.value = false
  }
}

function formatAmount (val) {
  return Number(val || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function methodLabel (m) {
  return { cash: '💵 Cash', 'e-wallet': '📱 E-Wallet', bank_transfer: '🏦 Bank Transfer' }[m] ?? m
}

function methodClass (m) {
  return { cash: 'method-cash', 'e-wallet': 'method-ewallet', bank_transfer: 'method-bank' }[m] ?? ''
}

function exportReport () {
  if (!result.value) return
  const rows = [
    ['Reconciliation Report'],
    ['Period', `${filters.from} to ${filters.to}`],
    ['Office', filters.office || 'All'],
    [''],
    ['Expected Total', result.value.expected_total],
    ['Actual Total', result.value.actual_total],
    ['Discrepancy', result.value.discrepancy],
    ['Discrepancy %', result.value.discrepancy_pct],
    ['Count', result.value.count],
    [''],
    ['By Method'],
    ['Method', 'Count', 'Amount'],
    ...Object.entries(result.value.by_method).map(([m, d]) => [m, d.count, d.amount]),
    [''],
    ['By Office'],
    ['Office', 'Count', 'Amount'],
    ...Object.entries(result.value.by_office).map(([o, d]) => [o, d.count, d.amount]),
  ]

  const csv      = rows.map(r => r.join(',')).join('\n')
  const blob     = new Blob([csv], { type: 'text/csv' })
  const url      = URL.createObjectURL(blob)
  const a        = document.createElement('a')
  a.href         = url
  a.download     = `reconciliation_${filters.from}_${filters.to}.csv`
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<style scoped>
.recon-dashboard { display: flex; flex-direction: column; gap: 16px; }

.recon-header {
  display: flex; align-items: flex-start; justify-content: space-between;
}
.recon-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin: 0; }
.recon-subtitle { font-size: 0.8rem; color: #94a3b8; margin-top: 2px; }

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
.btn-export:hover:not(:disabled) { opacity: 0.9; }
.btn-export:disabled { opacity: 0.4; cursor: not-allowed; }

.filter-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
}
.filter-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 150px; }
.filter-label { font-size: 0.72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
.filter-input {
  padding: 7px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.82rem;
  color: #1e293b;
  background: #f8fafc;
  width: 100%;
}
.filter-input:focus { outline: none; border-color: #3b82f6; background: white; }

.filter-btn-group { flex: none; }
.btn-run {
  padding: 8px 22px;
  background: linear-gradient(135deg, #1e40af, #1565c0);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}
.btn-run:hover:not(:disabled) { opacity: 0.9; }
.btn-run:disabled { opacity: 0.5; cursor: not-allowed; }

.loading-state {
  display: flex; align-items: center; gap: 12px;
  padding: 40px; justify-content: center; color: #64748b;
}
.spinner {
  width: 24px; height: 24px;
  border: 3px solid #e2e8f0;
  border-top-color: #3b82f6;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }

.kpi-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.expected { border-top: 4px solid #6366f1; }
.actual   { border-top: 4px solid #059669; }
.disc-ok  { border-top: 4px solid #10b981; }
.disc-flagged { border-top: 4px solid #ef4444; background: #fff5f5; }
.count    { border-top: 4px solid #f59e0b; }

.kpi-icon  { font-size: 1.4rem; }
.kpi-label { font-size: 0.72rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.kpi-val   { font-size: 1.35rem; font-weight: 800; color: #0f172a; }
.kpi-sub   { font-size: 0.72rem; color: #ef4444; font-weight: 600; }
.disc-ok .kpi-sub { color: #059669; }

.disc-bar-wrapper {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 14px 18px;
}
.disc-bar-label { font-size: 0.8rem; color: #475569; margin-bottom: 8px; }
.disc-track {
  position: relative;
  height: 14px;
  background: #f1f5f9;
  border-radius: 7px;
  overflow: hidden;
}
.disc-fill {
  height: 100%;
  border-radius: 7px;
  transition: width 0.6s ease;
}
.disc-fill-ok   { background: linear-gradient(90deg, #059669, #10b981); }
.disc-fill-warn { background: linear-gradient(90deg, #f59e0b, #ef4444); }
.disc-target {
  position: absolute;
  right: 0; top: 0; bottom: 0;
  width: 2px;
  background: rgba(0,0,0,0.2);
}

.breakdown-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.breakdown-table {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.bt-title {
  padding: 12px 16px;
  font-weight: 700;
  font-size: 0.82rem;
  color: #1e293b;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.mini-table { width: 100%; border-collapse: collapse; }
.mini-table th {
  padding: 8px 14px;
  font-size: 0.7rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  text-align: left;
  border-bottom: 1px solid #f1f5f9;
}
.mini-table td { padding: 9px 14px; font-size: 0.8rem; border-bottom: 1px solid #f8fafc; }
.amount-cell { font-weight: 700; color: #059669; }
.office-cell { font-weight: 600; color: #1e293b; }
.empty-cell  { text-align: center; color: #94a3b8; font-style: italic; padding: 16px; }

.method-badge { padding: 3px 10px; border-radius: 12px; font-size: 0.72rem; font-weight: 700; }
.method-cash    { background: #f0fdf4; color: #15803d; }
.method-ewallet { background: #eff6ff; color: #1d4ed8; }
.method-bank    { background: #fff7ed; color: #c2410c; }

.flag-notice {
  display: flex;
  gap: 14px;
  align-items: flex-start;
  background: #fff5f5;
  border: 1px solid #fecaca;
  border-radius: 12px;
  padding: 16px;
}
.flag-icon  { font-size: 1.5rem; flex-shrink: 0; }
.flag-title { font-weight: 700; color: #dc2626; margin-bottom: 4px; }
.flag-body  { font-size: 0.82rem; color: #7f1d1d; line-height: 1.6; }

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: #94a3b8;
  background: white;
  border: 1px dashed #e2e8f0;
  border-radius: 12px;
}
.empty-icon { font-size: 3rem; margin-bottom: 10px; }
.empty-text { font-size: 0.9rem; color: #64748b; }

@media (max-width: 768px) {
  .kpi-grid       { grid-template-columns: 1fr 1fr; }
  .breakdown-grid { grid-template-columns: 1fr; }
}
</style>
