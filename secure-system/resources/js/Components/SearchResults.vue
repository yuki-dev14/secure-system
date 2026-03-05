<script setup>
/**
 * SearchResults.vue
 *
 * Displays:
 *  - Results count + export buttons
 *  - Loading skeleton (10 rows) while fetching
 *  - Empty state
 *  - Pagination (number + prev/next)
 *
 * Props:
 *   pagination  – Laravel paginator meta
 *   loading     – show skeleton
 *   resultCount – total matching records
 *   filters     – current active filters (forwarded to export URLs)
 */
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    pagination:  { type: Object, default: null },
    loading:     { type: Boolean, default: false },
    resultCount: { type: Number, default: 0 },
    filters:     { type: Object, default: () => ({}) },
    canExport:   { type: Boolean, default: true },
});

const exporting = ref('');   // 'xlsx' | 'csv' | 'pdf' | ''

// ── Export ─────────────────────────────────────────────────────────────────
const doExport = (format) => {
    exporting.value = format;
    const params = new URLSearchParams({
        ...props.filters,
        format,
    }).toString();
    window.location.href = `/beneficiaries/export?${params}`;
    setTimeout(() => { exporting.value = ''; }, 3000);
};

// ── Pagination ──────────────────────────────────────────────────────────────
const goToPage = (url) => {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true });
};

// Build a visible page window (max 7 pages)
const pageLinks = () => {
    if (!props.pagination) return [];
    return props.pagination.links ?? [];
};

const formatNumber = (n) => Number(n).toLocaleString('en-PH');
</script>

<template>
    <div class="sr-root">

        <!-- ── Top bar: count + exports ───────────────────────────── -->
        <div class="top-bar">
            <div class="count-block">
                <template v-if="loading">
                    <div class="skel skel-line skel-sm"></div>
                </template>
                <template v-else>
                    <span class="count-num">{{ formatNumber(resultCount) }}</span>
                    <span class="count-lbl">beneficiar{{ resultCount !== 1 ? 'ies' : 'y' }} found</span>
                </template>
            </div>

            <!-- Export buttons -->
            <div v-if="canExport && !loading && resultCount > 0" class="export-row">
                <span class="export-label">Export:</span>
                <button
                    v-for="fmt in ['xlsx', 'csv', 'pdf']"
                    :key="fmt"
                    :id="`export-${fmt}-btn`"
                    class="export-btn"
                    :class="`export-${fmt}`"
                    :disabled="!!exporting"
                    @click="doExport(fmt)"
                >
                    <svg v-if="exporting === fmt" class="spinner" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31" stroke-linecap="round"/>
                    </svg>
                    <template v-else>
                        <!-- Excel icon -->
                        <svg v-if="fmt === 'xlsx'" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-4.12-4.122A1.5 1.5 0 0011.378 2H4.5zM6.25 9.5a.75.75 0 00-1.5 0v2.25H2.75a.75.75 0 000 1.5h2v2.25a.75.75 0 001.5 0V13.25h2.25a.75.75 0 000-1.5H6.25V9.5z" clip-rule="evenodd"/>
                        </svg>
                        <!-- CSV icon -->
                        <svg v-else-if="fmt === 'csv'" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-4.12-4.122A1.5 1.5 0 0011.378 2H4.5zm5.5 8.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V11a.75.75 0 01.75-.75zm-3 0a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V11a.75.75 0 01.75-.75zm6 0a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V11a.75.75 0 01.75-.75z" clip-rule="evenodd"/>
                        </svg>
                        <!-- PDF icon -->
                        <svg v-else viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-4.12-4.122A1.5 1.5 0 0011.378 2H4.5zM9 11.75a.75.75 0 01.75-.75h1.75a.75.75 0 010 1.5h-1v.75a.75.75 0 01-1.5 0V11.75z" clip-rule="evenodd"/>
                        </svg>
                    </template>
                    {{ fmt.toUpperCase() }}
                </button>
            </div>
        </div>

        <!-- ── Loading skeleton ───────────────────────────────────── -->
        <div v-if="loading" class="skeleton-list">
            <div v-for="i in 10" :key="i" class="skel-row">
                <div class="skel skel-avatar"></div>
                <div class="skel-body">
                    <div class="skel skel-line skel-lg"></div>
                    <div class="skel skel-line skel-sm" style="margin-top:6px"></div>
                </div>
                <div class="skel skel-badge"></div>
            </div>
        </div>

        <!-- ── Empty state ────────────────────────────────────────── -->
        <div v-else-if="resultCount === 0" class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803 7.5 7.5 0 0015.803 15.803z"/>
            </svg>
            <p class="empty-title">No beneficiaries found</p>
            <p class="empty-sub">Try adjusting your search terms or clearing the filters.</p>
        </div>

        <!-- ── Pagination ─────────────────────────────────────────── -->
        <div v-else-if="pagination && pagination.last_page > 1" class="pagination">
            <!-- Prev -->
            <button
                class="page-btn page-nav"
                :disabled="!pagination.prev_page_url"
                @click="goToPage(pagination.prev_page_url)"
            >
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/></svg>
                Prev
            </button>

            <!-- Page numbers -->
            <template v-for="link in pageLinks()" :key="link.label">
                <span v-if="link.label === '...'" class="page-ellipsis">…</span>
                <button
                    v-else-if="!['&laquo; Previous', 'Next &raquo;'].includes(link.label)"
                    class="page-btn"
                    :class="{ 'page-active': link.active }"
                    :disabled="link.active"
                    @click="goToPage(link.url)"
                    v-html="link.label"
                />
            </template>

            <!-- Next -->
            <button
                class="page-btn page-nav"
                :disabled="!pagination.next_page_url"
                @click="goToPage(pagination.next_page_url)"
            >
                Next
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 010-1.06z" clip-rule="evenodd"/></svg>
            </button>

            <!-- Page info -->
            <span class="page-info">
                Page {{ pagination.current_page }} of {{ pagination.last_page }}
                · {{ formatNumber(pagination.total) }} total
            </span>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.sr-root { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1rem; }

