<script setup>
import { computed } from 'vue';

const props = defineProps({
    result:     { type: Object, required: true },   // full scan response
    canVerify:  { type: Boolean, default: false },
    loading:    { type: Boolean, default: false },
});

const emit = defineEmits(['approve', 'view-details', 'report-issue', 'close']);

const b = computed(() => props.result.beneficiary);
const eligibility  = computed(() => props.result.eligibility);
const compliance   = computed(() => props.result.compliance);

const eligibilityBadge = computed(() => {
    if (!eligibility.value) return { label: 'Unknown', cls: 'badge-neutral' };
    return eligibility.value.eligible
        ? { label: 'Eligible', cls: 'badge-eligible' }
        : { label: 'Not Eligible', cls: 'badge-ineligible' };
});

function complianceBadgeClass(c) {
    if (!c) return 'comp-unknown';
    return c.compliant ? 'comp-pass' : 'comp-fail';
}
function complianceLabel(c) {
    if (!c) return '—';
    return c.compliant ? 'Compliant' : 'Non-Compliant';
}

function educationData(compliance) {
    return compliance?.details?.find(d => d.type === 'education')?.data;
}
function healthData(compliance) {
    return compliance?.details?.find(d => d.type === 'health')?.data;
}
function fdsData(compliance) {
    return compliance?.details?.find(d => d.type === 'fds')?.data;
}
</script>

<template>
    <div class="vc-shell">
        <!-- Duplicate scan warning -->
        <div v-if="result.is_duplicate_scan" class="dup-warning">
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
            </svg>
            <span>Rapid rescan detected — this token was scanned very recently.</span>
        </div>

        <!-- Header -->
        <div class="vc-header">
            <!-- Avatar -->
            <div class="vc-avatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
            </div>
            <div class="vc-id-block">
                <h2 class="vc-name">{{ b.family_head_name }}</h2>
                <div class="vc-meta-row">
                    <span class="vc-bin">{{ b.bin }}</span>
                    <span :class="['vc-elig-badge', eligibilityBadge.cls]">{{ eligibilityBadge.label }}</span>
                </div>
            </div>
            <button class="vc-close-btn" @click="$emit('close')">✕</button>
        </div>

        <!-- Info grid -->
        <div class="vc-info-grid">
            <div class="info-cell">
                <span class="info-lbl">Gender</span>
                <span class="info-val">{{ b.gender || '—' }}</span>
            </div>
            <div class="info-cell">
                <span class="info-lbl">Civil Status</span>
                <span class="info-val">{{ b.civil_status || '—' }}</span>
            </div>
            <div class="info-cell">
                <span class="info-lbl">Birthdate</span>
                <span class="info-val">{{ b.family_head_birthdate || '—' }}</span>
            </div>
            <div class="info-cell">
                <span class="info-lbl">Household Size</span>
                <span class="info-val">{{ b.household_size || '—' }}</span>
            </div>
            <div class="info-cell" style="grid-column: span 2;">
                <span class="info-lbl">Address</span>
                <span class="info-val">
                    {{ [b.barangay, b.municipality, b.province].filter(Boolean).join(', ') || '—' }}
                </span>
            </div>
            <div class="info-cell">
                <span class="info-lbl">Contact</span>
                <span class="info-val">{{ b.contact_number || '—' }}</span>
            </div>
            <div class="info-cell">
                <span class="info-lbl">Family Members</span>
                <span class="info-val">{{ b.family_members_count }} member(s)</span>
            </div>
        </div>

        <!-- Compliance Badges -->
        <div v-if="compliance" class="comp-section">
            <h3 class="section-title">Compliance Status</h3>
            <div class="comp-badges">
                <div v-for="detail in compliance.details" :key="detail.type"
                     :class="['comp-badge', complianceBadgeClass(detail)]">
                    <span class="comp-dot"></span>
                    <span class="comp-type">{{ detail.label }}</span>
                    <span class="comp-status">{{ complianceLabel(detail) }}</span>
                </div>
            </div>

            <!-- Non-compliance reasons -->
            <div v-if="!compliance.compliant" class="comp-reasons">
                <p v-for="(d, i) in compliance.details.filter(x => !x.compliant)" :key="i" class="reason-item">
                    <svg viewBox="0 0 16 16" fill="currentColor" class="reason-icon"><path d="M8 15A7 7 0 118 1a7 7 0 010 14zm0-1A6 6 0 108 2a6 6 0 000 12zm-.857-5.857a.857.857 0 111.714 0v2.571a.857.857 0 01-1.714 0V8.143zm.857-3.143a.857.857 0 110 1.714A.857.857 0 018 5z"/></svg>
                    {{ d.reason }}
                </p>
            </div>
        </div>

        <!-- Eligibility reasons if not eligible -->
        <div v-if="eligibility && !eligibility.eligible" class="elig-reasons">
            <h3 class="section-title">Ineligibility Reasons</h3>
            <ul class="reasons-list">
                <li v-for="(r, i) in eligibility.reasons" :key="i">{{ r }}</li>
            </ul>
        </div>

        <!-- Family members preview -->
        <div v-if="b.family_members?.length" class="members-section">
            <h3 class="section-title">Family Members ({{ b.family_members.length }})</h3>
            <div class="members-list">
                <div v-for="m in b.family_members.slice(0,5)" :key="m.id" class="member-row">
                    <span class="member-name">{{ m.full_name }}</span>
                    <span class="member-rel">{{ m.relationship_to_head }}</span>
                    <span class="member-age">Age {{ m.age }}</span>
                </div>
                <p v-if="b.family_members.length > 5" class="more-members">
                    +{{ b.family_members.length - 5 }} more members
                </p>
            </div>
        </div>

        <!-- Actions -->
        <div class="vc-actions">
            <button class="btn-report" @click="$emit('report-issue')"
                    id="vc-btn-report">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                Report Issue
            </button>
            <button class="btn-details" @click="$emit('view-details')"
                    id="vc-btn-details">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41z" clip-rule="evenodd"/></svg>
                View Details
            </button>
            <button v-if="canVerify && eligibility?.eligible"
                    class="btn-approve" @click="$emit('approve')" :disabled="loading"
                    id="vc-btn-approve">
                <svg v-if="!loading" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                <svg v-else class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ loading ? 'Processing…' : 'Approve for Distribution' }}
            </button>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
