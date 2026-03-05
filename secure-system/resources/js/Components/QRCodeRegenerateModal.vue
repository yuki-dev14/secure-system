<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    beneficiaryId:   { type: [Number, String], required: true },
    beneficiaryName: { type: String, default: '' },
    beneficiaryBin:  { type: String, default: '' },
    modelValue:      { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'regenerated']);

// ── State ──────────────────────────────────────────────────────────────────
const loading      = ref(false);
const error        = ref(null);
const step         = ref('form');   // 'form' | 'confirm' | 'success'
const newQrCode    = ref(null);
const downloadDone = ref(false);

const reasonOptions = [
    { value: 'lost',    label: 'Lost — QR code was misplaced or lost' },
    { value: 'damaged', label: 'Damaged — QR code is physically damaged/unreadable' },
    { value: 'expired', label: 'Expired — QR code has passed its validity period' },
    { value: 'compromised', label: 'Compromised — Security concern or unauthorized use' },
    { value: 'other',   label: 'Other — Custom reason' },
];

const form = ref({
    reason:         '',
    custom_reason:  '',
});

// ── Computed ───────────────────────────────────────────────────────────────
const showCustomReason = computed(() => form.value.reason === 'other');

const finalReason = computed(() => {
    if (!form.value.reason) return '';
    if (form.value.reason === 'other') return form.value.custom_reason.trim();
    const opt = reasonOptions.find(r => r.value === form.value.reason);
    return opt ? opt.label : form.value.reason;
});

const canProceed = computed(() => {
    if (!form.value.reason) return false;
    if (form.value.reason === 'other' && !form.value.custom_reason.trim()) return false;
    return true;
});

// ── Methods ────────────────────────────────────────────────────────────────
function close() {
    emit('update:modelValue', false);
    // Reset after close animation
    setTimeout(() => {
        step.value   = 'form';
        error.value  = null;
        form.value   = { reason: '', custom_reason: '' };
        newQrCode.value = null;
    }, 300);
}

function goToConfirm() {
    if (!canProceed.value) return;
    error.value = null;
    step.value  = 'confirm';
}

async function submitRegeneration() {
    loading.value = true;
    error.value   = null;
    try {
        const res = await fetch(route('qr.regenerate', props.beneficiaryId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ regeneration_reason: finalReason.value }),
        });
        const data = await res.json();
        if (data.success) {
            newQrCode.value = data.qr_code;
            step.value = 'success';
            emit('regenerated', data.qr_code);
        } else {
            error.value = data.message ?? 'Regeneration failed. Try again.';
            step.value  = 'form';
        }
    } catch (e) {
        error.value = 'Network error: ' + e.message;
        step.value  = 'form';
    } finally {
        loading.value = false;
    }
}

function downloadNewQr() {
    if (!newQrCode.value?.qr_image_url) return;
    const a   = document.createElement('a');
    a.href    = newQrCode.value.qr_image_url;
    a.download = `qr_${props.beneficiaryBin}_regenerated.png`;
    a.click();
    downloadDone.value = true;
    setTimeout(() => { downloadDone.value = false; }, 3000);
}
</script>

