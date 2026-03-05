<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    beneficiary: { type: Object, required: true },
    qrCode:      { type: Object, default: null },
});

const loading      = ref(false);
const error        = ref(null);
const downloadDone = ref(false);

const hasQr = computed(() => !!props.qrCode?.qr_image_url);

const issueDate  = computed(() => {
    if (!props.qrCode?.generated_at) return '—';
    return new Date(props.qrCode.generated_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
});

const expiryDate = computed(() => {
    if (!props.qrCode?.expires_at) return '—';
    return new Date(props.qrCode.expires_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
});

const cardNumber = computed(() => {
    if (!props.beneficiary?.id) return '—';
    return 'CRD-' + String(props.beneficiary.id).padStart(6, '0');
});

async function downloadCard() {
    loading.value = true;
    error.value   = null;
    try {
        const link   = document.createElement('a');
        link.href    = route('qr.card', props.beneficiary.id);
        link.target  = '_blank';
        link.click();
        downloadDone.value = true;
        setTimeout(() => { downloadDone.value = false; }, 3000);
    } catch (e) {
        error.value = 'Download failed: ' + e.message;
    } finally {
        loading.value = false;
    }
}

function printCard() {
    const printArea = document.getElementById('card-preview-area');
    if (!printArea) return;
    const w = window.open('', '_blank', 'width=400,height=300');
    w.document.write(`
        <!DOCTYPE html><html><head><title>ID Card — ${props.beneficiary.bin}</title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f8fafc; }
            @media print { body { background: white; } }
        </style></head>
        <body>${printArea.outerHTML}</body></html>
    `);
    w.document.close();
    setTimeout(() => { w.print(); w.close(); }, 500);
}
</script>

<template>
    <div class="card-preview-container">
        <!-- Header -->
        <div class="section-header">
            <div class="section-title-row">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>
                </svg>
                <h3 class="section-title">ID Card Preview</h3>
                <span class="dim-badge">85.6 × 53.98 mm</span>
            </div>
            <p class="section-sub">Credit-card sized beneficiary identification card with QR code.</p>
        </div>

        <!-- Error alert -->
        <div v-if="error" class="alert-error">{{ error }}</div>

        <!-- No QR Code warning -->
        <div v-if="!hasQr" class="no-qr-warn">
            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
            <p>Generate a QR code first to enable ID card download.</p>
        </div>

        <!-- Card Preview -->
        <div class="card-scale-wrapper">
            <div id="card-preview-area" class="id-card">
                <!-- Watermark -->
                <div class="watermark">SECURE</div>

                <!-- Header bar -->
                <div class="card-header-bar">
                    <div class="card-logo">
                        <span class="logo-abbr">DSWD</span>
                    </div>
                    <div class="card-header-text">
                        <div class="card-program">PANTAWID PAMILYA PILIPINO PROGRAM</div>
                        <div class="card-system">National Household Targeting System — SECURE</div>
                    </div>
                    <div class="valid-pill">VALID</div>
                </div>

                <!-- Card body -->
                <div class="card-body-row">
                    <!-- QR Image -->
                    <div class="card-qr-col">
                        <img
                            v-if="hasQr"
                            :src="qrCode.qr_image_url"
                            alt="QR Code"
                            class="card-qr-img"
                        />
                        <div v-else class="card-qr-placeholder">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1V4zm2 2V5h1v1H5zM3 13a1 1 0 011-1h3a1 1 0 011 1v3a1 1 0 01-1 1H4a1 1 0 01-1-1v-3zm2 2v-1h1v1H5zM13 3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V4a1 1 0 00-1-1h-3zm1 2v1h1V5h-1z" clip-rule="evenodd"/></svg>
                        </div>
                        <span class="scan-label">SCAN TO VERIFY</span>
                    </div>

                    <!-- Info -->
                    <div class="card-info-col">
                        <div class="card-field-group">
                            <div class="card-field">
                                <span class="card-label">Beneficiary ID No.</span>
                                <span class="card-value bin-value">{{ beneficiary.bin }}</span>
                            </div>
                            <div class="card-field">
                                <span class="card-label">Name of Family Head</span>
                                <span class="card-value name-value">{{ beneficiary.family_head_name }}</span>
                            </div>
                            <div class="card-field">
                                <span class="card-label">Location</span>
                                <span class="card-value loc-value">
                                    {{ beneficiary.barangay }}, {{ beneficiary.municipality }}, {{ beneficiary.province }}
                                </span>
                            </div>
                        </div>
                        <div class="card-field-group">
                            <div class="card-dates-row">
                                <div class="card-field">
                                    <span class="card-label">Issued</span>
                                    <span class="card-value sm-value">{{ issueDate }}</span>
                                </div>
                                <div class="card-field">
                                    <span class="card-label">Valid Until</span>
                                    <span class="card-value sm-value hl-value">{{ expiryDate }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="card-footer-bar">
                    <span class="footer-issuer">Department of Social Welfare and Development</span>
                    <span class="footer-card-id">{{ cardNumber }}</span>
                </div>
            </div>
        </div>

        <!-- Print guidelines -->
        <div class="print-guide">
            <svg viewBox="0 0 16 16" fill="currentColor" class="guide-icon"><path d="M5 8.5a.5.5 0 01.5-.5h5a.5.5 0 010 1h-5a.5.5 0 01-.5-.5z"/><path d="M3 0h10a2 2 0 012 2v12a2 2 0 01-2 2H3a2 2 0 01-2-2V2a2 2 0 012-2zm0 1a1 1 0 00-1 1v12a1 1 0 001 1h10a1 1 0 001-1V2a1 1 0 00-1-1H3z"/></svg>
            <span>Print at 300 DPI on CR80 card stock (85.6 × 53.98 mm) for best results.</span>
        </div>

        <!-- Actions -->
        <div class="card-actions">
            <button
                id="btn-download-id-card"
                class="btn-primary"
                :disabled="!hasQr || loading"
                @click="downloadCard"
            >
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>
                <span>{{ loading ? 'Generating…' : downloadDone ? '✓ Downloaded!' : 'Download PDF Card' }}</span>
            </button>

            <button
                id="btn-print-id-card"
                class="btn-secondary"
                :disabled="!hasQr"
                @click="printCard"
            >
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a1 1 0 001 1h8a1 1 0 001-1v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a1 1 0 00-1-1H6a1 1 0 00-1 1zm2 0h6v3H7V4zm-1 9a1 1 0 011-1h6a1 1 0 011 1v3H6v-3z" clip-rule="evenodd"/></svg>
                Print Card
            </button>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.card-preview-container { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1.25rem; }

.section-header   { display: flex; flex-direction: column; gap: 0.25rem; }
.section-title-row{ display: flex; align-items: center; gap: 0.625rem; }
.icon             { width: 20px; height: 20px; color: #34d399; flex-shrink: 0; }
.section-title    { font-size: 1rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.section-sub      { font-size: 0.8125rem; color: #64748b; margin: 0 0 0 1.625rem; }
.dim-badge        { font-size: 0.7rem; color: #64748b; background: rgba(255,255,255,0.05); padding: 0.2rem 0.5rem; border-radius: 9999px; border: 1px solid rgba(255,255,255,0.1); }

.alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5; padding: 0.75rem 1rem; border-radius: 0.75rem; font-size: 0.875rem; }

.no-qr-warn {
    display: flex; align-items: center; gap: 0.625rem;
    background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.2);
    border-radius: 0.75rem; padding: 0.875rem 1rem;
    color: #fcd34d; font-size: 0.8125rem;
}
.no-qr-warn svg { width: 16px; height: 16px; flex-shrink: 0; }

/* ── Card Scale Wrapper ── */
.card-scale-wrapper {
    display: flex; justify-content: center;
    padding: 1.5rem;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem;
}

.id-card {
    width: 340px; height: 215px;
    border: 2px solid #1e3a5f;
    border-radius: 12px; overflow: hidden;
    display: flex; flex-direction: column;
    background: #ffffff; position: relative;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
}

.watermark {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%) rotate(-30deg);
    font-size: 60px; color: rgba(30,58,95,0.04);
    font-weight: 900; white-space: nowrap;
    z-index: 0; letter-spacing: 3px; pointer-events: none;
}

.card-header-bar {
    background: linear-gradient(135deg, #1e3a5f 0%, #2d6a8f 100%);
    padding: 8px 12px; display: flex; align-items: center; gap: 8px; z-index: 1;
}
.card-logo {
    width: 32px; height: 32px; background: white; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.logo-abbr { font-size: 8px; font-weight: 900; color: #1e3a5f; }
.card-header-text { flex: 1; }
.card-program { font-size: 8px; font-weight: 900; color: white; letter-spacing: 0.5px; text-transform: uppercase; }
.card-system  { font-size: 6px; color: rgba(255,255,255,0.7); letter-spacing: 0.3px; }
.valid-pill   { background: #dcfce7; color: #16a34a; padding: 2px 6px; border-radius: 3px; font-size: 6px; font-weight: 800; text-transform: uppercase; }

.card-body-row { flex: 1; display: flex; padding: 10px; gap: 10px; z-index: 1; }

.card-qr-col { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; gap: 3px; }
.card-qr-img { width: 90px; height: 90px; border: 1px solid #e2e8f0; border-radius: 4px; padding: 3px; }
.card-qr-placeholder {
    width: 90px; height: 90px; border: 1px dashed rgba(99,102,241,0.3); border-radius: 4px;
    display: flex; align-items: center; justify-content: center; background: rgba(99,102,241,0.04);
}
.card-qr-placeholder svg { width: 40px; height: 40px; color: rgba(99,102,241,0.3); }
.scan-label { font-size: 6px; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; }

.card-info-col { flex: 1; display: flex; flex-direction: column; justify-content: space-between; }
.card-field-group { display: flex; flex-direction: column; gap: 6px; }
.card-field { display: flex; flex-direction: column; gap: 1px; }
.card-label { font-size: 6px; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; font-weight: 700; }
.card-value { font-size: 9px; color: #1e293b; font-weight: 700; line-height: 1.2; }
.bin-value  { font-size: 11px; color: #1e3a5f; letter-spacing: 0.5px; font-family: 'Courier New', monospace; }
.name-value { font-size: 8px; font-weight: 800; text-transform: uppercase; }
.loc-value  { font-size: 7px; font-weight: 500; }
.sm-value   { font-size: 7.5px; }
.hl-value   { color: #1e3a5f; }
.card-dates-row { display: flex; gap: 12px; }

.card-footer-bar {
    background: #f1f5f9; border-top: 1px solid #e2e8f0;
    padding: 4px 12px; display: flex; justify-content: space-between; align-items: center; z-index: 1;
}
.footer-issuer  { font-size: 6px; color: #64748b; font-weight: 600; }
.footer-card-id { font-size: 6px; color: #1e3a5f; font-family: 'Courier New', monospace; font-weight: 700; }

.print-guide {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.75rem; color: #475569;
    padding: 0.625rem 0.875rem;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 0.75rem;
}
.guide-icon { width: 14px; height: 14px; flex-shrink: 0; }

.card-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }

.btn-primary, .btn-secondary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1.125rem; border-radius: 0.75rem;
    font-size: 0.8125rem; font-weight: 600; cursor: pointer;
    border: none; font-family: 'Inter', sans-serif; transition: all 0.2s;
}
.btn-primary svg, .btn-secondary svg { width: 16px; height: 16px; }

.btn-primary   { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; box-shadow: 0 4px 12px rgba(99,102,241,0.3); }
.btn-primary:hover:not(:disabled)   { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }
.btn-secondary { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); color: #cbd5e1; }
.btn-secondary:hover:not(:disabled) { background: rgba(255,255,255,0.1); color: #f1f5f9; }
button:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }
</style>
