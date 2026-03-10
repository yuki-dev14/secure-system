<template>
    <div class="report-view">
        <!-- Metric Cards -->
        <div class="metric-cards">
            <div class="metric-card indigo">
                <div class="metric-icon">💰</div>
                <div class="metric-body">
                    <div class="metric-value">
                        ₱{{ formatCurrency(data.total_amount) }}
                    </div>
                    <div class="metric-label">Total Amount Distributed</div>
                </div>
            </div>
            <div class="metric-card blue">
                <div class="metric-icon">📦</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.total_count) }}
                    </div>
                    <div class="metric-label">Distribution Count</div>
                </div>
            </div>
            <div class="metric-card green">
                <div class="metric-icon">📊</div>
                <div class="metric-body">
                    <div class="metric-value">
                        ₱{{ formatCurrency(data.avg_payout) }}
                    </div>
                    <div class="metric-label">Average Payout</div>
                </div>
            </div>
            <div class="metric-card sky">
                <div class="metric-icon">👥</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.unique_beneficiaries) }}
                    </div>
                    <div class="metric-label">Beneficiaries Served</div>
                </div>
            </div>
            <div class="metric-card green">
                <div class="metric-icon">✅</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.reconciled) }}
                    </div>
                    <div class="metric-label">Reconciled</div>
                </div>
            </div>
            <div class="metric-card red">
                <div class="metric-icon">⚠️</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.unreconciled) }}
                    </div>
                    <div class="metric-label">Unreconciled</div>
                </div>
            </div>
        </div>

        <!-- Anomalies Alert -->
        <div class="anomaly-alert" v-if="data.anomalies?.length">
            <span class="alert-icon">⚠️</span>
            <div>
                <strong
                    >{{ data.anomalies.length }} Anomaly/Anomalies
                    Detected</strong
                >
                <p>
                    Review the detailed distribution table for unusual patterns.
                </p>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="charts-row">
            <!-- Distribution over time -->
            <div class="chart-card wide">
                <h3 class="chart-title">Distributions Over Time</h3>
                <apexchart
                    type="line"
                    height="280"
                    :options="timelineOptions"
                    :series="timelineSeries"
                />
            </div>
            <!-- Payment method pie -->
            <div class="chart-card">
                <h3 class="chart-title">By Payment Method</h3>
                <apexchart
                    type="donut"
                    height="280"
                    :options="methodOptions"
                    :series="methodSeries"
                />
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="charts-row">
            <!-- By Office Bar -->
            <div class="chart-card">
                <h3 class="chart-title">Distributions by Office</h3>
                <apexchart
                    type="bar"
                    height="280"
                    :options="officeOptions"
                    :series="officeSeries"
                />
            </div>
            <!-- Top Officers -->
            <div class="chart-card wide">
                <h3 class="chart-title">Top Distribution Officers</h3>
                <apexchart
                    type="bar"
                    height="280"
                    :options="officerOptions"
                    :series="officerSeries"
                />
            </div>
        </div>

        <!-- Officers Table -->
        <div class="table-card">
            <h3 class="chart-title">Distribution Officer Details</h3>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Office</th>
                            <th>Count</th>
                            <th>Total Distributed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in data.by_officer" :key="idx">
                            <td class="rank">{{ idx + 1 }}</td>
                            <td>{{ row.name }}</td>
                            <td>{{ row.office || "—" }}</td>
                            <td class="number">
                                {{ formatNumber(row.count) }}
                            </td>
                            <td class="number currency">
                                ₱{{ formatCurrency(row.total) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Anomalies Table -->
        <div class="table-card" v-if="data.anomalies?.length">
            <div class="table-header">
                <h3 class="chart-title" style="color: #dc2626">
                    🚨 Detected Anomalies
                </h3>
                <span class="table-badge red">{{ data.anomalies.length }}</span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Beneficiary</th>
                            <th>Amount / Count</th>
                            <th>Officer</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(a, idx) in data.anomalies" :key="idx">
                            <td>
                                <span class="badge badge-red">{{
                                    a.type?.replace("_", " ")
                                }}</span>
                            </td>
                            <td>{{ a.beneficiary || "—" }}</td>
                            <td class="number">
                                {{
                                    a.amount
                                        ? "₱" + formatCurrency(a.amount)
                                        : a.count + " records"
                                }}
                            </td>
                            <td>{{ a.officer || "—" }}</td>
                            <td>{{ a.date ? a.date.slice(0, 10) : "—" }}</td>
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
const formatCurrency = (n) =>
    Number(n || 0).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

// Timeline
const timelineSeries = computed(() => [
    { name: "Count", data: (props.data?.by_day || []).map((r) => r.count) },
]);
const timelineOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: { categories: (props.data?.by_day || []).map((r) => r.day) },
    colors: ["#6366f1"],
    stroke: { curve: "smooth", width: 2 },
    dataLabels: { enabled: false },
    tooltip: { y: { formatter: (v) => formatNumber(v) } },
}));

// Payment method
const methodSeries = computed(() =>
    (props.data?.by_payment_method || []).map((r) => r.count || 0),
);
const methodOptions = computed(() => ({
    labels: (props.data?.by_payment_method || []).map(
        (r) => r.payment_method?.replace("_", " ") || "Unknown",
    ),
    colors: ["#6366f1", "#22c55e", "#f59e0b", "#f43f5e"],
    legend: { position: "bottom" },
    dataLabels: { enabled: true },
}));

// By Office
const officeSeries = computed(() => [
    {
        name: "Distributions",
        data: (props.data?.by_office || []).map((r) => r.count || 0),
    },
]);
const officeOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: {
        categories: (props.data?.by_office || []).map(
            (r) => r.office || "Unknown",
        ),
    },
    colors: ["#3b82f6"],
    dataLabels: { enabled: false },
    plotOptions: { bar: { borderRadius: 4, horizontal: true } },
}));

// Top officers
const officerSeries = computed(() => [
    {
        name: "Total (₱)",
        data: (props.data?.by_officer || [])
            .slice(0, 10)
            .map((r) => Math.round(r.total || 0)),
    },
]);
const officerOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: {
        categories: (props.data?.by_officer || [])
            .slice(0, 10)
            .map((r) => r.name),
    },
    colors: ["#22c55e"],
    dataLabels: { enabled: false },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "50%" } },
    tooltip: { y: { formatter: (v) => "₱" + formatCurrency(v) } },
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
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
}
.metric-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-radius: 12px;
    border: 1px solid;
}
.metric-card.indigo {
    background: #eef2ff;
    border-color: #c7d2fe;
}
.metric-card.blue {
    background: #eff6ff;
    border-color: #bfdbfe;
}
.metric-card.green {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.metric-card.sky {
    background: #f0f9ff;
    border-color: #bae6fd;
}
.metric-card.red {
    background: #fff1f2;
    border-color: #fecdd3;
}
.metric-icon {
    font-size: 24px;
}
.metric-value {
    font-size: 20px;
    font-weight: 800;
    color: #1e293b;
}
.metric-label {
    font-size: 10px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.anomaly-alert {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 14px 20px;
}
.alert-icon {
    font-size: 24px;
}
.anomaly-alert strong {
    font-size: 14px;
    color: #92400e;
    display: block;
}
.anomaly-alert p {
    font-size: 12px;
    color: #a16207;
    margin-top: 2px;
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
.rank {
    font-weight: 700;
    color: #94a3b8;
}
.number {
    text-align: right;
    font-weight: 600;
}
.currency {
    color: #15803d;
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
</style>
