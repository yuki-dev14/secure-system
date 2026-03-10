<template>
    <div class="report-view">
        <!-- Metric Cards -->
        <div class="metric-cards">
            <div class="metric-card blue">
                <div class="metric-icon">👥</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.total) }}
                    </div>
                    <div class="metric-label">Total Beneficiaries</div>
                </div>
            </div>
            <div class="metric-card green">
                <div class="metric-icon">✅</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.active) }}
                    </div>
                    <div class="metric-label">Active</div>
                    <div class="metric-sub">
                        {{ data.active_percentage }}% of total
                    </div>
                </div>
            </div>
            <div class="metric-card red">
                <div class="metric-icon">❌</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.inactive) }}
                    </div>
                    <div class="metric-label">Inactive</div>
                    <div class="metric-sub">
                        {{ data.inactive_percentage }}% of total
                    </div>
                </div>
            </div>
            <div class="metric-card purple">
                <div class="metric-icon">🆕</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.newly_registered) }}
                    </div>
                    <div class="metric-label">Newly Registered</div>
                    <div class="metric-sub">In selected period</div>
                </div>
            </div>
            <div class="metric-card sky">
                <div class="metric-icon">🏠</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ data.avg_household_size }}
                    </div>
                    <div class="metric-label">Avg Household Size</div>
                </div>
            </div>
            <div class="metric-card amber">
                <div class="metric-icon">💰</div>
                <div class="metric-body">
                    <div class="metric-value">
                        ₱{{ formatNumber(Math.round(data.avg_annual_income)) }}
                    </div>
                    <div class="metric-label">Avg Annual Income</div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="charts-row">
            <!-- Active vs Inactive Pie -->
            <div class="chart-card">
                <h3 class="chart-title">Active vs Inactive</h3>
                <apexchart
                    type="donut"
                    height="280"
                    :options="activeInactiveOptions"
                    :series="activeInactiveSeries"
                />
            </div>

            <!-- Registrations Over Time -->
            <div class="chart-card wide">
                <h3 class="chart-title">
                    Registrations Over Time (Last 12 Months)
                </h3>
                <apexchart
                    type="area"
                    height="280"
                    :options="registrationsOptions"
                    :series="registrationsSeries"
                />
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="charts-row">
            <!-- Civil Status Donut -->
            <div class="chart-card">
                <h3 class="chart-title">Civil Status Distribution</h3>
                <apexchart
                    type="donut"
                    height="280"
                    :options="civilStatusOptions"
                    :series="civilStatusSeries"
                />
            </div>

            <!-- Income Distribution Bar -->
            <div class="chart-card wide">
                <h3 class="chart-title">Income Distribution</h3>
                <apexchart
                    type="bar"
                    height="280"
                    :options="incomeOptions"
                    :series="incomeSeries"
                />
            </div>
        </div>

        <!-- By Municipality Table -->
        <div class="table-card">
            <h3 class="chart-title">Beneficiaries by Municipality</h3>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Municipality</th>
                            <th>Count</th>
                            <th>Share</th>
                            <th>Bar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in byMunicipality" :key="idx">
                            <td class="rank">{{ idx + 1 }}</td>
                            <td>{{ row.municipality }}</td>
                            <td class="number">
                                {{ formatNumber(row.count) }}
                            </td>
                            <td class="number">{{ getShare(row.count) }}%</td>
                            <td>
                                <div class="progress-bar">
                                    <div
                                        class="progress-fill blue"
                                        :style="{
                                            width: getShare(row.count) + '%',
                                        }"
                                    ></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from "vue";
import VueApexCharts from "vue3-apexcharts";

const apexchart = VueApexCharts;

const props = defineProps({ data: Object, filters: Object });

// Helpers
const formatNumber = (n) => Number(n || 0).toLocaleString("en-PH");
const total = computed(() => props.data?.total || 1);

function getShare(count) {
    return ((count / total.value) * 100).toFixed(1);
}

const byMunicipality = computed(() => {
    const arr = props.data?.by_municipality || [];
    return arr.map((r) => ({
        municipality: r.municipality ?? r?.municipality,
        count: r.count ?? r?.count,
    }));
});

