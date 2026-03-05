<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    /** Full duplicateResult from the scan response */
    duplicateResult: { type: Object, required: true },
    beneficiaryId:   { type: [Number, String], required: true },
    /** Whether the current user is an Administrator */
    isAdmin:         { type: Boolean, default: false },
});

const emit = defineEmits(['dismissed', 'overridden', 'review-later']);

// ── State ───────────────────────────────────────────────────────────────────
const overrideReason    = ref('');
const overrideAction    = ref('different_person');
const loading           = ref(false);
const error             = ref('');
const showOverrideForm  = ref(false);

// ── Computed ─────────────────────────────────────────────────────────────────
const severity = computed(() => {
    const s = props.duplicateResult.confidence_score ?? 0;
    if (s >= 90) return 'high';
    if (s >= 65) return 'medium';
    return 'low';
});

const severityLabel = computed(() => ({
    high:   '⛔  High Risk — Distribution Blocked',
    medium: '⚠️  Medium Risk — Flagged for Review',
    low:    '🔍  Low Risk — Informational Flag',
}[severity.value]));

const recommendation = computed(() => props.duplicateResult.recommendation ?? 'flag');

const typeLabels = {
    recent_scan:           'Recent Duplicate Scan',
    address_match:         'Address Overlap',
    multiple_registration: 'Possible Multiple Registration',
    name_match:            'Name Similarity Match',
    contact_match:         'Contact Number Match',
    token_collision:       'Token Re-use Detected',
};

// ── Actions ──────────────────────────────────────────────────────────────────
async function submitOverride() {
    if (overrideReason.value.trim().length < 10) {
        error.value = 'Please provide a reason (at least 10 characters).';
        return;
    }

    loading.value = true;
    error.value   = '';
    try {
        const res = await axios.post(
            route('verification.override-duplicate', props.beneficiaryId),
            { override_reason: overrideReason.value, action: overrideAction.value }
        );
        emit('overridden', res.data);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Override request failed.';
    } finally {
        loading.value = false;
    }
}

function handleReviewLater() {
    emit('review-later');
}

function handleDismiss() {
    emit('dismissed');
}

function scoreBarWidth(score) {
    return Math.min(score, 100) + '%';
}

function scoreColor(score) {
    if (score >= 90) return '#ef4444';
    if (score >= 65) return '#f59e0b';
    return '#3b82f6';
}

function typeLabel(type) {
    return typeLabels[type] ?? type;
}
</script>

