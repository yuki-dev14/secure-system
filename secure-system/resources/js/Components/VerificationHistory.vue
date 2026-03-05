<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    beneficiaryId: { type: [Number, String], required: true },
});

// ── State ────────────────────────────────────────────────────────────────────
const logs    = ref([]);
const loading = ref(false);
const error   = ref('');

const pagination = reactive({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
    from: null,
    to: null,
});

const filters = reactive({
    status:        '',
    activity_type: '',
    date_from:     '',
    date_to:       '',
    page:          1,
});

const STATUSES = ['', 'success', 'failed'];
const ACTIVITY_TYPES = ['', 'scan', 'verify', 'qr_generate', 'qr_regenerate', 'card_generate'];

// ── Fetch ────────────────────────────────────────────────────────────────────
async function fetchHistory(page = 1) {
    loading.value = true;
    error.value   = '';
    try {
        const params = { ...filters, page };
        // Remove empty filters
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });

        const res = await axios.get(
            route('verification.history', props.beneficiaryId),
            { params }
        );
        logs.value       = res.data.data;
        Object.assign(pagination, res.data.pagination);
    } catch (e) {
        error.value = e.response?.data?.message ?? 'Failed to load verification history.';
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    pagination.current_page = 1;
    fetchHistory(1);
}

function goToPage(page) {
    if (page < 1 || page > pagination.last_page) return;
    fetchHistory(page);
}

function resetFilters() {
    filters.status        = '';
    filters.activity_type = '';
    filters.date_from     = '';
    filters.date_to       = '';
    fetchHistory(1);
}

async function exportHistory() {
    const params = new URLSearchParams();
    if (filters.status)        params.set('status',        filters.status);
    if (filters.activity_type) params.set('activity_type', filters.activity_type);
    if (filters.date_from)     params.set('date_from',     filters.date_from);
    if (filters.date_to)       params.set('date_to',       filters.date_to);
    params.set('export', 'csv');
    window.open(route('verification.history', props.beneficiaryId) + '?' + params.toString());
}

function statusBadgeClass(status) {
    if (status === 'success') return 'badge-success';
    if (status === 'failed')  return 'badge-failed';
    return 'badge-neutral';
}

function typeLabel(type) {
    const MAP = {
        scan: 'QR Scan',
        verify: 'Manual Verify',
        qr_generate: 'QR Generate',
        qr_regenerate: 'QR Regenerate',
        card_generate: 'Card Generate',
        batch_card_generate: 'Batch Cards',
    };
    return MAP[type] ?? type;
}

onMounted(() => fetchHistory(1));
</script>

