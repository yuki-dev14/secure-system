<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';
import QRCodeGenerator       from '@/Components/QRCodeGenerator.vue';
import QRCodeCard            from '@/Components/QRCodeCard.vue';
import QRCodeHistory         from '@/Components/QRCodeHistory.vue';
import QRCodeRegenerateModal from '@/Components/QRCodeRegenerateModal.vue';
import { usePermissions }    from '@/Composables/usePermissions';

const props = defineProps({
    beneficiary: { type: Object, required: true },
    activeQrCode: { type: Object, default: null },
    canGenerate:  { type: Boolean, default: false },
    canRegenerate:{ type: Boolean, default: false },
});

const { isAdmin } = usePermissions();
const page = usePage();

// ── State ──────────────────────────────────────────────────────────────────
const activeTab      = ref('generator');
const currentQrCode  = ref(props.activeQrCode);
const showRegenModal = ref(false);

const TABS = [
    { id: 'generator', label: 'QR Code',    icon: 'M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z' },
    { id: 'card',      label: 'ID Card',    icon: 'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z' },
    { id: 'history',   label: 'History',    icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' },
];

// ── Computed ───────────────────────────────────────────────────────────────
const b = computed(() => props.beneficiary);

const validityBadge = computed(() => {
    if (!currentQrCode.value)                            return { label: 'Not Generated', cls: 'badge-none' };
    if (!currentQrCode.value.is_valid)                   return { label: 'Revoked',       cls: 'badge-revoked' };
    if (currentQrCode.value.is_expired)                  return { label: 'Expired',       cls: 'badge-expired' };
    return { label: 'Valid & Active', cls: 'badge-valid' };
});

// ── Handlers ───────────────────────────────────────────────────────────────
function onQrGenerated(qrCode) {
    currentQrCode.value = qrCode;
}
function onQrRegenerated(qrCode) {
    currentQrCode.value  = qrCode;
    showRegenModal.value = false;
}
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="page-hd">
                <div class="hd-left">
                    <Link :href="route('beneficiaries.show', b.id)" class="back-link">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ b.family_head_name }}
                    </Link>
                    <div class="hd-title-row">
                        <div class="hd-avatar">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="page-title">QR Code Management</h1>
                            <div class="hd-meta">
                                <span class="bin-code">{{ b.bin }}</span>
                                <span :class="['qr-status-badge', validityBadge.cls]">
                                    {{ validityBadge.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hd-actions" v-if="canRegenerate && currentQrCode">
                    <button id="btn-open-regen" class="btn-regen" @click="showRegenModal = true">
                        <svg viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd"/>
                        </svg>
                        Regenerate QR Code
                    </button>
                </div>
            </div>
        </template>

        <div class="qr-wrap">
            <!-- Beneficiary summary strip -->
            <div class="summary-strip">
                <div class="strip-item">
                    <span class="strip-lbl">Beneficiary Name</span>
                    <span class="strip-val">{{ b.family_head_name }}</span>
                </div>
                <div class="strip-item">
                    <span class="strip-lbl">BIN</span>
                    <span class="strip-val mono accent">{{ b.bin }}</span>
                </div>
                <div class="strip-item">
                    <span class="strip-lbl">Status</span>
                    <span :class="['strip-val', b.is_active ? 'clr-green' : 'clr-red']">
                        {{ b.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="strip-item">
                    <span class="strip-lbl">QR Status</span>
                    <span :class="['strip-val', validityBadge.cls === 'badge-valid' ? 'clr-green' : 'clr-amber']">
                        {{ validityBadge.label }}
                    </span>
                </div>
                <div v-if="currentQrCode?.expires_at_human" class="strip-item">
                    <span class="strip-lbl">Expires</span>
                    <span class="strip-val">{{ currentQrCode.expires_at_human }}</span>
                </div>
            </div>

            <!-- Inactive warning -->
            <div v-if="!b.is_active" class="inactive-warn">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                <p>This beneficiary is <strong>inactive</strong>. QR code generation is disabled for inactive beneficiaries.</p>
            </div>

            <!-- Tab nav -->
            <div class="tab-nav">
                <button
                    v-for="tab in TABS"
                    :key="tab.id"
                    :id="`qr-tab-${tab.id}`"
                    :class="['tab-btn', { 'tab-active': activeTab === tab.id }]"
                    @click="activeTab = tab.id"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon"/>
                    </svg>
                    {{ tab.label }}
                </button>
            </div>

            <!-- Tab content panel -->
            <div class="tab-panel">
                <Transition name="tab-fade" mode="out-in">
                    <div :key="activeTab">
                        <!-- QR Generator tab -->
                        <QRCodeGenerator
                            v-if="activeTab === 'generator'"
                            :beneficiary-id="b.id"
                            :beneficiary-bin="b.bin"
                            :can-regenerate="canRegenerate"
                            :initial-qr-code="currentQrCode"
                            @qr-generated="onQrGenerated"
                            @qr-updated="onQrGenerated"
                        />

                        <!-- ID Card tab -->
                        <QRCodeCard
                            v-else-if="activeTab === 'card'"
                            :beneficiary="b"
                            :qr-code="currentQrCode"
                        />

                        <!-- History tab -->
                        <QRCodeHistory
                            v-else-if="activeTab === 'history'"
                            :beneficiary-id="b.id"
                        />
                    </div>
                </Transition>
            </div>
        </div>

        <!-- Regenerate Modal -->
        <QRCodeRegenerateModal
            v-model="showRegenModal"
            :beneficiary-id="b.id"
            :beneficiary-name="b.family_head_name"
            :beneficiary-bin="b.bin"
            @regenerated="onQrRegenerated"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
* { box-sizing: border-box; }

.qr-wrap { display: flex; flex-direction: column; gap: 1.5rem; font-family: 'Inter', sans-serif; }

/* ── Page Header ── */
.page-hd    { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.hd-left    { display: flex; flex-direction: column; gap: 0.75rem; }
.back-link  { display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; color: #64748b; text-decoration: none; transition: color 0.2s; }
.back-link svg { width: 16px; height: 16px; }
.back-link:hover { color: #a5b4fc; }
.hd-title-row { display: flex; align-items: center; gap: 1rem; }
.hd-avatar {
    width: 48px; height: 48px; border-radius: 0.875rem; flex-shrink: 0;
    background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(139,92,246,0.2));
    border: 1px solid rgba(99,102,241,0.3);
    display: flex; align-items: center; justify-content: center;
}
.hd-avatar svg { width: 24px; height: 24px; color: #a5b4fc; }
.page-title { font-size: 1.375rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.375rem; }
.hd-meta    { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.bin-code   { font-family: monospace; font-size: 0.875rem; font-weight: 700; color: #a5b4fc; }
.qr-status-badge { display: inline-block; padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; }
.badge-valid   { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
.badge-expired { background: rgba(245,158,11,0.12); color: #fcd34d; border: 1px solid rgba(245,158,11,0.25); }
.badge-revoked { background: rgba(239,68,68,0.1);  color: #fca5a5; border: 1px solid rgba(239,68,68,0.25); }
.badge-none    { background: rgba(100,116,139,0.1); color: #94a3b8; border: 1px solid rgba(100,116,139,0.2); }
.hd-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.btn-regen {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1.125rem; border-radius: 0.75rem;
    background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.25);
    color: #fcd34d; font-size: 0.875rem; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s;
}
.btn-regen svg  { width: 16px; height: 16px; }
.btn-regen:hover { background: rgba(245,158,11,0.18); border-color: rgba(245,158,11,0.4); }

/* ── Summary strip ── */
.summary-strip {
    display: flex; gap: 0; flex-wrap: wrap;
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; overflow: hidden;
}
.strip-item {
    flex: 1; min-width: 140px; padding: 1.125rem 1.375rem;
    display: flex; flex-direction: column; gap: 0.25rem;
    border-right: 1px solid rgba(255,255,255,0.06);
}
.strip-item:last-child { border-right: none; }
.strip-lbl  { font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.strip-val  { font-size: 0.9375rem; font-weight: 700; color: #f1f5f9; }
.mono       { font-family: monospace; }
.accent     { color: #a5b4fc; }
.clr-green  { color: #6ee7b7; }
.clr-red    { color: #fca5a5; }
.clr-amber  { color: #fcd34d; }

/* ── Inactive warning ── */
.inactive-warn {
    display: flex; align-items: flex-start; gap: 0.75rem;
    background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.2);
    border-radius: 0.875rem; padding: 0.875rem 1rem;
    color: #fca5a5; font-size: 0.875rem;
}
.inactive-warn svg { width: 18px; height: 18px; flex-shrink: 0; color: #f87171; margin-top: 1px; }
.inactive-warn p   { margin: 0; line-height: 1.5; }
.inactive-warn strong { font-weight: 700; }

/* ── Tab nav ── */
.tab-nav {
    display: flex; gap: 0.25rem; overflow-x: auto; padding-bottom: 0.25rem;
    scrollbar-width: none;
}
.tab-nav::-webkit-scrollbar { display: none; }
.tab-btn {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1rem; border-radius: 0.75rem;
    font-size: 0.8125rem; font-weight: 600; color: #64748b;
    background: none; border: 1px solid transparent;
    cursor: pointer; white-space: nowrap; transition: all 0.2s;
    font-family: 'Inter', sans-serif;
}
.tab-btn svg   { width: 15px; height: 15px; flex-shrink: 0; }
.tab-btn:hover { background: rgba(255,255,255,0.05); color: #94a3b8; }
.tab-active    { background: rgba(99,102,241,0.12) !important; border-color: rgba(99,102,241,0.25) !important; color: #a5b4fc !important; }

/* ── Tab panel ── */
.tab-panel {
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; padding: 1.75rem; min-height: 320px;
}

/* ── Transitions ── */
.tab-fade-enter-active { transition: all 0.2s ease; }
.tab-fade-leave-active { transition: all 0.15s ease; }
.tab-fade-enter-from   { opacity: 0; transform: translateY(8px); }
.tab-fade-leave-to     { opacity: 0; transform: translateY(-4px); }
</style>
