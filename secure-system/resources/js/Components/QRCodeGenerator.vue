<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    beneficiaryId:  { type: [Number, String], required: true },
    beneficiaryBin: { type: String, default: '' },
    canRegenerate:  { type: Boolean, default: false },
    initialQrCode:  { type: Object, default: null },
});

const emit = defineEmits(['qr-generated', 'qr-updated']);

// ── State ──────────────────────────────────────────────────────────────────
const qrCode      = ref(props.initialQrCode);
const loading     = ref(false);
const error       = ref(null);
const success     = ref(null);
const showRegen   = ref(false);

// ── Computed ───────────────────────────────────────────────────────────────
const hasActiveQr = computed(() => qrCode.value && qrCode.value.is_valid && !qrCode.value.is_expired);

const validityStatus = computed(() => {
    if (!qrCode.value) return null;
    if (!qrCode.value.is_valid)  return { label: 'Revoked',  cls: 'badge-revoked' };
    if (qrCode.value.is_expired) return { label: 'Expired',  cls: 'badge-expired' };
    return { label: 'Valid', cls: 'badge-valid' };
});

// ── Methods ────────────────────────────────────────────────────────────────
const clearMessages = () => { error.value = null; success.value = null; };

async function generateQr() {
    clearMessages();
    loading.value = true;
    try {
        const res = await fetch(route('qr.generate', props.beneficiaryId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
        });
        const data = await res.json();
        if (data.success) {
            qrCode.value  = data.qr_code;
            success.value = 'QR code generated successfully!';
            emit('qr-generated', data.qr_code);
        } else {
            if (data.qr_code) qrCode.value = data.qr_code; // existing one returned
            error.value = data.message;
        }
    } catch (e) {
        error.value = 'Network error: ' + e.message;
    } finally {
        loading.value = false;
    }
}

function downloadQr() {
    if (!qrCode.value?.qr_image_url) return;
    const a   = document.createElement('a');
    a.href    = qrCode.value.qr_image_url;
    a.download = `qr_${props.beneficiaryBin || props.beneficiaryId}.png`;
    a.click();
}

function printQr() {
    if (!qrCode.value?.qr_image_url) return;
    const w = window.open('', '_blank', 'width=400,height=400');
    w.document.write(`
        <!DOCTYPE html><html><head><title>QR Code — ${props.beneficiaryBin}</title>
        <style>
            body { margin: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; background:#fff; }
            img  { width: 280px; height: 280px; }
            @media print { body { margin: 0; } }
        </style></head>
        <body><img src="${qrCode.value.qr_image_url}" onload="window.print(); window.close();" /></body></html>
    `);
    w.document.close();
}

