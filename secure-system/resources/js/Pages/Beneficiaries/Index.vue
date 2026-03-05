<script setup>
import { ref, computed, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchBar from '@/Components/SearchBar.vue';
import AdvancedSearchFilters from '@/Components/AdvancedSearchFilters.vue';
import SearchResults from '@/Components/SearchResults.vue';

const props = defineProps({
    beneficiaries: Object,   // Laravel paginator
    filters:       Object,
    municipalities:Array,
    barangays:     Array,
    totals:        Object,
    resultCount:   { type: Number, default: 0 },
});

const page = usePage();
const isAdmin        = computed(() => page.props.auth?.user?.role === 'Administrator');
const isFieldOfficer = computed(() => page.props.auth?.user?.role === 'Field Officer');

// ── Processing state ───────────────────────────────────────────────────────
const loading = ref(false);

// ── Current reactive filters ───────────────────────────────────────────────
const currentFilters = ref({
    query:          props.filters?.query          ?? '',
    municipality:   props.filters?.municipality   ?? '',
    barangay:       props.filters?.barangay       ?? '',
    status:         props.filters?.status         ?? 'all',
    date_from:      props.filters?.date_from      ?? '',
    date_to:        props.filters?.date_to        ?? '',
    min_income:     props.filters?.min_income     ?? '',
    max_income:     props.filters?.max_income     ?? '',
    household_size: props.filters?.household_size ?? '',
    gender:         props.filters?.gender         ?? '',
    civil_status:   props.filters?.civil_status   ?? '',
    sort:           props.filters?.sort           ?? 'newest',
});

// ── Navigate with filters ──────────────────────────────────────────────────
const applyFilters = (overrides = {}) => {
    const params = { ...currentFilters.value, ...overrides };
    // Strip empty values
    const clean = Object.fromEntries(
        Object.entries(params).filter(([, v]) => v !== '' && v !== 'all' && v !== 'newest' || ['sort'].includes(v))
    );
    loading.value = true;
    router.get(route('beneficiaries.search'), clean, {
        preserveState: true,
        replace: true,
        onFinish: () => { loading.value = false; },
    });
};

// ── SearchBar handler ──────────────────────────────────────────────────────
const onSearch = (query) => {
    currentFilters.value.query = query;
    applyFilters();
};

// ── AdvancedSearchFilters apply ────────────────────────────────────────────
const onApplyFilters = (filters) => {
    currentFilters.value = { ...currentFilters.value, ...filters };
    applyFilters();
};

const onClearFilters = () => {
    currentFilters.value = {
        query: '', municipality: '', barangay: '', status: 'all',
        date_from: '', date_to: '', min_income: '', max_income: '',
        household_size: '', gender: '', civil_status: '', sort: 'newest',
    };
    loading.value = true;
    router.get(route('beneficiaries.index'), {}, {
        onFinish: () => { loading.value = false; },
    });
};

// ── Row actions ────────────────────────────────────────────────────────────
const confirmDeactivate = (b) => {
    if (confirm(`Deactivate ${b.family_head_name} (${b.bin})?\nAll active QR codes will be revoked.`)) {
        router.delete(route('beneficiaries.destroy', b.id));
    }
};

// ── Helpers ────────────────────────────────────────────────────────────────
const statusClass = (active) => active ? 'badge-active' : 'badge-inactive';
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' }) : '—';

const activeFilterCount = computed(() =>
    Object.entries(currentFilters.value).filter(([k, v]) =>
        v !== '' && v !== 'all' && !(k === 'sort' && v === 'newest')
    ).length
);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="page-hd">
                <div class="hd-left">
                    <h1 class="page-title">Beneficiaries</h1>
                    <p class="page-sub">Manage registered 4Ps / CCT beneficiary households</p>
                </div>
                <div class="hd-actions">
                    <!-- Advanced search link -->
                    <Link :href="route('beneficiaries.advanced-search')" class="btn-ghost-sm" id="advanced-search-link">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2.628 1.601C5.028 1.206 7.49 1 10 1s4.973.206 7.372.601a.75.75 0 01.628.74v2.288a2.25 2.25 0 01-.659 1.59l-4.682 4.683a2.25 2.25 0 00-.659 1.59v3.037c0 .684-.31 1.33-.844 1.757l-1.937 1.55A.75.75 0 018 18.25v-5.757a2.25 2.25 0 00-.659-1.591L2.659 6.22A2.25 2.25 0 012 4.629V2.34a.75.75 0 01.628-.74z" clip-rule="evenodd"/></svg>
                        Advanced
                    </Link>
                    <Link
                        v-if="isAdmin || isFieldOfficer"
                        :href="route('beneficiaries.create')"
                        class="btn-primary"
                        id="register-beneficiary-btn"
                    >
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z"/></svg>
                        Register Beneficiary
                    </Link>
                </div>
            </div>
        </template>

        <div class="content-wrap">

            <!-- ── Stat cards ──────────────────────────────────────────── -->
            <div class="stat-row">
                <div class="stat-card">
                    <span class="stat-label">Total Registered</span>
                    <span class="stat-val">{{ totals?.all?.toLocaleString() ?? 0 }}</span>
                </div>
                <div class="stat-card active-card">
                    <span class="stat-label">Active</span>
                    <span class="stat-val active-val">{{ totals?.active?.toLocaleString() ?? 0 }}</span>
                </div>
                <div class="stat-card inactive-card">
                    <span class="stat-label">Inactive</span>
                    <span class="stat-val inactive-val">{{ totals?.inactive?.toLocaleString() ?? 0 }}</span>
                </div>
            </div>

            <!-- ── Search + Filters row ────────────────────────────────── -->
            <div class="search-row">
                <div class="search-wrap">
                    <SearchBar
                        v-model="currentFilters.query"
                        @search="onSearch"
                    />
                </div>
                <AdvancedSearchFilters
                    :municipalities="municipalities ?? []"
                    :all-barangays="barangays ?? []"
                    :initial-filters="currentFilters"
                    :result-count="resultCount ?? beneficiaries?.total ?? 0"
                    :loading="loading"
                    @apply="onApplyFilters"
                    @clear="onClearFilters"
                />
            </div>

            <!-- ── Results header: count + exports ────────────────────── -->
            <SearchResults
                :pagination="beneficiaries"
                :loading="loading"
                :result-count="resultCount ?? beneficiaries?.total ?? 0"
                :filters="currentFilters"
            />

            <!-- ── Table ───────────────────────────────────────────────── -->
            <div v-if="!loading && beneficiaries?.data?.length > 0" class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>BIN</th>
                            <th>Name</th>
                            <th>Municipality</th>
                            <th>Barangay</th>
                            <th>Contact</th>
                            <th class="center">HH</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in beneficiaries.data" :key="b.id" class="data-row">
                            <td class="bin-cell">{{ b.bin }}</td>
                            <td>
                                <div class="name-cell">
                                    <div class="name-avatar">{{ b.family_head_name.charAt(0).toUpperCase() }}</div>
                                    <div>
                                        <div class="name-text">{{ b.family_head_name }}</div>
                                        <div class="gender-text">{{ b.gender }} · {{ b.civil_status }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ b.municipality }}</td>
                            <td>{{ b.barangay }}</td>
                            <td class="mono-sm">{{ b.contact_number }}</td>
                            <td class="center">{{ b.household_size }}</td>
                            <td>
                                <span :class="['status-badge', statusClass(b.is_active)]">
                                    {{ b.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="date-cell">{{ fmtDate(b.created_at) }}</td>
                            <td>
                                <div class="action-row">
                                    <Link :href="route('beneficiaries.show', b.id)" class="act-btn view-btn" title="View">
                                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41z" clip-rule="evenodd"/></svg>
                                    </Link>
                                    <Link
                                        v-if="isAdmin || isFieldOfficer"
                                        :href="route('beneficiaries.edit', b.id)"
                                        class="act-btn edit-btn"
                                        title="Edit"
                                    >
                                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z"/></svg>
                                    </Link>
                                    <Link
                                        :href="route('family-members.index', b.id)"
                                        class="act-btn fm-btn"
                                        title="Family Members"
                                    >
                                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"/></svg>
                                    </Link>
                                    <button
                                        v-if="isAdmin && b.is_active"
                                        class="act-btn del-btn"
                                        title="Deactivate"
                                        @click="confirmDeactivate(b)"
                                    >
                                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4z" clip-rule="evenodd"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

.content-wrap { display: flex; flex-direction: column; gap: 1.5rem; font-family: 'Inter', sans-serif; }

/* Header */
.page-hd { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.hd-left {}
.page-title { font-size: 1.5rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.page-sub   { font-size: 0.875rem; color: #64748b; margin: 0.25rem 0 0; }
.hd-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }

/* Buttons */
.btn-primary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white; padding: 0.625rem 1.25rem; border-radius: 0.75rem;
    font-size: 0.875rem; font-weight: 600; text-decoration: none;
    transition: all 0.2s; box-shadow: 0 4px 15px rgba(99,102,241,0.3); white-space: nowrap;
}
.btn-primary svg { width: 17px; height: 17px; }
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.4); }
.btn-ghost-sm {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.75rem; color: #94a3b8;
    font-size: 0.8125rem; font-weight: 600; text-decoration: none;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; white-space: nowrap;
}
.btn-ghost-sm svg { width: 15px; height: 15px; }
.btn-ghost-sm:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }

