<template>
    <AppLayout title="Reports & Analytics">
        <div class="reports-wrapper">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left">
                    <h1 class="page-title">
                        <span class="title-icon">📊</span>
                        Reports &amp; Analytics
                    </h1>
                    <p class="page-subtitle">
                        Generate comprehensive insights for the SECURE system
                    </p>
                </div>
                <div class="page-header-right">
                    <div class="last-generated" v-if="lastGenerated">
                        <span class="dot pulse"></span>
                        Last generated: {{ formatTime(lastGenerated) }}
                    </div>
                </div>
            </div>

            <!-- Report Type Tabs -->
            <div class="report-tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="tab-btn"
                    :class="{
                        active: activeTab === tab.key,
                        [tab.color]: true,
                    }"
                    @click="selectTab(tab.key)"
                >
                    <span class="tab-icon">{{ tab.icon }}</span>
                    <span class="tab-label">{{ tab.label }}</span>
                </button>
            </div>

            <!-- Filters Panel -->
            <div class="filters-panel" :class="{ expanded: showFilters }">
                <div class="filters-header" @click="showFilters = !showFilters">
                    <div class="filters-header-left">
                        <span>🔧</span>
                        <span>Filters &amp; Options</span>
                        <span class="filter-count" v-if="activeFilterCount"
                            >{{ activeFilterCount }} active</span
                        >
                    </div>
                    <span class="chevron" :class="{ rotated: showFilters }"
                        >▼</span
                    >
                </div>

                <div class="filters-body" v-show="showFilters">
                    <div class="filters-grid">
                        <!-- Date Range Presets -->
                        <div class="filter-group full-width">
                            <label class="filter-label">Date Range</label>
                            <div class="presets">
                                <button
                                    v-for="preset in datePresets"
                                    :key="preset.key"
                                    class="preset-btn"
                                    :class="{
                                        active: activePreset === preset.key,
                                    }"
                                    @click="applyPreset(preset)"
                                >
                                    {{ preset.label }}
                                </button>
                            </div>
                            <div class="date-inputs">
                                <div class="date-field">
                                    <label>From</label>
                                    <input
                                        type="date"
                                        v-model="filters.date_from"
                                        class="form-input"
                                        @change="activePreset = 'custom'"
                                    />
                                </div>
                                <div class="date-field">
                                    <label>To</label>
                                    <input
                                        type="date"
                                        v-model="filters.date_to"
                                        class="form-input"
                                        @change="activePreset = 'custom'"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Office -->
                        <div class="filter-group">
                            <label class="filter-label">Office</label>
                            <select
                                v-model="filters.office"
                                class="form-select"
                            >
                                <option value="">All Offices</option>
                                <option
                                    v-for="office in offices"
                                    :key="office"
                                    :value="office"
                                >
                                    {{ office }}
                                </option>
                            </select>
                        </div>

                        <!-- Dynamic filters based on tab -->
                        <template v-if="activeTab === 'compliance'">
                            <div class="filter-group">
                                <label class="filter-label"
                                    >Compliance Type</label
                                >
                                <select
                                    v-model="filters.compliance_type"
                                    class="form-select"
                                >
                                    <option value="all">All Types</option>
                                    <option value="education">Education</option>
                                    <option value="health">Health</option>
                                    <option value="fds">FDS</option>
                                </select>
                            </div>
                        </template>

                        <template v-if="activeTab === 'distribution'">
                            <div class="filter-group">
                                <label class="filter-label"
                                    >Payment Method</label
                                >
                                <select
                                    v-model="filters.payment_method"
                                    class="form-select"
                                >
                                    <option value="">All Methods</option>
                                    <option value="cash">Cash</option>
                                    <option value="e_wallet">E-Wallet</option>
                                    <option value="bank_transfer">
                                        Bank Transfer
                                    </option>
                                    <option value="check">Check</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label"
                                    >Distributed By</label
                                >
                                <select
                                    v-model="filters.distributed_by"
                                    class="form-select"
                                >
                                    <option value="">All Officers</option>
                                    <option
                                        v-for="user in users"
                                        :key="user.id"
                                        :value="user.id"
                                    >
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>
                        </template>

                        <template v-if="activeTab === 'beneficiary'">
                            <div class="filter-group">
                                <label class="filter-label">Municipality</label>
                                <input
                                    type="text"
                                    v-model="filters.municipality"
                                    class="form-input"
                                    placeholder="e.g. Quezon City"
                                />
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Status</label>
                                <select
                                    v-model="filters.status"
                                    class="form-select"
                                >
                                    <option value="">All</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </template>

                        <template v-if="activeTab === 'user'">
                            <div class="filter-group">
                                <label class="filter-label"
                                    >Specific User</label
                                >
                                <select
                                    v-model="filters.user_id"
                                    class="form-select"
                                >
                                    <option value="">All Users</option>
                                    <option
                                        v-for="user in users"
                                        :key="user.id"
                                        :value="user.id"
                                    >
                                        {{ user.name }}
                                    </option>
                                </select>
                            </div>
                        </template>
                    </div>

                    <div class="filter-actions">
                        <button class="btn-reset" @click="resetFilters">
                            Reset Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="action-bar">
                <button
                    class="btn-generate"
                    :disabled="loading"
                    @click="generateReport"
                    id="btn-generate-report"
                >
                    <span v-if="loading" class="spinner"></span>
                    <span v-else>▶</span>
                    {{ loading ? "Generating…" : "Generate Report" }}
                </button>

                <div class="export-group" v-if="reportData">
                    <button
                        class="btn-export btn-pdf"
                        @click="openExportModal('pdf')"
                        id="btn-export-pdf"
                    >
                        📄 PDF
                    </button>
                    <button
                        class="btn-export btn-excel"
                        @click="openExportModal('excel')"
                        id="btn-export-excel"
                    >
                        📊 Excel
                    </button>
                    <button
                        class="btn-export btn-csv"
                        @click="openExportModal('csv')"
                        id="btn-export-csv"
                    >
                        📋 CSV
                    </button>
                </div>
            </div>

            <!-- Loading Skeleton -->
            <div class="loading-skeleton" v-if="loading">
                <div class="skeleton-cards">
                    <div class="skeleton-card" v-for="i in 4" :key="i"></div>
                </div>
                <div class="skeleton-chart"></div>
            </div>

            <!-- Report Content -->
            <div class="report-content" v-if="reportData && !loading">
                <component
                    :is="activeComponent"
                    :data="reportData"
                    :filters="filters"
                />
            </div>

            <!-- Empty State -->
            <div class="empty-state" v-if="!loading && !reportData">
                <div class="empty-icon">📊</div>
                <h3>No report generated yet</h3>
                <p>
                    Select your filters and click
                    <strong>Generate Report</strong> to view analytics.
                </p>
            </div>

            <!-- Export Modal -->
            <ReportExportModal
                v-if="showExportModal"
                :format="exportFormat"
                :report-type="activeTab"
                :filters="filters"
                @close="showExportModal = false"
            />
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, defineAsyncComponent } from "vue";
import { usePage } from "@inertiajs/vue3";
import AppLayout from "@/Layouts/AuthenticatedLayout.vue";
import ReportExportModal from "@/Components/Reports/ReportExportModal.vue";
import axios from "axios";

