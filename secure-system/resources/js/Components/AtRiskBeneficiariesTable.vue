<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    beneficiaries: { type: Array, default: () => [] },
    // Each: { beneficiary_id, bin, family_head_name, municipality, barangay, overall_status,
    //         education_pct, health_pct, fds_pct, urgency_score, missing_requirements,
    //         was_previously_compliant }
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['view', 'remind']);

const page       = ref(1);
const perPage    = 10;
const sortKey    = ref('urgency_score');
const sortDir    = ref('desc');
const searchText = ref('');

/* ── Computed ────────────────────────────────────────────── */
const filtered = computed(() => {
    let list = props.beneficiaries;
    if (searchText.value.trim()) {
        const q = searchText.value.trim().toLowerCase();
        list = list.filter(b =>
            b.family_head_name?.toLowerCase().includes(q) ||
            b.bin?.toLowerCase().includes(q) ||
            b.municipality?.toLowerCase().includes(q)
        );
    }
    list = [...list].sort((a, b) => {
        const va = a[sortKey.value] ?? 0;
        const vb = b[sortKey.value] ?? 0;
        if (typeof va === 'string') return sortDir.value === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
        return sortDir.value === 'asc' ? va - vb : vb - va;
    });
    return list;
});

const totalPages = computed(() => Math.ceil(filtered.value.length / perPage));
const paged      = computed(() => filtered.value.slice((page.value - 1) * perPage, page.value * perPage));

function sort(key) {
    if (sortKey.value === key) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortKey.value = key;
        sortDir.value = 'desc';
    }
    page.value = 1;
}