<template>
    <!-- Backdrop -->
    <Teleport to="body">
        <Transition name="backdrop">
            <div
                v-if="modelValue"
                class="modal-backdrop"
                @click.self="close"
            >
                <!-- Modal -->
                <Transition name="modal-slide">
                    <div v-if="modelValue" class="modal" role="dialog" aria-modal="true" aria-labelledby="regen-title">
                        <!-- Modal Header -->
                        <div class="modal-header">
                            <div class="modal-title-row">
                                <div class="modal-icon" :class="step === 'success' ? 'icon-success' : 'icon-warn'">
                                    <svg v-if="step !== 'success'" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd"/>
                                    </svg>
                                    <svg v-else viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div>
                                    <h2 id="regen-title" class="modal-title">
                                        {{ step === 'success' ? 'QR Code Regenerated' : 'Regenerate QR Code' }}
                                    </h2>
                                    <p class="modal-sub">
                                        <span class="bin-pill">{{ beneficiaryBin }}</span>
                                        &nbsp;{{ beneficiaryName }}
                                    </p>
                                </div>
                            </div>
                            <button class="close-btn" @click="close" aria-label="Close">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                            </button>
                        </div>

                        <!-- Error alert -->
                        <div v-if="error" class="modal-alert alert-error">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                            {{ error }}
                        </div>

                        <!-- ── STEP: FORM ─────────────────────────────── -->
                        <div v-if="step === 'form'" class="modal-body">
                            <!-- Warning banner -->
                            <div class="warn-banner">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                                <div>
                                    <strong>Administrator Action Required</strong>
                                    <p>Regenerating a QR code will immediately invalidate the existing QR. The verification token remains unchanged.</p>
                                </div>
                            </div>

                            <!-- Reason select -->
                            <div class="form-field">
                                <label for="regen-reason" class="field-label">Reason for Regeneration <span class="required">*</span></label>
                                <select
                                    id="regen-reason"
                                    v-model="form.reason"
                                    class="field-select"
                                >
                                    <option value="">— Select a reason —</option>
                                    <option
                                        v-for="opt in reasonOptions"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >{{ opt.label }}</option>
                                </select>
                            </div>

                            <!-- Custom reason input -->
                            <Transition name="fade">
                                <div v-if="showCustomReason" class="form-field">
                                    <label for="regen-custom" class="field-label">Describe the reason <span class="required">*</span></label>
                                    <textarea
                                        id="regen-custom"
                                        v-model="form.custom_reason"
                                        class="field-textarea"
                                        placeholder="Provide a detailed reason for regeneration…"
                                        rows="3"
                                        maxlength="500"
                                    ></textarea>
                                    <span class="char-count">{{ form.custom_reason.length }}/500</span>
                                </div>
                            </Transition>
                        </div>

                        <!-- ── STEP: CONFIRM ──────────────────────────── -->
                        <div v-else-if="step === 'confirm'" class="modal-body">
                            <div class="confirm-section">
                                <div class="confirm-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                                    </svg>
                                </div>
                                <h3 class="confirm-title">Confirm Regeneration</h3>
                                <p class="confirm-msg">
                                    You are about to regenerate the QR code for <strong>{{ beneficiaryName }}</strong> (<span class="bin-inline">{{ beneficiaryBin }}</span>).
                                    The existing QR code will be permanently invalidated.
                                </p>
                                <div class="confirm-reason-box">
                                    <span class="confirm-reason-label">Reason</span>
                                    <span class="confirm-reason-text">{{ finalReason }}</span>
                                </div>
                                <p class="confirm-note">This action is logged and cannot be automatically undone.</p>
                            </div>
                        </div>

                        <!-- ── STEP: SUCCESS ──────────────────────────── -->
                        <div v-else-if="step === 'success'" class="modal-body">
                            <div class="success-section">
                                <div class="new-qr-wrap">
                                    <img
                                        v-if="newQrCode?.qr_image_url"
                                        :src="newQrCode.qr_image_url"
                                        alt="New QR Code"
                                        class="new-qr-img"
                                    />
                                </div>
                                <div class="success-meta">
                                    <div class="meta-row">
                                        <span class="meta-lbl">Generated At</span>
                                        <span class="meta-val">{{ newQrCode?.generated_at_human }}</span>
                                    </div>
                                    <div class="meta-row">
                                        <span class="meta-lbl">Valid Until</span>
                                        <span class="meta-val">{{ newQrCode?.expires_at_human }}</span>
                                    </div>
                                    <div class="meta-row">
                                        <span class="meta-lbl">Reason</span>
                                        <span class="meta-val italic">{{ finalReason }}</span>
                                    </div>
                                </div>
                                <p class="success-note">
                                    ✓ New QR code is now active. Previous QR has been invalidated.
                                </p>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="modal-footer">
                            <!-- Form step -->
                            <template v-if="step === 'form'">
                                <button id="btn-regen-cancel" class="btn-ghost" @click="close">Cancel</button>
                                <button
                                    id="btn-regen-next"
                                    class="btn-warn"
                                    :disabled="!canProceed"
                                    @click="goToConfirm"
                                >
                                    Review & Confirm
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                                </button>
                            </template>

                            <!-- Confirm step -->
                            <template v-else-if="step === 'confirm'">
                                <button id="btn-regen-back" class="btn-ghost" @click="step = 'form'" :disabled="loading">Back</button>
                                <button
                                    id="btn-regen-submit"
                                    class="btn-danger"
                                    :disabled="loading"
                                    @click="submitRegeneration"
                                >
                                    <div v-if="loading" class="btn-spinner"></div>
                                    <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39z" clip-rule="evenodd"/></svg>
                                    {{ loading ? 'Regenerating…' : 'Yes, Regenerate QR' }}
                                </button>
                            </template>

                            <!-- Success step -->
                            <template v-else>
                                <button
                                    id="btn-regen-download"
                                    class="btn-secondary"
                                    @click="downloadNewQr"
                                >
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/></svg>
                                    {{ downloadDone ? '✓ Downloaded!' : 'Download New QR' }}
                                </button>
                                <button id="btn-regen-done" class="btn-primary" @click="close">Done</button>
                            </template>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
