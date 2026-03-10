<template>
    <Teleport to="body">
        <div class="modal-overlay" @click.self="$emit('close')">
            <div
                class="modal-panel"
                role="dialog"
                aria-modal="true"
                aria-labelledby="export-modal-title"
            >
                <!-- Header -->
                <div class="modal-header">
                    <div class="modal-header-left">
                        <div class="modal-icon">📤</div>
                        <div>
                            <h2 id="export-modal-title" class="modal-title">
                                Export Report
                            </h2>
                            <p class="modal-subtitle">
                                {{ reportTypeLabel }} — Generate your export
                                file
                            </p>
                        </div>
                    </div>
                    <button
                        class="btn-close"
                        @click="$emit('close')"
                        aria-label="Close"
                    >
                        ✕
                    </button>
                </div>

                <!-- Body -->
                <div class="modal-body">
                    <!-- Format Selection -->
                    <div class="form-section">
                        <label class="section-label">Export Format</label>
                        <div class="format-grid">
                            <label
                                v-for="opt in formatOptions"
                                :key="opt.value"
                                class="format-option"
                                :class="{
                                    selected: selectedFormat === opt.value,
                                }"
                            >
                                <input
                                    type="radio"
                                    :value="opt.value"
                                    v-model="selectedFormat"
                                    class="sr-only"
                                    :id="`format-${opt.value}`"
                                />
                                <div class="format-icon">{{ opt.icon }}</div>
                                <div class="format-info">
                                    <div class="format-name">
                                        {{ opt.label }}
                                    </div>
                                    <div class="format-desc">
                                        {{ opt.desc }}
                                    </div>
                                </div>
                                <div
                                    class="format-check"
                                    v-if="selectedFormat === opt.value"
                                >
                                    ✓
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Filename -->
                    <div class="form-section">
                        <label class="section-label" for="export-filename"
                            >Filename</label
                        >
                        <div class="filename-input-wrapper">
                            <input
                                id="export-filename"
                                type="text"
                                v-model="filename"
                                class="form-input"
                                placeholder="report_filename"
                            />
                            <span class="file-ext"
                                >.{{
                                    selectedFormat === "excel"
                                        ? "xlsx"
                                        : selectedFormat
                                }}</span
                            >
                        </div>
                    </div>

                    <!-- Options -->
                    <div class="form-section">
                        <label class="section-label">Options</label>
                        <div class="options-list">
                            <label
                                class="option-item"
                                v-if="selectedFormat !== 'csv'"
                            >
                                <input
                                    type="checkbox"
                                    v-model="includeCharts"
                                    class="option-checkbox"
                                    id="opt-charts"
                                />
                                <div class="option-info">
                                    <div class="option-label">
                                        Include charts/graphs
                                    </div>
                                    <div class="option-desc">
                                        Embed visual charts in the export
                                    </div>
                                </div>
                            </label>
                            <label class="option-item">
                                <input
                                    type="checkbox"
                                    v-model="emailReport"
                                    class="option-checkbox"
                                    id="opt-email"
                                />
                                <div class="option-info">
                                    <div class="option-label">Email report</div>
                                    <div class="option-desc">
                                        Send a copy to an email address
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Email Input -->
                    <div class="form-section" v-if="emailReport">
                        <label class="section-label" for="email-recipient"
                            >Email Recipient</label
                        >
                        <input
                            id="email-recipient"
                            type="email"
                            v-model="emailRecipient"
                            class="form-input"
                            placeholder="admin@dswd.gov.ph"
                        />
                    </div>

                    <!-- Applied Filters Summary -->
                    <div class="filter-summary" v-if="hasFilters">
                        <div class="summary-label">Applied Filters:</div>
                        <div class="summary-tags">
                            <span v-if="filters.date_from" class="summary-tag"
                                >From: {{ filters.date_from }}</span
                            >
                            <span v-if="filters.date_to" class="summary-tag"
                                >To: {{ filters.date_to }}</span
                            >
                            <span v-if="filters.office" class="summary-tag"
                                >Office: {{ filters.office }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer">
                    <button class="btn-cancel" @click="$emit('close')">
                        Cancel
                    </button>
                    <button
                        class="btn-export"
                        :disabled="exporting"
                        @click="doExport"
                        id="btn-confirm-export"
                    >
                        <span v-if="exporting" class="spinner"></span>
                        <span v-else>{{
                            formatOptions.find(
                                (f) => f.value === selectedFormat,
                            )?.icon
                        }}</span>
                        {{
                            exporting
                                ? "Exporting…"
                                : `Export as ${selectedFormat.toUpperCase()}`
                        }}
                    </button>
                </div>

                <!-- Progress Overlay -->
                <div class="progress-overlay" v-if="exporting">
                    <div class="progress-bar-track">
                        <div
                            class="progress-bar-fill"
                            :style="{ width: progress + '%' }"
                        ></div>
                    </div>
                    <p class="progress-label">{{ progressLabel }}</p>
                </div>

                <!-- Success State -->
                <div class="success-overlay" v-if="exported">
                    <div class="success-icon">✅</div>
                    <h3>Export Successful!</h3>
                    <p>Your file has been generated and is downloading.</p>
                    <button class="btn-done" @click="$emit('close')">
                        Done
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import axios from "axios";

const props = defineProps({
    format: { type: String, default: "pdf" },
    reportType: { type: String, required: true },
    filters: { type: Object, default: () => ({}) },
});
const emit = defineEmits(["close"]);

const selectedFormat = ref(props.format);
const filename = ref("");
const includeCharts = ref(true);
const emailReport = ref(false);
const emailRecipient = ref("");
const exporting = ref(false);
const exported = ref(false);
const progress = ref(0);
const progressLabel = ref("Preparing report…");

const formatOptions = [
    {
        value: "pdf",
        icon: "📄",
        label: "PDF",
        desc: "Professional layout, charts, headers & footers",
    },
    {
        value: "excel",
        icon: "📊",
        label: "Excel",
        desc: "Multiple sheets, formatted tables, charts",
    },
    {
        value: "csv",
        icon: "📋",
        label: "CSV",
        desc: "Plain text, compatible with any spreadsheet app",
    },
];

const reportTypeLabels = {
    beneficiary: "Beneficiary Statistics Report",
    compliance: "Compliance Report",
    distribution: "Distribution Report",
    fraud: "Fraud Detection Report",
    user: "User Activity Report",
    qr: "QR Code Management Report",
};

const reportTypeLabel = computed(
    () => reportTypeLabels[props.reportType] || "Report",
);
const hasFilters = computed(
    () =>
        props.filters.date_from ||
        props.filters.date_to ||
        props.filters.office,
);

// Auto-generate filename
watch(
    [() => props.reportType, selectedFormat],
    () => {
        const today = new Date().toISOString().slice(0, 10).replace(/-/g, "");
        filename.value = `${props.reportType}_report_${today}`;
    },
    { immediate: true },
);

async function doExport() {
    exporting.value = true;
    progress.value = 10;
    progressLabel.value = "Generating report data…";

    try {
        // Simulate progress
        const progressInterval = setInterval(() => {
            if (progress.value < 80) {
                progress.value += Math.random() * 15;
                progressLabel.value =
                    progress.value < 40
                        ? "Gathering data…"
                        : progress.value < 60
                          ? "Formatting output…"
                          : "Almost done…";
            }
        }, 600);

        const payload = {
            format: selectedFormat.value,
            ...props.filters,
        };

        const response = await axios.post(
            `/reports/export/${props.reportType}`,
            payload,
            { responseType: "blob" },
        );

        clearInterval(progressInterval);
        progress.value = 100;

        // Trigger download
        const ext =
            selectedFormat.value === "excel" ? "xlsx" : selectedFormat.value;
        const url = URL.createObjectURL(new Blob([response.data]));
        const anchor = document.createElement("a");
        anchor.href = url;
        anchor.download = `${filename.value}.${ext}`;
        document.body.appendChild(anchor);
        anchor.click();
        document.body.removeChild(anchor);
        URL.revokeObjectURL(url);

        exported.value = true;
    } catch (err) {
        console.error("Export failed:", err);
        alert(
            err.response?.data?.message || "Export failed. Please try again.",
        );
        exporting.value = false;
        progress.value = 0;
    }
}
</script>

<style scoped>
/* Overlay */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    backdrop-filter: blur(4px);
    animation: fadeIn 0.15s ease;
}
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Panel */
.modal-panel {
    background: white;
    border-radius: 16px;
    width: 560px;
    max-width: 95vw;
    max-height: 92vh;
    overflow-y: auto;
    position: relative;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
    animation: slideUp 0.2s ease;
}
@keyframes slideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: none;
        opacity: 1;
    }
}