/* Stat cards */
.stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
@media (max-width: 600px) { .stat-row { grid-template-columns: 1fr; } }
.stat-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; padding: 1.25rem 1.5rem;
    display: flex; flex-direction: column; gap: 0.25rem;
    backdrop-filter: blur(10px);
}
.active-card   { border-color: rgba(16,185,129,0.2); background: rgba(16,185,129,0.05); }
.inactive-card { border-color: rgba(239,68,68,0.2);  background: rgba(239,68,68,0.05); }
.stat-label { font-size: 0.72rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; }
.stat-val   { font-size: 2rem; font-weight: 800; color: #f1f5f9; line-height: 1.1; }
.active-val   { color: #6ee7b7; }
.inactive-val { color: #fca5a5; }

/* Search row */
.search-row {
    display: flex; gap: 0.75rem; align-items: flex-start; flex-wrap: wrap;
}
.search-wrap { flex: 1; min-width: 250px; }

/* Table */
.table-wrap {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; overflow: hidden; overflow-x: auto;
}
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.data-table th {
    padding: 0.875rem 1rem; text-align: left;
    font-size: 0.72rem; font-weight: 700; color: #64748b;
    text-transform: uppercase; letter-spacing: 0.04em;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    background: rgba(0,0,0,0.25); white-space: nowrap;
}
.data-table th.center, .data-table td.center { text-align: center; }
.data-row { transition: background 0.15s; }
.data-row:hover { background: rgba(99,102,241,0.04); }
.data-row td {
    padding: 0.875rem 1rem; color: #cbd5e1;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle;
}
.data-row:last-child td { border-bottom: none; }

.bin-cell { font-family: monospace; font-size: 0.8125rem; color: #a5b4fc; font-weight: 700; white-space: nowrap; }
.name-cell { display: flex; align-items: center; gap: 0.75rem; }
.name-avatar {
    width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.875rem; font-weight: 700; color: white;
}
.name-text   { font-weight: 600; color: #f1f5f9; }
.gender-text { font-size: 0.7rem; color: #64748b; margin-top: 2px; }
.date-cell   { font-size: 0.8125rem; color: #64748b; white-space: nowrap; }
.mono-sm     { font-family: monospace; font-size: 0.8125rem; }

/* Status */
.status-badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; white-space: nowrap; }
.badge-active   { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
.badge-inactive { background: rgba(239,68,68,0.12);  color: #fca5a5; border: 1px solid rgba(239,68,68,0.25); }

/* Actions */
.action-row { display: flex; align-items: center; gap: 0.375rem; }
.act-btn {
    width: 32px; height: 32px; border-radius: 0.5rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; border: none; transition: all 0.15s; text-decoration: none;
}
.act-btn svg { width: 14px; height: 14px; }
.view-btn { background: rgba(99,102,241,0.15); color: #a5b4fc; }
.view-btn:hover { background: rgba(99,102,241,0.3); }
.edit-btn { background: rgba(245,158,11,0.15); color: #fcd34d; }
.edit-btn:hover { background: rgba(245,158,11,0.3); }
.fm-btn   { background: rgba(16,185,129,0.12); color: #6ee7b7; }
.fm-btn:hover   { background: rgba(16,185,129,0.25); }
.del-btn  { background: rgba(239,68,68,0.12);  color: #fca5a5; }
.del-btn:hover  { background: rgba(239,68,68,0.25); }
</style>
