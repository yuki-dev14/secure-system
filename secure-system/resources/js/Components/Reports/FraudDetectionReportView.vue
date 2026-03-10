<template>
    <div class="report-view">
        <!-- Alert Banner -->
        <div class="fraud-alert-banner" :class="alertLevel">
            <div class="banner-icon">{{ alertIcon }}</div>
            <div class="banner-content">
                <div class="banner-title">{{ alertTitle }}</div>
                <div class="banner-sub">
                    Reporting period: {{ data.period?.from }} –
                    {{ data.period?.to }}
                </div>
            </div>
            <div class="banner-total">
                <div class="banner-total-value">
                    {{ formatNumber(data.total) }}
                </div>
                <div class="banner-total-label">Total Detections</div>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="metric-cards">
            <div class="metric-card red">
                <div class="metric-icon">🔍</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.total) }}
                    </div>
                    <div class="metric-label">Duplicates Detected</div>
                </div>
            </div>
            <div class="metric-card orange">
                <div class="metric-icon">🎯</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.high_confidence) }}
                    </div>
                    <div class="metric-label">High Confidence (&gt;80%)</div>
                </div>
            </div>
            <div class="metric-card amber">
                <div class="metric-icon">🚫</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.action_breakdown?.flagged || 0) }}
                    </div>
                    <div class="metric-label">Flagged</div>
                </div>
            </div>
            <div class="metric-card blue">
                <div class="metric-icon">🔀</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.action_breakdown?.merged || 0) }}
                    </div>
                    <div class="metric-label">Merged</div>
                </div>
            </div>
            <div class="metric-card gray">
                <div class="metric-icon">✓</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{
                            formatNumber(data.action_breakdown?.dismissed || 0)
                        }}
                    </div>
                    <div class="metric-label">Dismissed</div>
                </div>
            </div>
            <div class="metric-card red">
                <div class="metric-icon">❌</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ formatNumber(data.failed_verifications) }}
                    </div>
                    <div class="metric-label">Failed Verifications</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-row-3">
            <!-- Timeline -->
            <div class="chart-card wide">
                <h3 class="chart-title">Fraud Attempts Over Time</h3>
                <apexchart
                    type="area"
                    height="240"
                    :options="timelineOptions"
                    :series="timelineSeries"
                />
            </div>
            <!-- Type Breakdown -->
            <div class="chart-card">
                <h3 class="chart-title">Detection Type</h3>
                <apexchart
                    type="donut"
                    height="240"
                    :options="typeOptions"
                    :series="typeSeries"
                />
            </div>
            <!-- Action Taken -->
            <div class="chart-card">
                <h3 class="chart-title">Action Taken</h3>
                <apexchart
                    type="bar"
                    height="240"
                    :options="actionOptions"
                    :series="actionSeries"
                />
            </div>
        </div>

        <!-- Multiple QR Scans -->
        <div class="table-card" v-if="data.multiple_scans?.length">
            <div class="table-header">
                <h3 class="chart-title" style="color: #dc2626">
                    🔄 Multiple QR Scans (Suspicious)
                </h3>
                <span class="badge badge-red"
                    >{{ data.multiple_scans.length }} beneficiaries</span
                >
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Beneficiary</th>
                            <th>BIN</th>
                            <th>Scan Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, idx) in data.multiple_scans"
                            :key="idx"
                        >
                            <td>{{ row.beneficiary }}</td>
                            <td class="mono">{{ row.bin }}</td>
                            <td>
                                <span class="badge badge-red"
                                    >{{ row.scan_count }} scans</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Events Table -->
        <div class="table-card">
            <div class="table-header">
                <h3 class="chart-title">Recent Fraud Events</h3>
                <span class="badge badge-gray"
                    >{{ data.events?.length || 0 }} records</span
                >
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Confidence</th>
                            <th>Primary Beneficiary</th>
                            <th>Duplicate</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Detected At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(e, idx) in data.events" :key="idx">
                            <td>
                                <span class="type-tag">{{
                                    e.type?.replace(/_/g, " ")
                                }}</span>
                            </td>
                            <td>
                                <div class="conf-bar">
                                    <div
                                        class="conf-fill"
                                        :style="{
                                            width: e.confidence + '%',
                                            background: confColor(e.confidence),
                                        }"
                                    ></div>
                                    <span class="conf-val"
                                        >{{ e.confidence }}%</span
                                    >
                                </div>
                            </td>
                            <td>
                                {{ e.primary }}<br /><small class="mono">{{
                                    e.primary_bin
                                }}</small>
                            </td>
                            <td>
                                {{ e.duplicate }}<br /><small class="mono">{{
                                    e.duplicate_bin
                                }}</small>
                            </td>
                            <td>
                                <span :class="actionClass(e.action_taken)">{{
                                    e.action_taken || "Pending"
                                }}</span>
                            </td>
                            <td>
                                <span :class="statusClass(e.status)">{{
                                    e.status
                                }}</span>
                            </td>
                            <td class="date-col">
                                {{ e.detected_at?.slice(0, 10) }}
                            </td>
                        </tr>
                        <tr v-if="!data.events?.length">
                            <td colspan="7" class="empty-row">
                                No fraud events found in this period.
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
const formatNumber = (n) => Number(n || 0).toLocaleString("en-PH");

