<script setup>
import { ref, reactive, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    logs: Object,    // paginated
    filters: Object,
});

const filters = reactive({
    type:   props.filters?.type   ?? 'all',
    from:   props.filters?.from   ?? '',
    to:     props.filters?.to     ?? '',
    status: props.filters?.status ?? 'all',
});

const applyFilters = () => {
    router.get(route('activity.index'), filters, { preserveState: true, replace: true });
};

const resetFilters = () => {
    filters.type   = 'all';
    filters.from   = '';
    filters.to     = '';
    filters.status = 'all';
    applyFilters();
};

const activityTypes = ['all', 'scan', 'verify', 'approve', 'reject', 'view', 'edit', 'delete'];

const typeColor = (type) => {
    const map = {
        scan:    { bg: 'rgba(99,102,241,0.15)',  color: '#a5b4fc',  text: 'Scan' },
        verify:  { bg: 'rgba(16,185,129,0.15)',  color: '#34d399',  text: 'Verify' },
        approve: { bg: 'rgba(16,185,129,0.15)',  color: '#34d399',  text: 'Approve' },
        reject:  { bg: 'rgba(239,68,68,0.15)',   color: '#f87171',  text: 'Reject' },
        view:    { bg: 'rgba(59,130,246,0.15)',  color: '#60a5fa',  text: 'View' },
        edit:    { bg: 'rgba(245,158,11,0.15)',  color: '#fbbf24',  text: 'Edit' },
        delete:  { bg: 'rgba(239,68,68,0.15)',   color: '#f87171',  text: 'Delete' },
    };
    return map[type] ?? { bg: 'rgba(255,255,255,0.08)', color: '#94a3b8', text: type };
};

const statusColor = (status) =>
    status === 'success'
        ? { color: '#34d399', bg: 'rgba(16,185,129,0.12)' }
        : { color: '#f87171', bg: 'rgba(239,68,68,0.12)' };

const formatDate = (ts) =>
    ts ? new Date(ts).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' }) : '—';

const truncate = (str, n = 90) =>
    str && str.length > n ? str.substring(0, n) + '…' : str;
</script>

