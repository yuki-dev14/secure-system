<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    familyMembers: { type: Array, required: true }, // pre-filtered school-age passed from parent
    beneficiaryId: { type: Number, required: true },
});

const emit = defineEmits(['submitted']);

/* ── Form state ─────────────────────────────────────────── */
const form = ref({
    family_member_id:      '',
    compliance_period:     currentPeriod(),
    school_name:           '',
    enrollment_status:     'Enrolled',
    attendance_percentage: 90,
});

const loading  = ref(false);
const success  = ref('');
const errors   = ref({});

function currentPeriod() {
    const now = new Date();
    const mm  = String(now.getMonth() + 1).padStart(2, '0');
    return `${now.getFullYear()}-${mm}`;
}

/* ── Computed ────────────────────────────────────────────── */
const schoolAgeMembers = computed(() => props.familyMembers.filter(m => m.is_school_age));
const attendancePct    = computed(() => Number(form.value.attendance_percentage));
const isThresholdMet   = computed(() => attendancePct.value >= 85);
const sliderColor      = computed(() => {
    if (attendancePct.value >= 85) return '#10b981';
    if (attendancePct.value >= 65) return '#f59e0b';
    return '#ef4444';
});

const selectedMember = computed(() =>
    schoolAgeMembers.value.find(m => m.id === Number(form.value.family_member_id))
);

