<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage }                  from '@inertiajs/vue3';
import AuthenticatedLayout          from '@/Layouts/AuthenticatedLayout.vue';
import ComplianceSummaryCard        from '@/Components/ComplianceSummaryCard.vue';
import ComplianceTimeline           from '@/Components/ComplianceTimeline.vue';
import EducationComplianceForm      from '@/Components/EducationComplianceForm.vue';
import HealthComplianceForm         from '@/Components/HealthComplianceForm.vue';
import FDSAttendanceForm            from '@/Components/FDSAttendanceForm.vue';
import ComplianceVerificationModal  from '@/Components/ComplianceVerificationModal.vue';
import axios                        from 'axios';

/* ── Props from Inertia controller ──────────────────────── */
const props = defineProps({
    beneficiary:   { type: Object, required: true },
    familyMembers: { type: Array,  default: () => [] },
    canRecord:     { type: Boolean, default: false },
    canVerify:     { type: Boolean, default: false },
    isAdmin:       { type: Boolean, default: false },
});

/* ── State ───────────────────────────────────────────────── */
const activeTab     = ref('summary');   // 'summary' | 'record' | 'timeline'
const activeForm    = ref('education'); // 'education' | 'health' | 'fds'
const loading       = ref(false);
const allRecords    = ref([]);
const summary       = ref(null);
const pagination    = ref({});
const selectedRecord = ref(null);
const showVerifyModal = ref(false);
const toast         = ref({ show: false, message: '', type: 'success' });

