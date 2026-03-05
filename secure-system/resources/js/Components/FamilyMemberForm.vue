<script setup>
/**
 * FamilyMemberForm.vue
 *
 * Reusable form component for adding/editing a single family member.
 * Used both in the batch builder (FamilyMembersList) and as a standalone modal.
 *
 * Props:
 *   modelValue   – the member object being edited (v-model)
 *   errors       – field-level errors from parent
 *   isEdit       – if true, shows "Update" button
 *
 * Emits:
 *   update:modelValue – field changes
 *   submit           – when the form is submitted
 *   cancel           – when the form is dismissed
 */
import { computed, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            full_name: '',
            birthdate: '',
            gender: '',
            relationship_to_head: '',
            birth_certificate_no: '',
            school_enrollment_status: 'Not Applicable',
            health_center_registered: false,
        }),
    },
    errors: { type: Object, default: () => ({}) },
    isEdit: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'submit', 'cancel']);

// ── Reactive form proxy ───────────────────────────────────────────────────
const field = (key) => ({
    get: () => props.modelValue[key],
    set: (val) => emit('update:modelValue', { ...props.modelValue, [key]: val }),
});

const fullName              = computed(field('full_name'));
const birthdate             = computed(field('birthdate'));
const gender                = computed(field('gender'));
const relationship          = computed(field('relationship_to_head'));
const birthCertNo           = computed(field('birth_certificate_no'));
const enrollmentStatus      = computed(field('school_enrollment_status'));
const healthCenterRegistered= computed(field('health_center_registered'));

// ── Age calculation ───────────────────────────────────────────────────────
const age = computed(() => {
    if (!birthdate.value) return null;
    const bd = new Date(birthdate.value);
    const today = new Date();
    let years = today.getFullYear() - bd.getFullYear();
    const m = today.getMonth() - bd.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < bd.getDate())) years--;
    return years;
});

const ageLabel = computed(() => {
    if (age.value === null) return '';
    if (age.value === 0) return '< 1 year old';
    return `${age.value} year${age.value !== 1 ? 's' : ''} old`;
});

const isSchoolAge = computed(() => age.value !== null && age.value >= 5 && age.value <= 21);
const needsHealthMonitoring = computed(() => age.value !== null && age.value <= 5);

// ── Auto-set school enrollment when age is outside school range ───────────
watch(birthdate, (val) => {
    if (!val) return;
    if (age.value < 5 || age.value > 21) {
        emit('update:modelValue', { ...props.modelValue, birthdate: val, school_enrollment_status: 'Not Applicable' });
    } else {
        emit('update:modelValue', { ...props.modelValue, birthdate: val });
    }
});

const enrollmentOptions = computed(() => {
    if (!isSchoolAge.value) return [{ value: 'Not Applicable', label: 'Not Applicable (auto)' }];
    return [
        { value: 'Enrolled',     label: 'Enrolled' },
        { value: 'Not Enrolled', label: 'Not Enrolled' },
    ];
});

const todayStr = new Date().toISOString().split('T')[0];
</script>