async function downloadCard() {
    clearMessages();
    loading.value = true;
    try {
        const a   = document.createElement('a');
        a.href    = route('qr.card', props.beneficiaryId);
        a.target  = '_blank';
        a.click();
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="qr-generator">
        <!-- Header -->
        <div class="section-header">
            <div class="section-title-row">
                <svg class="icon-qr" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z"/>
                </svg>
                <h3 class="section-title">QR Code</h3>
                <span v-if="validityStatus" :class="['validity-badge', validityStatus.cls]">
                    {{ validityStatus.label }}
                </span>
            </div>
            <p class="section-sub">Generate and manage the beneficiary's QR identification code.</p>
        </div>

        <!-- Alert messages -->
        <Transition name="fade">
            <div v-if="error" class="alert alert-error">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                <span>{{ error }}</span>
                <button class="alert-close" @click="error = null">✕</button>
            </div>
        </Transition>
        <Transition name="fade">
            <div v-if="success" class="alert alert-success">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                <span>{{ success }}</span>
                <button class="alert-close" @click="success = null">✕</button>
            </div>
        </Transition>

        <!-- QR Display / Generate Panel -->
        <div class="qr-panel">
            <!-- No QR Yet -->
            <div v-if="!qrCode" class="qr-empty">
                <div class="qr-placeholder">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/>
                    </svg>
                </div>
                <p class="qr-empty-label">No QR code generated yet</p>
                <p class="qr-empty-sub">Generate a QR code to enable field verification for this beneficiary.</p>
            </div>

            <!-- QR Image -->
            <div v-else class="qr-display">
                <div class="qr-image-wrap">
                    <img :src="qrCode.qr_image_url" alt="Beneficiary QR Code" class="qr-image" />
                    <div v-if="qrCode.is_expired || !qrCode.is_valid" class="qr-overlay">
                        <span>{{ !qrCode.is_valid ? 'REVOKED' : 'EXPIRED' }}</span>
                    </div>
                </div>
                <div class="qr-meta">
                    <div class="qr-meta-row">
                        <span class="meta-label">Generated</span>
                        <span class="meta-value">{{ qrCode.generated_at_human }}</span>
                    </div>
                    <div class="qr-meta-row">
                        <span class="meta-label">Expires</span>
                        <span class="meta-value">{{ qrCode.expires_at_human ?? '—' }}</span>
                    </div>
                    <div class="qr-meta-row">
                        <span class="meta-label">Status</span>
                        <span :class="['badge-sm', validityStatus?.cls]">{{ validityStatus?.label }}</span>
                    </div>
                </div>
            </div>

            <!-- Loading overlay -->
            <Transition name="fade">
                <div v-if="loading" class="qr-loading">
                    <div class="spinner"></div>
                    <span>Processing…</span>
                </div>
            </Transition>
        </div>

        <!-- Actions -->
        <div class="qr-actions">
            <button
                v-if="!qrCode"
                id="btn-generate-qr"
                class="btn-primary"
                :disabled="loading"
                @click="generateQr"
            >
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v2.5h-2.5a.75.75 0 000 1.5h2.5v2.5a.75.75 0 001.5 0v-2.5h2.5a.75.75 0 000-1.5h-2.5v-2.5z" clip-rule="evenodd"/></svg>
                Generate QR Code
            </button>

            <template v-if="qrCode">
                <button
                    id="btn-download-qr"
                    class="btn-secondary"
                    :disabled="loading"
                    @click="downloadQr"
                >
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>
                    Download PNG
                </button>

                <button
                    id="btn-print-qr"
                    class="btn-secondary"
                    :disabled="loading"
                    @click="printQr"
                >
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2.75C5 1.784 5.784 1 6.75 1h6.5c.966 0 1.75.784 1.75 1.75v3.552c.377.046.752.097 1.126.153A2.212 2.212 0 0118 8.653v4.097A2.25 2.25 0 0115.75 15h-.241l.305 1.984A1.75 1.75 0 0114.084 19H5.915a1.75 1.75 0 01-1.73-2.016L4.492 15H4.25A2.25 2.25 0 012 12.75V8.653c0-1.082.775-2.034 1.874-2.198.374-.056.75-.107 1.126-.153V2.75zm4.5 14.5H9l-.25-1.5h2.5L11 17.25h-.5zm1.5 0h-.5l.25-1.5h-.5l.25 1.5zm-3.25-1.5L7.5 17.25H6.75L7 15.75H5.915a.25.25 0 00-.247.288l.419 2.716A.25.25 0 006.334 19h7.332a.25.25 0 00.247-.246l.419-2.716a.25.25 0 00-.247-.288H12.5l.25 1.5H11l.25 1.5H9.75L10 15.75H7.75zm1-1.5h4.5V2.75A.25.25 0 0013 2.5H7a.25.25 0 00-.25.25v10.5h2.5-.5z" clip-rule="evenodd"/></svg>
                    Print QR
                </button>

                <button
                    id="btn-download-card"
                    class="btn-accent"
                    :disabled="loading"
                    @click="downloadCard"
                >
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.5 3A1.5 1.5 0 001 4.5v4A1.5 1.5 0 002.5 10h6A1.5 1.5 0 0010 8.5v-4A1.5 1.5 0 008.5 3h-6zm11 2A1.5 1.5 0 0012 6.5v7a1.5 1.5 0 001.5 1.5h4a1.5 1.5 0 001.5-1.5v-7A1.5 1.5 0 0017.5 5h-4zm-10 7A1.5 1.5 0 002 13.5v2A1.5 1.5 0 003.5 17h6a1.5 1.5 0 001.5-1.5v-2A1.5 1.5 0 009.5 12h-6z" clip-rule="evenodd"/></svg>
                    Download ID Card
                </button>

                <button
                    v-if="canRegenerate"
                    id="btn-regen-qr"
                    class="btn-warn"
                    :disabled="loading"
                    @click="showRegen = true"
                >
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd"/></svg>
                    Regenerate
                </button>
            </template>
        </div>

        <!-- Regenerate modal handled by parent via QRCodeRegenerateModal.vue -->
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.qr-generator {
    font-family: 'Inter', sans-serif;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* ── Header ── */
.section-header  { display: flex; flex-direction: column; gap: 0.25rem; }
.section-title-row { display: flex; align-items: center; gap: 0.625rem; }
.icon-qr         { width: 20px; height: 20px; color: #818cf8; flex-shrink: 0; }
.section-title   { font-size: 1rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.section-sub     { font-size: 0.8125rem; color: #64748b; margin: 0 0 0 1.625rem; }

/* ── Validity badges ── */
.validity-badge  { display: inline-block; padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; }
.badge-valid     { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
.badge-expired   { background: rgba(245,158,11,0.15); color: #fcd34d; border: 1px solid rgba(245,158,11,0.3); }
.badge-revoked   { background: rgba(239,68,68,0.12); color: #fca5a5; border: 1px solid rgba(239,68,68,0.25); }

/* ── Alerts ── */
.alert {
    display: flex; align-items: flex-start; gap: 0.625rem;
    padding: 0.875rem 1rem; border-radius: 0.75rem; font-size: 0.875rem;
}
.alert svg { width: 16px; height: 16px; flex-shrink: 0; margin-top: 1px; }
.alert-close { margin-left: auto; background: none; border: none; cursor: pointer; font-size: 0.75rem; opacity: 0.6; color: inherit; }
.alert-error   { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5; }
.alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.25); color: #6ee7b7; }

/* ── Panel ── */
.qr-panel {
    position: relative;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1rem;
    min-height: 220px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Empty state */
.qr-empty {
    display: flex; flex-direction: column; align-items: center;
    gap: 0.625rem; padding: 2rem; text-align: center;
}
.qr-placeholder {
    width: 120px; height: 120px; border-radius: 1rem;
    background: rgba(99,102,241,0.06); border: 2px dashed rgba(99,102,241,0.2);
    display: flex; align-items: center; justify-content: center;
}
.qr-placeholder svg { width: 60px; height: 60px; color: rgba(99,102,241,0.3); }
.qr-empty-label { font-size: 0.9375rem; font-weight: 600; color: #64748b; margin: 0; }
.qr-empty-sub   { font-size: 0.8125rem; color: #475569; margin: 0; max-width: 280px; }

/* QR display */
.qr-display {
    display: flex; gap: 1.5rem; align-items: center; padding: 1.5rem;
    flex-wrap: wrap; justify-content: center;
}
.qr-image-wrap {
    position: relative; flex-shrink: 0;
    border-radius: 0.75rem; overflow: hidden;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}
.qr-image {
    display: block; width: 160px; height: 160px;
    border-radius: 0.75rem;
    background: white; padding: 8px;
}
.qr-overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,0.65);
    display: flex; align-items: center; justify-content: center;
    border-radius: 0.75rem;
}
.qr-overlay span {
    font-size: 1.125rem; font-weight: 900; color: #fca5a5;
    letter-spacing: 0.15em;
}
.qr-meta        { display: flex; flex-direction: column; gap: 0.75rem; min-width: 160px; }
.qr-meta-row    { display: flex; flex-direction: column; gap: 0.125rem; }
.meta-label     { font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
.meta-value     { font-size: 0.875rem; color: #e2e8f0; font-weight: 500; }
.badge-sm       { display: inline-block; padding: 0.175rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; width: fit-content; }

/* ── Loading ── */
.qr-loading {
    position: absolute; inset: 0;
    background: rgba(15,23,42,0.75);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 0.75rem; border-radius: 1rem;
    backdrop-filter: blur(4px);
    font-size: 0.875rem; color: #94a3b8;
}
.spinner {
    width: 36px; height: 36px; border-radius: 50%;
    border: 3px solid rgba(99,102,241,0.2);
    border-top-color: #818cf8;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Actions ── */
.qr-actions { display: flex; flex-wrap: wrap; gap: 0.625rem; }

.btn-primary, .btn-secondary, .btn-accent, .btn-warn {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1.125rem; border-radius: 0.75rem;
    font-size: 0.8125rem; font-weight: 600; cursor: pointer;
    border: none; font-family: 'Inter', sans-serif;
    transition: all 0.2s; white-space: nowrap;
}
.btn-primary svg, .btn-secondary svg, .btn-accent svg, .btn-warn svg { width: 16px; height: 16px; flex-shrink: 0; }

.btn-primary   { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
.btn-primary:hover:not(:disabled)   { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }

.btn-secondary { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: #cbd5e1; }
.btn-secondary:hover:not(:disabled) { background: rgba(255,255,255,0.1); color: #f1f5f9; }

.btn-accent    { background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); color: #6ee7b7; }
.btn-accent:hover:not(:disabled)    { background: rgba(16,185,129,0.2); }

.btn-warn      { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.25); color: #fcd34d; }
.btn-warn:hover:not(:disabled)      { background: rgba(245,158,11,0.18); }

button:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }

/* ── Transitions ── */
.fade-enter-active, .fade-leave-active { transition: opacity 0.3s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
</style>
