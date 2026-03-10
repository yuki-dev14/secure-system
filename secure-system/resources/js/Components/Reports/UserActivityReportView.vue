<template>
    <div class="report-view">
        <!-- Summary -->
        <div class="metric-cards">
            <div class="metric-card purple">
                <div class="metric-icon">👤</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ data.per_user?.length || 0 }}
                    </div>
                    <div class="metric-label">Active Users</div>
                </div>
            </div>
            <div class="metric-card red">
                <div class="metric-icon">😴</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ data.inactive_users?.length || 0 }}
                    </div>
                    <div class="metric-label">Inactive Users</div>
                </div>
            </div>
            <div class="metric-card amber">
                <div class="metric-icon">⚠️</div>
                <div class="metric-body">
                    <div class="metric-value">
                        {{ data.unusual_patterns?.length || 0 }}
                    </div>
                    <div class="metric-label">Unusual Patterns</div>
                </div>
            </div>
            <div class="metric-card blue">
                <div class="metric-icon">📊</div>
                <div class="metric-body">
                    <div class="metric-value">{{ totalActivities }}</div>
                    <div class="metric-label">Total Activities</div>
                </div>
            </div>
        </div>

        <!-- Inactive Users Alert -->
        <div class="inactive-alert" v-if="data.inactive_users?.length">
            <span>⚠️</span>
            <strong>{{ data.inactive_users.length }} inactive user(s)</strong>
            had no activity in the selected period.
        </div>

        <!-- Charts -->
        <div class="charts-row">
            <!-- Activities per user bar -->
            <div class="chart-card wide">
                <h3 class="chart-title">Activities per User</h3>
                <apexchart
                    type="bar"
                    height="280"
                    :options="perUserOptions"
                    :series="perUserSeries"
                />
            </div>
            <!-- Activity trend line -->
            <div class="chart-card">
                <h3 class="chart-title">Activity Trend</h3>
                <apexchart
                    type="line"
                    height="280"
                    :options="trendOptions"
                    :series="trendSeries"
                />
            </div>
        </div>

        <!-- Heatmap (Activity by Hour/Day) -->
        <div class="chart-card">
            <h3 class="chart-title">
                Activity Heatmap (Hour of Day × Day of Week)
            </h3>
            <apexchart
                type="heatmap"
                height="200"
                :options="heatmapOptions"
                :series="heatmapSeries"
            />
        </div>

        <!-- Productivity Table -->
        <div class="table-card">
            <h3 class="chart-title">User Productivity Summary</h3>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Total Activities</th>
                            <th>Per Day</th>
                            <th>Per Hour</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, idx) in data.productivity" :key="idx">
                            <td class="rank">{{ idx + 1 }}</td>
                            <td class="user-name">{{ row.name || "—" }}</td>
                            <td class="number">
                                {{ formatNumber(row.total_activities) }}
                            </td>
                            <td class="number">{{ row.activities_per_day }}</td>
                            <td class="number">
                                {{ row.activities_per_hour }}
                            </td>
                            <td>
                                <div class="perf-bar">
                                    <div
                                        class="perf-fill"
                                        :style="{
                                            width:
                                                Math.min(
                                                    100,
                                                    (row.total_activities /
                                                        maxActivities) *
                                                        100,
                                                ) + '%',
                                        }"
                                    ></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Unusual Patterns -->
        <div class="table-card" v-if="data.unusual_patterns?.length">
            <div class="table-header">
                <h3 class="chart-title" style="color: #d97706">
                    ⚠️ Unusual Activity Patterns
                </h3>
                <span class="badge badge-amber">{{
                    data.unusual_patterns.length
                }}</span>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Office</th>
                            <th>Total Activities</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(u, idx) in data.unusual_patterns"
                            :key="idx"
                        >
                            <td>{{ u.name }}</td>
                            <td>{{ u.role }}</td>
                            <td>{{ u.office || "—" }}</td>
                            <td class="number">{{ formatNumber(u.total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Inactive Users -->
        <div class="table-card" v-if="data.inactive_users?.length">
            <h3 class="chart-title">Inactive Users</h3>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Office</th>
                            <th>Last Login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(u, idx) in data.inactive_users" :key="idx">
                            <td>{{ u.name }}</td>
                            <td>{{ u.role }}</td>
                            <td>{{ u.office_location || "—" }}</td>
                            <td>
                                {{
                                    u.last_login_at
                                        ? u.last_login_at.slice(0, 10)
                                        : "Never"
                                }}
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

const totalActivities = computed(() =>
    (props.data?.per_user || [])
        .reduce((s, u) => s + (u.total || 0), 0)
        .toLocaleString("en-PH"),
);
const maxActivities = computed(() =>
    Math.max(
        ...(props.data?.productivity || []).map((p) => p.total_activities || 0),
        1,
    ),
);

// Per user bar
const perUserSeries = computed(() => [
    {
        name: "Activities",
        data: (props.data?.per_user || [])
            .slice(0, 15)
            .map((u) => u.total || 0),
    },
]);
const perUserOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: {
        categories: (props.data?.per_user || [])
            .slice(0, 15)
            .map((u) => (u.name || "").split(" ")[0]),
    },
    colors: ["#7c3aed"],
    dataLabels: { enabled: false },
    plotOptions: { bar: { borderRadius: 4, columnWidth: "55%" } },
}));