// Async report components
const BeneficiaryStatisticsReport = defineAsyncComponent(
    () => import("@/Components/Reports/BeneficiaryStatisticsReport.vue"),
);
const ComplianceReportView = defineAsyncComponent(
    () => import("@/Components/Reports/ComplianceReportView.vue"),
);
const DistributionReportView = defineAsyncComponent(
    () => import("@/Components/Reports/DistributionReportView.vue"),
);
const FraudDetectionReportView = defineAsyncComponent(
    () => import("@/Components/Reports/FraudDetectionReportView.vue"),
);
const UserActivityReportView = defineAsyncComponent(
    () => import("@/Components/Reports/UserActivityReportView.vue"),
);
const QRCodeReportView = defineAsyncComponent(
    () => import("@/Components/Reports/QRCodeReportView.vue"),
);

const page = usePage();
const props = defineProps({ users: Array, offices: Array });

// State
const activeTab = ref("beneficiary");
const showFilters = ref(true);
const loading = ref(false);
const reportData = ref(null);
const lastGenerated = ref(null);
const showExportModal = ref(false);
const exportFormat = ref("pdf");
const activePreset = ref("this_month");

const filters = ref({
    date_from: "",
    date_to: "",
    office: "",
    status: "",
    municipality: "",
    compliance_type: "all",
    payment_method: "",
    distributed_by: "",
    user_id: "",
});

