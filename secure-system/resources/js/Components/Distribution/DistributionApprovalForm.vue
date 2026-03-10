<template>
  <div class="approval-form">
    <!-- Beneficiary card -->
    <div class="bene-card">
      <div class="bene-avatar">{{ initials }}</div>
      <div class="bene-info">
        <div class="bene-name">{{ beneficiary.family_head_name }}</div>
        <div class="bene-meta">
          <span class="bin-tag">BIN: {{ beneficiary.bin }}</span>
          <span class="location">📍 {{ beneficiary.barangay }}, {{ beneficiary.municipality }}, {{ beneficiary.province }}</span>
          <span class="hh">👥 {{ beneficiary.household_size }} household member(s)</span>
        </div>
      </div>
      <div class="bene-status" :class="beneficiary.is_active ? 'status-active' : 'status-inactive'">
        {{ beneficiary.is_active ? '🟢 Active' : '🔴 Inactive' }}
      </div>
    </div>

    <!-- Two-column layout: checks left, form right -->
    <div class="layout-cols">
      <!-- LEFT: Compliance Checklist -->
      <div class="left-col">
        <!-- Compliance summary checklist -->
        <div class="checklist-card">
          <div class="card-title">📋 Compliance Checklist</div>
          <div class="check-items">
            <div
              v-for="check in checklistItems"
              :key="check.key"
              class="check-item"
              :class="check.passed ? 'check-pass' : 'check-fail'"
            >
              <span class="check-icon">{{ check.passed ? '✅' : '❌' }}</span>
              <div class="check-body">
                <div class="check-label">{{ check.label }}</div>
                <div class="check-detail">{{ check.detail }}</div>
              </div>
            </div>
          </div>

          <!-- Eligibility result -->
          <div
            v-if="eligibilityChecked"
            class="eligibility-result"
            :class="isEligible ? 'result-eligible' : 'result-ineligible'"
          >
            <div class="result-icon">{{ isEligible ? '🎉' : '⛔' }}</div>
            <div class="result-body">
              <div class="result-title">
                {{ isEligible ? 'Eligible for Distribution' : 'Not Eligible' }}
              </div>
              <div v-if="!isEligible" class="result-issues">
                <div v-for="(issue, i) in ineligibilityIssues" :key="i">• {{ issue }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payout Calculator -->
        <PayoutCalculator
          v-if="calculation"
          :calculation="calculation"
          :is-admin="isAdmin"
          @override="handleOverride"
          class="mt-4"
        />
      </div>

      <!-- RIGHT: Distribution Form -->
      <div class="right-col">
        <div class="form-card">
          <div class="card-title">📝 Distribution Details</div>

          <!-- Period selector -->
          <div class="form-row-2">
            <div class="form-group">
              <label class="form-label">Payout Month *</label>
              <select v-model="form.payout_month" class="form-select" @change="checkEligibility">
                <option value="">Select month</option>
                <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Payout Year *</label>
              <select v-model="form.payout_year" class="form-select" @change="checkEligibility">
                <option v-for="y in yearList" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Payout Period Label *</label>
            <input
              v-model="form.payout_period"
              type="text"
              class="form-input"
              :placeholder="defaultPeriodLabel"
            />
          </div>

          <!-- Payment method -->
          <div class="form-group">
            <label class="form-label">Payment Method *</label>
            <div class="method-options">
              <label
                v-for="m in paymentMethods"
                :key="m.value"
                class="method-opt"
                :class="{ selected: form.payment_method === m.value }"
              >
                <input type="radio" v-model="form.payment_method" :value="m.value" class="method-radio" />
                <span class="method-icon">{{ m.icon }}</span>
                <span class="method-name">{{ m.label }}</span>
              </label>
            </div>
          </div>

          <!-- Transaction reference (e-wallet / bank) -->
          <div class="form-group" v-if="form.payment_method !== 'cash'">
            <label class="form-label">Transaction Reference Number</label>
            <input
              v-model="form.transaction_reference_number"
              type="text"
              class="form-input"
              placeholder="TXN-XXXXXXXX"
            />
          </div>

          <!-- Payout amount (editable with override) -->
          <div class="form-group">
            <label class="form-label">
              Payout Amount *
              <span v-if="overrideApplied" class="override-badge">⚠️ Overridden</span>
            </label>
            <div class="amount-input-wrap">
              <span class="peso-sign">₱</span>
              <input
                v-model.number="form.payout_amount"
                type="number"
                step="0.01"
                min="0"
                class="form-input amount-input"
                :readonly="!isAdmin || !overrideApplied"
              />
            </div>
            <div v-if="overrideReason" class="override-reason-note">
              Override reason: {{ overrideReason }}
            </div>
          </div>

          <!-- Remarks -->
          <div class="form-group">
            <label class="form-label">Remarks</label>
            <textarea
              v-model="form.remarks"
              rows="3"
              class="form-textarea"
              placeholder="Optional notes..."
            ></textarea>
          </div>

          <!-- Signature -->
          <div class="form-group">
            <SignaturePad @save="onSignatureSaved" @clear="form.signature_data_url = null" />
          </div>

          <!-- Submit button -->
          <div class="form-actions">
            <button
              v-if="canApprove && eligibilityChecked && isEligible"
              type="button"
              class="btn-approve"
              :disabled="submitting || !formValid"
              @click="showConfirm = true"
            >
              <span v-if="submitting">⏳ Processing...</span>
              <span v-else>💰 Record Distribution</span>
            </button>
            <div v-else-if="eligibilityChecked && !isEligible" class="not-eligible-msg">
              ⛔ Cannot distribute — eligibility requirements not met
            </div>
            <button
              v-else-if="!eligibilityChecked"
              type="button"
              class="btn-check"
              @click="checkEligibility"
              :disabled="!form.payout_month || !form.payout_year || checkingEligibility"
            >
              <span v-if="checkingEligibility">⏳ Checking...</span>
              <span v-else>🔍 Check Eligibility</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showConfirm" class="modal-overlay" @click.self="showConfirm = false">
        <div class="confirm-modal">
          <div class="cm-header">⚠️ Confirm Distribution</div>
          <div class="cm-body">
            <p>You are about to record a cash grant distribution to:</p>
            <div class="cm-bene">
              <strong>{{ beneficiary.family_head_name }}</strong>
              <span>BIN: {{ beneficiary.bin }}</span>
            </div>
            <div class="cm-amount">₱{{ formatAmount(form.payout_amount) }}</div>
            <p class="cm-period">Period: <strong>{{ form.payout_period || defaultPeriodLabel }}</strong></p>
            <p class="cm-method">Method: <strong>{{ methodLabels[form.payment_method] }}</strong></p>
          </div>
          <div class="cm-actions">
            <button class="btn-cancel" @click="showConfirm = false">Cancel</button>
            <button class="btn-confirm" @click="submitDistribution" :disabled="submitting">
              {{ submitting ? '⏳ Processing...' : '✅ Confirm & Record' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Success notification -->
    <div v-if="successData" class="success-banner">
      <div class="sb-icon">🎉</div>
      <div class="sb-body">
        <div class="sb-title">Distribution Recorded!</div>
        <div class="sb-detail">
          ₱{{ formatAmount(successData.distribution?.payout_amount) }} distributed.
          Ref: {{ successData.distribution?.transaction_reference_number }}
        </div>
      </div>
      <a v-if="successData.receipt_url" :href="successData.receipt_url" target="_blank" class="sb-receipt">
        📄 View Receipt
      </a>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import axios from 'axios'
import SignaturePad from './SignaturePad.vue'
import PayoutCalculator from './PayoutCalculator.vue'

const props = defineProps({
  beneficiary:  { type: Object, required: true },
  calculation:  { type: Object, default: null },
  summary:      { type: Object, default: null },
  canApprove:   { type: Boolean, default: false },
  isAdmin:      { type: Boolean, default: false },
})

const emit = defineEmits(['distributed'])

/* ── State ─────────────────────────────────────────────── */
const showConfirm       = ref(false)
const submitting        = ref(false)
const checkingEligibility = ref(false)
const eligibilityChecked  = ref(false)
const isEligible          = ref(false)
const ineligibilityIssues = ref([])
const serverChecks        = ref([])
const overrideApplied     = ref(false)
const overrideReason      = ref('')
const successData         = ref(null)

const currentYear  = new Date().getFullYear()
const currentMonth = new Date().getMonth() + 1

const form = reactive({
  payout_month:                 currentMonth,
  payout_year:                  currentYear,
  payout_period:                '',
  payment_method:               'cash',
  transaction_reference_number: '',
  payout_amount:                props.calculation?.total_amount ?? 0,
  remarks:                      '',
  signature_data_url:           null,
})

/* ── Computed ──────────────────────────────────────────── */
const initials = computed(() => {
  return (props.beneficiary.family_head_name ?? '?')
    .split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
})

const yearList = computed(() =>
  Array.from({ length: 6 }, (_, i) => currentYear - i)
)

const defaultPeriodLabel = computed(() =>
  monthName(form.payout_month) + ' ' + form.payout_year
)

const formValid = computed(() =>
  form.payout_month && form.payout_year && form.payment_method && form.payout_amount > 0
)

const paymentMethods = [
  { value: 'cash',          label: 'Cash',          icon: '💵' },
  { value: 'e-wallet',      label: 'E-Wallet',       icon: '📱' },
  { value: 'bank_transfer', label: 'Bank Transfer',  icon: '🏦' },
]

const methodLabels = { cash: 'Cash', 'e-wallet': 'E-Wallet', bank_transfer: 'Bank Transfer' }

/* ── Checklist items (from summary + server check) ─────── */
const checklistItems = computed(() => {
  if (serverChecks.value.length) {
    return serverChecks.value.map(c => ({
      key:    c.check,
      label:  c.check,
      detail: c.detail,
      passed: c.passed,
    }))
  }
  // Fallback from summary prop
  if (!props.summary) return []
  return [
    {
      key:    'overall',
      label:  'Overall Compliance Status',
      detail: props.summary.overall_compliance_status ?? '—',
      passed: props.summary.overall_compliance_status === 'compliant',
    },
    {
      key:    'education',
      label:  'Education Compliance (≥ 85%)',
      detail: (props.summary.education_compliance_percentage ?? 0) + '%',
      passed: (props.summary.education_compliance_percentage ?? 0) >= 85,
    },
    {
      key:    'health',
      label:  'Health Compliance (≥ 80%)',
      detail: (props.summary.health_compliance_percentage ?? 0) + '%',
      passed: (props.summary.health_compliance_percentage ?? 0) >= 80,
    },
    {
      key:    'fds',
      label:  'FDS Session Compliance',
      detail: (props.summary.fds_compliance_percentage ?? 0) + '%',
      passed: (props.summary.fds_compliance_percentage ?? 0) > 0,
    },
  ]
})

/* ── Watch for period defaults ─────────────────────────── */
watch([() => form.payout_month, () => form.payout_year], () => {
  if (!form.payout_period) form.payout_period = ''
  eligibilityChecked.value = false
})

/* ── Methods ───────────────────────────────────────────── */
async function checkEligibility () {
  if (!form.payout_month || !form.payout_year) return
  checkingEligibility.value = true
  eligibilityChecked.value  = false
  try {
    const res = await axios.post(
      `/distribution/approve/${props.beneficiary.id}`,
      { payout_month: form.payout_month, payout_year: form.payout_year }
    )
    eligibilityChecked.value  = true
    isEligible.value          = res.data.eligible
    ineligibilityIssues.value = res.data.issues ?? []
    serverChecks.value        = res.data.checks ?? []
    if (res.data.calculation) {
      form.payout_amount = res.data.calculation.total_amount
    }
  } catch (e) {
    const data = e.response?.data
    if (data && 'eligible' in data) {
      eligibilityChecked.value  = true
      isEligible.value          = false
      ineligibilityIssues.value = data.issues ?? []
      serverChecks.value        = data.checks ?? []
    } else {
      console.error('Eligibility check failed:', e)
    }
  } finally {
    checkingEligibility.value = false
  }
}

async function submitDistribution () {
  submitting.value = true
  showConfirm.value = false
  try {
    const payload = {
      beneficiary_id:               props.beneficiary.id,
      payout_amount:                form.payout_amount,
      payout_period:                form.payout_period || defaultPeriodLabel.value,
      payout_month:                 form.payout_month,
      payout_year:                  form.payout_year,
      payment_method:               form.payment_method,
      transaction_reference_number: form.transaction_reference_number || null,
      remarks:                      form.remarks || null,
      signature_data_url:           form.signature_data_url || null,
    }
    const res      = await axios.post('/distribution/record', payload)
    successData.value = res.data
    emit('distributed', res.data)
    eligibilityChecked.value = false
  } catch (e) {
    console.error('Distribution record failed:', e)
    alert(e.response?.data?.message ?? 'Failed to record distribution.')
  } finally {
    submitting.value = false
  }
}

function onSignatureSaved (dataUrl) {
  form.signature_data_url = dataUrl
}

function handleOverride ({ amount, reason }) {
  form.payout_amount = amount
  overrideApplied.value = true
  overrideReason.value  = reason
}

function monthName (m) {
  return new Date(2000, m - 1, 1).toLocaleString('en', { month: 'long' })
}

function formatAmount (val) {
  return Number(val || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<style scoped>
.approval-form { display: flex; flex-direction: column; gap: 18px; }

/* Beneficiary card */
.bene-card {
  display: flex;
  align-items: center;
  gap: 16px;
  background: linear-gradient(135deg, #1e40af, #1565c0);
  color: white;
  border-radius: 14px;
  padding: 18px 22px;
}

.bene-avatar {
  width: 52px; height: 52px;
  background: rgba(255,255,255,0.2);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 800;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.bene-name { font-size: 1.1rem; font-weight: 800; margin-bottom: 4px; }
.bene-meta { display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.78rem; opacity: 0.85; }

.bin-tag {
  background: rgba(255,255,255,0.2);
  padding: 2px 8px;
  border-radius: 10px;
  font-family: monospace;
  font-weight: 700;
  font-size: 0.72rem;
}

.bene-status {
  margin-left: auto;
  padding: 5px 12px;
  border-radius: 20px;
  font-weight: 700;
  font-size: 0.78rem;
  white-space: nowrap;
}
.status-active   { background: rgba(255,255,255,0.15); }
.status-inactive { background: rgba(239, 68, 68, 0.3); }

/* Layout */
.layout-cols {
  display: grid;
  grid-template-columns: 1fr 1.2fr;
  gap: 16px;
  align-items: start;
}

.mt-4 { margin-top: 14px; }

/* Checklist card */
.checklist-card, .form-card {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
}

.card-title {
  padding: 14px 18px;
  font-weight: 800;
  font-size: 0.88rem;
  color: #0f172a;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}

.check-items { padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; }

.check-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid transparent;
}
.check-pass { background: #f0fdf4; border-color: #bbf7d0; }
.check-fail { background: #fff5f5; border-color: #fecaca; }

.check-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }
.check-label { font-weight: 600; font-size: 0.8rem; color: #1e293b; }
.check-detail { font-size: 0.72rem; color: #64748b; margin-top: 2px; }

.eligibility-result {
  margin: 0 16px 16px;
  padding: 14px;
  border-radius: 10px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
}
.result-eligible  { background: #f0fdf4; border: 1px solid #86efac; }
.result-ineligible { background: #fff5f5; border: 1px solid #fecaca; }

.result-icon { font-size: 1.5rem; }
.result-title { font-weight: 700; font-size: 0.85rem; color: #1e293b; margin-bottom: 4px; }
.result-issues { font-size: 0.75rem; color: #dc2626; line-height: 1.6; }

/* Form */
.form-card { padding: 18px; display: flex; flex-direction: column; gap: 14px; }

.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 0.75rem; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; }

.form-input, .form-select, .form-textarea {
  padding: 9px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  font-size: 0.85rem;
  color: #1e293b;
  background: #f8fafc;
  transition: border-color 0.15s, box-shadow 0.15s;
  width: 100%;
}
.form-input:focus, .form-select:focus, .form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  background: white;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.form-input:read-only { background: #f1f5f9; cursor: not-allowed; color: #94a3b8; }
.form-textarea { resize: vertical; }

.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

.method-options {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.method-opt {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  font-size: 0.8rem;
  color: #475569;
  transition: all 0.15s;
  user-select: none;
}
.method-opt.selected {
  border-color: #3b82f6;
  background: #eff6ff;
  color: #1d4ed8;
  font-weight: 700;
}
.method-radio { display: none; }
.method-icon  { font-size: 1.1rem; }

.amount-input-wrap { display: flex; align-items: center; }
.peso-sign {
  padding: 9px 12px;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-right: none;
  border-radius: 8px 0 0 8px;
  font-weight: 700;
  color: #475569;
}
.amount-input { border-radius: 0 8px 8px 0; }

.override-badge {
  background: #fef3c7;
  color: #b45309;
  padding: 1px 8px;
  border-radius: 10px;
  font-size: 0.7rem;
  margin-left: 8px;
}
.override-reason-note { font-size: 0.72rem; color: #b45309; margin-top: 4px; font-style: italic; }

.form-actions { display: flex; justify-content: flex-end; }

.btn-approve {
  padding: 12px 28px;
  background: linear-gradient(135deg, #059669, #047857);
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 800;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.15s;
  box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
}
.btn-approve:hover:not(:disabled) { opacity: 0.9; transform: translateY(-2px); }
.btn-approve:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-check {
  padding: 10px 22px;
  background: linear-gradient(135deg, #1e40af, #1565c0);
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 700;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-check:hover:not(:disabled) { opacity: 0.9; }
.btn-check:disabled { opacity: 0.5; cursor: not-allowed; }

.not-eligible-msg {
  font-size: 0.82rem;
  color: #dc2626;
  font-weight: 600;
  padding: 10px 14px;
  background: #fff5f5;
  border-radius: 8px;
  border: 1px solid #fecaca;
}

/* Confirmation Modal */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.5);
  display: flex; align-items: center; justify-content: center;
  z-index: 9999;
}
.confirm-modal {
  background: white;
  border-radius: 16px;
  padding: 28px;
  max-width: 440px;
  width: 90%;
  box-shadow: 0 20px 60px rgba(0,0,0,0.25);
}
.cm-header {
  font-size: 1.1rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px solid #f1f5f9;
}
.cm-body p { font-size: 0.85rem; color: #475569; margin-bottom: 10px; }
.cm-bene {
  display: flex;
  flex-direction: column;
  gap: 2px;
  background: #f8fafc;
  padding: 10px;
  border-radius: 8px;
  margin-bottom: 14px;
}
.cm-bene strong { font-size: 1rem; color: #1e293b; }
.cm-bene span   { font-size: 0.78rem; color: #64748b; }
.cm-amount {
  font-size: 2rem;
  font-weight: 900;
  color: #059669;
  text-align: center;
  margin-bottom: 12px;
}
.cm-period, .cm-method { font-size: 0.82rem; color: #64748b; }

.cm-actions {
  display: flex;
  gap: 10px;
  justify-content: flex-end;
  margin-top: 20px;
}
.btn-cancel {
  padding: 9px 20px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: white;
  color: #475569;
  font-weight: 600;
  font-size: 0.82rem;
  cursor: pointer;
}
.btn-cancel:hover { background: #f1f5f9; }
.btn-confirm {
  padding: 9px 22px;
  background: linear-gradient(135deg, #059669, #047857);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.82rem;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-confirm:hover:not(:disabled) { opacity: 0.9; }
.btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

/* Success banner */
.success-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 12px;
  padding: 16px;
  animation: slideIn 0.3s ease;
}
@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: none; } }

.sb-icon  { font-size: 2rem; }
.sb-title { font-weight: 700; color: #15803d; }
.sb-detail { font-size: 0.78rem; color: #166534; margin-top: 2px; }
.sb-receipt {
  margin-left: auto;
  padding: 8px 16px;
  background: linear-gradient(135deg, #059669, #047857);
  color: white;
  border-radius: 8px;
  font-weight: 700;
  font-size: 0.78rem;
  text-decoration: none;
  white-space: nowrap;
  transition: all 0.15s;
}
.sb-receipt:hover { opacity: 0.9; }

@media (max-width: 768px) {
  .layout-cols { grid-template-columns: 1fr; }
}
</style>