function sortArrow(key) {
    if (sortKey.value !== key) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

/* ── CSV Export ──────────────────────────────────────────── */
function exportCSV() {
    const headers = ['BIN', 'Family Head', 'Municipality', 'Barangay', 'Status', 'Education%', 'Health%', 'FDS%', 'Urgency', 'Missing Items'];
    const rows = filtered.value.map(b => [
        b.bin, b.family_head_name, b.municipality, b.barangay ?? '',
        b.overall_status, b.education_pct, b.health_pct, b.fds_pct, b.urgency_score,
        (b.missing_requirements ?? []).join(' | '),
    ]);
    const csv = [headers, ...rows].map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;  a.download = 'at-risk-beneficiaries.csv';  a.click();
    URL.revokeObjectURL(url);
}

function statusClass(s) {
    return { compliant: 'st-pass', partial: 'st-warn', non_compliant: 'st-fail' }[s] ?? 'st-warn';
}
function pctColor(pct) {
    if (pct >= 85) return '#10b981';
    if (pct >= 50) return '#f59e0b';
    return '#ef4444';
}
</script>

<template>
    <div class="arbt-shell">
        <!-- Header -->
        <div class="arbt-hd">
            <div>
                <h3 class="arbt-title">At-Risk Beneficiaries</h3>
                <p class="arbt-sub">{{ filtered.length }} beneficiaries with compliance issues</p>
            </div>
            <div class="arbt-actions">
                <div class="search-wrap">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd"/></svg>
                    <input v-model="searchText" placeholder="Search name, BIN, location…" class="search-input" id="arbt-search"/>
                </div>
                <button class="btn-export" @click="exportCSV" id="arbt-export-btn">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a.75.75 0 01.75.75v10.638l3.96-4.158a.75.75 0 111.08 1.04l-5.25 5.5a.75.75 0 01-1.08 0l-5.25-5.5a.75.75 0 111.08-1.04l3.96 4.158V3.75A.75.75 0 0110 3z" clip-rule="evenodd"/></svg>
                    Export CSV
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="table-wrap" :class="{ 'tbl-loading': loading }">
            <div v-if="loading" class="tbl-loader">
                <div class="loader"></div><span>Loading…</span>
            </div>
            <table v-else class="arbt-table">
                <thead>
                    <tr>
                        <th @click="sort('family_head_name')" class="th-sort">Name {{ sortArrow('family_head_name') }}</th>
                        <th>BIN</th>
                        <th @click="sort('municipality')" class="th-sort">Location {{ sortArrow('municipality') }}</th>
                        <th @click="sort('overall_status')" class="th-sort">Status {{ sortArrow('overall_status') }}</th>
                        <th @click="sort('education_pct')" class="th-sort">Edu {{ sortArrow('education_pct') }}</th>
                        <th @click="sort('health_pct')" class="th-sort">Health {{ sortArrow('health_pct') }}</th>
                        <th @click="sort('fds_pct')" class="th-sort">FDS {{ sortArrow('fds_pct') }}</th>
                        <th @click="sort('urgency_score')" class="th-sort">Urgency {{ sortArrow('urgency_score') }}</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in paged" :key="b.beneficiary_id" class="arbt-row">
                        <td class="td-name">
                            <span class="td-fname">{{ b.family_head_name }}</span>
                            <span v-if="b.was_previously_compliant" class="badge-slip">Regressed</span>
                        </td>
                        <td class="td-mono">{{ b.bin }}</td>
                        <td class="td-loc">{{ b.municipality }}<br v-if="b.barangay"/><span class="td-brgy">{{ b.barangay }}</span></td>
                        <td>
                            <span :class="['status-badge', statusClass(b.overall_status)]">
                                {{ b.overall_status === 'non_compliant' ? 'Non-Compliant' : b.overall_status === 'partial' ? 'Partial' : 'Compliant' }}
                            </span>
                        </td>
                        <td>
                            <span class="pct-val" :style="`color:${pctColor(b.education_pct)}`">{{ b.education_pct }}%</span>
                        </td>
                        <td>
                            <span class="pct-val" :style="`color:${pctColor(b.health_pct)}`">{{ b.health_pct }}%</span>
                        </td>
                        <td>
                            <span class="pct-val" :style="`color:${pctColor(b.fds_pct)}`">{{ b.fds_pct }}%</span>
                        </td>
                        <td>
                            <div class="urgency-wrap">
                                <div class="urgency-bar-bg">
                                    <div class="urgency-bar-fill"
                                         :style="{
                                             width: b.urgency_score + '%',
                                             background: b.urgency_score >= 70 ? '#ef4444' : b.urgency_score >= 40 ? '#f59e0b' : '#10b981'
                                         }">
                                    </div>
                                </div>
                                <span class="urgency-num">{{ b.urgency_score }}</span>
                            </div>
                        </td>
                        <td>
                            <div class="td-actions">
                                <button class="td-btn td-btn-view" @click="emit('view', b)" :id="`arbt-view-${b.beneficiary_id}`">View</button>
                                <button class="td-btn td-btn-remind" @click="emit('remind', b)">Remind</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!paged.length">
                        <td colspan="9" class="td-empty">No at-risk beneficiaries found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="arbt-pagination">
            <span class="pg-info">Showing {{ ((page-1)*perPage)+1 }}–{{ Math.min(page*perPage, filtered.length) }} of {{ filtered.length }}</span>
            <div class="pg-btns">
                <button class="pg-btn" :disabled="page <= 1" @click="page--">‹ Prev</button>
                <span class="pg-cur">{{ page }} / {{ totalPages || 1 }}</span>
                <button class="pg-btn" :disabled="page >= totalPages" @click="page++">Next ›</button>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }
.arbt-shell {
    font-family: 'Inter', sans-serif;
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1.25rem; padding: 1.5rem;
    display: flex; flex-direction: column; gap: 1rem;
}
.arbt-hd { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.arbt-title { font-size: 1rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.arbt-sub   { font-size: 0.78rem; color: #64748b; margin: 0; }
.arbt-actions { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }

.search-wrap {
    display: flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem; padding: 0.5rem 0.75rem;
}
.search-wrap svg { width: 14px; height: 14px; color: #475569; flex-shrink: 0; }
.search-input {
    background: none; border: none; outline: none; color: #f1f5f9;
    font-size: 0.8125rem; font-family: 'Inter', sans-serif; width: 200px;
}
.search-input::placeholder { color: #475569; }

.btn-export {
    display: flex; align-items: center; gap: 0.375rem;
    background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.25);
    border-radius: 0.625rem; padding: 0.5rem 0.875rem;
    color: #a5b4fc; font-size: 0.8rem; font-weight: 600; cursor: pointer;
    font-family: 'Inter', sans-serif; transition: all 0.15s;
}
.btn-export svg { width: 14px; height: 14px; }
.btn-export:hover { background: rgba(99,102,241,0.25); }

.table-wrap { overflow-x: auto; border-radius: 0.875rem; position: relative; }
.tbl-loading { opacity: 0.5; pointer-events: none; }
.tbl-loader {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    gap: 0.75rem; color: #64748b; background: rgba(15,23,42,0.7); border-radius: 0.875rem; z-index: 2;
}
.loader {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.1); border-top-color: #6366f1;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.arbt-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.arbt-table thead tr { border-bottom: 1px solid rgba(255,255,255,0.08); }
.arbt-table th {
    padding: 0.625rem 0.875rem; text-align: left;
    font-size: 0.72rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;
    white-space: nowrap;
}
.th-sort { cursor: pointer; user-select: none; }
.th-sort:hover { color: #94a3b8; }

.arbt-row { border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.15s; }
.arbt-row:hover { background: rgba(255,255,255,0.03); }
.arbt-row td { padding: 0.75rem 0.875rem; vertical-align: middle; color: #94a3b8; }

.td-name { display: flex; align-items: center; gap: 0.5rem; }
.td-fname { font-weight: 600; color: #e2e8f0; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.badge-slip {
    background: rgba(245,158,11,0.15); color: #fcd34d; border: 1px solid rgba(245,158,11,0.25);
    border-radius: 999px; padding: 0.1rem 0.4rem; font-size: 0.65rem; font-weight: 700; white-space: nowrap;
}
.td-mono { font-family: monospace; font-size: 0.78rem; color: #64748b; }
.td-loc  { color: #94a3b8; }
.td-brgy { font-size: 0.72rem; color: #475569; }

.status-badge { padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
.st-pass { background: rgba(16,185,129,0.12); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.2); }
.st-warn { background: rgba(245,158,11,0.12); color: #fcd34d; border: 1px solid rgba(245,158,11,0.2); }
.st-fail { background: rgba(239,68,68,0.1);   color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); }

.pct-val { font-weight: 700; font-size: 0.875rem; }

.urgency-wrap { display: flex; align-items: center; gap: 0.5rem; }
.urgency-bar-bg { flex: 1; height: 6px; background: rgba(255,255,255,0.07); border-radius: 999px; overflow: hidden; }
.urgency-bar-fill { height: 100%; border-radius: 999px; transition: width 0.4s ease; }
.urgency-num { font-size: 0.75rem; font-weight: 700; color: #64748b; min-width: 26px; text-align: right; }

.td-actions { display: flex; gap: 0.375rem; }
.td-btn {
    padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 600;
    cursor: pointer; font-family: 'Inter', sans-serif; border: 1px solid; transition: all 0.15s;
}
.td-btn-view  { background: rgba(99,102,241,0.12); border-color: rgba(99,102,241,0.25); color: #a5b4fc; }
.td-btn-view:hover  { background: rgba(99,102,241,0.25); }
.td-btn-remind { background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.2); color: #fcd34d; }
.td-btn-remind:hover { background: rgba(245,158,11,0.2); }

.td-empty { text-align: center; padding: 2.5rem; color: #475569; font-style: italic; }

/* Pagination */
.arbt-pagination { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; }
.pg-info { font-size: 0.78rem; color: #475569; }
.pg-btns { display: flex; align-items: center; gap: 0.5rem; }
.pg-btn {
    padding: 0.3rem 0.75rem; border-radius: 0.5rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
    color: #64748b; font-size: 0.78rem; font-weight: 600; cursor: pointer;
    font-family: 'Inter', sans-serif; transition: all 0.15s;
}
.pg-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.pg-btn:hover:not(:disabled) { background: rgba(255,255,255,0.1); color: #94a3b8; }
.pg-cur { font-size: 0.78rem; color: #64748b; }
</style>