/* Header */
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24px 24px 20px;
    border-bottom: 1px solid #f1f5f9;
}
.modal-header-left {
    display: flex;
    align-items: center;
    gap: 14px;
}
.modal-icon {
    font-size: 32px;
}
.modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
}
.modal-subtitle {
    font-size: 13px;
    color: #64748b;
    margin-top: 2px;
}
.btn-close {
    width: 32px;
    height: 32px;
    border: none;
    background: #f1f5f9;
    border-radius: 50%;
    cursor: pointer;
    font-size: 14px;
    color: #64748b;
    transition: all 0.15s;
}
.btn-close:hover {
    background: #e2e8f0;
    color: #1e293b;
}

/* Body */
.modal-body {
    padding: 20px 24px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.form-section {
}
.section-label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
}

/* Format Grid */
.format-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.format-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s;
    position: relative;
}
.format-option:hover {
    border-color: #94a3b8;
    background: #f8fafc;
}
.format-option.selected {
    border-color: #3b82f6;
    background: #eff6ff;
}
.format-icon {
    font-size: 24px;
}
.format-info {
    flex: 1;
}
.format-name {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}
.format-desc {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}
.format-check {
    font-size: 16px;
    color: #3b82f6;
    font-weight: 700;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
}

/* Filename */
.filename-input-wrapper {
    display: flex;
    align-items: center;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}