const alertLevel = computed(() => {
    const t = props.data?.total || 0;
    return t >= 20 ? "level-high" : t >= 5 ? "level-medium" : "level-low";
});
const alertIcon = computed(
    () =>
        ({ "level-high": "🚨", "level-medium": "⚠️", "level-low": "ℹ️" })[
            alertLevel.value
        ] || "ℹ️",
);
const alertTitle = computed(
    () =>
        ({
            "level-high": "HIGH ALERT — Significant Fraud Activity Detected",
            "level-medium": "CAUTION — Moderate Fraud Activity",
            "level-low": "STATUS OK — Low Fraud Activity",
        })[alertLevel.value] || "Status OK",
);

const confColor = (v) =>
    v >= 80 ? "#dc2626" : v >= 50 ? "#f59e0b" : "#22c55e";
const actionClass = (a) =>
    ({
        flagged: "badge badge-red",
        merged: "badge badge-blue",
        dismissed: "badge badge-gray",
    })[a] || "badge badge-gray";
const statusClass = (s) =>
    `badge ${{ active: "badge-red", resolved: "badge-green", pending: "badge-amber" }[s] || "badge-gray"}`;

// Timeline
const timelineSeries = computed(() => [
    {
        name: "Detections",
        data: (props.data?.timeline || []).map((r) => r.count),
    },
]);
const timelineOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: { categories: (props.data?.timeline || []).map((r) => r.day) },
    colors: ["#f43f5e"],
    fill: {
        type: "gradient",
        gradient: { opacityFrom: 0.35, opacityTo: 0.05 },
    },
    stroke: { curve: "smooth", width: 2 },
    dataLabels: { enabled: false },
}));

// Type breakdown
const typeData = computed(() => props.data?.by_type || {});
const typeSeries = computed(() => Object.values(typeData.value));
const typeOptions = computed(() => ({
    labels: Object.keys(typeData.value).map((l) => l.replace(/_/g, " ")),
    colors: ["#f43f5e", "#f59e0b", "#6366f1", "#22c55e", "#06b6d4"],
    legend: { position: "bottom" },
}));

// Action taken
const actionSeries = computed(() => [
    { name: "Count", data: Object.values(props.data?.action_breakdown || {}) },
]);
const actionOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: {
        categories: Object.keys(props.data?.action_breakdown || {}).map(
            (k) => k.charAt(0).toUpperCase() + k.slice(1),
        ),
    },
    colors: ["#6366f1"],
    dataLabels: { enabled: true },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "50%" } },
}));
</script>

<style scoped>
.report-view {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.fraud-alert-banner {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px;
    border-radius: 12px;
    border: 1px solid;
}
.level-high {
    background: #fff1f2;
    border-color: #fecdd3;
}
.level-medium {
    background: #fffbeb;
    border-color: #fde68a;
}
.level-low {
    background: #f0fdf4;
    border-color: #bbf7d0;
}
.banner-icon {
    font-size: 36px;
}
.banner-title {
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
}
.banner-sub {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}
.banner-total {
    margin-left: auto;
    text-align: right;
}
.banner-total-value {
    font-size: 36px;
    font-weight: 800;
    color: #dc2626;
}
.banner-total-label {
    font-size: 11px;
    color: #64748b;
}

.metric-cards {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
}
.metric-card {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    border-radius: 10px;
    border: 1px solid;
}
.metric-card.red {
    background: #fff1f2;
    border-color: #fecdd3;
}
.metric-card.orange {
    background: #fff7ed;
    border-color: #fed7aa;
}
.metric-card.amber {
    background: #fffbeb;
    border-color: #fde68a;
}
.metric-card.blue {
    background: #eff6ff;
    border-color: #bfdbfe;
}
.metric-card.gray {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.metric-icon {
    font-size: 22px;
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

.charts-row-3 {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
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
    vertical-align: middle;
}
.data-table tbody tr:hover {
    background: #f8fafc;
}
.mono {
    font-family: "Courier New", monospace;
    font-size: 11px;
    color: #94a3b8;
}
.empty-row {
    text-align: center;
    color: #94a3b8;
    padding: 24px;
}
.date-col {
    white-space: nowrap;
}
.type-tag {
    font-size: 11px;
    font-weight: 600;
    background: #f1f5f9;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: capitalize;
}
.conf-bar {
    display: flex;
    align-items: center;
    gap: 6px;
}
.conf-fill {
    height: 6px;
    border-radius: 3px;
    flex: 0 0 auto;
    transition: width 0.6s;
}
.conf-val {
    font-size: 11px;
    font-weight: 700;
    color: #374151;
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
.badge-blue {
    background: #eff6ff;
    color: #2563eb;
}
.badge-green {
    background: #dcfce7;
    color: #16a34a;
}
.badge-amber {
    background: #fef9c3;
    color: #a16207;
}
.badge-gray {
    background: #f1f5f9;
    color: #64748b;
}
</style>