const tabs = [
    { key: 'summary',  label: 'Summary',  icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z' },
    { key: 'record',   label: 'Record',   icon: 'M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z', hideWhen: !props.canRecord },
    { key: 'timeline', label: 'Timeline', icon: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5' },
];

const forms = [
    { key: 'education', label: 'Education', color: '#a5b4fc' },
    { key: 'health',    label: 'Health',    color: '#f9a8d4' },
    { key: 'fds',       label: 'FDS',       color: '#fcd34d' },
];

/* ── Fetch data ──────────────────────────────────────────── */
async function fetchCompliance() {
    loading.value = true;
    try {
        const res = await axios.get(`/compliance/${props.beneficiary.id}`);
        const data = res.data;
        summary.value    = data.summary;
        pagination.value = data.pagination;

        // Flatten all records from grouped array
        const flat = [];
        (data.grouped ?? []).forEach(group => {
            (group.records ?? []).forEach(r => flat.push(r));
        });
        allRecords.value = flat;
    } catch (e) {
        showToast('Failed to load compliance data.', 'error');
    } finally {
        loading.value = false;
    }
}

onMounted(fetchCompliance);

/* ── Event handlers ──────────────────────────────────────── */
function onRecordSubmitted(data) {
    showToast(data.message ?? 'Compliance recorded!', 'success');
    fetchCompliance();
    // Switch to timeline after recording
    setTimeout(() => { activeTab.value = 'timeline'; }, 1500);
}

function onViewRecord(record) {
    selectedRecord.value  = record;
    showVerifyModal.value = true;
}

function onVerified(updatedRecord) {
    showToast('Compliance record verified!', 'success');
    // Update in local list
    const idx = allRecords.value.findIndex(r => r.id === updatedRecord.id);
    if (idx !== -1) allRecords.value[idx] = updatedRecord;
    fetchCompliance();
    setTimeout(() => { showVerifyModal.value = false; }, 1200);
}

/* ── Toast ───────────────────────────────────────────────── */
function showToast(message, type = 'success') {
    toast.value = { show: true, message, type };
    setTimeout(() => { toast.value.show = false; }, 3500);
}

/* ── Computed ────────────────────────────────────────────── */
const pageTitle = computed(() => `Compliance — ${props.beneficiary.family_head_name}`);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="page-hd">
                <div class="hd-left">
                    <div class="hd-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="hd-title">Compliance Tracking</h1>
                        <p class="hd-sub">
                            {{ beneficiary.family_head_name }}
                            <span class="hd-bin">BIN: {{ beneficiary.bin }}</span>
                            <span class="hd-muni">· {{ beneficiary.municipality }}</span>
                        </p>
                    </div>
                </div>

                <!-- Overall badge quick view -->
                <div v-if="summary" class="hd-status">
                    <span :class="['status-badge', `sb-${summary.overall_compliance_status}`]">
                        {{ summary.overall_compliance_status === 'compliant'
                            ? '✓ Compliant'
                            : summary.overall_compliance_status === 'partial'
                            ? '◑ Partial'
                            : '✗ Non-Compliant' }}
                    </span>
                </div>
            </div>
        </template>

        <!-- ── Tab Navigation ────────────────────────────────── -->
        <div class="tab-nav">
            <button v-for="t in tabs.filter(x => !x.hideWhen)" :key="t.key"
                    class="tab-btn"
                    :class="{ 'tab-active': activeTab === t.key }"
                    @click="activeTab = t.key">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="t.icon"/>
                </svg>
                {{ t.label }}
            </button>
        </div>

        <!-- ── Toast ─────────────────────────────────────────── -->
        <Transition name="toast-slide">
            <div v-if="toast.show" :class="['toast', `toast-${toast.type}`]">
                <svg v-if="toast.type === 'success'" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                </svg>
                <svg v-else viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                </svg>
                {{ toast.message }}
            </div>
        </Transition>

        <!-- Loading state -->
        <div v-if="loading" class="loading-state">
            <div class="loader"></div>
            <span>Loading compliance data…</span>
        </div>

        <div v-else class="content-area">

            <!-- ── SUMMARY TAB ─────────────────────────────── -->
            <div v-if="activeTab === 'summary'" class="tab-pane">
                <ComplianceSummaryCard
                    :summary="summary"
                    :beneficiary="beneficiary"
                    @view-details="activeTab = 'timeline'"
                />

                <!-- Family member compliance overview table -->
                <div class="members-table-wrap">
                    <h3 class="section-title">Family Members Overview</h3>
                    <div class="members-table">
                        <div class="mt-head">
                            <span>Member</span>
                            <span>Age</span>
                            <span>School Age</span>
                            <span>Health Monitor</span>
                            <span>Relationship</span>
                        </div>
                        <div v-for="m in familyMembers" :key="m.id" class="mt-row">
                            <span class="m-name">{{ m.full_name }}</span>
                            <span>{{ m.age }}</span>
                            <span>
                                <span v-if="m.is_school_age" class="badge-yes">Yes</span>
                                <span v-else class="badge-no">No</span>
                            </span>
                            <span>
                                <span v-if="m.needs_health_monitoring" class="badge-yes">Yes</span>
                                <span v-else class="badge-no">No</span>
                            </span>
                            <span class="m-rel">{{ m.relationship_to_head }}</span>
                        </div>
                        <div v-if="!familyMembers.length" class="mt-empty">No family members recorded.</div>
                    </div>
                </div>
            </div>

            <!-- ── RECORD TAB ──────────────────────────────── -->
            <div v-if="activeTab === 'record' && canRecord" class="tab-pane">
                <!-- Sub-tabs for form types -->
                <div class="form-tabs">
                    <button v-for="f in forms" :key="f.key"
                            class="ftab"
                            :class="{ 'ftab-active': activeForm === f.key }"
                            :style="activeForm === f.key ? `--fc:${f.color}` : ''"
                            @click="activeForm = f.key">
                        {{ f.label }}
                    </button>
                </div>

                <Transition name="form-switch" mode="out-in">
                    <EducationComplianceForm
                        v-if="activeForm === 'education'"
                        :family-members="familyMembers"
                        :beneficiary-id="beneficiary.id"
                        @submitted="onRecordSubmitted"
                    />
                    <HealthComplianceForm
                        v-else-if="activeForm === 'health'"
                        :family-members="familyMembers"
                        :beneficiary-id="beneficiary.id"
                        @submitted="onRecordSubmitted"
                    />
                    <FDSAttendanceForm
                        v-else-if="activeForm === 'fds'"
                        :family-members="familyMembers"
                        :beneficiary-id="beneficiary.id"
                        @submitted="onRecordSubmitted"
                    />
                </Transition>
            </div>

            <!-- ── TIMELINE TAB ────────────────────────────── -->
            <div v-if="activeTab === 'timeline'" class="tab-pane">
                <ComplianceTimeline
                    :records="allRecords"
                    @view="onViewRecord"
                />

                <!-- Pending verification count -->
                <div v-if="canVerify" class="verify-prompt">
                    <div class="vp-left">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 110-16 8 8 0 010 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                        </svg>
                        <div>
                            <p class="vp-title">Verification Required</p>
                            <p class="vp-sub">
                                {{ allRecords.filter(r => !r.verified_at).length }} record(s) pending verification.
                                Click any record in the timeline to verify.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Modal -->
        <ComplianceVerificationModal
            v-model:show="showVerifyModal"
            :record="selectedRecord"
            :can-verify="canVerify"
            @verified="onVerified"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
* { box-sizing: border-box; }

/* Header */
.page-hd {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 1rem; flex-wrap: wrap;
}
.hd-left { display: flex; align-items: flex-start; gap: 0.875rem; }
.hd-icon {
    width: 46px; height: 46px; border-radius: 0.875rem; flex-shrink: 0;
    background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.25);
    display: flex; align-items: center; justify-content: center; color: #a5b4fc;
}
.hd-icon svg { width: 24px; height: 24px; }
.hd-title { font-size: 1.25rem; font-weight: 800; color: #f1f5f9; margin: 0; }
.hd-sub   { font-size: 0.8125rem; color: #64748b; margin: 0; display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; }
.hd-bin   { font-weight: 600; color: #a5b4fc; }
.hd-muni  { color: #475569; }

.hd-status { display: flex; align-items: center; }
.status-badge {
    padding: 0.375rem 1rem; border-radius: 999px; font-size: 0.8125rem; font-weight: 700;
}
.sb-compliant     { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.25); }
.sb-partial       { background: rgba(245,158,11,0.15); color: #fcd34d; border: 1px solid rgba(245,158,11,0.25); }
.sb-non_compliant { background: rgba(239,68,68,0.12);  color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); }

/* Tabs */
.tab-nav {
    display: flex; gap: 0.375rem; flex-wrap: wrap;
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 1rem; padding: 0.375rem; margin-bottom: 1.25rem;
}
.tab-btn {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 1rem; border-radius: 0.625rem;
    font-size: 0.875rem; font-weight: 600; color: #64748b;
    background: none; border: none; cursor: pointer;
    font-family: 'Inter', sans-serif; transition: all 0.2s;
}
.tab-btn svg { width: 16px; height: 16px; }
.tab-btn:hover { background: rgba(255,255,255,0.06); color: #94a3b8; }
.tab-active { background: rgba(99,102,241,0.15) !important; color: #a5b4fc !important; }

/* Toast */
.toast {
    position: fixed; top: 80px; right: 1.5rem; z-index: 8000;
    display: flex; align-items: center; gap: 0.625rem;
    padding: 0.875rem 1.25rem; border-radius: 0.875rem; font-size: 0.875rem; font-weight: 600;
    box-shadow: 0 10px 40px rgba(0,0,0,0.4); max-width: 400px;
}
.toast svg { width: 16px; height: 16px; flex-shrink: 0; }
.toast-success { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; }
.toast-error   { background: rgba(239,68,68,0.15);  border: 1px solid rgba(239,68,68,0.3);  color: #fca5a5; }
.toast-slide-enter-active { transition: all 0.4s cubic-bezier(0.175,0.885,0.32,1.275); }
.toast-slide-leave-active { transition: all 0.25s ease-in; }
.toast-slide-enter-from, .toast-slide-leave-to { opacity: 0; transform: translateX(30px); }

/* Loading */
.loading-state {
    display: flex; align-items: center; justify-content: center; gap: 0.75rem;
    padding: 4rem 1rem; color: #64748b; font-size: 0.9rem;
}
.loader {
    width: 20px; height: 20px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.1); border-top-color: #6366f1;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Content */
.content-area { display: flex; flex-direction: column; gap: 1.25rem; }
.tab-pane { display: flex; flex-direction: column; gap: 1.25rem; }

/* Form sub-tabs */
.form-tabs { display: flex; gap: 0.375rem; }
.ftab {
    padding: 0.4rem 1rem; border-radius: 999px;
    font-size: 0.8rem; font-weight: 600;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
    color: #64748b; cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.15s;
}
.ftab:hover { background: rgba(255,255,255,0.09); color: #94a3b8; }
.ftab-active {
    background: color-mix(in srgb, var(--fc, #a5b4fc) 18%, transparent);
    border-color: color-mix(in srgb, var(--fc, #a5b4fc) 35%, transparent);
    color: var(--fc, #a5b4fc);
}

/* Members table */
.members-table-wrap { display: flex; flex-direction: column; gap: 0.75rem; }
.section-title { font-size: 0.875rem; font-weight: 700; color: #94a3b8; margin: 0; text-transform: uppercase; letter-spacing: 0.05em; }
.members-table {
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; overflow: hidden;
}
.mt-head {
    display: grid; grid-template-columns: 2fr 0.5fr 1fr 1fr 1fr;
    padding: 0.625rem 1rem; gap: 0.5rem;
    font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.mt-row {
    display: grid; grid-template-columns: 2fr 0.5fr 1fr 1fr 1fr;
    padding: 0.75rem 1rem; gap: 0.5rem; align-items: center;
    font-size: 0.8125rem; color: #94a3b8;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.15s;
}
.mt-row:last-child { border-bottom: none; }
.mt-row:hover { background: rgba(255,255,255,0.03); }
.mt-empty { padding: 1.5rem 1rem; font-size: 0.875rem; color: #475569; text-align: center; }
.m-name { font-weight: 600; color: #e2e8f0; }
.m-rel  { color: #64748b; }
.badge-yes { background: rgba(16,185,129,0.12); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.2); padding: 0.1rem 0.5rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
.badge-no  { background: rgba(255,255,255,0.05); color: #475569; border: 1px solid rgba(255,255,255,0.08); padding: 0.1rem 0.5rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }

@media (max-width: 640px) {
    .mt-head, .mt-row { grid-template-columns: 1fr 1fr; }
    .mt-head span:nth-child(n+3), .mt-row span:nth-child(n+3) { display: none; }
}

/* Verify prompt */
.verify-prompt {
    background: rgba(99,102,241,0.07); border: 1px solid rgba(99,102,241,0.2);
    border-radius: 1rem; padding: 1rem 1.25rem;
}
.vp-left { display: flex; align-items: flex-start; gap: 0.875rem; }
.vp-left svg { width: 20px; height: 20px; color: #a5b4fc; flex-shrink: 0; margin-top: 0.1rem; }
.vp-title { font-size: 0.875rem; font-weight: 700; color: #a5b4fc; margin: 0; }
.vp-sub   { font-size: 0.8rem; color: #64748b; margin: 0.25rem 0 0; }

/* Form switch transition */
.form-switch-enter-active { transition: all 0.25s ease; }
.form-switch-leave-active { transition: all 0.15s ease-in; }
.form-switch-enter-from { opacity: 0; transform: translateY(8px); }
.form-switch-leave-to  { opacity: 0; transform: translateY(-4px); }
</style>