/* ── Submit ──────────────────────────────────────────────── */
async function submit() {
    loading.value = true;
    errors.value  = {};
    success.value = '';

    try {
        const res = await axios.post('/compliance/education', form.value);
        success.value = res.data.message ?? 'Education compliance recorded!';
        emit('submitted', res.data);
        // reset
        form.value.school_name           = '';
        form.value.attendance_percentage = 90;
        form.value.enrollment_status     = 'Enrolled';
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            errors.value = { _general: [e.response?.data?.message ?? 'Server error. Please try again.'] };
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="ecf-shell">
        <div class="ecf-header">
            <div class="ecf-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
                </svg>
            </div>
            <div>
                <h3 class="ecf-title">Education Compliance</h3>
                <p class="ecf-subtitle">Record school attendance for school-age family members</p>
            </div>
        </div>

        <!-- Success banner -->
        <Transition name="fade">
            <div v-if="success" class="alert-success">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                {{ success }}
            </div>
        </Transition>

        <!-- General error -->
        <div v-if="errors._general" class="alert-error">{{ errors._general[0] }}</div>

        <form @submit.prevent="submit" class="ecf-form">
            <!-- Family Member -->
            <div class="field-group">
                <label class="field-label" for="edu-member">Family Member <span class="req">*</span></label>
                <select id="edu-member" v-model="form.family_member_id" class="field-select" required>
                    <option value="">— Select school-age member —</option>
                    <option v-for="m in schoolAgeMembers" :key="m.id" :value="m.id">
                        {{ m.full_name }} (Age {{ m.age }})
                    </option>
                </select>
                <p v-if="schoolAgeMembers.length === 0" class="field-hint warn">No school-age members (5–21) in this household.</p>
                <p v-if="errors.family_member_id" class="field-error">{{ errors.family_member_id[0] }}</p>
            </div>

            <!-- Compliance Period -->
            <div class="field-group">
                <label class="field-label" for="edu-period">Compliance Period <span class="req">*</span></label>
                <input id="edu-period" type="month" v-model="form.compliance_period" class="field-input" required/>
                <p v-if="errors.compliance_period" class="field-error">{{ errors.compliance_period[0] }}</p>
            </div>

            <!-- School Name -->
            <div class="field-group">
                <label class="field-label" for="edu-school">School Name <span class="req">*</span></label>
                <input id="edu-school" type="text" v-model="form.school_name"
                       placeholder="Enter school name"
                       class="field-input" required maxlength="255"/>
                <p v-if="errors.school_name" class="field-error">{{ errors.school_name[0] }}</p>
            </div>

            <!-- Enrollment Status -->
            <div class="field-group">
                <label class="field-label">Enrollment Status <span class="req">*</span></label>
                <div class="radio-group">
                    <label v-for="opt in ['Enrolled', 'Not Enrolled', 'Not Applicable']" :key="opt" class="radio-item">
                        <input type="radio" :value="opt" v-model="form.enrollment_status"/>
                        <span class="radio-custom"></span>
                        <span class="radio-label">{{ opt }}</span>
                    </label>
                </div>
                <p v-if="errors.enrollment_status" class="field-error">{{ errors.enrollment_status[0] }}</p>
            </div>

            <!-- Attendance Percentage -->
            <div class="field-group">
                <label class="field-label" for="edu-attendance">Attendance Percentage <span class="req">*</span></label>

                <!-- Live indicator -->
                <div class="attendance-indicator" :class="isThresholdMet ? 'ind-pass' : 'ind-fail'">
                    <div class="ind-pct">{{ attendancePct }}%</div>
                    <div class="ind-text">
                        <span v-if="isThresholdMet">✓ Meets 85% threshold</span>
                        <span v-else>✗ Below 85% threshold ({{ (85 - attendancePct).toFixed(1) }}% gap)</span>
                    </div>
                </div>

                <div class="slider-wrap">
                    <input id="edu-attendance" type="range"
                           v-model="form.attendance_percentage"
                           min="0" max="100" step="0.5"
                           class="attendance-slider"
                           :style="`--thumb-color:${sliderColor};--fill:${attendancePct}%`"/>
                    <div class="slider-labels">
                        <span>0%</span><span class="threshold-mark">85%</span><span>100%</span>
                    </div>
                </div>

                <input type="number" v-model="form.attendance_percentage"
                       min="0" max="100" step="0.1"
                       class="field-input mt-2" style="max-width:120px"/>
                <p v-if="errors.attendance_percentage" class="field-error">{{ errors.attendance_percentage[0] }}</p>
            </div>

            <!-- Submit -->
            <button type="submit" class="btn-submit" :disabled="loading || schoolAgeMembers.length === 0" id="edu-submit-btn">
                <span v-if="loading" class="spinner"></span>
                <svg v-else viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                Record Education Compliance
            </button>
        </form>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.ecf-shell {
    font-family: 'Inter', sans-serif;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1.25rem;
    padding: 1.75rem;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.ecf-header { display: flex; align-items: flex-start; gap: 0.875rem; }
.ecf-icon {
    width: 44px; height: 44px; border-radius: 0.75rem;
    background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.25);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    color: #a5b4fc;
}
.ecf-icon svg { width: 22px; height: 22px; }
.ecf-title  { font-size: 1.0625rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.ecf-subtitle { font-size: 0.8rem; color: #64748b; margin: 0; }

/* Alerts */
.alert-success {
    display: flex; align-items: center; gap: 0.5rem;
    background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25);
    border-radius: 0.75rem; padding: 0.75rem 1rem;
    font-size: 0.875rem; color: #6ee7b7;
}
.alert-success svg { width: 16px; height: 16px; flex-shrink: 0; }
.alert-error {
    background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);
    border-radius: 0.75rem; padding: 0.75rem 1rem;
    font-size: 0.875rem; color: #fca5a5;
}

/* Form */
.ecf-form { display: flex; flex-direction: column; gap: 1.25rem; }

.field-group { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label { font-size: 0.8125rem; font-weight: 600; color: #94a3b8; }
.req { color: #f87171; }
.field-hint { font-size: 0.75rem; color: #64748b; margin: 0; }
.field-hint.warn { color: #fcd34d; }
.field-error { font-size: 0.75rem; color: #f87171; margin: 0; }

.field-input, .field-select {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem; padding: 0.65rem 0.875rem;
    color: #f1f5f9; font-size: 0.875rem; font-family: 'Inter', sans-serif;
    width: 100%; outline: none; transition: border-color 0.2s;
    appearance: none;
}
.field-input:focus, .field-select:focus { border-color: rgba(99,102,241,0.5); }
.field-select option { background: #1e293b; color: #f1f5f9; }
.mt-2 { margin-top: 0.5rem; }

/* Radio */
.radio-group { display: flex; flex-wrap: wrap; gap: 0.625rem; }
.radio-item  { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
.radio-item input[type="radio"] { display: none; }
.radio-custom {
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.04); transition: all 0.15s; flex-shrink: 0;
}
.radio-item input:checked + .radio-custom {
    border-color: #6366f1;
    background: radial-gradient(circle, #6366f1 40%, transparent 41%);
}
.radio-label { font-size: 0.8125rem; color: #e2e8f0; }

/* Attendance indicator */
.attendance-indicator {
    display: flex; align-items: center; gap: 0.875rem;
    border-radius: 0.75rem; padding: 0.75rem 1rem;
    transition: all 0.3s;
}
.ind-pass { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); }
.ind-fail { background: rgba(239,68,68,0.1);   border: 1px solid rgba(239,68,68,0.2); }
.ind-pct {
    font-size: 1.75rem; font-weight: 800; line-height: 1;
    min-width: 3.5rem; text-align: center;
}
.ind-pass .ind-pct { color: #6ee7b7; }
.ind-fail .ind-pct { color: #f87171; }
.ind-text { font-size: 0.8125rem; font-weight: 600; }
.ind-pass .ind-text { color: #6ee7b7; }
.ind-fail .ind-text { color: #fca5a5; }

/* Slider */
.slider-wrap { display: flex; flex-direction: column; gap: 0.375rem; }
.attendance-slider {
    width: 100%; -webkit-appearance: none;
    height: 6px; border-radius: 999px;
    background: linear-gradient(to right, var(--thumb-color) 0%, var(--thumb-color) var(--fill), rgba(255,255,255,0.1) var(--fill), rgba(255,255,255,0.1) 100%);
    outline: none; cursor: pointer;
}
.attendance-slider::-webkit-slider-thumb {
    -webkit-appearance: none; width: 18px; height: 18px; border-radius: 50%;
    background: var(--thumb-color); box-shadow: 0 0 0 3px rgba(255,255,255,0.1); cursor: pointer;
    transition: box-shadow 0.2s;
}
.attendance-slider::-webkit-slider-thumb:hover { box-shadow: 0 0 0 5px rgba(255,255,255,0.15); }
.slider-labels {
    display: flex; justify-content: space-between;
    font-size: 0.7rem; color: #475569;
}
.threshold-mark { color: #fcd34d; font-weight: 600; }

/* Submit */
.btn-submit {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; border: none; border-radius: 0.75rem;
    padding: 0.75rem 1.5rem; font-size: 0.9375rem; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: opacity 0.2s, transform 0.15s;
}
.btn-submit:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-submit svg { width: 18px; height: 18px; }

.spinner {
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: white;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
