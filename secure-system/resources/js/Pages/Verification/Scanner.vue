<script setup>
import { ref, computed } from 'vue';
import { router, usePage }         from '@inertiajs/vue3';
import AuthenticatedLayout         from '@/Layouts/AuthenticatedLayout.vue';
import QRScanner                   from '@/Components/QRScanner.vue';
import ManualTokenEntry            from '@/Components/ManualTokenEntry.vue';
import BeneficiaryVerificationCard from '@/Components/BeneficiaryVerificationCard.vue';
import ComplianceDashboard         from '@/Components/ComplianceDashboard.vue';
import DuplicateAlert              from '@/Components/DuplicateAlert.vue';
import SimilarBeneficiaries        from '@/Components/SimilarBeneficiaries.vue';
import axios                       from 'axios';

const props = defineProps({
    canVerify: { type: Boolean, default: false },
    isAdmin:   { type: Boolean, default: false },
});

// ── State ────────────────────────────────────────────────────────────────────
const showScanner      = ref(false);
const useManual        = ref(false);
const scanResult       = ref(null);  // successful scan response
const activeTab        = ref('card'); // card | compliance
const scanning         = ref(false);
const verifyLoading    = ref(false);
const toast            = ref(null);
const scannerRef       = ref(null);
const errorBanner      = ref('');

const TABS = [
    { id: 'card',       label: 'Verification Card' },
    { id: 'compliance', label: 'Compliance Details' },
    { id: 'duplicate',  label: 'Duplicate Analysis' },
    { id: 'history',    label: 'Activity History'  },
];

// Exposed duplicate result (also set when scan is blocked)
const duplicateResult  = ref(null);
const scanBlocked      = ref(false);

// ── Actions ──────────────────────────────────────────────────────────────────
function openScanner() {
    scanResult.value  = null;
    errorBanner.value = '';
    showScanner.value = true;
    useManual.value   = false;
}

function closeScanner() {
    showScanner.value = false;
}

async function handleScanned(token) {
    if (scanning.value) return;
    scanning.value    = true;
    errorBanner.value = '';

    try {
        const res = await axios.post(route('verification.scan'), {
            verification_token: token,
        });

        // Even on success, store duplicate result for the 'Duplicate Analysis' tab
        duplicateResult.value = res.data.duplicate_result ?? null;
        scanBlocked.value     = false;
        scanResult.value      = res.data;
        showScanner.value     = false;
        useManual.value       = false;
        activeTab.value       = (duplicateResult.value?.is_duplicate) ? 'duplicate' : 'card';
        showToast('QR code scanned successfully.', 'success');
    } catch (err) {
        const data = err.response?.data ?? {};
        const msg  = data.message ?? 'Scan failed. Please try again.';

        // Blocked by high-confidence duplicate — surface the duplicate info
        if (data.blocked && data.duplicate_result) {
            duplicateResult.value = data.duplicate_result;
            scanBlocked.value     = true;
            showScanner.value     = false;
            useManual.value       = false;
            activeTab.value       = 'duplicate';
            showToast(msg, 'error');
        } else {
            errorBanner.value = msg;
            showToast(msg, 'error');
            scannerRef.value?.resetScanner();
        }
    } finally {
        scanning.value = false;
    }
}

async function handleApprove() {
    if (!scanResult.value?.beneficiary) return;
    verifyLoading.value = true;
    try {
        await axios.post(route('verification.verify', scanResult.value.beneficiary.id), {
            verification_notes: 'Approved for distribution via scanner.',
        });
        showToast('Beneficiary approved for distribution.', 'success');
    } catch (err) {
        showToast(err.response?.data?.message ?? 'Verification failed.', 'error');
    } finally {
        verifyLoading.value = false;
    }
}

function handleViewDetails() {
    if (!scanResult.value?.beneficiary) return;
    router.visit(route('beneficiaries.show', scanResult.value.beneficiary.id));
}

function handleReportIssue() {
    showToast('Issue reporting feature — contact your administrator.', 'info');
}

function resetScan() {
    scanResult.value     = null;
    duplicateResult.value= null;
    scanBlocked.value    = false;
    errorBanner.value    = '';
}