.filename-input-wrapper .form-input {
    border: none;
    border-radius: 0;
    flex: 1;
}
.file-ext {
    background: #f1f5f9;
    padding: 10px 12px;
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
    white-space: nowrap;
}

/* Options */
.options-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.option-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s;
}
.option-item:hover {
    background: #f8fafc;
}
.option-checkbox {
    position: relative;
    top: 1px;
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    cursor: pointer;
}
.option-label {
    font-size: 13px;
    font-weight: 600;
    color: #1e293b;
}
.option-desc {
    font-size: 12px;
    color: #64748b;
}
.form-input {
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    width: 100%;
    color: #1e293b;
}
.form-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Filter Summary */
.filter-summary {
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px;
}
.summary-label {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}
.summary-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.summary-tag {
    background: #e2e8f0;
    color: #475569;
    font-size: 12px;
    padding: 3px 10px;
    border-radius: 20px;
}

/* Footer */
.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    padding: 16px 24px;
    border-top: 1px solid #f1f5f9;
}
.btn-cancel {
    padding: 10px 20px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: white;
    color: #64748b;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-cancel:hover {
    border-color: #94a3b8;
}
.btn-export {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    background: linear-gradient(135deg, #1e40af, #3b82f6);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}
.btn-export:hover:not(:disabled) {
    box-shadow: 0 6px 18px rgba(59, 130, 246, 0.4);
}
.btn-export:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}
.spinner {
    width: 14px;
    height: 14px;
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

/* Progress */
.progress-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-top: 1px solid #f1f5f9;
    padding: 16px 24px;
}
.progress-bar-track {
    height: 8px;
    background: #f1f5f9;
    border-radius: 4px;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #3b82f6, #6366f1);
    border-radius: 4px;
    transition: width 0.4s ease;
}
.progress-label {
    font-size: 12px;
    color: #64748b;
    margin-top: 8px;
    text-align: center;
}

/* Success */
.success-overlay {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.97);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    border-radius: 16px;
}
.success-icon {
    font-size: 48px;
}
.success-overlay h3 {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
}
.success-overlay p {
    color: #64748b;
    font-size: 14px;
}
.btn-done {
    margin-top: 8px;
    padding: 10px 28px;
    background: #22c55e;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
}
.btn-done:hover {
    background: #16a34a;
}
</style>
