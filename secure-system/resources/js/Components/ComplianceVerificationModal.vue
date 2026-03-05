<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    record:   { type: Object, default: null },  // compliance record to verify
    canVerify:{ type: Boolean, default: false },
    show:     { type: Boolean, default: false },
});

const emit = defineEmits(['update:show', 'verified']);

const loading = ref(false);
const success = ref('');
const error   = ref('');
const showConfirm = ref(false);

/* ── Close ───────────────────────────────────────────────── */
function close() {
    emit('update:show', false);
    success.value     = '';
    error.value       = '';
    showConfirm.value = false;
}

/* ── Verify ──────────────────────────────────────────────── */
async function verify() {
    loading.value     = true;
    success.value     = '';
    error.value       = '';
    showConfirm.value = false;

    try {
        const res = await axios.post(`/compliance/verify/${props.record.id}`);
        success.value = res.data.message ?? 'Compliance record verified!';
        emit('verified', res.data.record);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to verify. Please try again.';
    } finally {
        loading.value = false;
    }
}

/* ── Formatters ──────────────────────────────────────────── */
function typeLabel(t) {
    return { education: 'Education', health: 'Health', fds: 'FDS (Family Dev. Session)' }[t] ?? t;
}
function typeColor(t) {
    return { education: '#a5b4fc', health: '#f9a8d4', fds: '#fcd34d' }[t] ?? '#94a3b8';
}