function showToast(message, type = 'success') {
    toast.value = { message, type };
    setTimeout(() => { toast.value = null; }, 4000);
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="page-hd">
                <div class="hd-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="page-title">Verification Scanner</h1>
                    <p class="page-sub">Scan beneficiary QR codes to verify eligibility</p>
                </div>
            </div>
        </template>

        <div class="scanner-page">

            <!-- Toast -->
            <Transition name="toast-fade">
                <div v-if="toast" :class="['toast', `toast-${toast.type}`]">
                    <svg v-if="toast.type === 'success'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                    <svg v-else-if="toast.type === 'error'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                    <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>
                    {{ toast.message }}
                </div>
            </Transition>

            <!-- Error banner -->
            <div v-if="errorBanner" class="error-banner">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                <span>{{ errorBanner }}</span>
                <button class="dismiss-btn" @click="errorBanner = ''">✕</button>
            </div>

            <!-- Main content grid -->
            <div class="content-grid">
                <!-- Left: Scanner controls -->
                <div class="scanner-col">
                    <!-- Idle state -->
                    <div v-if="!showScanner" class="scanner-idle">
                        <div class="idle-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                            </svg>
                        </div>
                        <h2 class="idle-title">Ready to Scan</h2>
                        <p class="idle-sub">Click the button below to open the camera and scan a beneficiary QR code.</p>
                        <div class="idle-actions">
                            <button class="btn-open-camera" id="btn-open-camera" @click="openScanner">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                                </svg>
                                Open Camera Scanner
                            </button>
                            <button class="btn-manual-toggle" id="btn-manual-toggle"
                                    @click="useManual = !useManual; showScanner = false;">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                                {{ useManual ? 'Hide' : 'Manual Token Entry' }}
                            </button>
                        </div>
                    </div>

                    <!-- Camera Scanner -->
                    <Transition name="slide-down">
                        <QRScanner
                            v-if="showScanner"
                            ref="scannerRef"
                            :active="showScanner"
                            @scanned="handleScanned"
                            @close="closeScanner"
                            @error="(e) => { useManual = true; showScanner = false; }"
                        />
                    </Transition>

                    <!-- Manual entry -->
                    <Transition name="slide-down">
                        <div v-if="useManual && !showScanner" class="manual-wrap">
                            <ManualTokenEntry @submit="handleScanned" />
                        </div>
                    </Transition>

                    <!-- Camera tip -->
                    <div class="camera-tip" v-if="!showScanner && !useManual">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>
                        <span>Camera access requires HTTPS. If camera is unavailable, use Manual Token Entry.</span>
                    </div>
                </div>

                <!-- Right: Result panel -->
                <div class="result-col">
                    <!-- No result placeholder -->
                    <div v-if="!scanResult" class="result-placeholder">
                        <div class="ph-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                        </div>
                        <p class="ph-title">No Beneficiary Scanned</p>
                        <p class="ph-sub">Scan a QR code to view beneficiary details and eligibility status here.</p>
                    </div>

                    <!-- Scan result OR blocked duplicate -->
                    <div v-else class="result-content">
                        <!-- Tab nav -->
                        <div class="res-tab-nav">
                            <button v-for="tab in TABS" :key="tab.id"
                                    :id="`scan-tab-${tab.id}`"
                                    :class="['res-tab', { 'tab-active': activeTab === tab.id }]"
                                    @click="activeTab = tab.id">
                                {{ tab.label }}
                                <span v-if="tab.id === 'duplicate' && duplicateResult?.is_duplicate"
                                      :class="['tab-dot', `dot-${duplicateResult.recommendation}`]">
                                </span>
                            </button>
                        </div>

                        <Transition name="tab-fade" mode="out-in">
                            <div :key="activeTab">
                                <!-- Verification Card -->
                                <BeneficiaryVerificationCard
                                    v-if="activeTab === 'card' && scanResult"
                                    :result="scanResult"
                                    :can-verify="canVerify"
                                    :loading="verifyLoading"
                                    @approve="handleApprove"
                                    @view-details="handleViewDetails"
                                    @report-issue="handleReportIssue"
                                    @close="resetScan"
                                />

                                <!-- Compliance Dashboard -->
                                <ComplianceDashboard
                                    v-else-if="activeTab === 'compliance' && scanResult?.compliance"
                                    :compliance="scanResult.compliance"
                                />

                                <!-- Duplicate Analysis -->
                                <div v-else-if="activeTab === 'duplicate'" class="dup-tab-wrap">
                                    <!-- Blocked banner -->
                                    <div v-if="scanBlocked" class="blocked-banner">
                                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                                        Distribution is blocked pending duplicate review.
                                    </div>

                                    <DuplicateAlert
                                        v-if="duplicateResult"
                                        :duplicate-result="duplicateResult"
                                        :beneficiary-id="scanResult?.beneficiary?.id ?? 0"
                                        :is-admin="isAdmin"
                                        @overridden="(d) => { showToast('Override approved — you may proceed.', 'success'); scanBlocked = false; activeTab = 'card'; }"
                                        @dismissed="() => { activeTab = 'card'; }"
                                        @review-later="() => showToast('Flagged for later review.', 'info')"
                                    />

                                    <SimilarBeneficiaries
                                        v-if="duplicateResult?.similar_beneficiaries?.length && scanResult?.beneficiary"
                                        class="sb-mt"
                                        :primary="scanResult.beneficiary"
                                        :similar="duplicateResult.similar_beneficiaries"
                                    />
                                </div>

                                <!-- History tab: link to full page -->
                                <div v-else-if="activeTab === 'history'" class="history-link-card">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="history-link-title">Full Verification History</p>
                                    <p class="history-link-sub">View all scans and verifications for this beneficiary.</p>
                                    <button class="btn-goto-history"
                                            @click="router.visit(route('verification.history', scanResult?.beneficiary?.id))"
                                            :disabled="!scanResult?.beneficiary?.id">
                                        Open Full History
                                    </button>
                                </div>
                            </div>
                        </Transition>

                        <!-- Scan another button -->
                        <div class="scan-another">
                            <button class="btn-scan-another" @click="resetScan(); openScanner();" id="btn-scan-another">
                                Scan Another Beneficiary
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
* { box-sizing: border-box; }

.scanner-page { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1.25rem; }

/* Header */
.page-hd  { display: flex; align-items: flex-start; gap: 1rem; }
.hd-icon  {
    width: 48px; height: 48px; border-radius: 0.875rem; flex-shrink: 0;
    background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(139,92,246,0.2));
    border: 1px solid rgba(99,102,241,0.3);
    display: flex; align-items: center; justify-content: center;
}
.hd-icon svg { width: 24px; height: 24px; color: #a5b4fc; }
.page-title { font-size: 1.375rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.25rem; }
.page-sub   { font-size: 0.8125rem; color: #64748b; margin: 0; }

/* Toast */
.toast {
    position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999;
    display: flex; align-items: center; gap: 0.625rem;
    padding: 0.75rem 1.125rem;
    border-radius: 0.875rem;
    font-size: 0.875rem; font-weight: 600;
    box-shadow: 0 8px 30px rgba(0,0,0,0.4);
    font-family: 'Inter', sans-serif;
}
.toast svg { width: 18px; height: 18px; flex-shrink: 0; }
.toast-success { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.3); color: #6ee7b7; }
.toast-error   { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #fca5a5; }
.toast-info    { background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.3); color: #a5b4fc; }
.toast-fade-enter-active { transition: all 0.3s ease; }
.toast-fade-leave-active { transition: all 0.25s ease; }
.toast-fade-enter-from   { opacity: 0; transform: translateX(20px); }
.toast-fade-leave-to     { opacity: 0; transform: translateX(20px); }

/* Error banner */
.error-banner {
    display: flex; align-items: center; gap: 0.625rem;
    background: rgba(239,68,68,0.08);
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 0.875rem;
    padding: 0.875rem 1rem;
    color: #fca5a5;
    font-size: 0.875rem;
}
.error-banner svg { width: 18px; height: 18px; flex-shrink: 0; }
.error-banner span { flex: 1; }
.dismiss-btn {
    background: none; border: none;
    color: #f87171; cursor: pointer;
    font-size: 0.875rem; font-weight: 700;
    padding: 0 0.25rem;
}

/* Content grid */
.content-grid {
    display: grid;
    grid-template-columns: 420px 1fr;
    gap: 1.25rem;
    align-items: start;
}
@media (max-width: 860px) {
    .content-grid { grid-template-columns: 1fr; }
}

/* Scanner column */
.scanner-col { display: flex; flex-direction: column; gap: 1rem; }

/* Idle */
.scanner-idle {
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1.25rem;
    padding: 2.5rem 1.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 1rem;
}
.idle-icon {
    width: 72px; height: 72px;
    background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.15));
    border: 1px solid rgba(99,102,241,0.25);
    border-radius: 1.25rem;
    display: flex; align-items: center; justify-content: center;
    color: #a5b4fc;
}
.idle-icon svg { width: 36px; height: 36px; }
.idle-title { font-size: 1.125rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.idle-sub   { font-size: 0.875rem; color: #64748b; margin: 0; line-height: 1.5; }
.idle-actions { display: flex; flex-direction: column; gap: 0.625rem; width: 100%; }

.btn-open-camera, .btn-manual-toggle {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 0.875rem;
    font-size: 0.9rem; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.2s; width: 100%;
}
.btn-open-camera svg { width: 18px; height: 18px; }
.btn-manual-toggle svg { width: 16px; height: 16px; }

.btn-open-camera {
    background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(139,92,246,0.2));
    border: 1px solid rgba(99,102,241,0.35);
    color: #c4b5fd;
    font-size: 0.9375rem; font-weight: 700;
}
.btn-open-camera:hover { background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(139,92,246,0.3)); }

.btn-manual-toggle {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    color: #94a3b8;
    font-size: 0.85rem;
}
.btn-manual-toggle:hover { background: rgba(255,255,255,0.06); color: #cbd5e1; }

.manual-wrap { display: contents; }

.camera-tip {
    display: flex; align-items: flex-start; gap: 0.5rem;
    padding: 0.75rem;
    background: rgba(99,102,241,0.05);
    border: 1px solid rgba(99,102,241,0.1);
    border-radius: 0.75rem;
    font-size: 0.75rem; color: #64748b;
    line-height: 1.4;
}
.camera-tip svg { width: 14px; height: 14px; flex-shrink: 0; color: #6366f1; margin-top: 1px; }

/* Result column */
.result-col {
    display: flex; flex-direction: column; gap: 1rem;
}

.result-placeholder {
    display: flex; flex-direction: column; align-items: center; text-align: center;
    gap: 0.75rem;
    background: rgba(255,255,255,0.01);
    border: 1px dashed rgba(255,255,255,0.08);
    border-radius: 1.25rem;
    padding: 3.5rem 2rem;
    min-height: 360px;
    justify-content: center;
}
.ph-icon {
    width: 80px; height: 80px;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1.5rem;
    display: flex; align-items: center; justify-content: center;
    color: #334155;
}
.ph-icon svg { width: 40px; height: 40px; }
.ph-title { font-size: 1.125rem; font-weight: 700; color: #334155; margin: 0; }
.ph-sub   { font-size: 0.875rem; color: #334155; margin: 0; max-width: 280px; line-height: 1.5; }

.result-content { display: flex; flex-direction: column; gap: 0.875rem; }

/* Result tabs */
.res-tab-nav {
    display: flex; gap: 0.25rem;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 0.875rem;
    padding: 0.25rem;
}
.res-tab {
    flex: 1;
    padding: 0.5rem 0.75rem;
    border-radius: 0.6875rem;
    font-size: 0.8125rem; font-weight: 600;
    color: #64748b;
    background: none; border: none;
    cursor: pointer; transition: all 0.2s;
    font-family: 'Inter', sans-serif;
    white-space: nowrap;
}
.res-tab:hover { color: #94a3b8; }
.tab-active { background: rgba(99,102,241,0.15) !important; color: #a5b4fc !important; }

/* history link */
.history-link-card {
    display: flex; flex-direction: column; align-items: center;
    gap: 0.75rem; padding: 2.5rem;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem;
    text-align: center;
}
.history-link-card svg { width: 36px; height: 36px; color: #334155; }
.history-link-title { font-size: 1rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.history-link-sub   { font-size: 0.8125rem; color: #64748b; margin: 0; }
.btn-goto-history {
    padding: 0.5625rem 1.125rem;
    background: rgba(99,102,241,0.12);
    border: 1px solid rgba(99,102,241,0.25);
    border-radius: 0.625rem;
    color: #a5b4fc; font-size: 0.875rem; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.2s;
}
.btn-goto-history:hover { background: rgba(99,102,241,0.22); }

/* Scan another */
.scan-another { display: flex; justify-content: center; }
.btn-scan-another {
    padding: 0.5625rem 1.375rem;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 0.75rem;
    color: #94a3b8; font-size: 0.875rem; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.2s;
}
.btn-scan-another:hover { background: rgba(255,255,255,0.07); color: #e2e8f0; }

/* Transitions */
.slide-down-enter-active { transition: all 0.3s ease; }
.slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from   { opacity: 0; transform: translateY(-12px); }
.slide-down-leave-to     { opacity: 0; transform: translateY(-8px); }
.tab-fade-enter-active { transition: all 0.2s ease; }
.tab-fade-leave-active { transition: all 0.15s ease; }
.tab-fade-enter-from   { opacity: 0; transform: translateY(6px); }
.tab-fade-leave-to     { opacity: 0; }

/* Tab notification dot */
.tab-dot {
    display: inline-block;
    width: 7px; height: 7px;
    border-radius: 50%;
    margin-left: 4px;
    vertical-align: middle;
}
.dot-block { background: #ef4444; box-shadow: 0 0 4px #ef4444; }
.dot-flag  { background: #f59e0b; box-shadow: 0 0 4px #f59e0b; }
.dot-allow { background: #10b981; }

/* Duplicate tab content */
.dup-tab-wrap { display: flex; flex-direction: column; gap: 1rem; }
.blocked-banner {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.75rem 1rem;
    background: rgba(239,68,68,0.1);
    border: 1px solid rgba(239,68,68,0.25);
    border-radius: 0.75rem;
    color: #fca5a5; font-size: 0.875rem; font-weight: 700;
}
.blocked-banner svg { width: 18px; height: 18px; flex-shrink: 0; }
.sb-mt { margin-top: 0.25rem; }
</style>
