<template>
    <div class="report-view">
        <!-- Metric Cards -->
        <div class="metric-cards">
            <div class="metric-card sky">
                <div class="metric-icon">📱</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.total) }}
                    </div>
                    <div class="metric-label">Total QR Codes</div>
                </div>
            </div>
            <div class="metric-card green">
                <div class="metric-icon">✅</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.active) }}
                    </div>
                    <div class="metric-label">Active</div>
                </div>
            </div>
            <div class="metric-card amber">
                <div class="metric-icon">⏰</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.expired) }}
                    </div>
                    <div class="metric-label">Expired</div>
                </div>
            </div>
            <div class="metric-card red">
                <div class="metric-icon">🚫</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.invalid) }}
                    </div>
                    <div class="metric-label">Invalid</div>
                </div>
            </div>
            <div class="metric-card purple">
                <div class="metric-icon">🔄</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.regenerated) }}
                    </div>
                    <div class="metric-label">Regenerated</div>
                    <div class="metric-sub">
                        {{ data.replacement_rate }}% rate
                    </div>
                </div>
            </div>
            <div class="metric-card red">
                <div class="metric-icon">❌</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.failed_scans) }}
                    </div>
                    <div class="metric-label">Failed Scans</div>
                </div>
            </div>
            <div class="metric-card blue">
                <div class="metric-icon">⏱️</div>
                <div class="metric-body">
                    <div class="metric-value">{{ data.avg_lifespan_days }}</div>
                    <div class="metric-label">Avg Lifespan (days)</div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-row-3">
            <!-- Status Donut -->
            <div class="chart-card">
                <h3 class="chart-title">QR Code Status</h3>
                <apexchart
                    type="donut"
                    height="250"
                    :options="statusOptions"
                    :series="statusSeries"
                />
            </div>
            <!-- Regeneration Reasons Bar -->
            <div class="chart-card">
                <h3 class="chart-title">Regeneration Reasons</h3>
                <apexchart
                    type="bar"
                    height="250"
                    :options="reasonOptions"
                    :series="reasonSeries"
                />
            </div>
            <!-- QR Issuance Over Time -->
            <div class="chart-card">
                <h3 class="chart-title">QR Issuance Over Time</h3>
                <apexchart
                    type="line"
                    height="250"
                    :options="issuanceOptions"
                    :series="issuanceSeries"
                />
            </div>
        </div>

        <!-- High Regen by Office -->
        <div class="table-card" v-if="data.high_regen_by_office?.length">
            <h3 class="chart-title">High Regeneration Rate by Office</h3>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Office</th>
                            <th>Regeneration Count</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, idx) in data.high_regen_by_office"
                            :key="idx"
                        >
                            <td>{{ row.office || "Unknown" }}</td>
                            <td class="number">
                                {{ formatNumber(row.regen_count) }}
                            </td>
                            <td>
                                <span
                                    :class="
                                        idx === 0
                                            ? 'badge badge-red'
                                            : idx <= 2
                                              ? 'badge badge-amber'
                                              : 'badge badge-gray'
                                    "
                                >
                                    {{
                                        idx === 0
                                            ? "Highest Priority"
                                            : idx <= 2
                                              ? "High"
                                              : "Monitor"
                                    }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Failed Scans Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="chart-title">
                    Beneficiaries with Frequent Scan Failures
                </h3>
                <span
                    class="badge badge-red"
                    v-if="data.failed_scans_by_beneficiary?.length"
                    >{{ data.failed_scans_by_beneficiary.length }} flagged</span
                >
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Beneficiary</th>
                            <th>BIN</th>
                            <th>Failure Count</th>
                            <th>Action Needed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(
                                row, idx
                            ) in data.failed_scans_by_beneficiary"
                            :key="idx"
                        >
                            <td>{{ row.beneficiary }}</td>
                            <td class="mono">{{ row.bin }}</td>
                            <td class="number">
                                <span class="badge badge-red">{{
                                    row.failures
                                }}</span>
                            </td>
                            <td>
                                <span class="action-tag"
                                    >Regenerate QR Code</span
                                >
                            </td>
                        </tr>
                        <tr v-if="!data.failed_scans_by_beneficiary?.length">
                            <td colspan="4" class="empty-row">
                                ✅ No excessive scan failures detected.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Replacement Planning Widget -->
        <div class="planning-widget">
            <h3 class="chart-title">QR Code Replacement Planning</h3>
            <div class="planning-grid">
                <div class="planning-item cyan">
                    <div class="planning-label">Codes Expiring Soon (Est.)</div>
                    <div class="planning-value">
                        {{ Math.ceil((data.active || 0) * 0.1) }}
                    </div>
                    <div class="planning-sub">Next 30 days</div>
                </div>
                <div class="planning-item amber">
                    <div class="planning-label">Currently Expired</div>
                    <div class="planning-value">
                        {{ formatNumber(data.expired) }}
                    </div>
                    <div class="planning-sub">Need renewal</div>
                </div>
                <div class="planning-item red">
                    <div class="planning-label">Invalid Codes</div>
                    <div class="planning-value">
                        {{ formatNumber(data.invalid) }}
                    </div>
                    <div class="planning-sub">Need regeneration</div>
                </div>
                <div class="planning-item green">
                    <div class="planning-label">Avg Lifespan</div>
                    <div class="planning-value">
                        {{ data.avg_lifespan_days }} days
                    </div>
                    <div class="planning-sub">Per QR code</div>
                </div>
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

// Status Donut
const statusSeries = computed(() => [
    props.data?.active || 0,
    props.data?.expired || 0,
    props.data?.invalid || 0,
]);
const statusOptions = computed(() => ({
    labels: ["Active", "Expired", "Invalid"],
    colors: ["#22c55e", "#f59e0b", "#f43f5e"],
    legend: { position: "bottom" },
    plotOptions: { pie: { donut: { size: "60%" } } },
}));

// Regeneration reasons
const reasonData = computed(() => props.data?.by_reason || {});
const reasonSeries = computed(() => [
    { name: "Count", data: Object.values(reasonData.value) },
]);
const reasonOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: {
        categories: Object.keys(reasonData.value).map((k) =>
            k.replace(/_/g, " "),
        ),
    },
    colors: ["#7c3aed"],
    dataLabels: { enabled: false },
    plotOptions: { bar: { borderRadius: 4, horizontal: true } },
}));