* { box-sizing: border-box; }

/* ── Backdrop ── */
.modal-backdrop {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(6px);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
}

/* ── Modal ── */
.modal {
    background: #0f172a;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.25rem;
    width: 100%; max-width: 500px;
    box-shadow: 0 25px 80px rgba(0,0,0,0.7);
    overflow: hidden;
    font-family: 'Inter', sans-serif;
}

/* ── Header ── */
.modal-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 1.5rem 1.5rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}
.modal-title-row { display: flex; align-items: flex-start; gap: 0.875rem; }
.modal-icon {
    width: 40px; height: 40px; border-radius: 0.75rem;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.modal-icon svg { width: 20px; height: 20px; }
.icon-warn    { background: rgba(245,158,11,0.12); color: #fcd34d; border: 1px solid rgba(245,158,11,0.25); }
.icon-success { background: rgba(16,185,129,0.12); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.25); }
.modal-title  { font-size: 1rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.25rem; }
.modal-sub    { font-size: 0.8125rem; color: #64748b; margin: 0; display: flex; align-items: center; gap: 0.375rem; }
.bin-pill     { font-family: monospace; font-size: 0.75rem; font-weight: 700; color: #a5b4fc; background: rgba(99,102,241,0.12); padding: 0.125rem 0.375rem; border-radius: 0.375rem; }
.close-btn    { background: none; border: none; cursor: pointer; color: #475569; padding: 0.25rem; border-radius: 0.5rem; transition: all 0.2s; display: flex; }
.close-btn svg{ width: 18px; height: 18px; }
.close-btn:hover { background: rgba(255,255,255,0.07); color: #94a3b8; }

/* ── Alerts ── */
.modal-alert {
    display: flex; align-items: flex-start; gap: 0.625rem;
    margin: 0 1.5rem; padding: 0.75rem 1rem; border-radius: 0.75rem; font-size: 0.8125rem;
}
.modal-alert svg { width: 15px; height: 15px; flex-shrink: 0; margin-top: 1px; }
.alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5; }

/* ── Body ── */
.modal-body { padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1rem; }

/* Warning banner */
.warn-banner {
    display: flex; align-items: flex-start; gap: 0.75rem;
    background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2);
    border-radius: 0.875rem; padding: 0.875rem 1rem; color: #d4a017;
}
.warn-banner svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; color: #fcd34d; }
.warn-banner strong { font-size: 0.8125rem; font-weight: 700; color: #fcd34d; display: block; margin-bottom: 0.25rem; }
.warn-banner p { font-size: 0.75rem; color: #b45309; margin: 0; }

/* Form fields */
.form-field   { display: flex; flex-direction: column; gap: 0.375rem; }
.field-label  { font-size: 0.8rem; font-weight: 700; color: #94a3b8; }
.required     { color: #f87171; }
.field-select, .field-textarea {
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.12);
    border-radius: 0.75rem; padding: 0.625rem 0.875rem;
    color: #f1f5f9; font-size: 0.875rem; font-family: 'Inter', sans-serif;
    outline: none; transition: border-color 0.2s; width: 100%;
}
.field-select:focus, .field-textarea:focus { border-color: rgba(99,102,241,0.5); }
.field-select option { background: #1e293b; }
.field-textarea { resize: vertical; min-height: 80px; }
.char-count { font-size: 0.7rem; color: #475569; text-align: right; }

/* Confirm step */
.confirm-section { display: flex; flex-direction: column; align-items: center; gap: 0.875rem; text-align: center; }
.confirm-icon {
    width: 56px; height: 56px; border-radius: 1rem;
    background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.25);
    display: flex; align-items: center; justify-content: center;
}
.confirm-icon svg { width: 28px; height: 28px; color: #fcd34d; }
.confirm-title { font-size: 1.0625rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.confirm-msg   { font-size: 0.875rem; color: #94a3b8; max-width: 380px; margin: 0; line-height: 1.6; }
.confirm-msg strong { color: #f1f5f9; }
.bin-inline { font-family: monospace; color: #a5b4fc; }
.confirm-reason-box {
    display: flex; flex-direction: column; gap: 0.25rem;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 0.75rem; padding: 0.75rem 1rem; width: 100%; text-align: left;
}
.confirm-reason-label { font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
.confirm-reason-text  { font-size: 0.875rem; color: #e2e8f0; font-style: italic; }
.confirm-note { font-size: 0.75rem; color: #475569; margin: 0; }

/* Success step */
.success-section { display: flex; flex-direction: column; align-items: center; gap: 1rem; }
.new-qr-wrap { padding: 8px; background: white; border-radius: 0.75rem; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
.new-qr-img  { display: block; width: 140px; height: 140px; }
.success-meta { display: flex; flex-direction: column; gap: 0.625rem; width: 100%; }
.meta-row      { display: flex; flex-direction: column; gap: 0.1rem; }
.meta-lbl      { font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
.meta-val      { font-size: 0.875rem; color: #e2e8f0; }
.italic        { font-style: italic; color: #94a3b8; }
.success-note  { font-size: 0.8125rem; color: #6ee7b7; background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); padding: 0.625rem 1rem; border-radius: 0.75rem; width: 100%; text-align: center; margin: 0; }

/* ── Footer ── */
.modal-footer {
    display: flex; justify-content: flex-end; gap: 0.625rem;
    padding: 1.25rem 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.07);
    background: rgba(255,255,255,0.02);
}

.btn-ghost, .btn-warn, .btn-danger, .btn-primary, .btn-secondary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1.125rem; border-radius: 0.75rem;
    font-size: 0.8125rem; font-weight: 600; cursor: pointer;
    border: none; font-family: 'Inter', sans-serif; transition: all 0.2s;
}
.btn-ghost    { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #64748b; }
.btn-ghost:hover:not(:disabled)    { background: rgba(255,255,255,0.08); color: #cbd5e1; }
.btn-warn     { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.25); color: #fcd34d; }
.btn-warn:hover:not(:disabled)     { background: rgba(245,158,11,0.18); }
.btn-danger   { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
.btn-danger:hover:not(:disabled)   { background: rgba(239,68,68,0.22); }
.btn-primary  { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
.btn-primary:hover:not(:disabled)  { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }
.btn-secondary{ background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: #cbd5e1; }
.btn-secondary:hover:not(:disabled){ background: rgba(255,255,255,0.1); color: #f1f5f9; }
button:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }
.btn-ghost svg, .btn-warn svg, .btn-danger svg, .btn-primary svg, .btn-secondary svg { width: 16px; height: 16px; }

/* Spinner */
.btn-spinner { width: 14px; height: 14px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.2); border-top-color: white; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Transitions ── */
.backdrop-enter-active, .backdrop-leave-active { transition: opacity 0.25s; }
.backdrop-enter-from, .backdrop-leave-to       { opacity: 0; }

.modal-slide-enter-active { transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
.modal-slide-leave-active { transition: all 0.2s ease; }
.modal-slide-enter-from   { opacity: 0; transform: scale(0.92) translateY(20px); }
.modal-slide-leave-to     { opacity: 0; transform: scale(0.95); }

.fade-enter-active, .fade-leave-active { transition: all 0.25s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; transform: translateY(-4px); }
</style>
