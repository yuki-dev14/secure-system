<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    familyMembers: { type: Array, required: true },
    beneficiaryId: { type: Number, required: true },
});

const emit = defineEmits(['submitted']);

function currentPeriod() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

function todayDate() {
    return new Date().toISOString().split('T')[0];
}

/* ── Form state ─────────────────────────────────────────── */
const form = ref({
    family_member_id:    '',
    compliance_period:   currentPeriod(),
    health_checkup_date: todayDate(),
    vaccination_status:  'Complete',
    health_center_name:  '',
});

const loading = ref(false);
const success = ref('');
const errors  = ref({});

/* ── Computed ────────────────────────────────────────────── */
const healthMembers = computed(() =>
    props.familyMembers.filter(m => m.needs_health_monitoring || m.age <= 5)
);

// Determine if checkup date is within compliance period
const checkupInPeriod = computed(() => {
    if (!form.value.health_checkup_date || !form.value.compliance_period) return null;
    const [yr, mo] = form.value.compliance_period.split('-').map(Number);
    const d = new Date(form.value.health_checkup_date);
    return d.getFullYear() === yr && (d.getMonth() + 1) === mo;
});

const projectedCompliant = computed(() => {
    return checkupInPeriod.value && form.value.vaccination_status === 'Complete';
});

