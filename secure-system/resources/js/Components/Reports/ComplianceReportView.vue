<template>
    <div class="report-view">
        <!-- Rate Gauges -->
        <div class="gauge-row">
            <div class="gauge-card" :class="overallColor">
                <div class="gauge-title">Overall Compliance</div>
                <div class="gauge-value">{{ data.overall_rate }}%</div>
                <div class="gauge-bar">
                    <div
                        class="gauge-fill"
                        :style="{
                            width: data.overall_rate + '%',
                            background: gaugeColor(data.overall_rate),
                        }"
                    ></div>
                </div>
                <div class="gauge-sub">
                    {{ data.compliant_count }} / {{ data.total }} compliant
                </div>
            </div>
            <div class="gauge-card" :class="rateColor(data.education_rate)">
                <div class="gauge-title">Education</div>
                <div class="gauge-value" style="color: #2563eb">
                    {{ data.education_rate }}%
                </div>
                <div class="gauge-bar">
                    <div
                        class="gauge-fill"
                        :style="{
                            width: data.education_rate + '%',
                            background: '#3b82f6',
                        }"
                    ></div>
                </div>
            </div>
            <div class="gauge-card" :class="rateColor(data.health_rate)">
                <div class="gauge-title">Health</div>
                <div class="gauge-value" style="color: #dc2626">
                    {{ data.health_rate }}%
                </div>
                <div class="gauge-bar">
                    <div
                        class="gauge-fill"
                        :style="{
                            width: data.health_rate + '%',
                            background: '#f43f5e',
                        }"
                    ></div>
                </div>
            </div>
            <div class="gauge-card" :class="rateColor(data.fds_rate)">
                <div class="gauge-title">FDS (Family Dev. Session)</div>
                <div class="gauge-value" style="color: #7c3aed">
                    {{ data.fds_rate }}%
                </div>
                <div class="gauge-bar">
                    <div
                        class="gauge-fill"
                        :style="{
                            width: data.fds_rate + '%',
                            background: '#a855f7',
                        }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Counts Row -->
        <div class="metric-cards">
            <div class="metric-card green">
                <div class="metric-icon">✅</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.compliant_count) }}
                    </div>
                    <div class="metric-label">Compliant</div>
                </div>
            </div>
            <div class="metric-card red">
                <div class="metric-icon">❌</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.non_compliant_count) }}
                    </div>
                    <div class="metric-label">Non-Compliant</div>
                </div>
            </div>
            <div class="metric-card amber">
                <div class="metric-icon">⚠️</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.partial_count) }}
                    </div>
                    <div class="metric-label">Partial</div>
                </div>
            </div>
            <div class="metric-card blue">
                <div class="metric-icon">👥</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.total) }}
                    </div>
                    <div class="metric-label">Total Tracked</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-row">
            <!-- Trend Line -->
            <div class="chart-card wide">
                <h3 class="chart-title">Compliance Trend (Last 12 Months)</h3>
                <apexchart
                    type="line"
                    height="280"
                    :options="trendOptions"
                    :series="trendSeries"
                />
            </div>
            <!-- Stacked Bar by Location -->
            <div class="chart-card">
                <h3 class="chart-title">Status by Municipality</h3>
                <apexchart
                    type="bar"
                    height="280"
                    :options="locationOptions"
                    :series="locationSeries"
                />
            </div>
        </div>

        <!-- Non-Compliant Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="chart-title">Non-Compliant Beneficiaries</h3>
                <span class="table-badge red"
                    >{{ data.non_compliant_list?.length || 0 }} records</span
                >
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>BIN</th>
                            <th>Municipality</th>
                            <th>Education</th>
                            <th>Health</th>
                            <th>FDS</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, idx) in data.non_compliant_list"
                            :key="idx"
                        >
                            <td>{{ row.name }}</td>
                            <td class="mono">{{ row.bin }}</td>
                            <td>{{ row.municipality }}</td>
                            <td>
                                <span :class="pctClass(row.education_rate)"
                                    >{{ row.education_rate }}%</span
                                >
                            </td>
                            <td>
                                <span :class="pctClass(row.health_rate)"
                                    >{{ row.health_rate }}%</span
                                >
                            </td>
                            <td>
                                <span :class="pctClass(row.fds_rate)"
                                    >{{ row.fds_rate }}%</span
                                >
                            </td>
                            <td>
                                <span class="badge badge-red">{{
                                    row.overall_status?.replace("_", " ")
                                }}</span>
                            </td>
                        </tr>
                        <tr v-if="!data.non_compliant_list?.length">
                            <td colspan="7" class="empty-row">
                                🎉 No non-compliant beneficiaries found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- At-Risk Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="chart-title">At-Risk Beneficiaries</h3>
                <span class="table-badge amber"
                    >{{ data.at_risk?.length || 0 }} at risk</span
                >
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>BIN</th>
                            <th>Municipality</th>
                            <th>Overall Status</th>
                            <th>Education</th>
                            <th>Health</th>
                            <th>FDS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in data.at_risk" :key="idx">
                            <td>{{ row.name }}</td>
                            <td class="mono">{{ row.bin }}</td>
                            <td>{{ row.municipality }}</td>
                            <td>
                                <span class="badge badge-amber">{{
                                    row.overall_status?.replace("_", " ")
                                }}</span>
                            </td>
                            <td>{{ row.education_rate }}%</td>
                            <td>{{ row.health_rate }}%</td>
                            <td>{{ row.fds_rate }}%</td>
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
const formatNumber = (n) => Number(n || 0).toLocaleString("en-PH");

