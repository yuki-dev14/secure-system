<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    beneficiaryId: { type: [Number, String], required: true },
});

// ── State ──────────────────────────────────────────────────────────────────
const history      = ref([]);
const loading      = ref(false);
const error        = ref(null);
const filter       = ref('all'); // 'all' | 'valid' | 'expired' | 'revoked'

// ── Fetch history on mount ─────────────────────────────────────────────────
onMounted(() => fetchHistory());

async function fetchHistory() {
    loading.value = true;
    error.value   = null;
    try {
        const res  = await fetch(route('qr.history', props.beneficiaryId), {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json();
        if (data.success) {
            history.value = data.history;
        } else {
            error.value = data.message ?? 'Failed to load QR history.';
        }
    } catch (e) {
        error.value = 'Network error: ' + e.message;
    } finally {
        loading.value = false;
    }
}

// ── Computed ───────────────────────────────────────────────────────────────
const filtered = computed(() => {
    if (filter.value === 'all') return history.value;
    if (filter.value === 'valid')   return history.value.filter(q => q.is_valid && !q.is_expired);
    if (filter.value === 'expired') return history.value.filter(q => q.is_expired);
    if (filter.value === 'revoked') return history.value.filter(q => !q.is_valid && !q.is_expired);
    return history.value;
});

const counts = computed(() => ({
    all:     history.value.length,
    valid:   history.value.filter(q => q.is_valid && !q.is_expired).length,
    expired: history.value.filter(q => q.is_expired).length,
    revoked: history.value.filter(q => !q.is_valid && !q.is_expired).length,
}));

function statusLabel(qr) {
    if (!qr.is_valid && !qr.is_expired) return { text: 'Revoked',  cls: 'badge-revoked' };
    if (qr.is_expired)                  return { text: 'Expired',  cls: 'badge-expired' };
    if (qr.is_valid)                    return { text: 'Valid',    cls: 'badge-valid'   };
    return { text: 'Unknown', cls: '' };
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function formatDateShort(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
    <div class="qr-history">
        <!-- Header -->
        <div class="section-header">
            <div class="section-title-row">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="section-title">QR Code History</h3>
                <span class="count-badge">{{ counts.all }}</span>
            </div>
            <p class="section-sub">All QR codes generated for this beneficiary, including revoked and expired records.</p>
        </div>

        <!-- Filter tabs -->
        <div class="filter-tabs" role="tablist">
            <button
                v-for="f in [
                    { key: 'all',     label: 'All',     count: counts.all     },
                    { key: 'valid',   label: 'Valid',   count: counts.valid   },
                    { key: 'expired', label: 'Expired', count: counts.expired },
                    { key: 'revoked', label: 'Revoked', count: counts.revoked },
                ]"
                :key="f.key"
                :id="`filter-${f.key}`"
                role="tab"
                :aria-selected="filter === f.key"
                :class="['filter-tab', { 'filter-active': filter === f.key }]"
                @click="filter = f.key"
            >
                {{ f.label }}
                <span class="filter-count">{{ f.count }}</span>
            </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="loading-state">
            <div class="spinner"></div>
            <span>Loading history…</span>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="alert-error">{{ error }}</div>

        <!-- Empty -->
        <div v-else-if="filtered.length === 0" class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p>No records found{{ filter !== 'all' ? ` for "${filter}" filter` : '' }}.</p>
        </div>

        <!-- Timeline table -->
        <div v-else class="history-wrap">
            <!-- Visual Timeline -->
            <div class="timeline">
                <div
                    v-for="(qr, index) in filtered"
                    :key="qr.id"
                    class="timeline-item"
                >
                    <!-- Dot -->
                    <div :class="['tl-dot', statusLabel(qr).cls === 'badge-valid' ? 'dot-valid' : statusLabel(qr).cls === 'badge-expired' ? 'dot-expired' : 'dot-revoked']">
                        <svg v-if="qr.is_valid && !qr.is_expired" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                        <svg v-else-if="qr.is_expired" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                        <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                    </div>

                    <!-- Connector line -->
                    <div v-if="index < filtered.length - 1" class="tl-line"></div>

                    <!-- Content -->
                    <div class="tl-content">
                        <div class="tl-header">
                            <span class="tl-id">QR #{{ qr.id }}</span>
                            <span :class="['tl-badge', statusLabel(qr).cls]">{{ statusLabel(qr).text }}</span>
                            <span v-if="qr.regenerated_at" class="tl-regen-flag">Regenerated</span>
                        </div>
                        <div class="tl-meta">
                            <div class="tl-meta-item">
                                <span class="meta-lbl">Generated</span>
                                <span class="meta-val">{{ formatDate(qr.generated_at) }}</span>
                            </div>
                            <div class="tl-meta-item">
                                <span class="meta-lbl">Expires</span>
                                <span class="meta-val">{{ formatDateShort(qr.expires_at) }}</span>
                            </div>
                            <div v-if="qr.regenerated_reason" class="tl-meta-item">
                                <span class="meta-lbl">Reason</span>
                                <span class="meta-val reason-val">{{ qr.regenerated_reason }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table view -->
            <div class="table-wrap">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Generated At</th>
                            <th>Expires</th>
                            <th>Status</th>
                            <th>Type</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="qr in filtered" :key="qr.id" class="table-row">
                            <td class="id-cell">{{ qr.id }}</td>
                            <td>{{ formatDate(qr.generated_at) }}</td>
                            <td>{{ formatDateShort(qr.expires_at) }}</td>
                            <td>
                                <span :class="['badge-sm', statusLabel(qr).cls]">{{ statusLabel(qr).text }}</span>
                            </td>
                            <td>
                                <span :class="['type-pill', qr.regenerated_at ? 'type-regen' : 'type-orig']">
                                    {{ qr.regenerated_at ? 'Regenerated' : 'Original' }}
                                </span>
                            </td>
                            <td class="reason-cell">{{ qr.regenerated_reason ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Refresh -->
        <div class="history-footer">
            <button id="btn-refresh-history" class="btn-ghost" @click="fetchHistory" :disabled="loading">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39z" clip-rule="evenodd"/></svg>
                Refresh
            </button>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.qr-history { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1.25rem; }

/* ── Header ── */
.section-header   { display: flex; flex-direction: column; gap: 0.25rem; }
.section-title-row{ display: flex; align-items: center; gap: 0.625rem; }
.icon             { width: 20px; height: 20px; color: #a78bfa; flex-shrink: 0; }
.section-title    { font-size: 1rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.section-sub      { font-size: 0.8125rem; color: #64748b; margin: 0 0 0 1.625rem; }
.count-badge      { background: rgba(167,139,250,0.15); color: #a78bfa; border: 1px solid rgba(167,139,250,0.25); padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; }

/* ── Filter tabs ── */
.filter-tabs { display: flex; gap: 0.375rem; flex-wrap: wrap; }
.filter-tab {
    display: flex; align-items: center; gap: 0.375rem;
    padding: 0.4rem 0.875rem; border-radius: 0.625rem;
    font-size: 0.8rem; font-weight: 600;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    color: #64748b; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s;
}
.filter-tab:hover  { background: rgba(255,255,255,0.07); color: #94a3b8; }
.filter-active { background: rgba(99,102,241,0.12) !important; border-color: rgba(99,102,241,0.25) !important; color: #a5b4fc !important; }
.filter-count { font-size: 0.65rem; font-weight: 800; background: rgba(255,255,255,0.1); padding: 0.1rem 0.35rem; border-radius: 9999px; }

/* ── Loading / Error / Empty ── */
.loading-state { display: flex; align-items: center; gap: 0.75rem; padding: 2rem; color: #64748b; justify-content: center; }
.spinner { width: 24px; height: 24px; border-radius: 50%; border: 2px solid rgba(99,102,241,0.2); border-top-color: #818cf8; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); color: #fca5a5; padding: 0.75rem 1rem; border-radius: 0.75rem; font-size: 0.875rem; }
.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.625rem; padding: 2.5rem; color: #475569; text-align: center; }
.empty-state svg { width: 40px; height: 40px; color: #334155; }
.empty-state p { font-size: 0.875rem; font-weight: 600; color: #64748b; margin: 0; }

/* ── History wrap ── */
.history-wrap { display: flex; flex-direction: column; gap: 1rem; }

/* ── Timeline ── */
.timeline { display: flex; flex-direction: column; padding: 1rem; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 0.875rem; gap: 0; }
.timeline-item { display: flex; gap: 0.875rem; position: relative; }
.tl-dot {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; z-index: 1;
}
.tl-dot svg { width: 16px; height: 16px; }
.dot-valid   { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1.5px solid rgba(16,185,129,0.3); }
.dot-expired { background: rgba(245,158,11,0.12); color: #fcd34d; border: 1.5px solid rgba(245,158,11,0.25); }
.dot-revoked { background: rgba(239,68,68,0.1);  color: #fca5a5; border: 1.5px solid rgba(239,68,68,0.25); }
.tl-line { position: absolute; left: 13px; top: 28px; width: 2px; height: calc(100% - 28px + 1rem); background: rgba(255,255,255,0.06); z-index: 0; }
.tl-content { flex: 1; padding-bottom: 1.25rem; }
.tl-header  { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.375rem; }
.tl-id      { font-size: 0.8125rem; font-weight: 700; color: #94a3b8; }
.tl-badge   { display: inline-block; padding: 0.175rem 0.5rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 700; }
.badge-valid   { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
.badge-expired { background: rgba(245,158,11,0.12); color: #fcd34d; border: 1px solid rgba(245,158,11,0.25); }
.badge-revoked { background: rgba(239,68,68,0.1);  color: #fca5a5; border: 1px solid rgba(239,68,68,0.25); }
.tl-regen-flag { font-size: 0.65rem; font-weight: 600; color: #a78bfa; background: rgba(167,139,250,0.12); padding: 0.175rem 0.5rem; border-radius: 9999px; border: 1px solid rgba(167,139,250,0.25); }
.tl-meta       { display: flex; flex-wrap: wrap; gap: 1rem; }
.tl-meta-item  { display: flex; flex-direction: column; gap: 0.1rem; }
.meta-lbl { font-size: 0.6rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; }
.meta-val { font-size: 0.8125rem; color: #cbd5e1; font-weight: 500; }
.reason-val { font-style: italic; color: #94a3b8; }

/* ── Table ── */
.table-wrap { overflow-x: auto; }
.history-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.history-table th { padding: 0.625rem 0.875rem; text-align: left; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; background: rgba(0,0,0,0.15); border-bottom: 1px solid rgba(255,255,255,0.07); }
.history-table td { padding: 0.75rem 0.875rem; color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.04); }
.table-row:last-child td { border-bottom: none; }
.id-cell { font-family: monospace; color: #94a3b8; font-size: 0.75rem; }
.badge-sm   { display: inline-block; padding: 0.175rem 0.5rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 700; }
.type-pill  { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 700; }
.type-orig  { background: rgba(99,102,241,0.12); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.2); }
.type-regen { background: rgba(167,139,250,0.12); color: #a78bfa; border: 1px solid rgba(167,139,250,0.2); }
.reason-cell { font-style: italic; color: #64748b; max-width: 200px; }

/* ── Footer ── */
.history-footer { display: flex; justify-content: flex-end; }
.btn-ghost {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.875rem; border-radius: 0.625rem;
    font-size: 0.8rem; font-weight: 600; color: #64748b;
    background: none; border: 1px solid rgba(255,255,255,0.08);
    cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s;
}
.btn-ghost svg { width: 14px; height: 14px; }
.btn-ghost:hover:not(:disabled) { background: rgba(255,255,255,0.06); color: #94a3b8; }
.btn-ghost:disabled { opacity: 0.4; cursor: not-allowed; }
</style>