<template>
    <div class="fm-form">
        <div class="form-grid">
            <!-- Full Name -->
            <div class="field-group full-width">
                <label class="field-label" :for="`fn_${$.uid}`">
                    Full Name <span class="req">*</span>
                </label>
                <input
                    :id="`fn_${$.uid}`"
                    :value="fullName"
                    @input="fullName = $event.target.value"
                    type="text"
                    placeholder="Last, First M.I."
                    class="field-input"
                    :class="{ 'field-error': errors.full_name }"
                />
                <span v-if="errors.full_name" class="err-msg">{{ errors.full_name }}</span>
            </div>

            <!-- Birthdate + age badge -->
            <div class="field-group">
                <label class="field-label" :for="`bd_${$.uid}`">
                    Date of Birth <span class="req">*</span>
                </label>
                <div class="input-with-badge">
                    <input
                        :id="`bd_${$.uid}`"
                        :value="birthdate"
                        @input="birthdate = $event.target.value"
                        type="date"
                        :max="todayStr"
                        class="field-input"
                        :class="{ 'field-error': errors.birthdate }"
                    />
                    <span v-if="age !== null" class="age-badge">{{ ageLabel }}</span>
                </div>
                <span v-if="errors.birthdate" class="err-msg">{{ errors.birthdate }}</span>
                <!-- Health monitoring indicator -->
                <span v-if="needsHealthMonitoring" class="health-flag">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 100 16A8 8 0 0010 2zm0 4a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 110-2 1 1 0 010 2z"/></svg>
                    Requires health compliance monitoring (age ≤ 5)
                </span>
            </div>

            <!-- Gender -->
            <div class="field-group">
                <label class="field-label" :for="`gd_${$.uid}`">
                    Gender <span class="req">*</span>
                </label>
                <select
                    :id="`gd_${$.uid}`"
                    :value="gender"
                    @change="gender = $event.target.value"
                    class="field-input"
                    :class="{ 'field-error': errors.gender }"
                >
                    <option value="">Select gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                <span v-if="errors.gender" class="err-msg">{{ errors.gender }}</span>
            </div>

            <!-- Relationship -->
            <div class="field-group">
                <label class="field-label" :for="`rel_${$.uid}`">
                    Relationship to Head <span class="req">*</span>
                </label>
                <select
                    :id="`rel_${$.uid}`"
                    :value="relationship"
                    @change="relationship = $event.target.value"
                    class="field-input"
                    :class="{ 'field-error': errors.relationship_to_head }"
                >
                    <option value="">Select relationship</option>
                    <option value="Spouse">Spouse</option>
                    <option value="Child">Child</option>
                    <option value="Parent">Parent</option>
                    <option value="Sibling">Sibling</option>
                    <option value="Other">Other</option>
                </select>
                <span v-if="errors.relationship_to_head" class="err-msg">{{ errors.relationship_to_head }}</span>
            </div>

            <!-- Birth Certificate No -->
            <div class="field-group">
                <label class="field-label" :for="`bc_${$.uid}`">
                    PSA Birth Cert. No. <span class="opt">(optional)</span>
                </label>
                <input
                    :id="`bc_${$.uid}`"
                    :value="birthCertNo"
                    @input="birthCertNo = $event.target.value"
                    type="text"
                    placeholder="e.g. 2024-12345678"
                    class="field-input"
                    :class="{ 'field-error': errors.birth_certificate_no }"
                    maxlength="50"
                />
                <span v-if="errors.birth_certificate_no" class="err-msg">{{ errors.birth_certificate_no }}</span>
            </div>

            <!-- School Enrollment Status -->
            <div class="field-group">
                <label class="field-label" :for="`es_${$.uid}`">
                    School Enrollment
                    <span class="req">*</span>
                    <span v-if="!isSchoolAge && age !== null" class="auto-tag">auto</span>
                </label>
                <select
                    :id="`es_${$.uid}`"
                    :value="enrollmentStatus"
                    @change="enrollmentStatus = $event.target.value"
                    class="field-input"
                    :class="{ 'field-error': errors.school_enrollment_status }"
                    :disabled="!isSchoolAge && age !== null"
                >
                    <option
                        v-for="opt in enrollmentOptions"
                        :key="opt.value"
                        :value="opt.value"
                    >{{ opt.label }}</option>
                </select>
                <span v-if="!isSchoolAge && age !== null" class="hint-msg">
                    {{ age < 5 ? 'Below school age (< 5)' : 'Above school age (> 21)' }} — automatically set.
                </span>
                <span v-if="errors.school_enrollment_status" class="err-msg">{{ errors.school_enrollment_status }}</span>
            </div>

            <!-- Health Center Registered -->
            <div class="field-group full-width">
                <label class="toggle-label" :for="`hc_${$.uid}`">
                    <div class="toggle-wrapper">
                        <input
                            :id="`hc_${$.uid}`"
                            type="checkbox"
                            class="toggle-input"
                            :checked="healthCenterRegistered"
                            @change="healthCenterRegistered = $event.target.checked"
                        />
                        <span class="toggle-track">
                            <span class="toggle-thumb"></span>
                        </span>
                    </div>
                    <div>
                        <span class="toggle-text">Registered at Government Health Center</span>
                        <span class="toggle-hint">Check if this member is registered at a PhilHealth / BHS facility</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Form actions -->
        <div class="form-actions">
            <button type="button" class="btn-ghost" @click="emit('cancel')">Cancel</button>
            <button
                type="button"
                class="btn-primary"
                :disabled="loading"
                @click="emit('submit')"
            >
                <svg v-if="loading" class="spinner" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31" stroke-linecap="round"/>
                </svg>
                {{ isEdit ? 'Update Member' : 'Add Member' }}
            </button>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