const gaugeColor = (val) =>
    val >= 80 ? "#22c55e" : val >= 60 ? "#f59e0b" : "#f43f5e";
const overallColor = computed(() => {
    const r = props.data?.overall_rate || 0;
    return r >= 80 ? "gauge-green" : r >= 60 ? "gauge-amber" : "gauge-red";
});
const rateColor = (r) =>
    r >= 80 ? "gauge-green" : r >= 60 ? "gauge-amber" : "gauge-red";
const pctClass = (v) =>
    v >= 80 ? "pct-green" : v >= 60 ? "pct-amber" : "pct-red";

// Trend chart
const trendSeries = computed(() => [
    {
        name: "Education",
        data: (props.data?.trend || []).map((r) => r.education),
    },
    { name: "Health", data: (props.data?.trend || []).map((r) => r.health) },
    { name: "FDS", data: (props.data?.trend || []).map((r) => r.fds) },
]);
const trendOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: { categories: (props.data?.trend || []).map((r) => r.month) },
    colors: ["#3b82f6", "#f43f5e", "#a855f7"],
    stroke: { curve: "smooth", width: 2 },
    yaxis: { min: 0, max: 100, labels: { formatter: (v) => v + "%" } },
    legend: { position: "bottom" },
    dataLabels: { enabled: false },
}));

// By location stacked bar
const locationSeries = computed(() => [
    {
        name: "Compliant",
        data: (props.data?.by_location || []).map((r) => r.compliant),
    },
    {
        name: "Non-Compliant",
        data: (props.data?.by_location || []).map((r) => r.non_compliant),
    },
    {
        name: "Partial",
        data: (props.data?.by_location || []).map((r) => r.partial),
    },
]);
const locationOptions = computed(() => ({
    chart: { toolbar: { show: false }, stacked: true },
    xaxis: {
        categories: (props.data?.by_location || []).map((r) => r.municipality),
    },
    colors: ["#22c55e", "#f43f5e", "#f59e0b"],
    plotOptions: { bar: { horizontal: true, borderRadius: 2 } },
    dataLabels: { enabled: false },
    legend: { position: "bottom" },
}));
</script>

<style scoped>
.report-view {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.gauge-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
.gauge-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}
.gauge-card.gauge-green {
    border-color: #bbf7d0;
    background: #f0fdf4;
}
.gauge-card.gauge-amber {
    border-color: #fde68a;
    background: #fffbeb;
}
.gauge-card.gauge-red {
    border-color: #fecdd3;
    background: #fff1f2;
}
.gauge-title {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #64748b;
    margin-bottom: 8px;
}
.gauge-value {
    font-size: 36px;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 12px;
}
.gauge-bar {
    height: 8px;
    background: #e2e8f0;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 8px;
}
.gauge-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.8s ease;
}
.gauge-sub {
    font-size: 11px;
    color: #94a3b8;
}

.metric-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
.metric-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 18px;
    border-radius: 12px;
    border: 1px solid;
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

.charts-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}
.chart-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
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
.table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}
.table-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.table-badge.red {
    background: #fee2e2;
    color: #dc2626;
}
.table-badge.amber {
    background: #fef9c3;
    color: #a16207;
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
}
.data-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
    color: #374151;
}
.data-table tbody tr:hover {
    background: #f8fafc;
}
.mono {
    font-family: "Courier New", monospace;
    font-size: 12px;
}
.empty-row {
    text-align: center;
    color: #94a3b8;
    padding: 24px;
}
.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}
.badge-red {
    background: #fee2e2;
    color: #dc2626;
}
.badge-amber {
    background: #fef9c3;
    color: #a16207;
}
.pct-green {
    font-weight: 700;
    color: #16a34a;
}
.pct-amber {
    font-weight: 700;
    color: #d97706;
}
.pct-red {
    font-weight: 700;
    color: #dc2626;
}
</style>