// Tabs configuration
const tabs = [
    {
        key: "beneficiary",
        label: "Beneficiary Statistics",
        icon: "👥",
        color: "tab-blue",
    },
    {
        key: "compliance",
        label: "Compliance Report",
        icon: "✅",
        color: "tab-green",
    },
    {
        key: "distribution",
        label: "Distribution Report",
        icon: "💰",
        color: "tab-indigo",
    },
    { key: "fraud", label: "Fraud Detection", icon: "🛡️", color: "tab-red" },
    { key: "user", label: "User Activity", icon: "👤", color: "tab-purple" },
    { key: "qr", label: "QR Code Report", icon: "📱", color: "tab-sky" },
];

// Date presets
const datePresets = [
    {
        key: "today",
        label: "Today",
        getDates: () => {
            const t = today();
            return { from: t, to: t };
        },
    },
    {
        key: "this_week",
        label: "This Week",
        getDates: () => ({ from: startOf("week"), to: today() }),
    },
    {
        key: "this_month",
        label: "This Month",
        getDates: () => ({ from: startOf("month"), to: today() }),
    },
    {
        key: "last_month",
        label: "Last Month",
        getDates: () => ({ from: lastMonthStart(), to: lastMonthEnd() }),
    },
    {
        key: "last_3m",
        label: "Last 3 Months",
        getDates: () => ({ from: nMonthsAgo(3), to: today() }),
    },
    {
        key: "last_6m",
        label: "Last 6 Months",
        getDates: () => ({ from: nMonthsAgo(6), to: today() }),
    },
    {
        key: "this_year",
        label: "This Year",
        getDates: () => ({ from: startOf("year"), to: today() }),
    },
    { key: "custom", label: "Custom", getDates: () => null },
];

// Computed active component
const activeComponent = computed(() => {
    const map = {
        beneficiary: BeneficiaryStatisticsReport,
        compliance: ComplianceReportView,
        distribution: DistributionReportView,
        fraud: FraudDetectionReportView,
        user: UserActivityReportView,
        qr: QRCodeReportView,
    };
    return map[activeTab.value];
});

const activeFilterCount = computed(() => {
    return Object.values(filters.value).filter((v) => v && v !== "all").length;
});

// Methods
function selectTab(key) {
    activeTab.value = key;
    reportData.value = null;
}

function applyPreset(preset) {
    if (preset.key === "custom") {
        activePreset.value = "custom";
        return;
    }
    const dates = preset.getDates();
    if (dates) {
        filters.value.date_from = dates.from;
        filters.value.date_to = dates.to;
        activePreset.value = preset.key;
    }
}

async function generateReport() {
    loading.value = true;
    reportData.value = null;

    const endpointMap = {
        beneficiary: "/reports/beneficiary-statistics",
        compliance: "/reports/compliance",
        distribution: "/reports/distribution",
        fraud: "/reports/fraud-detection",
        user: "/reports/user-activity",
        qr: "/reports/qr-code",
    };

    try {
        const { data } = await axios.post(
            endpointMap[activeTab.value],
            filters.value,
        );
        reportData.value = data.data;
        lastGenerated.value = new Date();
    } catch (err) {
        console.error("Report generation failed:", err);
        alert(
            err.response?.data?.message ||
                "Failed to generate report. Please try again.",
        );
    } finally {
        loading.value = false;
    }
}

function openExportModal(format) {
    exportFormat.value = format;
    showExportModal.value = true;
}

function resetFilters() {
    filters.value = {
        date_from: "",
        date_to: "",
        office: "",
        status: "",
        municipality: "",
        compliance_type: "all",
        payment_method: "",
        distributed_by: "",
        user_id: "",
    };
    activePreset.value = "";
}

function formatTime(date) {
    return new Intl.DateTimeFormat("en-PH", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
    }).format(date);
}

// Date helpers
const today = () => new Date().toISOString().slice(0, 10);
const startOf = (unit) => {
    const d = new Date();
    if (unit === "week") d.setDate(d.getDate() - d.getDay());
    if (unit === "month") d.setDate(1);
    if (unit === "year") {
        d.setMonth(0);
        d.setDate(1);
    }
    return d.toISOString().slice(0, 10);
};
const nMonthsAgo = (n) => {
    const d = new Date();
    d.setMonth(d.getMonth() - n);
    return d.toISOString().slice(0, 10);
};
const lastMonthStart = () => {
    const d = new Date();
    d.setDate(1);
    d.setMonth(d.getMonth() - 1);
    return d.toISOString().slice(0, 10);
};
const lastMonthEnd = () => {
    const d = new Date();
    d.setDate(0);
    return d.toISOString().slice(0, 10);
};

// Initialize with this month preset
applyPreset(datePresets.find((p) => p.key === "this_month"));
</script>

