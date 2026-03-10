<template>
  <div class="bulk-upload">
    <div class="upload-header">
      <div>
        <h3 class="upload-title">📤 Bulk Distribution Upload</h3>
        <p class="upload-sub">Upload a CSV file to record multiple distributions at once</p>
      </div>
      <button class="btn-template" @click="downloadTemplate">📋 Download Template</button>
    </div>

    <!-- Drop zone -->
    <div
      class="drop-zone"
      :class="{ 'drag-over': isDragging, 'has-file': file }"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
      @click="triggerFileInput"
    >
      <input
        ref="fileInputRef"
        type="file"
        accept=".csv"
        class="hidden-input"
        @change="onFileSelected"
      />
      <div v-if="!file" class="drop-idle">
        <div class="drop-icon">☁️</div>
        <div class="drop-text">Drag & drop CSV file here</div>
        <div class="drop-sub">or click to browse — max 5 MB</div>
      </div>
      <div v-else class="drop-selected">
        <div class="file-icon">📄</div>
        <div class="file-info">
          <div class="file-name">{{ file.name }}</div>
          <div class="file-size">{{ formatSize(file.size) }}</div>
        </div>
        <button class="btn-remove" @click.stop="removeFile">✕</button>
      </div>
    </div>

    <!-- Parse status -->
    <div v-if="parseError" class="alert alert-error">
      ❌ {{ parseError }}
    </div>

    <!-- Preview -->
    <div v-if="preview.length" class="preview-section">
      <div class="preview-header">
        <div class="preview-title">Preview — {{ preview.length }} record(s)</div>
        <div class="preview-badges">
          <span class="badge-valid">✅ {{ validCount }} valid</span>
          <span class="badge-invalid" v-if="invalidCount">❌ {{ invalidCount }} invalid</span>
        </div>
      </div>

      <div class="preview-table-wrap">
        <table class="preview-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Beneficiary ID</th>
              <th>Amount (₱)</th>
              <th>Period</th>
              <th>Month</th>
              <th>Year</th>
              <th>Method</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, idx) in preview"
              :key="idx"
              :class="row._valid ? 'row-valid' : 'row-invalid'"
            >
              <td>{{ idx + 1 }}</td>
              <td>{{ row.beneficiary_id }}</td>
              <td>₱{{ Number(row.payout_amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>
              <td>{{ row.payout_period }}</td>
              <td>{{ row.payout_month }}</td>
              <td>{{ row.payout_year }}</td>
              <td>
                <span class="method-badge" :class="methodClass(row.payment_method)">{{ methodLabel(row.payment_method) }}</span>
              </td>
              <td>
                <span v-if="row._valid" class="status-ok">✅ Valid</span>
                <span v-else class="status-err" :title="row._errors?.join(', ')">❌ {{ row._errors?.[0] }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="import-actions">
        <button
          class="btn-import"
          :disabled="!validCount || uploading"
          @click="importData"
        >
          <span v-if="uploading">⏳ Importing...</span>
          <span v-else>📥 Import {{ validCount }} Valid Record(s)</span>
        </button>
      </div>
    </div>

    <!-- Progress -->
    <div v-if="uploading" class="progress-bar-wrap">
      <div class="progress-track">
        <div class="progress-fill" :style="{ width: uploadProgress + '%' }"></div>
      </div>
      <div class="progress-label">{{ uploadProgress }}%</div>
    </div>

    <!-- Results -->
    <div v-if="importResult" class="results-section">
      <div class="results-header">
        <div class="results-title">Import Results</div>
      </div>
      <div class="results-kpis">
        <div class="res-kpi success">
          <div class="rkpi-label">✅ Successful</div>
          <div class="rkpi-val">{{ importResult.success_count }}</div>
        </div>
        <div class="res-kpi failure" v-if="importResult.failure_count">
          <div class="rkpi-label">❌ Failed</div>
          <div class="rkpi-val">{{ importResult.failure_count }}</div>
        </div>
      </div>

      <div v-if="importResult.failure_count" class="failure-list">
        <div class="fl-title">Failed Records</div>
        <div
          v-for="(fail, i) in importResult.failures"
          :key="i"
          class="fail-row"
        >
          <span class="fail-idx">Row {{ fail.index + 1 }}</span>
          <span class="fail-id">BID: {{ fail.beneficiary_id }}</span>
          <span class="fail-reason">{{ fail.reason }}</span>
        </div>
        <button class="btn-error-report" @click="downloadErrorReport">📥 Download Error Report</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const emit = defineEmits(['imported'])

const fileInputRef = ref(null)
const file         = ref(null)
const isDragging   = ref(false)
const parseError   = ref(null)
const preview      = ref([])
const uploading    = ref(false)
const uploadProgress = ref(0)
const importResult = ref(null)

const VALID_METHODS = ['cash', 'e-wallet', 'bank_transfer']

const validCount   = computed(() => preview.value.filter(r => r._valid).length)
const invalidCount = computed(() => preview.value.filter(r => !r._valid).length)

function triggerFileInput () { fileInputRef.value?.click() }

function onDrop (e) {
  isDragging.value = false
  const droppedFile = e.dataTransfer.files[0]
  if (droppedFile) handleFile(droppedFile)
}

function onFileSelected (e) {
  const f = e.target.files[0]
  if (f) handleFile(f)
}

function removeFile () {
  file.value      = null
  preview.value   = []
  parseError.value = null
  importResult.value = null
  if (fileInputRef.value) fileInputRef.value.value = ''
}

function handleFile (f) {
  parseError.value = null
  preview.value    = []
  importResult.value = null

  if (!f.name.toLowerCase().endsWith('.csv')) {
    parseError.value = 'Please upload a CSV file (.csv)'
    return
  }
  if (f.size > 5 * 1024 * 1024) {
    parseError.value = 'File exceeds 5 MB limit.'
    return
  }

  file.value = f
  parseCsv(f)
}

function parseCsv (f) {
  const reader = new FileReader()
  reader.onload = (e) => {
    const text  = e.target.result
    const lines = text.split('\n').map(l => l.trim()).filter(Boolean)
    if (lines.length < 2) {
      parseError.value = 'CSV is empty or has only a header row.'
      return
    }

    const header = lines[0].split(',').map(h => h.trim().toLowerCase())
    const rows   = []

    for (let i = 1; i < lines.length; i++) {
      const values  = lines[i].split(',').map(v => v.trim())
      const row     = {}
      const errors  = []
      header.forEach((h, j) => { row[h] = values[j] ?? '' })

      // Validate
      if (!row.beneficiary_id || isNaN(Number(row.beneficiary_id)))
        errors.push('beneficiary_id required')
      if (!row.payout_amount || isNaN(Number(row.payout_amount)) || Number(row.payout_amount) < 0)
        errors.push('invalid payout_amount')
      if (!row.payout_period)
        errors.push('payout_period required')
      if (!row.payout_month || isNaN(Number(row.payout_month)) || Number(row.payout_month) < 1 || Number(row.payout_month) > 12)
        errors.push('invalid payout_month (1-12)')
      if (!row.payout_year || isNaN(Number(row.payout_year)) || Number(row.payout_year) < 2020)
        errors.push('invalid payout_year')
      if (!VALID_METHODS.includes(row.payment_method))
        errors.push(`payment_method must be: ${VALID_METHODS.join(', ')}`)

      row._valid  = errors.length === 0
      row._errors = errors
      rows.push(row)
    }

    preview.value = rows
  }
  reader.onerror = () => { parseError.value = 'Failed to read file.' }
  reader.readAsText(f)
}

async function importData () {
  const records = preview.value
    .filter(r => r._valid)
    .map(r => ({
      beneficiary_id: Number(r.beneficiary_id),
      payout_amount:  Number(r.payout_amount),
      payout_period:  r.payout_period,
      payout_month:   Number(r.payout_month),
      payout_year:    Number(r.payout_year),
      payment_method: r.payment_method,
      remarks:        r.remarks ?? 'Bulk CSV import',
    }))

  uploading.value     = true
  uploadProgress.value = 0
  importResult.value  = null

  // Simulate progress
  const progressInterval = setInterval(() => {
    if (uploadProgress.value < 85) uploadProgress.value += 5
  }, 150)

  try {
    const res        = await axios.post('/distribution/bulk', { records })
    importResult.value = res.data
    uploadProgress.value = 100
    emit('imported', res.data)
  } catch (e) {
    importResult.value = {
      success_count: 0,
      failure_count: records.length,
      failures: [{ index: 0, beneficiary_id: '—', reason: e.response?.data?.message ?? 'Server error' }],
    }
  } finally {
    clearInterval(progressInterval)
    uploading.value = false
  }
}

function downloadTemplate () {
  const csvContent = [
    'beneficiary_id,payout_amount,payout_period,payout_month,payout_year,payment_method,remarks',
    '1,3000,January 2026,1,2026,cash,Regular monthly distribution',
    '2,4000,January 2026,1,2026,e-wallet,',
  ].join('\n')
  const blob = new Blob([csvContent], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href = url; a.download = 'distribution_template.csv'; a.click()
  URL.revokeObjectURL(url)
}

function downloadErrorReport () {
  if (!importResult.value?.failures?.length) return
  const rows = [
    ['Row', 'Beneficiary ID', 'Reason'],
    ...importResult.value.failures.map(f => [f.index + 1, f.beneficiary_id, f.reason]),
  ]
  const csv    = rows.map(r => r.join(',')).join('\n')
  const blob   = new Blob([csv], { type: 'text/csv' })
  const url    = URL.createObjectURL(blob)
  const a      = document.createElement('a')
  a.href = url; a.download = 'error_report.csv'; a.click()
  URL.revokeObjectURL(url)
}

function formatSize (bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

function methodLabel (m) { return { cash: '💵 Cash', 'e-wallet': '📱 E-Wallet', bank_transfer: '🏦 Bank' }[m] ?? m }
function methodClass (m) { return { cash: 'method-cash', 'e-wallet': 'method-ewallet', bank_transfer: 'method-bank' }[m] ?? '' }
</script>

<style scoped>
.bulk-upload { display: flex; flex-direction: column; gap: 16px; }

.upload-header { display: flex; align-items: flex-start; justify-content: space-between; }
.upload-title  { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; }
.upload-sub    { font-size: 0.78rem; color: #94a3b8; margin-top: 2px; }

.btn-template {
  padding: 7px 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.78rem;
  color: #475569;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-template:hover { background: #e2e8f0; }

.drop-zone {
  border: 2px dashed #cbd5e1;
  border-radius: 14px;
  background: #f8fafc;
  padding: 36px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
}
.drop-zone.drag-over { border-color: #3b82f6; background: #eff6ff; }
.drop-zone.has-file  { border-style: solid; border-color: #10b981; background: #f0fdf4; }

.hidden-input { display: none; }

.drop-idle .drop-icon { font-size: 2.5rem; margin-bottom: 8px; }
.drop-text { font-weight: 600; color: #475569; font-size: 0.9rem; }
.drop-sub  { font-size: 0.75rem; color: #94a3b8; margin-top: 4px; }

.drop-selected {
  display: flex;
  align-items: center;
  gap: 12px;
  text-align: left;
}
.file-icon { font-size: 2rem; }
.file-name { font-weight: 600; color: #1e293b; font-size: 0.85rem; }
.file-size { font-size: 0.72rem; color: #94a3b8; }
.btn-remove {
  margin-left: auto;
  background: none;
  border: none;
  color: #94a3b8;
  font-size: 1.1rem;
  cursor: pointer;
  padding: 4px 8px;
  border-radius: 50%;
  transition: all 0.15s;
}
.btn-remove:hover { background: #fee2e2; color: #dc2626; }

.alert {
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 0.82rem;
  font-weight: 600;
}
.alert-error { background: #fff5f5; border: 1px solid #fecaca; color: #dc2626; }

.preview-section {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}

.preview-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.preview-title { font-weight: 700; font-size: 0.85rem; color: #1e293b; }
.preview-badges { display: flex; gap: 8px; }
.badge-valid, .badge-invalid {
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 0.72rem;
  font-weight: 700;
}
.badge-valid   { background: #f0fdf4; color: #15803d; }
.badge-invalid { background: #fff5f5; color: #dc2626; }

.preview-table-wrap { overflow-x: auto; max-height: 300px; overflow-y: auto; }
.preview-table { width: 100%; border-collapse: collapse; min-width: 700px; }
.preview-table th {
  padding: 8px 12px;
  font-size: 0.7rem;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  position: sticky; top: 0;
}
.preview-table td { padding: 8px 12px; font-size: 0.78rem; border-bottom: 1px solid #f8fafc; }
.row-valid   { background: white; }
.row-invalid { background: #fff8f8; }
.status-ok  { color: #15803d; font-weight: 600; font-size: 0.75rem; }
.status-err { color: #dc2626; font-weight: 600; font-size: 0.75rem; white-space: nowrap; }

.method-badge { padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; font-weight: 700; }
.method-cash    { background: #f0fdf4; color: #15803d; }
.method-ewallet { background: #eff6ff; color: #1d4ed8; }
.method-bank    { background: #fff7ed; color: #c2410c; }

.import-actions { padding: 12px 16px; display: flex; justify-content: flex-end; }
.btn-import {
  padding: 9px 22px;
  background: linear-gradient(135deg, #1e40af, #1565c0);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-import:hover:not(:disabled) { opacity: 0.9; }
.btn-import:disabled { opacity: 0.45; cursor: not-allowed; }

.progress-bar-wrap { display: flex; align-items: center; gap: 12px; }
.progress-track {
  flex: 1;
  height: 10px;
  background: #f1f5f9;
  border-radius: 5px;
  overflow: hidden;
}
.progress-fill {
  height: 100%;
  background: linear-gradient(90deg, #3b82f6, #1d4ed8);
  border-radius: 5px;
  transition: width 0.3s ease;
}
.progress-label { font-size: 0.78rem; color: #64748b; font-weight: 600; white-space: nowrap; }

.results-section {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}
.results-header {
  padding: 12px 16px;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}
.results-title { font-weight: 700; font-size: 0.85rem; color: #1e293b; }

.results-kpis { display: flex; gap: 10px; padding: 12px 16px; }
.res-kpi {
  padding: 10px 18px;
  border-radius: 10px;
  text-align: center;
}
.res-kpi.success { background: #f0fdf4; border: 1px solid #86efac; }
.res-kpi.failure { background: #fff5f5; border: 1px solid #fecaca; }
.rkpi-label { font-size: 0.72rem; color: #64748b; font-weight: 600; }
.rkpi-val   { font-size: 1.3rem; font-weight: 800; color: #0f172a; }

.failure-list { padding: 0 16px 16px; }
.fl-title { font-weight: 700; font-size: 0.78rem; color: #dc2626; margin-bottom: 8px; }
.fail-row {
  display: flex;
  gap: 12px;
  padding: 7px 10px;
  background: #fff5f5;
  border-radius: 6px;
  margin-bottom: 4px;
  font-size: 0.78rem;
  align-items: center;
  flex-wrap: wrap;
}
.fail-idx    { font-weight: 700; color: #64748b; }
.fail-id     { font-weight: 700; color: #1e293b; }
.fail-reason { color: #dc2626; flex: 1; }

.btn-error-report {
  margin-top: 10px;
  padding: 7px 14px;
  background: #fff5f5;
  border: 1px solid #fecaca;
  color: #dc2626;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.78rem;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-error-report:hover { background: #fee2e2; }
</style>
