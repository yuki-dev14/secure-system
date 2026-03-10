<template>
  <div class="payout-calculator">
    <!-- Header -->
    <div class="calc-header">
      <div class="calc-title">
        <span class="calc-icon">💰</span>
        <span>Payout Calculation</span>
      </div>
      <div class="calc-total">
        ₱{{ formatAmount(calculation.total_amount) }}
      </div>
    </div>

    <!-- Breakdown rows -->
    <div class="breakdown-list">
      <div class="breakdown-row base">
        <div class="row-left">
          <span class="row-dot base-dot"></span>
          <div>
            <div class="row-label">Base Cash Grant</div>
            <div class="row-detail">Standard household payout</div>
          </div>
        </div>
        <div class="row-amount base-amount">₱{{ formatAmount(calculation.base_amount) }}</div>
      </div>

      <div class="breakdown-row edu" v-if="calculation.school_age_children_count > 0 || calculation.education_subsidy > 0">
        <div class="row-left">
          <span class="row-dot edu-dot"></span>
          <div>
            <div class="row-label">Education Subsidy</div>
            <div class="row-detail">
              {{ calculation.school_age_children_count }} school-age child(ren)
              × ₱{{ formatAmount(calculation.per_child_education) }}
            </div>
          </div>
        </div>
        <div class="row-amount edu-amount">+ ₱{{ formatAmount(calculation.education_subsidy) }}</div>
      </div>
      <div class="breakdown-row edu" v-else>
        <div class="row-left">
          <span class="row-dot edu-dot" style="opacity: 0.3;"></span>
          <div>
            <div class="row-label" style="opacity: 0.5;">Education Subsidy</div>
            <div class="row-detail" style="opacity: 0.5;">No school-age children</div>
          </div>
        </div>
        <div class="row-amount edu-amount" style="opacity: 0.5;">₱0.00</div>
      </div>

      <div class="breakdown-row health" v-if="calculation.under_5_children_count > 0 || calculation.health_subsidy > 0">
        <div class="row-left">
          <span class="row-dot health-dot"></span>
          <div>
            <div class="row-label">Health Subsidy</div>
            <div class="row-detail">
              {{ calculation.under_5_children_count }} child(ren) under 5
              × ₱{{ formatAmount(calculation.per_child_health) }}
            </div>
          </div>
        </div>
        <div class="row-amount health-amount">+ ₱{{ formatAmount(calculation.health_subsidy) }}</div>
      </div>
      <div class="breakdown-row health" v-else>
        <div class="row-left">
          <span class="row-dot health-dot" style="opacity: 0.3;"></span>
          <div>
            <div class="row-label" style="opacity: 0.5;">Health Subsidy</div>
            <div class="row-detail" style="opacity: 0.5;">No children under 5</div>
          </div>
        </div>
        <div class="row-amount health-amount" style="opacity: 0.5;">₱0.00</div>
      </div>

      <!-- Divider -->
      <div class="breakdown-divider"></div>

      <!-- Total -->
      <div class="breakdown-row total-row">
        <div class="row-left">
          <span class="row-dot total-dot"></span>
          <div class="row-label total-label">Total Payout</div>
        </div>
        <div class="row-amount total-amount">= ₱{{ formatAmount(calculation.total_amount) }}</div>
      </div>
    </div>

    <!-- Visual bar chart -->
    <div class="bar-chart" v-if="calculation.total_amount > 0">
      <div class="bar-segment base-bar"
           :style="{ width: pct(calculation.base_amount) + '%' }"
           :title="`Base: ₱${formatAmount(calculation.base_amount)}`">
      </div>
      <div class="bar-segment edu-bar"
           :style="{ width: pct(calculation.education_subsidy) + '%' }"
           v-if="calculation.education_subsidy > 0"
           :title="`Education: ₱${formatAmount(calculation.education_subsidy)}`">
      </div>
      <div class="bar-segment health-bar"
           :style="{ width: pct(calculation.health_subsidy) + '%' }"
           v-if="calculation.health_subsidy > 0"
           :title="`Health: ₱${formatAmount(calculation.health_subsidy)}`">
      </div>
    </div>
    <div class="bar-legend">
      <span class="legend-item"><span class="legend-dot base-dot"></span> Base</span>
      <span class="legend-item"><span class="legend-dot edu-dot"></span> Education</span>
      <span class="legend-item"><span class="legend-dot health-dot"></span> Health</span>
    </div>

    <!-- Household info -->
    <div class="household-info">
      <div class="hh-item">
        <span class="hh-label">Household Size</span>
        <span class="hh-val">{{ calculation.household_size }}</span>
      </div>
      <div class="hh-item">
        <span class="hh-label">School-Age Children</span>
        <span class="hh-val">{{ calculation.school_age_children_count }}</span>
      </div>
      <div class="hh-item">
        <span class="hh-label">Children Under 5</span>
        <span class="hh-val">{{ calculation.under_5_children_count }}</span>
      </div>
    </div>

    <!-- Override (Admin only) -->
    <div v-if="isAdmin" class="override-section">
      <button type="button" class="btn-override" @click="showOverride = !showOverride">
        {{ showOverride ? '✕ Cancel Override' : '⚠️ Override Amount (Admin)' }}
      </button>
      <div v-if="showOverride" class="override-form">
        <label class="override-label">Override Amount (₱)</label>
        <input
          type="number"
          v-model.number="overrideAmount"
          class="override-input"
          :placeholder="calculation.total_amount"
          min="0"
          step="0.01"
        />
        <label class="override-label">Reason for Override *</label>
        <textarea
          v-model="overrideReason"
          class="override-textarea"
          placeholder="State reason for amount override..."
          rows="2"
        ></textarea>
        <button
          type="button"
          class="btn-apply-override"
          :disabled="!overrideReason"
          @click="applyOverride"
        >
          Apply Override
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  calculation: {
    type: Object,
    required: true,
    // { base_amount, education_subsidy, health_subsidy, total_amount,
    //   school_age_children_count, under_5_children_count,
    //   per_child_education, per_child_health, household_size }
  },
  isAdmin: { type: Boolean, default: false },
})