/* ── Submit ──────────────────────────────────────────────── */
async function submit() {
    loading.value = true;
    errors.value  = {};
    success.value = '';

    try {
        const res = await axios.post('/compliance/health', form.value);
        success.value = res.data.message ?? 'Health compliance recorded!';
        emit('submitted', res.data);
        form.value.health_center_name = '';
    } catch (e) {
        if (e.response?.status === 422) {
            errors.value = e.response.data.errors ?? {};
        } else {
            errors.value = { _general: [e.response?.data?.message ?? 'Server error.'] };
        }
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="hcf-shell">
        <div class="hcf-header">
            <div class="hcf-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                </svg>
            </div>
            <div>
                <h3 class="hcf-title">Health Compliance</h3>
                <p class="hcf-subtitle">Record health check-up and vaccination status (children 0–5)</p>
            </div>
        </div>

        <Transition name="fade">
            <div v-if="success" class="alert-success">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                {{ success }}
            </div>
        </Transition>
        <div v-if="errors._general" class="alert-error">{{ errors._general[0] }}</div>

        <form @submit.prevent="submit" class="hcf-form">
            <!-- Family Member -->
            <div class="field-group">
                <label class="field-label" for="hlth-member">Family Member <span class="req">*</span></label>
                <select id="hlth-member" v-model="form.family_member_id" class="field-select" required>
                    <option value="">— Select member (age 0–5) —</option>
                    <option v-for="m in healthMembers" :key="m.id" :value="m.id">
                        {{ m.full_name }} (Age {{ m.age }})
                    </option>
                </select>
                <p v-if="healthMembers.length === 0" class="field-hint warn">No health-monitored members (age 0–5) in this household.</p>
                <p v-if="errors.family_member_id" class="field-error">{{ errors.family_member_id[0] }}</p>
            </div>

            <!-- Compliance Period -->
            <div class="field-group">
                <label class="field-label" for="hlth-period">Compliance Period <span class="req">*</span></label>
                <input id="hlth-period" type="month" v-model="form.compliance_period" class="field-input" required/>
                <p v-if="errors.compliance_period" class="field-error">{{ errors.compliance_period[0] }}</p>
            </div>

            <!-- Health Checkup Date -->
            <div class="field-group">
                <label class="field-label" for="hlth-date">Health Check-up Date <span class="req">*</span></label>
                <input id="hlth-date" type="date" v-model="form.health_checkup_date"
                       :max="todayDate()" class="field-input" required/>

                <!-- Period check hint -->
                <div v-if="form.health_checkup_date && form.compliance_period" class="period-check"
                     :class="checkupInPeriod ? 'period-ok' : 'period-warn'">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path v-if="checkupInPeriod" fill-rule="evenodd"
                            d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                        <path v-else fill-rule="evenodd"
                            d="M4 10a.75.75 0 01.75-.75h10.5a.75.75 0 010 1.5H4.75A.75.75 0 014 10z" clip-rule="evenodd"/>
                    </svg>
                    <span v-if="checkupInPeriod">Check-up date falls within the compliance period ✓</span>
                    <span v-else>Check-up date is outside the compliance period — will be non-compliant</span>
                </div>

                <p v-if="errors.health_checkup_date" class="field-error">{{ errors.health_checkup_date[0] }}</p>
            </div>

            <!-- Vaccination Status -->
            <div class="field-group">
                <label class="field-label" for="hlth-vacc">Vaccination Status <span class="req">*</span></label>
                <select id="hlth-vacc" v-model="form.vaccination_status" class="field-select" required>
                    <option value="Complete">Complete</option>
                    <option value="Incomplete">Incomplete</option>
                    <option value="Not Applicable">Not Applicable</option>
                </select>
                <p v-if="errors.vaccination_status" class="field-error">{{ errors.vaccination_status[0] }}</p>
            </div>

            <!-- Health Center -->
            <div class="field-group">
                <label class="field-label" for="hlth-center">Health Center Name <span class="opt">(optional)</span></label>
                <input id="hlth-center" type="text" v-model="form.health_center_name"
                       placeholder="e.g. Barangay Health Center"
                       class="field-input" maxlength="255"/>
            </div>

            <!-- Live Compliance Indicator -->
            <div class="comp-indicator" :class="projectedCompliant ? 'ind-pass' : 'ind-fail'">
                <div class="ci-dot" :class="projectedCompliant ? 'dot-pass' : 'dot-fail'"></div>
                <div class="ci-body">
                    <span class="ci-status" :class="projectedCompliant ? 'clr-green' : 'clr-red'">
                        {{ projectedCompliant ? 'Will be COMPLIANT' : 'Will be NON-COMPLIANT' }}
                    </span>
                    <span class="ci-reason">
                        <span v-if="!checkupInPeriod">Check-up date must be within the compliance period.</span>
                        <span v-else-if="form.vaccination_status !== 'Complete'">Vaccination status must be "Complete".</span>
                        <span v-else>Both check-up timing and vaccination status verified.</span>
                    </span>
                </div>
            </div>

            <button type="submit" class="btn-submit" :disabled="loading || healthMembers.length === 0" id="hlth-submit-btn">
                <span v-if="loading" class="spinner"></span>
                <svg v-else viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                Record Health Compliance
            </button>
        </form>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }
.hcf-shell {
    font-family: 'Inter', sans-serif;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1.25rem;
    padding: 1.75rem;
    display: flex; flex-direction: column; gap: 1.25rem;
}
.hcf-header { display: flex; align-items: flex-start; gap: 0.875rem; }
.hcf-icon {
    width: 44px; height: 44px; border-radius: 0.75rem;
    background: rgba(236,72,153,0.15); border: 1px solid rgba(236,72,153,0.25);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    color: #f9a8d4;
}
.hcf-icon svg { width: 22px; height: 22px; }
.hcf-title   { font-size: 1.0625rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.hcf-subtitle { font-size: 0.8rem; color: #64748b; margin: 0; }

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
.hcf-form { display: flex; flex-direction: column; gap: 1.25rem; }
.field-group { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label { font-size: 0.8125rem; font-weight: 600; color: #94a3b8; }
.req { color: #f87171; }
.opt { color: #475569; font-weight: 400; }
.field-hint { font-size: 0.75rem; color: #64748b; margin: 0; }
.field-hint.warn { color: #fcd34d; }
.field-error { font-size: 0.75rem; color: #f87171; margin: 0; }
.field-input, .field-select {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem; padding: 0.65rem 0.875rem;
    color: #f1f5f9; font-size: 0.875rem; font-family: 'Inter', sans-serif;
    width: 100%; outline: none; transition: border-color 0.2s; appearance: none;
}
.field-input:focus, .field-select:focus { border-color: rgba(99,102,241,0.5); }
.field-select option { background: #1e293b; color: #f1f5f9; }

.period-check {
    display: flex; align-items: center; gap: 0.375rem;
    padding: 0.5rem 0.75rem; border-radius: 0.5rem;
    font-size: 0.78rem; font-weight: 500;
}
.period-ok   { background: rgba(16,185,129,0.1); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.2); }
.period-warn { background: rgba(245,158,11,0.1); color: #fcd34d; border: 1px solid rgba(245,158,11,0.2); }
.period-check svg { width: 14px; height: 14px; flex-shrink: 0; }

.comp-indicator {
    display: flex; align-items: flex-start; gap: 0.75rem;
    border-radius: 0.75rem; padding: 0.875rem 1rem;
}
.ind-pass { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); }
.ind-fail { background: rgba(239,68,68,0.08);   border: 1px solid rgba(239,68,68,0.2); }
.ci-dot {
    width: 10px; height: 10px; border-radius: 50%; margin-top: 0.3rem; flex-shrink: 0;
}
.dot-pass { background: #10b981; box-shadow: 0 0 8px rgba(16,185,129,0.5); }
.dot-fail { background: #ef4444; box-shadow: 0 0 8px rgba(239,68,68,0.4); }
.ci-body { display: flex; flex-direction: column; gap: 0.25rem; }
.ci-status { font-size: 0.875rem; font-weight: 700; }
.ci-reason { font-size: 0.78rem; color: #64748b; }
.clr-green { color: #6ee7b7; }
.clr-red   { color: #f87171; }

.btn-submit {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    background: linear-gradient(135deg, #ec4899, #a855f7);
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
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