// Activity trend
const trendSeries = computed(() => [
    {
        name: "Activities",
        data: (props.data?.by_day || []).map((r) => r.count),
    },
]);
const trendOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    xaxis: { categories: (props.data?.by_day || []).map((r) => r.day) },
    colors: ["#a855f7"],
    stroke: { curve: "smooth", width: 2 },
    dataLabels: { enabled: false },
}));

// Heatmap (dow × hour)
const dayLabels = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const rawHeatmap = computed(() => props.data?.by_hour_heatmap || []);
const heatmapSeries = computed(() => {
    return dayLabels.map((day, dow) => ({
        name: day,
        data: Array.from({ length: 24 }, (_, hour) => {
            const cell = rawHeatmap.value.find(
                (r) => r.dow === dow && r.hour === hour,
            );
            return {
                x: String(hour).padStart(2, "0") + "h",
                y: cell?.count || 0,
            };
        }),
    }));
});
const heatmapOptions = computed(() => ({
    chart: { toolbar: { show: false } },
    colors: ["#7c3aed"],
    dataLabels: { enabled: false },
    legend: { show: false },
    tooltip: { y: { formatter: (v) => v + " activities" } },
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
    grid-template-columns: repeat(4, 1fr);
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
.metric-card.purple {
    background: #faf5ff;
    border-color: #e9d5ff;
}
.metric-card.red {
    background: #fff1f2;
    border-color: #fecdd3;
}
.metric-card.amber {
    background: #fffbeb;
    border-color: #fde68a;
}
.metric-card.blue {
    background: #eff6ff;
    border-color: #bfdbfe;
}
.metric-icon {
    font-size: 26px;
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
.inactive-alert {
    display: flex;
    align-items: center;
    gap: 10px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-radius: 10px;
    padding: 12px 16px;
    font-size: 13px;
    color: #c2410c;
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
.rank {
    font-weight: 700;
    color: #94a3b8;
    width: 30px;
}
.number {
    text-align: right;
    font-weight: 600;
}
.user-name {
    font-weight: 600;
}
.perf-bar {
    height: 8px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
    min-width: 80px;
}
.perf-fill {
    height: 100%;
    border-radius: 4px;
    background: linear-gradient(90deg, #7c3aed, #a855f7);
    transition: width 0.6s;
}
.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}
.badge-amber {
    background: #fef9c3;
    color: #a16207;
}
</style>