* { box-sizing: border-box; }

.vc-shell {
    font-family: 'Inter', sans-serif;
    background: rgba(15,23,42,0.97);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.25rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* Duplicate warning */
.dup-warning {
    display: flex; align-items: center; gap: 0.625rem;
    background: rgba(245,158,11,0.08);
    border-bottom: 1px solid rgba(245,158,11,0.2);
    padding: 0.75rem 1.25rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: #fcd34d;
}
.dup-warning svg { width: 16px; height: 16px; flex-shrink: 0; }

/* Header */
.vc-header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.375rem 1.375rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    position: relative;
}
.vc-avatar {
    width: 56px; height: 56px;
    border-radius: 1rem;
    background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(139,92,246,0.2));
    border: 1px solid rgba(99,102,241,0.3);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    color: #a5b4fc;
}
.vc-avatar svg { width: 28px; height: 28px; }
.vc-id-block { flex: 1; min-width: 0; }
.vc-name {
    font-size: 1.0625rem;
    font-weight: 800;
    color: #f1f5f9;
    margin: 0 0 0.375rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.vc-meta-row { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.vc-bin {
    font-family: monospace;
    font-size: 0.8125rem;
    font-weight: 700;
    color: #a5b4fc;
}
.vc-elig-badge {
    display: inline-block;
    padding: 0.2rem 0.625rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 700;
}
.badge-eligible   { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
.badge-ineligible { background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.25); }
.badge-neutral    { background: rgba(100,116,139,0.1); color: #94a3b8; border: 1px solid rgba(100,116,139,0.2); }
.vc-close-btn {
    position: absolute; top: 1rem; right: 1rem;
    background: rgba(100,116,139,0.1);
    border: 1px solid rgba(100,116,139,0.2);
    border-radius: 0.5rem;
    color: #64748b; font-size: 0.875rem; font-weight: 700;
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.2s;
}
.vc-close-btn:hover { color: #94a3b8; background: rgba(100,116,139,0.2); }

/* Info grid */
.vc-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.info-cell {
    padding: 0.75rem 1.375rem;
    display: flex; flex-direction: column; gap: 0.125rem;
    border-right: 1px solid rgba(255,255,255,0.05);
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.info-cell:nth-child(even) { border-right: none; }
.info-lbl { font-size: 0.6875rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; }
.info-val  { font-size: 0.875rem; font-weight: 600; color: #e2e8f0; }

/* Compliance */
.comp-section { padding: 1rem 1.375rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
.section-title { font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 0.625rem; }
.comp-badges { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.comp-badge {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.3125rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem; font-weight: 600;
}
.comp-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.comp-type { color: inherit; opacity: 0.85; }
.comp-status { font-weight: 700; }
.comp-pass { background: rgba(16,185,129,0.12); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.2); }
.comp-pass .comp-dot { background: #10b981; }
.comp-fail { background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); }
.comp-fail .comp-dot { background: #ef4444; }
.comp-unknown { background: rgba(100,116,139,0.1); color: #94a3b8; border: 1px solid rgba(100,116,139,0.2); }

.comp-reasons { margin-top: 0.625rem; display: flex; flex-direction: column; gap: 0.3rem; }
.reason-item {
    display: flex; align-items: flex-start; gap: 0.375rem;
    font-size: 0.78rem; color: #fca5a5; margin: 0;
}
.reason-icon { width: 12px; height: 12px; flex-shrink: 0; margin-top: 1px; }

/* Eligibility reasons */
.elig-reasons { padding: 0.875rem 1.375rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
.reasons-list { margin: 0; padding-left: 1rem; display: flex; flex-direction: column; gap: 0.25rem; }
.reasons-list li { font-size: 0.8125rem; color: #fca5a5; line-height: 1.4; }

/* Family members */
.members-section { padding: 0.875rem 1.375rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
.members-list { display: flex; flex-direction: column; gap: 0.25rem; }
.member-row {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.375rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 0.8125rem;
}
.member-name { flex: 1; color: #e2e8f0; font-weight: 600; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.member-rel  { color: #64748b; font-size: 0.75rem; }
.member-age  { color: #64748b; font-size: 0.75rem; white-space: nowrap; }
.more-members { margin: 0.375rem 0 0; font-size: 0.75rem; color: #475569; font-style: italic; }

/* Actions */
.vc-actions {
    display: flex; gap: 0.625rem; flex-wrap: wrap;
    padding: 1.125rem 1.375rem;
    background: rgba(255,255,255,0.01);
}
.btn-report, .btn-details, .btn-approve {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.5625rem 0.9375rem;
    border-radius: 0.625rem;
    font-size: 0.8rem; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.2s;
    white-space: nowrap;
}
.btn-report svg, .btn-details svg, .btn-approve svg { width: 14px; height: 14px; }

.btn-report  { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #fca5a5; }
.btn-report:hover { background: rgba(239,68,68,0.15); }

.btn-details { background: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.2); color: #a5b4fc; }
.btn-details:hover { background: rgba(99,102,241,0.15); }

.btn-approve {
    margin-left: auto;
    background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(5,150,105,0.15));
    border: 1px solid rgba(16,185,129,0.3);
    color: #6ee7b7;
    font-weight: 700;
}
.btn-approve:hover:not(:disabled) { background: linear-gradient(135deg, rgba(16,185,129,0.25), rgba(5,150,105,0.25)); }
.btn-approve:disabled { opacity: 0.5; cursor: not-allowed; }

.spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
