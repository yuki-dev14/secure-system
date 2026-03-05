<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DuplicateDetectionModal from '@/Components/DuplicateDetectionModal.vue';

// ── Multi-step form state ─────────────────────────────────────────────────
const STORAGE_KEY = 'beneficiary_draft_v1';
const STEPS = ['Personal Info', 'Address', 'Economic Info', 'Review & Submit'];

const currentStep = ref(1);
const loading      = ref(false);
const showDuplicateModal = ref(false);
const duplicateMatches   = ref([]);

// Flash from server (duplicate found) — passed via Inertia shared props
const page = { props: { errors: {}, flash: {} } };

const form = ref({
    family_head_name:      '',
    family_head_birthdate: '',
    gender:                '',
    civil_status:          '',
    contact_number:        '',
    email:                 '',
    barangay:              '',
    municipality:          '',
    province:              '',
    zip_code:              '',
    annual_income:         '',
    household_size:        '',
    override_duplicates:   false,
});

const errors = ref({});

// ── Draft persistence ─────────────────────────────────────────────────────
const saveDraft = () => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(form.value));
};

const clearDraft = () => localStorage.removeItem(STORAGE_KEY);

onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            Object.assign(form.value, parsed);
        } catch {}
    }
});

watch(form, saveDraft, { deep: true });

// ── Validation ────────────────────────────────────────────────────────────
const validateStep = (step) => {
    const e = {};
    if (step === 1) {
        if (!form.value.family_head_name.trim()) e.family_head_name = 'Full name is required.';
        if (!form.value.family_head_birthdate)   e.family_head_birthdate = 'Birthdate is required.';
        else if (new Date(form.value.family_head_birthdate) >= new Date()) e.family_head_birthdate = 'Birthdate must be before today.';
        if (!form.value.gender)      e.gender = 'Please select a gender.';
        if (!form.value.civil_status)e.civil_status = 'Please select civil status.';
        if (!form.value.contact_number)         e.contact_number = 'Contact number is required.';
        else if (!/^[0-9]{11}$/.test(form.value.contact_number)) e.contact_number = 'Must be 11 digits (e.g. 09xxxxxxxxx).';
        if (form.value.email && !/^\S+@\S+\.\S+$/.test(form.value.email)) e.email = 'Invalid email address.';
    }
    if (step === 2) {
        if (!form.value.barangay.trim())    e.barangay    = 'Barangay is required.';
        if (!form.value.municipality.trim())e.municipality= 'Municipality is required.';
        if (!form.value.province.trim())    e.province    = 'Province is required.';
        if (!form.value.zip_code.trim())    e.zip_code    = 'ZIP code is required.';
        else if (form.value.zip_code.length !== 4) e.zip_code = 'ZIP code must be exactly 4 digits.';
    }
    if (step === 3) {
        if (form.value.annual_income === '' || form.value.annual_income < 0) e.annual_income = 'Annual income must be 0 or greater.';
        if (!form.value.household_size || form.value.household_size < 1) e.household_size = 'Household size must be at least 1.';
    }
    errors.value = e;
    return Object.keys(e).length === 0;
};

const next = () => {
    if (validateStep(currentStep.value)) currentStep.value++;
};

const prev = () => {
    errors.value = {};
    currentStep.value--;
};

// ── Contact number formatting ─────────────────────────────────────────────
const formatContact = () => {
    form.value.contact_number = form.value.contact_number.replace(/\D/g, '').slice(0, 11);
};

// ── Submission ────────────────────────────────────────────────────────────
const submit = () => {
    if (!validateStep(currentStep.value)) return;

    loading.value = true;
    router.post(route('beneficiaries.store'), form.value, {
        onSuccess: () => {
            clearDraft();
            loading.value = false;
        },
        onError: (errs) => {
            loading.value = false;
            errors.value  = errs;
            // Check if duplicates were found (server sets flash)
        },
        onFinish: () => { loading.value = false; },
        onBefore: () => { loading.value = true; },
    });
};