.fm-form { font-family: 'Inter', sans-serif; }

/* Grid */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
.full-width { grid-column: 1 / -1; }

.field-group { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label { font-size: 0.8rem; font-weight: 600; color: #94a3b8; display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; }
.req { color: #f87171; }
.opt { color: #475569; font-weight: 400; }
.auto-tag {
    font-size: 0.65rem; padding: 0.1rem 0.375rem; border-radius: 9999px;
    background: rgba(99,102,241,0.2); color: #a5b4fc; font-weight: 700;
}

.field-input {
    padding: 0.6rem 0.875rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem; color: #f1f5f9; font-size: 0.875rem;
    font-family: 'Inter', sans-serif; outline: none; transition: border-color 0.2s;
    appearance: auto;
}
.field-input:focus { border-color: rgba(99,102,241,0.5); background: rgba(99,102,241,0.05); }
.field-input:disabled { opacity: 0.5; cursor: not-allowed; }
.field-error { border-color: rgba(239,68,68,0.5) !important; }
.err-msg  { font-size: 0.75rem; color: #f87171; }
.hint-msg { font-size: 0.72rem; color: #64748b; font-style: italic; }

/* Age badge inline */
.input-with-badge { position: relative; }
.age-badge {
    position: absolute; right: 0.625rem; top: 50%; transform: translateY(-50%);
    font-size: 0.7rem; font-weight: 700; color: #a5b4fc;
    background: rgba(99,102,241,0.15); padding: 0.15rem 0.5rem;
    border-radius: 9999px; pointer-events: none;
}

/* Health flag */
.health-flag {
    display: flex; align-items: center; gap: 0.375rem;
    font-size: 0.72rem; color: #fcd34d;
}
.health-flag svg { width: 13px; height: 13px; flex-shrink: 0; }

/* Toggle */
.toggle-label {
    display: flex; align-items: flex-start; gap: 0.875rem; cursor: pointer;
    padding: 0.875rem 1rem;
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 0.75rem; transition: border-color 0.2s;
}
.toggle-label:hover { border-color: rgba(99,102,241,0.3); }
.toggle-wrapper { flex-shrink: 0; padding-top: 2px; }
.toggle-input { display: none; }
.toggle-track {
    width: 40px; height: 22px; border-radius: 9999px;
    background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.15);
    display: block; position: relative; cursor: pointer; transition: all 0.2s;
}
.toggle-input:checked + .toggle-track {
    background: rgba(99,102,241,0.5); border-color: rgba(99,102,241,0.6);
}
.toggle-thumb {
    position: absolute; top: 2px; left: 2px;
    width: 16px; height: 16px; border-radius: 50%;
    background: white; transition: transform 0.2s;
}
.toggle-input:checked + .toggle-track .toggle-thumb { transform: translateX(18px); }
.toggle-text { font-size: 0.875rem; font-weight: 600; color: #f1f5f9; display: block; }
.toggle-hint { font-size: 0.75rem; color: #64748b; display: block; margin-top: 2px; }

/* Actions */
.form-actions {
    display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem;
    margin-top: 1.5rem; padding-top: 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.btn-ghost {
    padding: 0.575rem 1.125rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.75rem; color: #94a3b8;
    font-size: 0.875rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.btn-ghost:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }
.btn-primary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; padding: 0.575rem 1.375rem; border-radius: 0.75rem;
    font-size: 0.875rem; font-weight: 600; border: none;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3);
    font-family: 'Inter', sans-serif;
}
.btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.spinner { width: 15px; height: 15px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