const emit = defineEmits(['override'])

const showOverride   = ref(false)
const overrideAmount = ref(null)
const overrideReason = ref('')

function formatAmount (val) {
  return Number(val || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function pct (part) {
  const total = props.calculation.total_amount
  if (!total) return 0
  return Math.round((part / total) * 100)
}

function applyOverride () {
  if (!overrideReason.value) return
  emit('override', {
    amount:  overrideAmount.value ?? props.calculation.total_amount,
    reason:  overrideReason.value,
    original: props.calculation.total_amount,
  })
  showOverride.value = false
}
</script>

<style scoped>
.payout-calculator {
  background: linear-gradient(145deg, #f8faff, #fff);
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  overflow: hidden;
}

.calc-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  background: linear-gradient(135deg, #1e40af, #1565c0);
  color: white;
}

.calc-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 0.9rem;
}

.calc-icon { font-size: 1.1rem; }

.calc-total {
  font-size: 1.4rem;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.breakdown-list {
  padding: 14px 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.breakdown-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.row-left {
  display: flex;
  align-items: center;
  gap: 10px;
}

.row-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex-shrink: 0;
}

.base-dot   { background: #3b82f6; }
.edu-dot    { background: #8b5cf6; }
.health-dot { background: #10b981; }
.total-dot  { background: linear-gradient(135deg, #3b82f6, #10b981); }

.row-label {
  font-weight: 600;
  font-size: 0.82rem;
  color: #1e293b;
}

.row-detail {
  font-size: 0.72rem;
  color: #94a3b8;
  margin-top: 1px;
}

.row-amount {
  font-weight: 700;
  font-size: 0.85rem;
}

.base-amount   { color: #3b82f6; }
.edu-amount    { color: #8b5cf6; }
.health-amount { color: #10b981; }

.breakdown-divider {
  border-top: 1px dashed #e2e8f0;
  margin: 4px 0;
}

.total-row { padding-top: 2px; }
.total-label { font-size: 0.88rem; color: #0f172a; }
.total-amount {
  font-size: 1rem;
  color: #1e40af;
  font-weight: 800;
}

/* Bar chart */
.bar-chart {
  margin: 0 18px;
  height: 10px;
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  background: #f1f5f9;
}

.bar-segment {
  height: 100%;
  transition: width 0.4s ease;
}

.base-bar   { background: #3b82f6; }
.edu-bar    { background: #8b5cf6; }
.health-bar { background: #10b981; }

.bar-legend {
  display: flex;
  gap: 14px;
  padding: 8px 18px 0;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 0.7rem;
  color: #64748b;
}

.legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

/* Household info */
.household-info {
  display: flex;
  gap: 6px;
  padding: 10px 18px 14px;
  flex-wrap: wrap;
}

.hh-item {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 6px 12px;
  text-align: center;
  flex: 1;
  min-width: 80px;
}

.hh-label {
  display: block;
  font-size: 0.67rem;
  color: #94a3b8;
  margin-bottom: 2px;
}

.hh-val {
  display: block;
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
}

/* Override */
.override-section {
  border-top: 1px solid #fef3c7;
  padding: 12px 18px;
  background: #fffbeb;
}

.btn-override {
  font-size: 0.75rem;
  color: #b45309;
  border: 1px solid #fcd34d;
  background: #fef9c3;
  padding: 5px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.15s ease;
}

.btn-override:hover { background: #fef08a; }

.override-form {
  margin-top: 10px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.override-label {
  font-size: 0.75rem;
  font-weight: 600;
  color: #78350f;
}

.override-input, .override-textarea {
  width: 100%;
  padding: 7px 10px;
  border: 1px solid #fcd34d;
  border-radius: 6px;
  font-size: 0.82rem;
  background: white;
  color: #1e293b;
}

.btn-apply-override {
  padding: 7px 16px;
  background: linear-gradient(135deg, #d97706, #b45309);
  color: white;
  border: none;
  border-radius: 6px;
  font-weight: 700;
  font-size: 0.78rem;
  cursor: pointer;
  align-self: flex-end;
  transition: all 0.15s ease;
}

.btn-apply-override:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-apply-override:hover:not(:disabled) { opacity: 0.9; }
</style>