// ── Duplicate modal handlers ──────────────────────────────────────────────
const handleDuplicateOverride = () => {
    form.value.override_duplicates = true;
    showDuplicateModal.value = false;
    submit();
};

const handleDuplicateDraft = () => {
    showDuplicateModal.value = false;
    // Draft is already auto-saved; user can come back later
    router.get(route('beneficiaries.index'));
};

// ── Philippine address suggestions (common provinces) ────────────────────
const phProvinces = [
    'Batangas','Bulacan','Cavite','Laguna','Pampanga','Quezon','Rizal','Tarlac','Zambales','Nueva Ecija',
    'Ilocos Norte','Ilocos Sur','La Union','Pangasinan','Abra','Apayao','Benguet','Ifugao','Kalinga','Mountain Province',
    'Batanes','Cagayan','Isabela','Nueva Vizcaya','Quirino','Aurora',
    'Albay','Camarines Norte','Camarines Sur','Catanduanes','Masbate','Sorsogon',
    'Aklan','Antique','Capiz','Guimaras','Iloilo','Negros Occidental',
    'Bohol','Cebu','Negros Oriental','Siquijor',
    'Biliran','Eastern Samar','Leyte','Northern Samar','Samar','Southern Leyte',
    'Zamboanga del Norte','Zamboanga del Sur','Zamboanga Sibugay',
    'Bukidnon','Camiguin','Lanao del Norte','Misamis Occidental','Misamis Oriental',
    'Davao de Oro','Davao del Norte','Davao del Sur','Davao Occidental','Davao Oriental',
    'Cotabato','Sarangani','South Cotabato','Sultan Kudarat',
    'Agusan del Norte','Agusan del Sur','Dinagat Islands','Surigao del Norte','Surigao del Sur',
    'Basilan','Lanao del Sur','Maguindanao del Norte','Maguindanao del Sur','Sulu','Tawi-Tawi',
    'Metro Manila',
].sort();

