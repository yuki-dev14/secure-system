<script setup>
import { ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DuplicateDetectionModal from '@/Components/DuplicateDetectionModal.vue';

const props = defineProps({
    beneficiary: Object,
});

const page  = usePage();
const loading = ref(false);
const showDuplicateModal = ref(false);
const duplicateMatches   = ref([]);

const form = ref({ ...props.beneficiary, override_duplicates: false });
const errors = ref({});

// Validate critical fields before submit
const validate = () => {
    const e = {};
    if (!form.value.family_head_name?.trim())    e.family_head_name = 'Full name is required.';
    if (!form.value.family_head_birthdate)        e.family_head_birthdate = 'Birthdate is required.';
    if (!form.value.gender)                       e.gender = 'Gender is required.';
    if (!form.value.civil_status)                 e.civil_status = 'Civil status is required.';
    if (!form.value.contact_number || !/^[0-9]{11}$/.test(form.value.contact_number)) e.contact_number = '11-digit Philippine number required.';
    if (form.value.email && !/^\S+@\S+\.\S+$/.test(form.value.email)) e.email = 'Invalid email.';
    if (!form.value.barangay?.trim())    e.barangay = 'Barangay required.';
    if (!form.value.municipality?.trim())e.municipality = 'Municipality required.';
    if (!form.value.province?.trim())    e.province = 'Province required.';
    if (!form.value.zip_code || form.value.zip_code.length !== 4) e.zip_code = '4-digit ZIP required.';
    if (form.value.annual_income === '' || form.value.annual_income < 0) e.annual_income = 'Valid income required.';
    if (!form.value.household_size || form.value.household_size < 1) e.household_size = 'Household size ≥ 1.';
    errors.value = e;
    return Object.keys(e).length === 0;
};

const formatContact = () => {
    form.value.contact_number = form.value.contact_number.replace(/\D/g, '').slice(0, 11);
};

const submit = () => {
    if (!validate()) return;
    loading.value = true;
    router.put(route('beneficiaries.update', props.beneficiary.id), form.value, {
        onError: (errs) => { errors.value = errs; loading.value = false; },
        onFinish: () => { loading.value = false; },
    });
};

const handleDuplicateOverride = () => {
    form.value.override_duplicates = true;
    showDuplicateModal.value = false;
    submit();
};

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
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="page-hd">
                <div>
                    <h1 class="page-title">Edit Beneficiary</h1>
                    <p class="page-sub">
                        {{ beneficiary.bin }} — {{ beneficiary.family_head_name }}
                    </p>
                </div>
            </div>
        </template>

        <div class="edit-wrap">
            <div class="form-card">
                <form @submit.prevent="submit">

                    <!-- ── Personal Information ─────────────────────── -->
                    <div class="section-head">
                        <h2 class="section-title">Personal Information</h2>
                    </div>
                    <div class="form-grid">
                        <div class="field-group full-width">
                            <label for="edit_family_head_name" class="field-label">Full Name <span class="req">*</span></label>
                            <input id="edit_family_head_name" v-model="form.family_head_name" type="text" class="field-input" :class="{ 'field-error': errors.family_head_name }"/>
                            <span v-if="errors.family_head_name" class="err-msg">{{ errors.family_head_name }}</span>
                            <p class="field-hint">⚠ Name changes require Administrator approval and will be logged.</p>
                        </div>

                        <div class="field-group">
                            <label for="edit_birthdate" class="field-label">Date of Birth <span class="req">*</span></label>
                            <input id="edit_birthdate" v-model="form.family_head_birthdate" type="date" class="field-input" :class="{ 'field-error': errors.family_head_birthdate }" :max="new Date().toISOString().split('T')[0]"/>
                            <span v-if="errors.family_head_birthdate" class="err-msg">{{ errors.family_head_birthdate }}</span>
                        </div>

                        <div class="field-group">
                            <label for="edit_gender" class="field-label">Gender <span class="req">*</span></label>
                            <select id="edit_gender" v-model="form.gender" class="field-input" :class="{ 'field-error': errors.gender }">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <span v-if="errors.gender" class="err-msg">{{ errors.gender }}</span>
                        </div>

                        <div class="field-group">
                            <label for="edit_civil_status" class="field-label">Civil Status <span class="req">*</span></label>
                            <select id="edit_civil_status" v-model="form.civil_status" class="field-input" :class="{ 'field-error': errors.civil_status }">
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Widowed">Widowed</option>
                                <option value="Separated">Separated</option>
                            </select>
                            <span v-if="errors.civil_status" class="err-msg">{{ errors.civil_status }}</span>
                        </div>

                        <div class="field-group">
                            <label for="edit_contact" class="field-label">Contact Number <span class="req">*</span></label>
                            <div class="input-prefix-wrap">
                                <span class="input-prefix">🇵🇭</span>
                                <input id="edit_contact" v-model="form.contact_number" type="tel" placeholder="09XXXXXXXXX" class="field-input prefix-input" :class="{ 'field-error': errors.contact_number }" maxlength="11" @input="formatContact"/>
                            </div>
                            <span v-if="errors.contact_number" class="err-msg">{{ errors.contact_number }}</span>
                        </div>

                        <div class="field-group">
                            <label for="edit_email" class="field-label">Email <span class="opt">(optional)</span></label>
                            <input id="edit_email" v-model="form.email" type="email" class="field-input" :class="{ 'field-error': errors.email }"/>
                            <span v-if="errors.email" class="err-msg">{{ errors.email }}</span>
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <!-- ── Address ─────────────────────────────────── -->
                    <div class="section-head">
                        <h2 class="section-title">Address</h2>
                    </div>
                    <div class="form-grid">
                        <div class="field-group full-width">
                            <label for="edit_province" class="field-label">Province <span class="req">*</span></label>
                            <select id="edit_province" v-model="form.province" class="field-input" :class="{ 'field-error': errors.province }">
                                <option value="">Select province</option>
                                <option v-for="p in phProvinces" :key="p" :value="p">{{ p }}</option>
                            </select>
                            <span v-if="errors.province" class="err-msg">{{ errors.province }}</span>
                        </div>

                        <div class="field-group">
                            <label for="edit_municipality" class="field-label">Municipality / City <span class="req">*</span></label>
                            <input id="edit_municipality" v-model="form.municipality" type="text" class="field-input" :class="{ 'field-error': errors.municipality }"/>
                            <span v-if="errors.municipality" class="err-msg">{{ errors.municipality }}</span>
                        </div>

                        <div class="field-group">
                            <label for="edit_barangay" class="field-label">Barangay <span class="req">*</span></label>
                            <input id="edit_barangay" v-model="form.barangay" type="text" class="field-input" :class="{ 'field-error': errors.barangay }"/>
                            <span v-if="errors.barangay" class="err-msg">{{ errors.barangay }}</span>
                        </div>

                        <div class="field-group">
                            <label for="edit_zip" class="field-label">ZIP Code <span class="req">*</span></label>
                            <input id="edit_zip" v-model="form.zip_code" type="text" maxlength="4" class="field-input" :class="{ 'field-error': errors.zip_code }"/>
                            <span v-if="errors.zip_code" class="err-msg">{{ errors.zip_code }}</span>
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <!-- ── Economic Information ────────────────────── -->
                    <div class="section-head">
                        <h2 class="section-title">Economic Information</h2>
                    </div>
                    <div class="form-grid">
                        <div class="field-group">
                            <label for="edit_income" class="field-label">Annual Income (₱) <span class="req">*</span></label>
                            <div class="input-prefix-wrap">
                                <span class="input-prefix">₱</span>
                                <input id="edit_income" v-model="form.annual_income" type="number" min="0" step="0.01" class="field-input prefix-input" :class="{ 'field-error': errors.annual_income }"/>
                            </div>
                            <span v-if="errors.annual_income" class="err-msg">{{ errors.annual_income }}</span>
                        </div>

                        <div class="field-group">
                            <label for="edit_household_size" class="field-label">Household Size <span class="req">*</span></label>
                            <input id="edit_household_size" v-model="form.household_size" type="number" min="1" class="field-input" :class="{ 'field-error': errors.household_size }"/>
                            <span v-if="errors.household_size" class="err-msg">{{ errors.household_size }}</span>
                        </div>
                    </div>

                    <!-- ── Server errors ────────────────────────────── -->
                    <div v-if="Object.keys(errors).length" class="server-errors">
                        <p v-for="(msg, field) in errors" :key="field" class="server-err-item">{{ msg }}</p>
                    </div>

                    <!-- ── Action buttons ──────────────────────────── -->
                    <div class="form-footer">
                        <a :href="route('beneficiaries.show', beneficiary.id)" class="btn-ghost">Cancel</a>
                        <button type="submit" class="btn-primary" :disabled="loading">
                            <svg v-if="loading" class="spinner" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31" stroke-linecap="round"/></svg>
                            {{ loading ? 'Saving…' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Duplicate modal -->
        <DuplicateDetectionModal
            v-if="showDuplicateModal"
            :matches="duplicateMatches"
            @proceed="handleDuplicateOverride"
            @draft="showDuplicateModal = false"
            @close="showDuplicateModal = false"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

.edit-wrap { max-width: 860px; margin: 0 auto; font-family: 'Inter', sans-serif; }
.page-hd { display: flex; align-items: flex-start; justify-content: space-between; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.25rem; }
.page-sub   { font-size: 0.875rem; color: #64748b; margin: 0; }

.form-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; padding: 2rem;
}

.section-head   { margin-bottom: 1.25rem; }
.section-title  { font-size: 1rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.section-divider { height: 1px; background: rgba(255,255,255,0.07); margin: 1.75rem 0; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 0.5rem; }
@media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
.full-width { grid-column: 1 / -1; }

.field-group { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label { font-size: 0.8125rem; font-weight: 600; color: #94a3b8; }
.req { color: #f87171; }
.opt { color: #475569; font-weight: 400; }
.field-hint { font-size: 0.75rem; color: #f59e0b; margin: 0.25rem 0 0; }

.field-input {
    padding: 0.625rem 0.875rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem; color: #f1f5f9; font-size: 0.875rem;
    font-family: 'Inter', sans-serif; outline: none; transition: border-color 0.2s;
    appearance: auto;
}
.field-input:focus { border-color: rgba(99,102,241,0.5); background: rgba(99,102,241,0.05); }
.field-error { border-color: rgba(239,68,68,0.5) !important; }
.err-msg { font-size: 0.75rem; color: #f87171; }

.input-prefix-wrap { position: relative; }
.input-prefix { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.875rem; color: #64748b; pointer-events: none; }
.prefix-input { padding-left: 2rem; }

.server-errors { margin-top: 1.25rem; padding: 1rem; background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.25); border-radius: 0.75rem; }
.server-err-item { font-size: 0.8125rem; color: #fca5a5; margin: 0.25rem 0; }

.form-footer {
    display: flex; justify-content: flex-end; align-items: center; gap: 0.75rem;
    margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid rgba(255,255,255,0.06);
}

.btn-ghost {
    display: inline-flex; align-items: center; padding: 0.625rem 1.125rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.75rem; color: #94a3b8;
    font-size: 0.875rem; font-weight: 600; text-decoration: none;
    transition: all 0.2s;
}
.btn-ghost:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }

.btn-primary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; padding: 0.625rem 1.375rem; border-radius: 0.75rem;
    font-size: 0.875rem; font-weight: 600; border: none;
    cursor: pointer; transition: all 0.2s;
    box-shadow: 0 4px 15px rgba(99,102,241,0.3);
    font-family: 'Inter', sans-serif;
}
.btn-primary:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

.spinner { width: 16px; height: 16px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
