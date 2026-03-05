<script setup>
import { ref, computed, onMounted } from 'vue';
import AuthenticatedLayout      from '@/Layouts/AuthenticatedLayout.vue';
import ComplianceChart          from '@/Components/ComplianceChart.vue';
import AtRiskBeneficiariesTable from '@/Components/AtRiskBeneficiariesTable.vue';
import ComplianceByLocation     from '@/Components/ComplianceByLocation.vue';
import axios                    from 'axios';

/* ── Props from Inertia ──────────────────────────────────── */
const props = defineProps({
    metrics:       { type: Object, required: true },
    trend:         { type: Array,  default: () => [] },
    atRisk:        { type: Array,  default: () => [] },
    byLocation:    { type: Array,  default: () => [] },
    currentPeriod: { type: String, default: '' },
});

/* ── State ───────────────────────────────────────────────── */
const loading        = ref(false);
const atRiskData     = ref(props.atRisk);
const locationData   = ref(props.byLocation);
const trendData      = ref(props.trend);
const trendMonths    = ref(6);

const metricCards = computed(() => [
    { key: 'total',   label: 'Total Beneficiaries',  value: props.metrics.total_beneficiaries, icon: iconPaths.users,    color: '#a5b4fc', bg: 'rgba(99,102,241,0.12)'  },
    { key: 'comp',    label: 'Compliant',             value: `${props.metrics.compliant_pct}%`, icon: iconPaths.check,    color: '#6ee7b7', bg: 'rgba(16,185,129,0.12)' },
    { key: 'pend',    label: 'Pending Verification',  value: props.metrics.pending_verifications, icon: iconPaths.clock, color: '#fcd34d', bg: 'rgba(245,158,11,0.12)'  },
    { key: 'noncomp', label: 'Non-Compliant',         value: `${props.metrics.non_compliant_pct}%`, icon: iconPaths.x,  color: '#f87171', bg: 'rgba(239,68,68,0.12)'    },
]);

/* ── Chart data builders ─────────────────────────────────── */
const pieChartData = computed(() => ({
    labels: ['Compliant', 'Partial', 'Non-Compliant'],
    datasets: [{
        data: [props.metrics.compliant_count, props.metrics.partial_count, props.metrics.non_compliant_count],
        backgroundColor: ['rgba(16,185,129,0.8)', 'rgba(245,158,11,0.8)', 'rgba(239,68,68,0.8)'],
        borderColor:     ['rgba(16,185,129,1)',    'rgba(245,158,11,1)',    'rgba(239,68,68,1)'],
        borderWidth: 2,
        hoverOffset: 6,
    }],
}));

const lineChartData = computed(() => {
    const labels = trendData.value.map(t => t.label);
    return {
        labels,
        datasets: [
            { label: 'Overall',   data: trendData.value.map(t => t.overall),   borderColor: '#a5b4fc', backgroundColor: 'rgba(99,102,241,0.08)',  fill: true, tension: 0.4, borderWidth: 2.5, pointRadius: 3 },
            { label: 'Education', data: trendData.value.map(t => t.education), borderColor: '#6ee7b7', backgroundColor: 'transparent', tension: 0.4, borderWidth: 2, pointRadius: 2, borderDash: [5,3] },
            { label: 'Health',    data: trendData.value.map(t => t.health),    borderColor: '#f9a8d4', backgroundColor: 'transparent', tension: 0.4, borderWidth: 2, pointRadius: 2, borderDash: [5,3] },
            { label: 'FDS',       data: trendData.value.map(t => t.fds),       borderColor: '#fcd34d', backgroundColor: 'transparent', tension: 0.4, borderWidth: 2, pointRadius: 2, borderDash: [5,3] },
        ],
    };
}); // Note: 'borderDash' → use 'borderDash' in Chart.js 4 as 'borderDash' option inside dataset

const barChartData = computed(() => ({
    labels: locationData.value.map(l => l.municipality),
    datasets: [
        { label: 'Compliant %', data: locationData.value.map(l => l.compliant_pct), backgroundColor: 'rgba(16,185,129,0.7)',  borderRadius: 4 },
        { label: 'Partial %',   data: locationData.value.map(l => l.partial > 0 ? Math.round((l.partial / l.total)*100) : 0),  backgroundColor: 'rgba(245,158,11,0.7)', borderRadius: 4 },
    ],
}));

const stackedBarData = computed(() => ({
    labels: trendData.value.map(t => t.label),
    datasets: [
        { label: 'Education', data: trendData.value.map(t => t.education), backgroundColor: 'rgba(99,102,241,0.7)', borderRadius: 4 },
        { label: 'Health',    data: trendData.value.map(t => t.health),    backgroundColor: 'rgba(236,72,153,0.7)', borderRadius: 4 },
        { label: 'FDS',       data: trendData.value.map(t => t.fds),       backgroundColor: 'rgba(245,158,11,0.7)', borderRadius: 4 },
    ],
}));