const stepTitle = computed(() => STEPS[currentStep.value - 1]);
const progress  = computed(() => ((currentStep.value - 1) / (STEPS.length - 1)) * 100);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="page-hd">
                <div>
                    <h1 class="page-title">Register Beneficiary</h1>
                    <p class="page-sub">Step {{ currentStep }} of {{ STEPS.length }} — {{ stepTitle }}</p>
                </div>
            </div>
        </template>

        <div class="reg-wrap">
            <!-- ── Step progress indicator ────────────────────────────── -->
            <div class="progress-card">
                <div class="step-labels">
                    <span
                        v-for="(label, idx) in STEPS"
                        :key="idx"
                        class="step-label"
                        :class="{
                            'step-done':    idx + 1 < currentStep,
                            'step-current': idx + 1 === currentStep,
                        }"
                    >
                        <span class="step-indicator">
                            <svg v-if="idx + 1 < currentStep" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                            <span v-else>{{ idx + 1 }}</span>
                        </span>
                        <span class="step-text">{{ label }}</span>
                    </span>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" :style="{ width: progress + '%' }"></div>
                </div>
            </div>

            <!-- ── Form card ──────────────────────────────────────────── -->
            <div class="form-card">
                <form @submit.prevent="currentStep < STEPS.length ? next() : submit()">

                    <!-- Step 1: Personal Information -->
                    <Transition name="slide" mode="out-in">
                        <div v-if="currentStep === 1" key="step1" class="step-body">
                            <h2 class="step-heading">Personal Information</h2>
                            <p class="step-hint">Enter the household head's personal details.</p>

                            <div class="form-grid">
                                <div class="field-group full-width">
                                    <label for="family_head_name" class="field-label">Full Name <span class="req">*</span></label>
                                    <input
                                        id="family_head_name"
                                        v-model="form.family_head_name"
                                        type="text"
                                        placeholder="Last Name, First Name M.I."
                                        class="field-input"
                                        :class="{ 'field-error': errors.family_head_name }"
                                        autocomplete="name"
                                    />
                                    <span v-if="errors.family_head_name" class="err-msg">{{ errors.family_head_name }}</span>
                                </div>

                                <div class="field-group">
                                    <label for="family_head_birthdate" class="field-label">Date of Birth <span class="req">*</span></label>
                                    <input
                                        id="family_head_birthdate"
                                        v-model="form.family_head_birthdate"
                                        type="date"
                                        class="field-input"
                                        :class="{ 'field-error': errors.family_head_birthdate }"
                                        :max="new Date().toISOString().split('T')[0]"
                                    />
                                    <span v-if="errors.family_head_birthdate" class="err-msg">{{ errors.family_head_birthdate }}</span>
                                </div>

                                <div class="field-group">
                                    <label for="gender" class="field-label">Gender <span class="req">*</span></label>
                                    <select id="gender" v-model="form.gender" class="field-input" :class="{ 'field-error': errors.gender }">
                                        <option value="">Select gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                    <span v-if="errors.gender" class="err-msg">{{ errors.gender }}</span>
                                </div>

                                <div class="field-group">
                                    <label for="civil_status" class="field-label">Civil Status <span class="req">*</span></label>
                                    <select id="civil_status" v-model="form.civil_status" class="field-input" :class="{ 'field-error': errors.civil_status }">
                                        <option value="">Select status</option>
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Widowed">Widowed</option>
                                        <option value="Separated">Separated</option>
                                    </select>
                                    <span v-if="errors.civil_status" class="err-msg">{{ errors.civil_status }}</span>
                                </div>

                                <div class="field-group">
                                    <label for="contact_number" class="field-label">Contact Number <span class="req">*</span></label>
                                    <div class="input-prefix-wrap">
                                        <span class="input-prefix">🇵🇭</span>
                                        <input
                                            id="contact_number"
                                            v-model="form.contact_number"
                                            type="tel"
                                            placeholder="09XXXXXXXXX"
                                            class="field-input prefix-input"
                                            :class="{ 'field-error': errors.contact_number }"
                                            maxlength="11"
                                            @input="formatContact"
                                        />
                                    </div>
                                    <span v-if="errors.contact_number" class="err-msg">{{ errors.contact_number }}</span>
                                </div>

                                <div class="field-group">
                                    <label for="email" class="field-label">Email Address <span class="opt">(optional)</span></label>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        placeholder="example@email.com"
                                        class="field-input"
                                        :class="{ 'field-error': errors.email }"
                                        autocomplete="email"
                                    />
                                    <span v-if="errors.email" class="err-msg">{{ errors.email }}</span>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <!-- Step 2: Address Information -->
                    <Transition name="slide" mode="out-in">
                        <div v-if="currentStep === 2" key="step2" class="step-body">
                            <h2 class="step-heading">Address Information</h2>
                            <p class="step-hint">Enter the household's complete Philippine address.</p>

                            <div class="form-grid">
                                <div class="field-group full-width">
                                    <label for="province" class="field-label">Province <span class="req">*</span></label>
                                    <select id="province" v-model="form.province" class="field-input" :class="{ 'field-error': errors.province }">
                                        <option value="">Select province</option>
                                        <option v-for="p in phProvinces" :key="p" :value="p">{{ p }}</option>
                                    </select>
                                    <span v-if="errors.province" class="err-msg">{{ errors.province }}</span>
                                </div>

                                <div class="field-group">
                                    <label for="municipality" class="field-label">Municipality / City <span class="req">*</span></label>
                                    <input id="municipality" v-model="form.municipality" type="text" placeholder="e.g. Lipa City" class="field-input" :class="{ 'field-error': errors.municipality }"/>
                                    <span v-if="errors.municipality" class="err-msg">{{ errors.municipality }}</span>
                                </div>

                                <div class="field-group">
                                    <label for="barangay" class="field-label">Barangay <span class="req">*</span></label>
                                    <input id="barangay" v-model="form.barangay" type="text" placeholder="e.g. Brgy. Santuario" class="field-input" :class="{ 'field-error': errors.barangay }"/>
                                    <span v-if="errors.barangay" class="err-msg">{{ errors.barangay }}</span>
                                </div>

                                <div class="field-group">
                                    <label for="zip_code" class="field-label">ZIP Code <span class="req">*</span></label>
                                    <input id="zip_code" v-model="form.zip_code" type="text" placeholder="4-digit ZIP" class="field-input" :class="{ 'field-error': errors.zip_code }" maxlength="4"/>
                                    <span v-if="errors.zip_code" class="err-msg">{{ errors.zip_code }}</span>
                                </div>
                            </div>
                        </div>
                    </Transition>

                    <!-- Step 3: Economic Information -->
                    <Transition name="slide" mode="out-in">
                        <div v-if="currentStep === 3" key="step3" class="step-body">
                            <h2 class="step-heading">Economic Information</h2>
                            <p class="step-hint">Household economic profile for beneficiary assessment.</p>

                            <div class="form-grid">
                                <div class="field-group">
                                    <label for="annual_income" class="field-label">Annual Household Income (₱) <span class="req">*</span></label>
                                    <div class="input-prefix-wrap">
                                        <span class="input-prefix">₱</span>
                                        <input
                                            id="annual_income"
                                            v-model="form.annual_income"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                            class="field-input prefix-input"
                                            :class="{ 'field-error': errors.annual_income }"
                                        />
                                    </div>
                                    <span v-if="errors.annual_income" class="err-msg">{{ errors.annual_income }}</span>
                                </div>

                                <div class="field-group">
                                    <label for="household_size" class="field-label">Household Size <span class="req">*</span></label>
                                    <input
                                        id="household_size"
                                        v-model="form.household_size"
                                        type="number"
                                        min="1"
                                        placeholder="Number of members"
                                        class="field-input"
                                        :class="{ 'field-error': errors.household_size }"
                                    />
                                    <span v-if="errors.household_size" class="err-msg">{{ errors.household_size }}</span>
                                </div>
                            </div>

                            <div class="info-box">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>
                                <p>Annual income and household size are used to determine eligibility for the 4Ps / CCT program. Information is treated with strict confidentiality.</p>
                            </div>
                        </div>
                    </Transition>

                    <!-- Step 4: Review & Submit -->
                    <Transition name="slide" mode="out-in">
                        <div v-if="currentStep === 4" key="step4" class="step-body">
                            <h2 class="step-heading">Review & Submit</h2>
                            <p class="step-hint">Please verify all information before submitting.</p>

                            <div class="review-grid">
                                <div class="review-section">
                                    <h3 class="review-section-title">Personal Information</h3>
                                    <div class="review-row"><span class="review-label">Full Name</span><span class="review-val">{{ form.family_head_name || '—' }}</span></div>
                                    <div class="review-row"><span class="review-label">Date of Birth</span><span class="review-val">{{ form.family_head_birthdate || '—' }}</span></div>
                                    <div class="review-row"><span class="review-label">Gender</span><span class="review-val">{{ form.gender || '—' }}</span></div>
                                    <div class="review-row"><span class="review-label">Civil Status</span><span class="review-val">{{ form.civil_status || '—' }}</span></div>
                                    <div class="review-row"><span class="review-label">Contact</span><span class="review-val">{{ form.contact_number || '—' }}</span></div>
                                    <div class="review-row"><span class="review-label">Email</span><span class="review-val">{{ form.email || 'Not provided' }}</span></div>
                                </div>

                                <div class="review-section">
                                    <h3 class="review-section-title">Address</h3>
                                    <div class="review-row"><span class="review-label">Province</span><span class="review-val">{{ form.province || '—' }}</span></div>
                                    <div class="review-row"><span class="review-label">Municipality</span><span class="review-val">{{ form.municipality || '—' }}</span></div>
                                    <div class="review-row"><span class="review-label">Barangay</span><span class="review-val">{{ form.barangay || '—' }}</span></div>
                                    <div class="review-row"><span class="review-label">ZIP Code</span><span class="review-val">{{ form.zip_code || '—' }}</span></div>
                                </div>

                                <div class="review-section">
                                    <h3 class="review-section-title">Economic Profile</h3>
                                    <div class="review-row"><span class="review-label">Annual Income</span><span class="review-val">₱{{ Number(form.annual_income || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</span></div>
                                    <div class="review-row"><span class="review-label">Household Size</span><span class="review-val">{{ form.household_size || '—' }} member(s)</span></div>
                                </div>
                            </div>

                            <!-- Server-side errors -->
                            <div v-if="Object.keys(errors).length" class="server-errors">
                                <p v-for="(msg, field) in errors" :key="field" class="server-err-item">{{ msg }}</p>
                            </div>
                        </div>
                    </Transition>

                    <!-- ── Navigation buttons ─────────────────────────────── -->
                    <div class="nav-btns">
                        <button
                            v-if="currentStep > 1"
                            type="button"
                            class="btn-ghost"
                            @click="prev"
                        >
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 0 1 0 1.06L8.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06l-4.25-4.25a.75.75 0 0 1 0-1.06l4.25-4.25a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
                            Back
                        </button>
                        <span v-else></span>

                        <div class="right-btns">
                            <button type="button" class="btn-draft" @click="router.get(route('beneficiaries.index'))">
                                Save Draft & Exit
                            </button>
                            <button
                                type="submit"
                                class="btn-primary"
                                :disabled="loading"
                            >
                                <svg v-if="loading" class="spinner" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31" stroke-linecap="round"/></svg>
                                <span v-if="currentStep < STEPS.length">Next</span>
                                <span v-else>{{ loading ? 'Submitting…' : 'Submit Registration' }}</span>
                                <svg v-if="!loading && currentStep < STEPS.length" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Duplicate detection modal -->
        <DuplicateDetectionModal
            v-if="showDuplicateModal"
            :matches="duplicateMatches"
            @proceed="handleDuplicateOverride"
            @draft="handleDuplicateDraft"
            @close="showDuplicateModal = false"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

.reg-wrap { display: flex; flex-direction: column; gap: 1.5rem; max-width: 860px; margin: 0 auto; font-family: 'Inter', sans-serif; }

.page-hd { display: flex; align-items: flex-start; justify-content: space-between; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.page-sub   { font-size: 0.875rem; color: #64748b; margin: 0.25rem 0 0; }

/* Progress card */
.progress-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; padding: 1.25rem 1.5rem;
}
.step-labels {
    display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem;
}
.step-label {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.8125rem; color: #475569; font-weight: 500;
    flex: 1; min-width: 120px;
}
.step-current { color: #a5b4fc; }
.step-done    { color: #6ee7b7; }
.step-indicator {
    width: 24px; height: 24px; border-radius: 50%;
    background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700; flex-shrink: 0;
}
.step-current .step-indicator { background: rgba(99,102,241,0.3); border-color: rgba(99,102,241,0.6); color: #a5b4fc; }
.step-done .step-indicator    { background: rgba(16,185,129,0.2); border-color: rgba(16,185,129,0.4); color: #6ee7b7; }
.step-indicator svg { width: 13px; height: 13px; }

.progress-bar-bg   { height: 4px; background: rgba(255,255,255,0.07); border-radius: 9999px; overflow: hidden; }
.progress-bar-fill { height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6); border-radius: 9999px; transition: width 0.4s ease; }

/* Form card */
.form-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; padding: 2rem;
}

.step-body { animation: fadeIn 0.2s ease; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

.step-heading { font-size: 1.125rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.25rem; }
.step-hint    { font-size: 0.875rem; color: #64748b; margin: 0 0 1.5rem; }

/* Form grid */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
@media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
.full-width { grid-column: 1 / -1; }

.field-group { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label { font-size: 0.8125rem; font-weight: 600; color: #94a3b8; }
.req { color: #f87171; }
.opt { color: #475569; font-weight: 400; }

.field-input {
    padding: 0.625rem 0.875rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem; color: #f1f5f9; font-size: 0.875rem;
    font-family: 'Inter', sans-serif; outline: none; transition: border-color 0.2s;
    appearance: auto;
}
.field-input::placeholder { color: #475569; }
.field-input:focus { border-color: rgba(99,102,241,0.5); background: rgba(99,102,241,0.05); }
.field-error { border-color: rgba(239,68,68,0.5) !important; }
.err-msg { font-size: 0.75rem; color: #f87171; }

/* Input prefix */
.input-prefix-wrap { position: relative; }
.input-prefix {
    position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%);
    font-size: 0.875rem; color: #64748b; pointer-events: none;
}
.prefix-input { padding-left: 2rem; }

/* Info box */
.info-box {
    display: flex; gap: 0.75rem; align-items: flex-start;
    margin-top: 1.5rem; padding: 1rem;
    background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2);
    border-radius: 0.75rem;
}
.info-box svg { width: 18px; height: 18px; color: #a5b4fc; flex-shrink: 0; margin-top: 1px; }
.info-box p   { font-size: 0.8125rem; color: #94a3b8; margin: 0; line-height: 1.5; }

/* Review layout */
.review-grid { display: flex; flex-direction: column; gap: 1.25rem; }
.review-section {
    background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 0.75rem; overflow: hidden;
}
.review-section-title {
    font-size: 0.75rem; font-weight: 700; color: #a5b4fc;
    text-transform: uppercase; letter-spacing: 0.05em;
    padding: 0.75rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.06);
    background: rgba(99,102,241,0.05);
}
.review-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.625rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.04);
    gap: 1rem;
}
.review-row:last-child { border-bottom: none; }
.review-label { font-size: 0.8125rem; color: #64748b; font-weight: 500; flex-shrink: 0; }
.review-val   { font-size: 0.875rem; color: #f1f5f9; font-weight: 500; text-align: right; }

/* Server errors */
.server-errors {
    margin-top: 1.25rem; padding: 1rem;
    background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25);
    border-radius: 0.75rem;
}
.server-err-item { font-size: 0.8125rem; color: #fca5a5; margin: 0.25rem 0; }

/* Nav buttons */
.nav-btns {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 2rem; padding-top: 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.06); gap: 1rem;
}
.right-btns { display: flex; gap: 0.75rem; align-items: center; }

.btn-ghost {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.625rem 1.125rem; border-radius: 0.75rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    color: #94a3b8; font-size: 0.875rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.btn-ghost svg { width: 16px; height: 16px; }
.btn-ghost:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }

.btn-draft {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.625rem 1rem; border-radius: 0.75rem;
    background: transparent; border: 1px solid rgba(255,255,255,0.1);
    color: #64748b; font-size: 0.8125rem; font-weight: 500;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
    white-space: nowrap;
}
.btn-draft:hover { border-color: rgba(255,255,255,0.2); color: #94a3b8; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; padding: 0.625rem 1.375rem; border-radius: 0.75rem;
    font-size: 0.875rem; font-weight: 600; border: none;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 15px rgba(99,102,241,0.3);
    font-family: 'Inter', sans-serif;
}
.btn-primary svg { width: 16px; height: 16px; }
.btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

/* Spinner */
.spinner {
    width: 16px; height: 16px;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Transitions */
.slide-enter-active, .slide-leave-active { transition: all 0.25s ease; }
.slide-enter-from { opacity: 0; transform: translateX(20px); }
.slide-leave-to   { opacity: 0; transform: translateX(-20px); }
</style>