/* Top bar */
.top-bar {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 0.75rem;
}
.count-block { display: flex; align-items: baseline; gap: 0.5rem; }
.count-num { font-size: 1.5rem; font-weight: 800; color: #f1f5f9; }
.count-lbl { font-size: 0.875rem; color: #64748b; }

/* Export row */
.export-row { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.export-label { font-size: 0.78rem; color: #64748b; }
.export-btn {
    display: inline-flex; align-items: center; gap: 0.375rem;
    padding: 0.4rem 0.875rem; border-radius: 0.625rem;
    font-size: 0.78rem; font-weight: 700; border: 1px solid;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.export-btn svg { width: 14px; height: 14px; }
.export-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.export-xlsx { background: rgba(16,185,129,0.12); color: #6ee7b7; border-color: rgba(16,185,129,0.25); }
.export-xlsx:hover:not(:disabled) { background: rgba(16,185,129,0.22); }
.export-csv  { background: rgba(245,158,11,0.12); color: #fcd34d; border-color: rgba(245,158,11,0.25); }
.export-csv:hover:not(:disabled)  { background: rgba(245,158,11,0.22); }
.export-pdf  { background: rgba(239,68,68,0.12);  color: #fca5a5; border-color: rgba(239,68,68,0.25); }
.export-pdf:hover:not(:disabled)  { background: rgba(239,68,68,0.22); }
.spinner { width: 13px; height: 13px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Skeleton */
.skeleton-list { display: flex; flex-direction: column; gap: 0.75rem; }
.skel-row { display: flex; align-items: center; gap: 0.875rem; padding: 0.875rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 0.875rem; }
.skel { background: rgba(255,255,255,0.06); border-radius: 0.5rem; animation: shimmer 1.5s infinite; }
@keyframes shimmer {
    0%   { opacity: 0.5; }
    50%  { opacity: 1; }
    100% { opacity: 0.5; }
}
.skel-avatar { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; }
.skel-body   { flex: 1; }
.skel-line   { height: 12px; border-radius: 8px; }
.skel-sm     { width: 40%; }
.skel-lg     { width: 65%; }
.skel-badge  { width: 56px; height: 22px; border-radius: 9999px; }

/* Empty state */
.empty-state {
    display: flex; flex-direction: column; align-items: center; gap: 0.75rem;
    padding: 3.5rem 2rem; text-align: center;
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 1rem;
}
.empty-state svg { width: 52px; height: 52px; color: #334155; }
.empty-title { font-size: 1rem; font-weight: 700; color: #64748b; margin: 0; }
.empty-sub   { font-size: 0.875rem; color: #475569; margin: 0; }

/* Pagination */
.pagination {
    display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap;
    margin-top: 0.25rem;
}
.page-btn {
    min-width: 36px; height: 36px; border-radius: 0.625rem; padding: 0 0.625rem;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.25rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    color: #94a3b8; font-size: 0.8rem; font-weight: 600;
    cursor: pointer; transition: all 0.15s; font-family: 'Inter', sans-serif;
}
.page-btn svg { width: 14px; height: 14px; }
.page-btn:hover:not(:disabled) { background: rgba(255,255,255,0.12); color: #f1f5f9; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.page-active { background: rgba(99,102,241,0.25) !important; border-color: rgba(99,102,241,0.4) !important; color: #a5b4fc !important; }
.page-nav    { min-width: 72px; }
.page-ellipsis { color: #475569; padding: 0 0.25rem; font-size: 0.8rem; }
.page-info { font-size: 0.75rem; color: #64748b; margin-left: 0.5rem; white-space: nowrap; }
</style>