<style scoped>
.reports-wrapper {
    padding: 24px;
    max-width: 1600px;
    margin: 0 auto;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
}
.page-title {
    font-size: 28px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 10px;
}
.title-icon {
    font-size: 32px;
}
.page-subtitle {
    color: #64748b;
    margin-top: 4px;
    font-size: 14px;
}
.last-generated {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #64748b;
    background: #f1f5f9;
    padding: 6px 12px;
    border-radius: 20px;
}
.dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
}
.pulse {
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0.4;
    }
}

/* Tabs */
.report-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.tab-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    border: 2px solid #e2e8f0;
    background: white;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    transition: all 0.2s;
    white-space: nowrap;
}
.tab-btn:hover {
    border-color: #94a3b8;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.tab-btn.active.tab-blue {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #1d4ed8;
}
.tab-btn.active.tab-green {
    background: #f0fdf4;
    border-color: #22c55e;
    color: #15803d;
}
.tab-btn.active.tab-indigo {
    background: #eef2ff;
    border-color: #6366f1;
    color: #4338ca;
}
.tab-btn.active.tab-red {
    background: #fff1f2;
    border-color: #f43f5e;
    color: #be123c;
}
.tab-btn.active.tab-purple {
    background: #faf5ff;
    border-color: #a855f7;
    color: #7e22ce;
}
.tab-btn.active.tab-sky {
    background: #f0f9ff;
    border-color: #38bdf8;
    color: #0369a1;
}
.tab-icon {
    font-size: 16px;
}

/* Filters */
.filters-panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 20px;
    cursor: pointer;
    user-select: none;
    background: #f8fafc;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}
.filters-header:hover {
    background: #f1f5f9;
}
.filters-header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}
.filter-count {
    background: #3b82f6;
    color: white;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 700;
}
.chevron {
    transition: transform 0.3s;
    font-size: 12px;
    color: #94a3b8;
}
.chevron.rotated {
    transform: rotate(180deg);
}
.filters-body {
    padding: 20px;
}
.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}
.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.full-width {
    grid-column: 1 / -1;
}
.filter-label {
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.presets {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
}
.preset-btn {
    padding: 5px 12px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    background: white;
    font-size: 12px;
    cursor: pointer;
    color: #475569;
    transition: all 0.15s;
}
.preset-btn:hover {
    border-color: #3b82f6;
    color: #1d4ed8;
}
.preset-btn.active {
    background: #3b82f6;
    border-color: #3b82f6;
    color: white;
}
.date-inputs {
    display: flex;
    gap: 12px;
}
.date-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}
.date-field label {
    font-size: 11px;
    color: #94a3b8;
}
.form-input,
.form-select {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    color: #1e293b;
    background: white;
    transition: border-color 0.2s;
    width: 100%;
}
.form-input:focus,
.form-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.filter-actions {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}
.btn-reset {
    padding: 7px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    color: #64748b;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-reset:hover {
    border-color: #e11d48;
    color: #e11d48;
}

/* Action Bar */
.action-bar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
}
.btn-generate {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);
}
.btn-generate:hover:not(:disabled) {
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.45);
    transform: translateY(-1px);
}
.btn-generate:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
.export-group {
    display: flex;
    gap: 8px;
    margin-left: auto;
}
.btn-export {
    padding: 9px 16px;
    border-radius: 8px;
    border: 1px solid;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-pdf {
    background: #fff1f2;
    border-color: #fda4af;
    color: #be123c;
}
.btn-pdf:hover {
    background: #be123c;
    color: white;
}
.btn-excel {
    background: #f0fdf4;
    border-color: #86efac;
    color: #15803d;
}
.btn-excel:hover {
    background: #15803d;
    color: white;
}
.btn-csv {
    background: #eff6ff;
    border-color: #93c5fd;
    color: #1d4ed8;
}
.btn-csv:hover {
    background: #1d4ed8;
    color: white;
}

/* Loading Skeleton */
.loading-skeleton {
    animation: shimmer 1.5s infinite;
}
.skeleton-cards {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 20px;
}
.skeleton-card {
    height: 90px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200%;
    border-radius: 12px;
    animation: skeleton-move 1.5s infinite;
}
.skeleton-chart {
    height: 300px;
    background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
    background-size: 200%;
    border-radius: 12px;
    animation: skeleton-move 1.5s infinite;
}
@keyframes skeleton-move {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #94a3b8;
}
.empty-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}
.empty-state h3 {
    font-size: 20px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 8px;
}
.empty-state p {
    color: #94a3b8;
}
</style>