<template>
    <div class="vh-shell">
        <!-- Filters -->
        <div class="vh-filters">
            <div class="filter-group">
                <label class="filter-lbl">Status</label>
                <select class="filter-select" v-model="filters.status">
                    <option value="">All Statuses</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-lbl">Activity Type</label>
                <select class="filter-select" v-model="filters.activity_type">
                    <option value="">All Types</option>
                    <option value="scan">QR Scan</option>
                    <option value="verify">Manual Verify</option>
                    <option value="qr_generate">QR Generate</option>
                    <option value="qr_regenerate">QR Regenerate</option>
                    <option value="card_generate">Card Generate</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-lbl">From</label>
                <input type="date" class="filter-input" v-model="filters.date_from" />
            </div>
            <div class="filter-group">
                <label class="filter-lbl">To</label>
                <input type="date" class="filter-input" v-model="filters.date_to" />
            </div>
            <div class="filter-actions">
                <button class="btn-search" @click="applyFilters" id="vh-btn-search">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                    Search
                </button>
                <button class="btn-reset" @click="resetFilters" id="vh-btn-reset">
                    Reset
                </button>
                <button class="btn-export" @click="exportHistory" id="vh-btn-export">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-11.25a.75.75 0 00-1.5 0v4.59L7.3 9.24a.75.75 0 00-1.1 1.02l3.25 3.5a.75.75 0 001.1 0l3.25-3.5a.75.75 0 10-1.1-1.02l-1.95 2.1V6.75z" clip-rule="evenodd"/></svg>
                    Export
                </button>
            </div>
        </div>

        <!-- Summary -->
        <div class="vh-summary" v-if="!loading">
            <span>Showing {{ pagination.from ?? 0 }}–{{ pagination.to ?? 0 }} of {{ pagination.total }} records</span>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="vh-loading">
            <div class="spinner"></div>
            <span>Loading history…</span>
        </div>

        <!-- Error -->
        <div v-else-if="error" class="vh-error">{{ error }}</div>

        <!-- Empty -->
        <div v-else-if="!logs.length" class="vh-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/>
            </svg>
            <p>No verification history found.</p>
        </div>

        <!-- Table -->
        <div v-else class="vh-table-wrap">
            <table class="vh-table">
                <thead>
                    <tr>
                        <th>Date / Time</th>
                        <th>Operator</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="log in logs" :key="log.id">
                        <td>
                            <span class="log-date">{{ log.created_at_formatted }}</span>
                            <span class="log-ago">{{ log.created_at_human }}</span>
                        </td>
                        <td>
                            <span class="log-operator">{{ log.operator?.name ?? 'System' }}</span>
                        </td>
                        <td>
                            <span class="log-type">{{ typeLabel(log.activity_type) }}</span>
                        </td>
                        <td>
                            <span :class="['log-badge', statusBadgeClass(log.status)]">
                                {{ log.status }}
                            </span>
                        </td>
                        <td>
                            <span class="log-remarks">{{ log.remarks || '—' }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="pagination.last_page > 1" class="vh-pagination">
            <button class="pg-btn" @click="goToPage(pagination.current_page - 1)"
                    :disabled="pagination.current_page <= 1">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/></svg>
            </button>
            <span class="pg-info">Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
            <button class="pg-btn" @click="goToPage(pagination.current_page + 1)"
                    :disabled="pagination.current_page >= pagination.last_page">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg>
            </button>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.vh-shell { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1rem; }

/* Filters */
.vh-filters {
    display: flex; gap: 0.625rem; flex-wrap: wrap; align-items: flex-end;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 0.875rem;
    padding: 1rem;
}
.filter-group { display: flex; flex-direction: column; gap: 0.25rem; }
.filter-lbl   { font-size: 0.6875rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; }
.filter-select, .filter-input {
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.5rem;
    color: #e2e8f0;
    font-size: 0.8125rem;
    padding: 0.4375rem 0.625rem;
    outline: none;
    font-family: 'Inter', sans-serif;
}
.filter-select option { background: #1e293b; }
.filter-select:focus, .filter-input:focus { border-color: rgba(165,180,252,0.4); }

.filter-actions { display: flex; gap: 0.5rem; align-items: flex-end; }
.btn-search, .btn-reset, .btn-export {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.4375rem 0.875rem;
    border-radius: 0.5rem;
    font-size: 0.8rem; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.2s;
}
.btn-search svg, .btn-reset svg, .btn-export svg { width: 14px; height: 14px; }
.btn-search { background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.3); color: #a5b4fc; }
.btn-search:hover { background: rgba(99,102,241,0.25); }
.btn-reset  { background: rgba(100,116,139,0.1); border: 1px solid rgba(100,116,139,0.2); color: #94a3b8; }
.btn-reset:hover { background: rgba(100,116,139,0.2); }
.btn-export { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #6ee7b7; }
.btn-export:hover { background: rgba(16,185,129,0.18); }

.vh-summary { font-size: 0.75rem; color: #475569; padding: 0 0.25rem; }

/* Loading / Empty / Error */
.vh-loading {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 2rem; color: #64748b; font-size: 0.875rem;
}
.spinner {
    width: 20px; height: 20px;
    border: 2px solid rgba(165,180,252,0.2);
    border-top-color: #a5b4fc;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.vh-error { padding: 1rem; color: #fca5a5; font-size: 0.875rem; background: rgba(239,68,68,0.06); border-radius: 0.625rem; border: 1px solid rgba(239,68,68,0.2); }

.vh-empty {
    display: flex; flex-direction: column; align-items: center; gap: 0.5rem;
    padding: 2.5rem; color: #475569; font-size: 0.875rem;
}
.vh-empty svg { width: 36px; height: 36px; color: #334155; }
.vh-empty p { margin: 0; }

/* Table */
.vh-table-wrap {
    overflow-x: auto;
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 0.875rem;
}
.vh-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.vh-table thead { background: rgba(255,255,255,0.03); }
.vh-table th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.6875rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}
.vh-table td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: top;
}
.vh-table tr:hover td { background: rgba(255,255,255,0.02); }

.log-date   { display: block; color: #e2e8f0; font-weight: 600; }
.log-ago    { display: block; font-size: 0.72rem; color: #475569; margin-top: 0.125rem; }
.log-operator { color: #cbd5e1; font-weight: 600; }
.log-type   { color: #94a3b8; }
.log-remarks { color: #64748b; font-size: 0.76rem; max-width: 200px; word-break: break-word; }

.log-badge {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: capitalize;
}
.badge-success { background: rgba(16,185,129,0.12); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.25); }
.badge-failed  { background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); }
.badge-neutral { background: rgba(100,116,139,0.1); color: #94a3b8; border: 1px solid rgba(100,116,139,0.2); }

/* Pagination */
.vh-pagination {
    display: flex; align-items: center; gap: 0.75rem;
    justify-content: center;
    padding-top: 0.25rem;
}
.pg-btn {
    width: 32px; height: 32px;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.09);
    border-radius: 0.5rem;
    color: #94a3b8;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.pg-btn svg { width: 16px; height: 16px; }
.pg-btn:hover:not(:disabled) { background: rgba(255,255,255,0.08); color: #e2e8f0; }
.pg-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.pg-info { font-size: 0.8125rem; color: #64748b; font-weight: 600; }
</style>