// Issuance over time
const issuanceSeries = computed(() => [
    { name: "Issued", data: (props.data?.by_date || []).map((r) => r.count) },
]);
const issuanceOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: { categories: (props.data?.by_date || []).map((r) => r.day) },
    colors: ["#0ea5e9"],
    stroke: { curve: "smooth", width: 2 },
    dataLabels: { enabled: false },
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
    grid-template-columns: repeat(7, 1fr);
    gap: 12px;
}
.metric-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    border-radius: 10px;
    border: 1px solid;
}
.metric-card.sky {
    background: #f0f9ff;
    border-color: #bae6fd;
}
.metric-card.green {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.metric-card.amber {
    background: #fffbeb;
    border-color: #fde68a;
}
.metric-card.red {
    background: #fff1f2;
    border-color: #fecdd3;
}
.metric-card.purple {
    background: #faf5ff;
    border-color: #e9d5ff;
}
.metric-card.blue {
    background: #eff6ff;
    border-color: #bfdbfe;
}
.metric-icon {
    font-size: 22px;
}
.metric-value {
    font-size: 18px;
    font-weight: 800;
    color: #1e293b;
}
.metric-label {
    font-size: 10px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.metric-sub {
    font-size: 10px;
    color: #94a3b8;
}
.charts-row-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
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
    gap: 12px;
    margin-bottom: 16px;
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
.number {
    text-align: right;
    font-weight: 600;
}
.empty-row {
    text-align: center;
    color: #94a3b8;
    padding: 24px;
}
.action-tag {
    font-size: 11px;
    background: #eff6ff;
    color: #2563eb;
    padding: 3px 8px;
    border-radius: 4px;
    font-weight: 600;
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
.badge-gray {
    background: #f1f5f9;
    color: #64748b;
}
.planning-widget {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}
.planning-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-top: 12px;
}
.planning-item {
    padding: 16px;
    border-radius: 10px;
    text-align: center;
    border: 1px solid;
}
.planning-item.cyan {
    background: #f0f9ff;
    border-color: #bae6fd;
}
.planning-item.amber {
    background: #fffbeb;
    border-color: #fde68a;
}
.planning-item.red {
    background: #fff1f2;
    border-color: #fecdd3;
}
.planning-item.green {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.planning-label {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.planning-value {
    font-size: 28px;
    font-weight: 800;
    color: #1e293b;
    margin: 8px 0;
}
.planning-sub {
    font-size: 11px;
    color: #94a3b8;
}
</style>