<template>
    <div :class="['da-shell', `da-${severity}`]">

        <!-- ── Header banner ──────────────────────────────────────────────── -->
        <div :class="['da-banner', `banner-${severity}`]">
            <div class="da-banner-icon">
                <svg v-if="severity === 'high'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <svg v-else-if="severity === 'medium'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                </svg>
                <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
                </svg>
            </div>
            <div class="da-banner-text">
                <p class="banner-title">{{ severityLabel }}</p>
                <p class="banner-sub">
                    Type: <strong>{{ typeLabel(duplicateResult.duplicate_type) }}</strong>
                    &nbsp;·&nbsp;
                    Detected: {{ new Date().toLocaleTimeString() }}
                </p>
            </div>
        </div>

        <!-- ── Confidence score ───────────────────────────────────────────── -->
        <div class="da-score-row">
            <span class="score-lbl">Confidence Score</span>
            <div class="score-bar-bg">
                <div class="score-bar-fill"
                     :style="{ width: scoreBarWidth(duplicateResult.confidence_score), background: scoreColor(duplicateResult.confidence_score) }">
                </div>
            </div>
            <span class="score-num" :style="{ color: scoreColor(duplicateResult.confidence_score) }">
                {{ duplicateResult.confidence_score }}%
            </span>
        </div>

        <!-- ── Detection breakdown ─────────────────────────────────────────── -->
        <div v-if="duplicateResult.detections?.length" class="da-detections">
            <p class="section-lbl">Detection Details</p>
            <div v-for="(det, i) in duplicateResult.detections" :key="i" class="det-row">
                <span :class="['det-type-badge', `badge-score-${det.score >= 90 ? 'high' : det.score >= 65 ? 'med' : 'low'}`]">
                    {{ typeLabel(det.type) }}
                </span>
                <span class="det-desc">{{ det.description }}</span>
                <span class="det-score" :style="{ color: scoreColor(det.score) }">{{ det.score }}%</span>
            </div>
        </div>

        <!-- ── Similar beneficiaries count ────────────────────────────────── -->
        <div v-if="duplicateResult.similar_beneficiaries?.length" class="da-similar-preview">
            <p class="section-lbl">
                {{ duplicateResult.similar_beneficiaries.length }} Similar Beneficiar{{ duplicateResult.similar_beneficiaries.length === 1 ? 'y' : 'ies' }} Found
            </p>
            <div v-for="b in duplicateResult.similar_beneficiaries.slice(0, 3)" :key="b.id" class="sim-row">
                <div class="sim-avatar">{{ (b.family_head_name ?? '?')[0].toUpperCase() }}</div>
                <div class="sim-info">
                    <p class="sim-name">{{ b.family_head_name }}</p>
                    <p class="sim-meta">BIN: {{ b.bin }} &nbsp;·&nbsp; {{ b.municipality }}</p>
                </div>
                <span v-if="b.score" class="sim-score" :style="{ color: scoreColor(b.score) }">
                    {{ b.score }}%
                </span>
                <span v-if="b.similarity_type" class="sim-type">{{ b.similarity_type.replace('_', ' ') }}</span>
            </div>
            <p v-if="duplicateResult.similar_beneficiaries.length > 3" class="sim-more">
                + {{ duplicateResult.similar_beneficiaries.length - 3 }} more — see full comparison below
            </p>
        </div>

        <!-- ── Actions ────────────────────────────────────────────────────── -->
        <div class="da-actions">

            <!-- High confidence: admin-only override form -->
            <template v-if="recommendation === 'block'">
                <p class="action-warning">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                    Distribution is blocked. Administrator approval is required to proceed.
                </p>

                <div v-if="isAdmin" class="override-section">
                    <button class="btn-toggle-override" id="da-btn-toggle-override"
                            @click="showOverrideForm = !showOverrideForm">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                        {{ showOverrideForm ? 'Cancel Override' : 'Request Admin Override' }}
                    </button>

                    <Transition name="slide">
                        <div v-if="showOverrideForm" class="override-form">
                            <p class="form-title">Administrator Override Authorization</p>

                            <div class="form-group">
                                <label class="form-lbl">Override Action</label>
                                <select class="form-select" v-model="overrideAction" id="da-override-action">
                                    <option value="different_person">Different person — allow distribution</option>
                                    <option value="review_later">Flag for later review — allow once</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-lbl">Reason / Justification <span class="required">*</span></label>
                                <textarea
                                    class="form-textarea"
                                    id="da-override-reason"
                                    v-model="overrideReason"
                                    placeholder="Explain why this beneficiary should be allowed to proceed despite the duplicate flag. Minimum 10 characters."
                                    rows="3">
                                </textarea>
                                <span class="char-count" :class="{ 'count-warn': overrideReason.length < 10 }">
                                    {{ overrideReason.length }} / 1000
                                </span>
                            </div>

                            <p v-if="error" class="form-error">{{ error }}</p>

                            <div class="form-actions">
                                <button class="btn-override-submit" id="da-btn-submit-override"
                                        @click="submitOverride" :disabled="loading">
                                    <svg v-if="loading" class="spinner" viewBox="0 0 24 24" fill="none">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="40" stroke-dashoffset="20"/>
                                    </svg>
                                    {{ loading ? 'Processing…' : 'Approve Override & Proceed' }}
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>

                <p v-else class="non-admin-note">Contact an Administrator to override this block.</p>
            </template>

            <!-- Medium / low: flexible actions -->
            <template v-else>
                <div class="action-btns">
                    <button class="btn-flag" id="da-btn-flag-review" @click="handleReviewLater">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M3 3a1 1 0 000 2h11a1 1 0 100-2H3zM3 7a1 1 0 000 2h7a1 1 0 100-2H3zM3 11a1 1 0 100 2h4a1 1 0 100-2H3z"/></svg>
                        Flag for Review
                    </button>
                    <button class="btn-dismiss" id="da-btn-dismiss" @click="handleDismiss">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        Acknowledge & Proceed
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
* { box-sizing: border-box; }