const isAlreadyVerified = computed(() => !!props.record?.verified_at);
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="show" class="cv-overlay" @click.self="close">
                <div class="cv-modal" role="dialog" aria-modal="true">

                    <!-- Header -->
                    <div class="cv-header">
                        <div class="cv-header-left">
                            <div class="cv-icon"
                                 :style="record ? `border-color:${typeColor(record.compliance_type)}33;background:${typeColor(record.compliance_type)}15` : ''">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                                     :style="record ? `color:${typeColor(record.compliance_type)}` : ''">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="cv-title">Compliance Verification</h2>
                                <p class="cv-subtitle" v-if="record">
                                    {{ typeLabel(record.compliance_type) }} · {{ record.compliance_period }}
                                </p>
                            </div>
                        </div>
                        <button class="cv-close" @click="close" aria-label="Close">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="cv-body">
                        <div v-if="!record" class="cv-empty">No record selected.</div>
                        <template v-else>

                            <!-- Current status pill -->
                            <div class="status-row">
                                <span :class="['status-pill', record.is_compliant ? 'pill-pass' : 'pill-fail']">
                                    {{ record.is_compliant ? '✓ Compliant' : '✗ Non-Compliant' }}
                                </span>
                                <span v-if="isAlreadyVerified" class="verified-badge">
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                    Already Verified
                                </span>
                                <span v-else class="pending-badge">Pending Verification</span>
                            </div>

                            <!-- Details grid -->
                            <div class="details-grid">
                                <div class="detail-item">
                                    <span class="di-label">Family Member</span>
                                    <span class="di-value">{{ record.family_member_name ?? '—' }}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="di-label">Period</span>
                                    <span class="di-value">{{ record.compliance_period }}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="di-label">Type</span>
                                    <span class="di-value" :style="`color:${typeColor(record.compliance_type)}`">
                                        {{ typeLabel(record.compliance_type) }}
                                    </span>
                                </div>

                                <!-- Education fields -->
                                <template v-if="record.compliance_type === 'education'">
                                    <div class="detail-item">
                                        <span class="di-label">School</span>
                                        <span class="di-value">{{ record.school_name ?? '—' }}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="di-label">Enrollment</span>
                                        <span class="di-value">{{ record.enrollment_status ?? '—' }}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="di-label">Attendance</span>
                                        <span class="di-value"
                                              :class="Number(record.attendance_percentage) >= 85 ? 'clr-green' : 'clr-red'">
                                            {{ record.attendance_percentage != null ? record.attendance_percentage + '%' : '—' }}
                                        </span>
                                    </div>
                                </template>

                                <!-- Health fields -->
                                <template v-if="record.compliance_type === 'health'">
                                    <div class="detail-item">
                                        <span class="di-label">Check-up Date</span>
                                        <span class="di-value">{{ record.health_checkup_date ?? '—' }}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="di-label">Vaccination</span>
                                        <span class="di-value"
                                              :class="record.vaccination_status === 'Complete' ? 'clr-green' : 'clr-red'">
                                            {{ record.vaccination_status ?? '—' }}
                                        </span>
                                    </div>
                                </template>

                                <!-- FDS fields -->
                                <template v-if="record.compliance_type === 'fds'">
                                    <div class="detail-item">
                                        <span class="di-label">FDS Attendance</span>
                                        <span class="di-value" :class="record.fds_attendance ? 'clr-green' : 'clr-red'">
                                            {{ record.fds_attendance ? 'Attended' : 'Did Not Attend' }}
                                        </span>
                                    </div>
                                </template>

                                <!-- Verifier info (if already verified) -->
                                <template v-if="isAlreadyVerified">
                                    <div class="detail-item span-2">
                                        <span class="di-label">Verified By</span>
                                        <span class="di-value">
                                            {{ record.verified_by?.name ?? '—' }} ({{ record.verified_by?.role ?? '—' }})
                                        </span>
                                    </div>
                                    <div class="detail-item span-2">
                                        <span class="di-label">Verified At</span>
                                        <span class="di-value">{{ record.verified_at_human }}</span>
                                    </div>
                                </template>

                                <div class="detail-item">
                                    <span class="di-label">Record ID</span>
                                    <span class="di-value mono">#{{ record.id }}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="di-label">Recorded</span>
                                    <span class="di-value">{{ record.created_at ? new Date(record.created_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—' }}</span>
                                </div>
                            </div>

                            <!-- Who needs to verify -->
                            <div v-if="!isAlreadyVerified" class="verify-note">
                                <svg viewBox="0 0 20 20" fill="currentColor" style="width:14px;height:14px;flex-shrink:0;">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>
                                </svg>
                                Verification required from a <strong>Compliance Verifier</strong> or <strong>Administrator</strong>.
                            </div>

                            <!-- Success / Error -->
                            <Transition name="fade">
                                <div v-if="success" class="alert-success">
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                    {{ success }}
                                </div>
                            </Transition>
                            <div v-if="error" class="alert-error">{{ error }}</div>

                            <!-- Confirm dialog -->
                            <Transition name="fade">
                                <div v-if="showConfirm" class="confirm-box">
                                    <p class="confirm-text">
                                        Are you sure you want to verify this compliance record?
                                        This action cannot be undone.
                                    </p>
                                    <div class="confirm-actions">
                                        <button class="btn-cancel" @click="showConfirm = false">Cancel</button>
                                        <button class="btn-confirm" :disabled="loading" @click="verify" id="confirm-verify-btn">
                                            <span v-if="loading" class="spinner"></span>
                                            <span v-else>Confirm Verify</span>
                                        </button>
                                    </div>
                                </div>
                            </Transition>
                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="cv-footer">
                        <button class="btn-close-modal" @click="close">Close</button>

                        <button v-if="canVerify && record && !isAlreadyVerified && !success"
                                class="btn-verify"
                                @click="showConfirm = true"
                                :disabled="loading || showConfirm"
                                id="verify-compliance-btn">
                            <svg viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                            </svg>
                            Verify Record
                        </button>

                        <div v-if="!canVerify && record && !isAlreadyVerified" class="no-permission">
                            You don't have permission to verify records.
                        </div>
                    </div>

                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
* { box-sizing: border-box; }

/* Overlay */
.cv-overlay {
    position: fixed; inset: 0; z-index: 9000;
    background: rgba(0,0,0,0.65);
    backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}

/* Modal container */
.cv-modal {
    font-family: 'Inter', sans-serif;
    background: #0f172a;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.5rem;
    width: 100%; max-width: 540px;
    box-shadow: 0 30px 80px rgba(0,0,0,0.7);
    display: flex; flex-direction: column; overflow: hidden;
    max-height: 90vh;
}

/* Header */
.cv-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 1.5rem 1.5rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    gap: 0.75rem;
}
.cv-header-left { display: flex; align-items: flex-start; gap: 0.875rem; }
.cv-icon {
    width: 44px; height: 44px; border-radius: 0.875rem;
    border: 1px solid rgba(255,255,255,0.1);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    transition: all 0.3s;
}
.cv-icon svg { width: 22px; height: 22px; }
.cv-title    { font-size: 1.0625rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.cv-subtitle { font-size: 0.8rem; color: #64748b; margin: 0; }

.cv-close {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 0.5rem; padding: 0.375rem; color: #64748b; cursor: pointer;
    transition: all 0.15s; flex-shrink: 0;
}
.cv-close svg { width: 18px; height: 18px; display: block; }
.cv-close:hover { background: rgba(239,68,68,0.15); color: #fca5a5; border-color: rgba(239,68,68,0.2); }

/* Body */
.cv-body {
    padding: 1.25rem 1.5rem;
    display: flex; flex-direction: column; gap: 1rem;
    overflow-y: auto; flex: 1;
}
.cv-empty { font-size: 0.875rem; color: #64748b; text-align: center; padding: 2rem 0; }

/* Status row */
.status-row { display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap; }
.status-pill {
    padding: 0.25rem 0.875rem; border-radius: 999px; font-size: 0.8rem; font-weight: 700;
}
.pill-pass { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.25); }
.pill-fail { background: rgba(239,68,68,0.1);   color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); }

.verified-badge {
    display: flex; align-items: center; gap: 0.3rem;
    padding: 0.25rem 0.75rem; border-radius: 999px;
    background: rgba(99,102,241,0.15); color: #a5b4fc;
    border: 1px solid rgba(99,102,241,0.25); font-size: 0.78rem; font-weight: 600;
}
.verified-badge svg { width: 12px; height: 12px; }
.pending-badge {
    padding: 0.25rem 0.75rem; border-radius: 999px;
    background: rgba(245,158,11,0.12); color: #fcd34d;
    border: 1px solid rgba(245,158,11,0.2); font-size: 0.78rem; font-weight: 600;
    font-style: italic;
}

/* Details grid */
.details-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 0.875rem; padding: 1rem;
}
.span-2 { grid-column: span 2; }
.detail-item { display: flex; flex-direction: column; gap: 0.25rem; }
.di-label { font-size: 0.72rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; }
.di-value { font-size: 0.875rem; font-weight: 600; color: #e2e8f0; }
.mono { font-family: 'Fira Code', monospace; font-size: 0.8125rem; }
.clr-green { color: #6ee7b7; }
.clr-red   { color: #f87171; }

/* Verify note */
.verify-note {
    display: flex; align-items: center; gap: 0.5rem;
    background: rgba(99,102,241,0.07); border: 1px solid rgba(99,102,241,0.15);
    border-radius: 0.75rem; padding: 0.75rem 0.875rem;
    font-size: 0.8rem; color: #94a3b8;
}
.verify-note strong { color: #a5b4fc; }

/* Alerts */
.alert-success {
    display: flex; align-items: center; gap: 0.5rem;
    background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25);
    border-radius: 0.75rem; padding: 0.75rem 1rem; font-size: 0.875rem; color: #6ee7b7;
}
.alert-success svg { width: 15px; height: 15px; flex-shrink: 0; }
.alert-error {
    background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2);
    border-radius: 0.75rem; padding: 0.75rem 1rem; font-size: 0.875rem; color: #fca5a5;
}

/* Confirm box */
.confirm-box {
    background: rgba(99,102,241,0.07); border: 1px solid rgba(99,102,241,0.2);
    border-radius: 0.875rem; padding: 1rem;
    display: flex; flex-direction: column; gap: 0.875rem;
}
.confirm-text { font-size: 0.875rem; color: #94a3b8; margin: 0; line-height: 1.5; }
.confirm-actions { display: flex; gap: 0.625rem; justify-content: flex-end; }
.btn-cancel {
    padding: 0.5rem 1rem; border-radius: 0.5rem;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    color: #94a3b8; font-size: 0.8125rem; font-weight: 600; cursor: pointer;
    font-family: 'Inter', sans-serif; transition: all 0.15s;
}
.btn-cancel:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }
.btn-confirm {
    display: flex; align-items: center; gap: 0.375rem;
    padding: 0.5rem 1rem; border-radius: 0.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none;
    color: white; font-size: 0.8125rem; font-weight: 700; cursor: pointer;
    font-family: 'Inter', sans-serif; transition: opacity 0.15s;
}
.btn-confirm:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-confirm:hover:not(:disabled) { opacity: 0.9; }

/* Footer */
.cv-footer {
    display: flex; align-items: center; justify-content: space-between; gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.07);
    flex-wrap: wrap;
}
.btn-close-modal {
    padding: 0.625rem 1.25rem; border-radius: 0.625rem;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    color: #94a3b8; font-size: 0.875rem; font-weight: 600; cursor: pointer;
    font-family: 'Inter', sans-serif; transition: all 0.15s;
}
.btn-close-modal:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }

.btn-verify {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1.375rem; border-radius: 0.625rem;
    background: linear-gradient(135deg, #10b981, #059669); border: none;
    color: white; font-size: 0.875rem; font-weight: 700; cursor: pointer;
    font-family: 'Inter', sans-serif; transition: opacity 0.2s, transform 0.15s;
}
.btn-verify:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
.btn-verify:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-verify svg { width: 16px; height: 16px; }

.no-permission { font-size: 0.78rem; color: #475569; font-style: italic; }

/* Spinner */
.spinner {
    width: 14px; height: 14px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: white;
    animation: spin 0.7s linear infinite; display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Transitions */
.modal-enter-active { transition: all 0.3s cubic-bezier(0.175,0.885,0.32,1.275); }
.modal-leave-active { transition: all 0.2s ease-in; }
.modal-enter-from .cv-modal   { opacity: 0; transform: scale(0.92) translateY(10px); }
.modal-leave-to .cv-modal     { opacity: 0; transform: scale(0.95) translateY(6px); }
.modal-enter-from, .modal-leave-to { background: rgba(0,0,0,0); }
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