<template>
    <Head title="Activity History — SECURE System" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-hdr">
                <div class="hdr-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="page-title">Activity History</h1>
                    <p class="page-subtitle">Your recent actions in the SECURE system</p>
                </div>
                <Link :href="route('profile.edit')" class="back-btn">← Back to Profile</Link>
            </div>
        </template>

        <div class="content-wrap">
            <!-- Filter bar -->
            <div class="filter-bar">
                <!-- Type filter -->
                <div class="filter-group">
                    <label class="filter-label">Activity Type</label>
                    <div class="select-wrap">
                        <select v-model="filters.type" class="filter-select">
                            <option v-for="t in activityTypes" :key="t" :value="t">
                                {{ t === 'all' ? 'All Types' : t.charAt(0).toUpperCase() + t.slice(1) }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Status filter -->
                <div class="filter-group">
                    <label class="filter-label">Status</label>
                    <div class="select-wrap">
                        <select v-model="filters.status" class="filter-select">
                            <option value="all">All Statuses</option>
                            <option value="success">Success</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                </div>

                <!-- Date range -->
                <div class="filter-group">
                    <label class="filter-label">From</label>
                    <input type="date" v-model="filters.from" class="filter-input"/>
                </div>
                <div class="filter-group">
                    <label class="filter-label">To</label>
                    <input type="date" v-model="filters.to" class="filter-input"/>
                </div>

                <div class="filter-actions">
                    <button @click="applyFilters" class="btn-filter">Apply</button>
                    <button @click="resetFilters" class="btn-reset">Reset</button>
                </div>
            </div>

            <!-- Summary stat -->
            <div class="result-info">
                Showing {{ logs.from ?? 0 }}–{{ logs.to ?? 0 }} of {{ logs.total ?? 0 }} entries
            </div>

            <!-- Table -->
            <div class="table-card">
                <div v-if="logs.data.length === 0" class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p>No activity records found for the selected filters.</p>
                </div>

                <table v-else class="activity-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Timestamp</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in logs.data" :key="log.id" class="table-row">
                            <td>
                                <span class="type-badge" :style="{ background: typeColor(log.activity_type).bg, color: typeColor(log.activity_type).color }">
                                    {{ typeColor(log.activity_type).text }}
                                </span>
                            </td>
                            <td class="desc-cell" :title="log.activity_description">
                                {{ truncate(log.activity_description) }}
                            </td>
                            <td class="date-cell">{{ formatDate(log.timestamp) }}</td>
                            <td class="ip-cell"><code>{{ log.ip_address }}</code></td>
                            <td>
                                <span class="status-dot" :style="{ background: statusColor(log.status).bg, color: statusColor(log.status).color }">
                                    <span class="dot" :style="{ background: statusColor(log.status).color }"></span>
                                    {{ log.status }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="logs.last_page > 1" class="pagination">
                <component
                    v-for="link in logs.links"
                    :key="link.label"
                    :is="link.url ? 'a' : 'span'"
                    :href="link.url ?? undefined"
                    v-html="link.label"
                    class="page-link"
                    :class="{
                        active: link.active,
                        disabled: !link.url,
                    }"
                    @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

/* Header */
.page-hdr { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; font-family: 'Inter', sans-serif; }
.hdr-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.25); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.hdr-icon svg { width: 22px; height: 22px; color: #a5b4fc; }
.page-title { font-size: 1.125rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.2rem; }
.page-subtitle { font-size: 0.8125rem; color: #64748b; margin: 0; }
.back-btn { margin-left: auto; font-size: 0.8125rem; color: #94a3b8; text-decoration: none; padding: 0.5rem 0.875rem; border-radius: 0.625rem; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04); transition: all 0.2s; font-family: 'Inter', sans-serif; }
.back-btn:hover { color: #f1f5f9; background: rgba(255,255,255,0.08); }

/* Layout */
.content-wrap { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1rem; }

/* Filter bar */
.filter-bar { display: flex; align-items: flex-end; gap: 0.875rem; flex-wrap: wrap; padding: 1rem 1.25rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 1rem; }
.filter-group { display: flex; flex-direction: column; gap: 0.35rem; }
.filter-label { font-size: 0.7rem; font-weight: 500; color: #64748b; letter-spacing: 0.04em; text-transform: uppercase; }
.select-wrap { position: relative; }
.filter-select {
    appearance: none; -webkit-appearance: none;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    color: #e2e8f0; border-radius: 0.625rem; padding: 0.5rem 2rem 0.5rem 0.75rem;
    font-size: 0.8125rem; cursor: pointer; outline: none;
    font-family: 'Inter', sans-serif;
    transition: border-color 0.2s;
}
.filter-select:focus { border-color: #6366f1; }
.filter-select option { background: #1e293b; }
.filter-input {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    color: #e2e8f0; border-radius: 0.625rem; padding: 0.5rem 0.75rem;
    font-size: 0.8125rem; outline: none; font-family: 'Inter', sans-serif;
    transition: border-color 0.2s;
}
.filter-input:focus { border-color: #6366f1; }
.filter-input::-webkit-calendar-picker-indicator { filter: invert(0.6); }
.filter-actions { display: flex; gap: 0.5rem; }
.btn-filter {
    padding: 0.5rem 1rem; background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; font-size: 0.8125rem; font-weight: 600; border: none;
    border-radius: 0.625rem; cursor: pointer; font-family: 'Inter', sans-serif;
    box-shadow: 0 2px 10px rgba(99,102,241,0.3); transition: all 0.2s;
}
.btn-filter:hover { box-shadow: 0 4px 16px rgba(99,102,241,0.45); }
.btn-reset { padding: 0.5rem 1rem; background: rgba(255,255,255,0.06); color: #94a3b8; font-size: 0.8125rem; border: 1px solid rgba(255,255,255,0.1); border-radius: 0.625rem; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; }
.btn-reset:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }

.result-info { font-size: 0.8rem; color: #475569; }

/* Table card */
.table-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07); border-radius: 1rem; overflow: hidden; }

.empty-state { display: flex; flex-direction: column; align-items: center; gap: 0.75rem; padding: 3rem; color: #475569; text-align: center; }
.empty-state svg { width: 40px; height: 40px; opacity: 0.4; }
.empty-state p { font-size: 0.875rem; margin: 0; }

.activity-table { width: 100%; border-collapse: collapse; }
.activity-table thead tr { border-bottom: 1px solid rgba(255,255,255,0.07); }
.activity-table th { padding: 0.875rem 1rem; text-align: left; font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
.activity-table td { padding: 0.875rem 1rem; font-size: 0.8125rem; color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.04); }
.table-row { transition: background 0.15s; }
.table-row:hover { background: rgba(255,255,255,0.03); }
.table-row:last-child td { border-bottom: none; }
.desc-cell { color: #cbd5e1; max-width: 320px; }
.date-cell { white-space: nowrap; color: #64748b; font-size: 0.775rem; }
.ip-cell code { font-size: 0.75rem; color: #64748b; background: rgba(255,255,255,0.05); padding: 0.15rem 0.4rem; border-radius: 4px; }

.type-badge { padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
.status-dot { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
.dot { width: 6px; height: 6px; border-radius: 50%; }

/* Pagination */
.pagination { display: flex; gap: 0.25rem; flex-wrap: wrap; justify-content: center; }
.page-link { padding: 0.4rem 0.75rem; border-radius: 0.5rem; font-size: 0.8125rem; cursor: pointer; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); color: #94a3b8; transition: all 0.15s; text-decoration: none; }
.page-link:hover:not(.disabled):not(.active) { background: rgba(255,255,255,0.08); color: #f1f5f9; }
.page-link.active { background: rgba(99,102,241,0.25); border-color: rgba(99,102,241,0.4); color: #a5b4fc; }
.page-link.disabled { opacity: 0.4; cursor: not-allowed; }
</style>