// Active vs Inactive
const activeInactiveSeries = computed(() => [
    props.data?.active || 0,
    props.data?.inactive || 0,
]);
const activeInactiveOptions = computed(() => ({
    labels: ["Active", "Inactive"],
    colors: ["#22c55e", "#f43f5e"],
    legend: { position: "bottom" },
    dataLabels: { enabled: true },
    plotOptions: { pie: { donut: { size: "60%" } } },
}));

// Registrations over time
const registrationsSeries = computed(() => [
    {
        name: "Registrations",
        data: (props.data?.registrations_over_time || []).map((r) => r.count),
    },
]);
const registrationsOptions = computed(() => ({
    chart: { toolbar: { show: false }, animations: { enabled: true } },
    xaxis: {
        categories: (props.data?.registrations_over_time || []).map(
            (r) => r.month,
        ),
    },
    colors: ["#3b82f6"],
    fill: {
        type: "gradient",
        gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 },
    },
    stroke: { curve: "smooth", width: 2 },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v) => formatNumber(v) } },
}));

// Civil status
const civilStatusData = computed(() => {
    const cs = props.data?.civil_status || {};
    return {
        labels: Object.keys(cs).map(
            (l) => l.charAt(0).toUpperCase() + l.slice(1),
        ),
        values: Object.values(cs),
    };
});
const civilStatusSeries = computed(() => civilStatusData.value.values);
const civilStatusOptions = computed(() => ({
    labels: civilStatusData.value.labels,
    colors: ["#6366f1", "#f59e0b", "#10b981", "#f43f5e", "#06b6d4"],
    legend: { position: "bottom" },
    plotOptions: { pie: { donut: { size: "60%" } } },
}));

// Income distribution
const incomeLabels = [
    "<₱10K",
    "₱10-20K",
    "₱20-30K",
    "₱30-50K",
    "₱50-100K",
    ">₱100K",
];
const incomeKeys = [
    "under_10k",
    "10k_20k",
    "20k_30k",
    "30k_50k",
    "50k_100k",
    "over_100k",
];
const incomeSeries = computed(() => [
    {
        name: "Beneficiaries",
        data: incomeKeys.map((k) => props.data?.income_distribution?.[k] || 0),
    },
]);
const incomeOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: { categories: incomeLabels },
    colors: ["#6366f1"],
    dataLabels: { enabled: false },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "55%" } },
    tooltip: { y: { formatter: (v) => formatNumber(v) } },
}));
</script>

<style scoped>
.report-view {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.metric-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
}
.metric-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px;
    border-radius: 12px;
    border: 1px solid;
    transition:
        transform 0.2s,
        box-shadow 0.2s;
}
.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
}
.metric-card.blue {
    background: #eff6ff;
    border-color: #bfdbfe;
}
.metric-card.green {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.metric-card.red {
    background: #fff1f2;
    border-color: #fecdd3;
}
.metric-card.purple {
    background: #faf5ff;
    border-color: #e9d5ff;
}
.metric-card.sky {
    background: #f0f9ff;
    border-color: #bae6fd;
}
.metric-card.amber {
    background: #fffbeb;
    border-color: #fde68a;
}
.metric-icon {
    font-size: 28px;
}
.metric-value {
    font-size: 24px;
    font-weight: 800;
    color: #1e293b;
}
.metric-label {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.metric-sub {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 2px;
}

.charts-row {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
}
.chart-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}
.chart-card.wide {
    grid-column: span 1;
}
.chart-title {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
}

.table-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}
.table-wrapper {
    overflow-x: auto;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.data-table thead tr {
    background: #f8fafc;
}
.data-table th {
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 2px solid #e2e8f0;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.data-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #374151;
}
.data-table tbody tr:hover {
    background: #f8fafc;
}
.rank {
    font-weight: 700;
    color: #94a3b8;
    width: 30px;
}
.number {
    text-align: right;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}
.progress-bar {
    height: 8px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
    min-width: 100px;
}
.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s ease;
}
.progress-fill.blue {
    background: linear-gradient(90deg, #3b82f6, #6366f1);
}
</style>