.da-shell {
    font-family: 'Inter', sans-serif;
    border-radius: 1.125rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 0;
    border: 1px solid;
}

/* Severity colours */
.da-high   { border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.04); }
.da-medium { border-color: rgba(245,158,11,0.3); background: rgba(245,158,11,0.04); }
.da-low    { border-color: rgba(59,130,246,0.3); background: rgba(59,130,246,0.04); }

/* Banner */
.da-banner {
    display: flex; align-items: flex-start; gap: 0.875rem;
    padding: 1rem 1.25rem;
}
.banner-high   { background: rgba(239,68,68,0.1); border-bottom: 1px solid rgba(239,68,68,0.15); }
.banner-medium { background: rgba(245,158,11,0.1); border-bottom: 1px solid rgba(245,158,11,0.15); }
.banner-low    { background: rgba(59,130,246,0.1); border-bottom: 1px solid rgba(59,130,246,0.15); }

.da-banner-icon { flex-shrink: 0; margin-top: 2px; }
.da-banner-icon svg { width: 22px; height: 22px; }
.banner-high   .da-banner-icon svg { color: #f87171; }
.banner-medium .da-banner-icon svg { color: #fbbf24; }
.banner-low    .da-banner-icon svg { color: #60a5fa; }

.da-banner-text { display: flex; flex-direction: column; gap: 0.125rem; }
.banner-title { font-size: 0.9375rem; font-weight: 800; margin: 0; }
.banner-high   .banner-title { color: #fca5a5; }
.banner-medium .banner-title { color: #fde68a; }
.banner-low    .banner-title { color: #bfdbfe; }
.banner-sub  { font-size: 0.78rem; color: #64748b; margin: 0; }
.banner-sub strong { color: #94a3b8; }

/* Score bar */
.da-score-row {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.score-lbl { font-size: 0.75rem; font-weight: 700; color: #475569; white-space: nowrap; }
.score-bar-bg {
    flex: 1; height: 6px;
    background: rgba(255,255,255,0.06);
    border-radius: 999px; overflow: hidden;
}
.score-bar-fill { height: 100%; border-radius: 999px; transition: width 0.6s ease; }
.score-num { font-size: 0.875rem; font-weight: 800; min-width: 36px; text-align: right; }

/* Detections */
.da-detections {
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    display: flex; flex-direction: column; gap: 0.375rem;
}
.section-lbl {
    font-size: 0.6875rem; font-weight: 700; color: #475569;
    text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 0.25rem;
}
.det-row {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.8125rem;
}
.det-type-badge {
    padding: 0.125rem 0.5rem; border-radius: 9999px;
    font-size: 0.7rem; font-weight: 700; white-space: nowrap;
}
.badge-score-high { background: rgba(239,68,68,0.12); color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); }
.badge-score-med  { background: rgba(245,158,11,0.12); color: #fde68a; border: 1px solid rgba(245,158,11,0.2); }
.badge-score-low  { background: rgba(59,130,246,0.1);  color: #bfdbfe; border: 1px solid rgba(59,130,246,0.2); }
.det-desc  { flex: 1; color: #94a3b8; }
.det-score { font-weight: 700; font-size: 0.78rem; }

/* Similar beneficiaries */
.da-similar-preview {
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    display: flex; flex-direction: column; gap: 0.5rem;
}
.sim-row {
    display: flex; align-items: center; gap: 0.625rem;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 0.625rem;
    padding: 0.5rem 0.75rem;
}
.sim-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: rgba(99,102,241,0.15);
    border: 1px solid rgba(99,102,241,0.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.875rem; font-weight: 700; color: #a5b4fc;
    flex-shrink: 0;
}
.sim-info { flex: 1; }
.sim-name { font-size: 0.8125rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.sim-meta { font-size: 0.72rem; color: #64748b; margin: 0; }
.sim-score { font-size: 0.8rem; font-weight: 700; }
.sim-type  { font-size: 0.68rem; color: #475569; background: rgba(255,255,255,0.04); padding: 0.1rem 0.4rem; border-radius: 4px; }
.sim-more  { font-size: 0.75rem; color: #475569; margin: 0; text-align: center; font-style: italic; }

/* Actions */
.da-actions { padding: 1rem 1.25rem; display: flex; flex-direction: column; gap: 0.75rem; }

.action-warning {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.8125rem; font-weight: 700; color: #fca5a5;
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.15);
    border-radius: 0.625rem;
    padding: 0.625rem 0.875rem;
    margin: 0;
}
.action-warning svg { width: 16px; height: 16px; flex-shrink: 0; }

.btn-toggle-override {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5625rem 1rem;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 0.625rem;
    color: #fca5a5; font-size: 0.875rem; font-weight: 700;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.2s;
    width: 100%;
}
.btn-toggle-override svg { width: 16px; height: 16px; }
.btn-toggle-override:hover { background: rgba(239,68,68,0.18); }

.non-admin-note { font-size: 0.8rem; color: #475569; text-align: center; font-style: italic; margin: 0; }

/* Override form */
.override-form {
    display: flex; flex-direction: column; gap: 0.75rem;
    background: rgba(239,68,68,0.05);
    border: 1px solid rgba(239,68,68,0.15);
    border-radius: 0.875rem;
    padding: 1rem;
}
.form-title { font-size: 0.875rem; font-weight: 700; color: #fca5a5; margin: 0; }
.form-group { display: flex; flex-direction: column; gap: 0.25rem; }
.form-lbl {
    font-size: 0.6875rem; font-weight: 700; color: #64748b;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.required { color: #f87171; }
.form-select, .form-textarea {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.5rem;
    color: #e2e8f0;
    font-size: 0.8125rem;
    padding: 0.4375rem 0.625rem;
    outline: none;
    font-family: 'Inter', sans-serif;
    resize: vertical;
}
.form-select option { background: #1e293b; }
.form-select:focus, .form-textarea:focus { border-color: rgba(239,68,68,0.4); }
.char-count { font-size: 0.7rem; color: #475569; text-align: right; }
.count-warn { color: #f87171; }
.form-error { font-size: 0.8rem; color: #fca5a5; margin: 0; background: rgba(239,68,68,0.08); padding: 0.5rem; border-radius: 0.5rem; }
.form-actions { display: flex; justify-content: flex-end; }
.btn-override-submit {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5625rem 1.25rem;
    background: rgba(239,68,68,0.15);
    border: 1px solid rgba(239,68,68,0.3);
    border-radius: 0.625rem;
    color: #fca5a5; font-size: 0.875rem; font-weight: 700;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.2s;
}
.btn-override-submit:hover:not(:disabled) { background: rgba(239,68,68,0.25); }
.btn-override-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.spinner { width: 14px; height: 14px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Medium/low action buttons */
.action-btns { display: flex; gap: 0.625rem; }
.btn-flag, .btn-dismiss {
    flex: 1;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.375rem;
    padding: 0.5625rem 1rem;
    border-radius: 0.625rem;
    font-size: 0.8125rem; font-weight: 700;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.2s;
}
.btn-flag svg, .btn-dismiss svg { width: 14px; height: 14px; }
.btn-flag {
    background: rgba(245,158,11,0.1);
    border: 1px solid rgba(245,158,11,0.2);
    color: #fde68a;
}
.btn-flag:hover { background: rgba(245,158,11,0.18); }
.btn-dismiss {
    background: rgba(16,185,129,0.1);
    border: 1px solid rgba(16,185,129,0.2);
    color: #6ee7b7;
}
.btn-dismiss:hover { background: rgba(16,185,129,0.18); }

/* Slide transition */
.slide-enter-active { transition: all 0.25s ease; }
.slide-leave-active { transition: all 0.2s ease; }
.slide-enter-from   { opacity: 0; transform: translateY(-8px); }
.slide-leave-to     { opacity: 0; transform: translateY(-6px); }
</style>