const stackedBarOptions = {
    scales: {
        x: { stacked: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 11 } } },
        y: { stacked: false, max: 100, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 11 } } },
    },
};

const barOptions = {
    scales: {
        x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 11 } } },
        y: { max: 100, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 11 } } },
    },
};

/* ── Refresh / fetch ─────────────────────────────────────── */
async function refreshTrend() {
    loading.value = true;
    try {
        const res = await axios.get(`/dashboard/compliance/trends?months=${trendMonths.value}`);
        trendData.value = res.data.trend ?? [];
    } finally { loading.value = false; }
}

async function refreshAtRisk() {
    loading.value = true;
    try {
        const res = await axios.get('/dashboard/compliance/at-risk');
        atRiskData.value = res.data.data ?? [];
    } finally { loading.value = false; }
}

async function refreshLocation() {
    loading.value = true;
    try {
        const res = await axios.get('/dashboard/compliance/location');
        locationData.value = res.data.data ?? [];
    } finally { loading.value = false; }
}

function refreshAll() {
    refreshTrend();
    refreshAtRisk();
    refreshLocation();
}

function viewBeneficiary(b) {
    window.location.href = `/compliance/page/${b.beneficiary_id}`;
}

/* ── Icon paths ──────────────────────────────────────────── */
const iconPaths = {
    users: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    check: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    clock: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
    x:     'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="dash-hd">
                <div class="hd-left">
                    <div class="hd-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="hd-title">Compliance Dashboard</h1>
                        <p class="hd-sub">Period: <span class="hd-period">{{ currentPeriod }}</span></p>
                    </div>
                </div>
                <button class="btn-refresh" @click="refreshAll" :disabled="loading" id="dashboard-refresh-btn">
                    <svg viewBox="0 0 20 20" fill="currentColor" :class="{ 'spin': loading }">
                        <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd"/>
                    </svg>
                    Refresh
                </button>
            </div>
        </template>

        <!-- ── Metric Cards ───────────────────────────────────── -->
        <div class="metrics-grid">
            <div v-for="m in metricCards" :key="m.key" class="metric-card" :style="`--mc:${m.color};--bg:${m.bg}`">
                <div class="mc-icon-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="m.icon"/>
                    </svg>
                </div>
                <div class="mc-body">
                    <p class="mc-label">{{ m.label }}</p>
                    <p class="mc-value">{{ m.value }}</p>
                </div>
            </div>
        </div>

        <!-- ── By Type Row ────────────────────────────────────── -->
        <div class="type-row">
            <div v-for="([key, label, color]) in [['education','Education','#a5b4fc'],['health','Health','#f9a8d4'],['fds','FDS','#fcd34d']]"
                 :key="key" class="type-mini">
                <span class="tm-label">{{ label }}</span>
                <div class="tm-bar-bg">
                    <div class="tm-bar-fill"
                         :style="{width: metrics.by_type[key] + '%', background: color}">
                    </div>
                </div>
                <span class="tm-pct" :style="`color:${color}`">{{ metrics.by_type[key] }}%</span>
            </div>
        </div>

        <!-- ── Charts Grid ────────────────────────────────────── -->
        <div class="charts-grid">

            <!-- Pie: Distribution -->
            <div class="chart-card chart-sm">
                <ComplianceChart chart-type="doughnut" :chart-data="pieChartData" title="Compliance Distribution"
                    :options="{ cutout: '65%', plugins: { legend: { position: 'bottom' } } }" :height="220"/>
            </div>

            <!-- Line: Trend -->
            <div class="chart-card chart-lg">
                <div class="chart-header">
                    <ComplianceChart chart-type="line" :chart-data="lineChartData" title="Compliance Trend" :height="220"/>
                    <div class="trend-controls">
                        <select v-model="trendMonths" @change="refreshTrend" class="month-select" id="trend-month-select">
                            <option :value="3">3 months</option>
                            <option :value="6">6 months</option>
                            <option :value="12">12 months</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bar: By Location -->
            <div class="chart-card chart-lg">
                <ComplianceChart chart-type="bar" :chart-data="barChartData" :options="barOptions"
                    title="Compliance by Municipality" :height="220"/>
            </div>

            <!-- Stacked Bar: By Type over Time -->
            <div class="chart-card chart-sm">
                <ComplianceChart chart-type="bar" :chart-data="stackedBarData" :options="stackedBarOptions"
                    title="Type Breakdown (Trend)" :height="220"/>
            </div>

        </div>

        <!-- ── At-Risk Table ──────────────────────────────────── -->
        <AtRiskBeneficiariesTable
            :beneficiaries="atRiskData"
            :loading="loading"
            @view="viewBeneficiary"
            @remind="() => {}"
        />

        <!-- ── Compliance by Location ─────────────────────────── -->
        <ComplianceByLocation
            :data="locationData"
            :loading="loading"
            @drill-down="(loc) => {}"
        />
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
* { box-sizing: border-box; }

/* Header */
.dash-hd { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.hd-left  { display: flex; align-items: flex-start; gap: 0.875rem; }
.hd-icon {
    width: 46px; height: 46px; border-radius: 0.875rem; flex-shrink: 0;
    background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.25);
    display: flex; align-items: center; justify-content: center; color: #a5b4fc;
}
.hd-icon svg { width: 24px; height: 24px; }
.hd-title  { font-size: 1.25rem; font-weight: 800; color: #f1f5f9; margin: 0; font-family: 'Inter', sans-serif; }
.hd-sub    { font-size: 0.8rem; color: #64748b; margin: 0; font-family: 'Inter', sans-serif; }
.hd-period { color: #a5b4fc; font-weight: 600; }

.btn-refresh {
    display: flex; align-items: center; gap: 0.5rem;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.625rem; padding: 0.5rem 1rem;
    color: #94a3b8; font-size: 0.875rem; font-weight: 600; cursor: pointer;
    font-family: 'Inter', sans-serif; transition: all 0.2s;
}
.btn-refresh:hover:not(:disabled) { background: rgba(255,255,255,0.1); color: #f1f5f9; }
.btn-refresh:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-refresh svg { width: 16px; height: 16px; }
.spin { animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Metrics grid */
.metrics-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.875rem; margin-bottom: 1rem;
}
@media (max-width: 900px) { .metrics-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px) { .metrics-grid { grid-template-columns: 1fr; } }

.metric-card {
    background: var(--bg, rgba(99,102,241,0.12));
    border: 1px solid color-mix(in srgb, var(--mc, #a5b4fc) 25%, transparent);
    border-radius: 1rem; padding: 1.25rem;
    display: flex; align-items: center; gap: 1rem;
    transition: transform 0.2s;
}
.metric-card:hover { transform: translateY(-2px); }
.mc-icon-wrap {
    width: 44px; height: 44px; border-radius: 0.75rem; flex-shrink: 0;
    background: color-mix(in srgb, var(--mc) 20%, transparent);
    display: flex; align-items: center; justify-content: center;
    color: var(--mc);
}
.mc-icon-wrap svg { width: 22px; height: 22px; }
.mc-body { display: flex; flex-direction: column; gap: 0.25rem; }
.mc-label { font-size: 0.75rem; font-weight: 600; color: #64748b; margin: 0; font-family: 'Inter', sans-serif; text-transform: uppercase; letter-spacing: 0.04em; }
.mc-value { font-size: 1.625rem; font-weight: 800; color: var(--mc); margin: 0; font-family: 'Inter', sans-serif; }

/* Type row */
.type-row {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1rem;
}
@media (max-width: 500px) { .type-row { grid-template-columns: 1fr; } }
.type-mini {
    display: flex; align-items: center; gap: 0.625rem;
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 0.75rem; padding: 0.75rem 1rem;
}
.tm-label { font-size: 0.75rem; font-weight: 700; color: #64748b; width: 58px; flex-shrink: 0; font-family: 'Inter', sans-serif; }
.tm-bar-bg { flex: 1; height: 8px; background: rgba(255,255,255,0.07); border-radius: 999px; overflow: hidden; }
.tm-bar-fill { height: 100%; border-radius: 999px; transition: width 0.6s ease; }
.tm-pct { font-size: 0.875rem; font-weight: 800; min-width: 40px; text-align: right; font-family: 'Inter', sans-serif; }

/* Charts */
.charts-grid {
    display: grid;
    grid-template-columns: 1fr 2fr;
    grid-template-rows: auto auto;
    gap: 1rem;
    margin-bottom: 1rem;
}
@media (max-width: 900px) { .charts-grid { grid-template-columns: 1fr; } }

.chart-card {
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1.25rem; padding: 1.25rem;
}
.chart-sm { grid-column: 1; }
.chart-lg { grid-column: 2; }
@media (max-width: 900px) { .chart-sm, .chart-lg { grid-column: 1; } }

.chart-header { display: flex; flex-direction: column; gap: 0.625rem; }
.trend-controls { display: flex; justify-content: flex-end; }
.month-select {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.5rem; padding: 0.3rem 0.625rem; color: #94a3b8;
    font-size: 0.8rem; font-family: 'Inter', sans-serif; cursor: pointer; outline: none;
}
.month-select option { background: #1e293b; color: #f1f5f9; }
</style>
