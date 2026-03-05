<script setup>
import { computed } from "vue";

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            education_compliance_percentage: 0,
            health_compliance_percentage: 0,
            fds_compliance_percentage: 0,
            overall_compliance_status: "non_compliant",
            missing_requirements: [],
            fds_sessions_attended: 0,
            fds_sessions_required: 2,
            last_updated_at: null,
        }),
    },
    beneficiary: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["view-details"]);

/* ── Helpers ─────────────────────────────────────────────── */
function circleProps(pct) {
    const r = 26;
    const c = 2 * Math.PI * r;
    return {
        r,
        circumference: c,
        dashoffset: c - (Math.min(pct, 100) / 100) * c,
    };
}

function statusColor(pct) {
    if (pct >= 85) return "#10b981";
    if (pct >= 50) return "#f59e0b";
    return "#ef4444";
}

function overallBadge(status) {
    return (
        {
            compliant: { label: "Compliant", cls: "badge-compliant" },
            partial: { label: "Partial", cls: "badge-partial" },
            non_compliant: { label: "Non-Compliant", cls: "badge-fail" },
        }[status] ?? { label: status, cls: "badge-fail" }
    );
}

/* ── Computed ────────────────────────────────────────────── */
const eduPct = computed(() =>
    Number(props.summary?.education_compliance_percentage ?? 0),
);
const healthPct = computed(() =>
    Number(props.summary?.health_compliance_percentage ?? 0),
);
const fdsPct = computed(() =>
    Number(props.summary?.fds_compliance_percentage ?? 0),
);

const overallPct = computed(() =>
    Math.round((eduPct.value + healthPct.value + fdsPct.value) / 3),
);
const overallCp = computed(() => circleProps(overallPct.value));

const badge = computed(() =>
    overallBadge(props.summary?.overall_compliance_status),
);
const missing = computed(() => props.summary?.missing_requirements ?? []);

const sections = computed(() => [
    {
        key: "education",
        label: "Education",
        pct: eduPct.value,
        cp: circleProps(eduPct.value),
        color: statusColor(eduPct.value),
        icon: "M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342",
        tint: "rgba(99,102,241,0.12)",
        stroke: "#a5b4fc",
    },
    {
        key: "health",
        label: "Health",
        pct: healthPct.value,
        cp: circleProps(healthPct.value),
        color: statusColor(healthPct.value),
        icon: "M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z",
        tint: "rgba(236,72,153,0.12)",
        stroke: "#f9a8d4",
    },
    {
        key: "fds",
        label: "FDS",
        pct: fdsPct.value,
        cp: circleProps(fdsPct.value),
        color: statusColor(fdsPct.value),
        icon: "M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z",
        tint: "rgba(245,158,11,0.12)",
        stroke: "#fcd34d",
    },
]);
</script>

<template>
    <div class="csc-shell">
        <!-- ── Overall Banner ─────────────────────────────────── -->
        <div
            class="overall-banner"
            :class="`banner-${summary?.overall_compliance_status ?? 'non_compliant'}`"
        >
            <!-- Big circular progress -->
            <div class="overall-circle">
                <svg width="96" height="96" viewBox="0 0 96 96">
                    <circle
                        cx="48"
                        cy="48"
                        :r="overallCp.r"
                        fill="none"
                        stroke="rgba(255,255,255,0.07)"
                        stroke-width="7"
                    />
                    <circle
                        cx="48"
                        cy="48"
                        :r="overallCp.r"
                        fill="none"
                        :stroke="statusColor(overallPct)"
                        stroke-width="7"
                        stroke-linecap="round"
                        :stroke-dasharray="overallCp.circumference"
                        :stroke-dashoffset="overallCp.dashoffset"
                        transform="rotate(-90 48 48)"
                        class="progress-arc"
                    />
                    <text
                        x="50%"
                        y="50%"
                        text-anchor="middle"
                        dominant-baseline="central"
                        font-size="18"
                        font-weight="800"
                        :fill="statusColor(overallPct)"
                    >
                        {{ overallPct }}%
                    </text>
                </svg>
            </div>

            <div class="overall-info">
                <div class="oi-top">
                    <span :class="['oi-badge', badge.cls]">{{
                        badge.label
                    }}</span>
                </div>
                <p class="oi-name">
                    {{ beneficiary?.family_head_name ?? "Beneficiary" }}
                </p>
                <p class="oi-sub">
                    BIN: {{ beneficiary?.bin ?? "—" }}
                    <span v-if="summary?.last_updated_at" class="oi-updated">
                        · Updated
                        {{
                            new Date(
                                summary.last_updated_at,
                            ).toLocaleDateString("en-PH", {
                                month: "short",
                                day: "numeric",
                            })
                        }}
                    </span>
                </p>
            </div>

            <button
                class="oi-detail-btn"
                @click="emit('view-details')"
                id="compliance-view-details-btn"
            >
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path
                        fill-rule="evenodd"
                        d="M2 10a.75.75 0 01.75-.75h12.59l-2.1-1.95a.75.75 0 111.02-1.1l3.5 3.25a.75.75 0 010 1.1l-3.5 3.25a.75.75 0 11-1.02-1.1l2.1-1.95H2.75A.75.75 0 012 10z"
                        clip-rule="evenodd"
                    />
                </svg>
                View Details
            </button>
        </div>

        <!-- ── Three Section Cards ────────────────────────────── -->
        <div class="sections-grid">
            <div v-for="s in sections" :key="s.key" class="section-card">
                <!-- Header row -->
                <div class="sc-top">
                    <div class="sc-icon-wrap" :style="`background:${s.tint}`">
                        <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            :style="`color:${s.stroke}`"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                :d="s.icon"
                            />
                        </svg>
                    </div>
                    <span class="sc-label">{{ s.label }}</span>
                    <span
                        class="sc-status-dot"
                        :style="`background:${s.color}`"
                    ></span>
                </div>

                <!-- Circular progress -->
                <div class="sc-circle">
                    <svg width="72" height="72" viewBox="0 0 72 72">
                        <circle
                            cx="36"
                            cy="36"
                            :r="s.cp.r"
                            fill="none"
                            stroke="rgba(255,255,255,0.06)"
                            stroke-width="6"
                        />
                        <circle
                            cx="36"
                            cy="36"
                            :r="s.cp.r"
                            fill="none"
                            :stroke="s.color"
                            stroke-width="6"
                            stroke-linecap="round"
                            :stroke-dasharray="s.cp.circumference"
                            :stroke-dashoffset="s.cp.dashoffset"
                            transform="rotate(-90 36 36)"
                            class="progress-arc"
                        />
                        <text
                            x="50%"
                            y="50%"
                            text-anchor="middle"
                            dominant-baseline="central"
                            font-size="13"
                            font-weight="800"
                            :fill="s.color"
                        >
                            {{ Math.round(s.pct) }}%
                        </text>
                    </svg>
                </div>

                <!-- Status badge -->
                <span
                    class="sc-badge"
                    :class="{
                        'sb-pass': s.pct >= 85,
                        'sb-warn': s.pct >= 50 && s.pct < 85,
                        'sb-fail': s.pct < 50,
                    }"
                >
                    {{
                        s.pct >= 85
                            ? "Compliant"
                            : s.pct >= 50
                              ? "Partial"
                              : "Non-Compliant"
                    }}
                </span>

                <!-- FDS specific: session count -->
                <div v-if="s.key === 'fds'" class="sc-fds-info">
                    <span
                        >{{ summary?.fds_sessions_attended ?? 0 }} /
                        {{ summary?.fds_sessions_required ?? 2 }} sessions</span
                    >
                </div>
            </div>
        </div>

        <!-- ── Missing Requirements ───────────────────────────── -->
        <div v-if="missing.length" class="missing-section">
            <div class="ms-header">
                <svg viewBox="0 0 20 20" fill="currentColor" class="ms-icon">
                    <path
                        fill-rule="evenodd"
                        d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                        clip-rule="evenodd"
                    />
                </svg>
                <span class="ms-title"
                    >Missing Requirements ({{ missing.length }})</span
                >
            </div>
            <ul class="ms-list">
                <li v-for="(item, i) in missing" :key="i" class="ms-item">
                    <span class="ms-bullet"></span>
                    {{ item }}
                </li>
            </ul>
        </div>
        <div v-else class="all-clear">
            <svg viewBox="0 0 20 20" fill="currentColor">
                <path
                    fill-rule="evenodd"
                    d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z"
                    clip-rule="evenodd"
                />
            </svg>
            All compliance requirements met for this period.
        </div>
    </div>
</template>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap");
* {
    box-sizing: border-box;
}

.csc-shell {
    font-family: "Inter", sans-serif;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ── Overall Banner ─────────────────────────────────────── */
.overall-banner {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
    border-radius: 1.25rem;
    padding: 1.5rem;
    border: 1px solid transparent;
    position: relative;
    overflow: hidden;
}
.banner-compliant {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.25);
}
.banner-partial {
    background: rgba(245, 158, 11, 0.08);
    border-color: rgba(245, 158, 11, 0.25);
}
.banner-non_compliant {
    background: rgba(239, 68, 68, 0.07);
    border-color: rgba(239, 68, 68, 0.2);
}

.overall-circle {
    flex-shrink: 0;
}
.progress-arc {
    transition: stroke-dashoffset 0.8s cubic-bezier(0.4, 0, 0.2, 1);
}

.overall-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    flex: 1;
}
.oi-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.oi-badge {
    display: inline-block;
    padding: 0.2rem 0.75rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
}
.badge-compliant {
    background: rgba(16, 185, 129, 0.18);
    color: #6ee7b7;
    border: 1px solid rgba(16, 185, 129, 0.3);
}
.badge-partial {
    background: rgba(245, 158, 11, 0.18);
    color: #fcd34d;
    border: 1px solid rgba(245, 158, 11, 0.3);
}
.badge-fail {
    background: rgba(239, 68, 68, 0.15);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.25);
}

.oi-name {
    font-size: 1.125rem;
    font-weight: 800;
    color: #f1f5f9;
    margin: 0;
}
.oi-sub {
    font-size: 0.78rem;
    color: #64748b;
    margin: 0;
}
.oi-updated {
    color: #475569;
}

.oi-detail-btn {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    background: rgba(255, 255, 255, 0.07);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.625rem;
    padding: 0.5rem 1rem;
    color: #94a3b8;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    font-family: "Inter", sans-serif;
    transition: all 0.2s;
    white-space: nowrap;
}
.oi-detail-btn svg {
    width: 15px;
    height: 15px;
}
.oi-detail-btn:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #f1f5f9;
}

/* ── Sections Grid ──────────────────────────────────────── */
.sections-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}
@media (max-width: 640px) {
    .sections-grid {
        grid-template-columns: 1fr;
    }
}

.section-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.07);
    border-radius: 1rem;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.625rem;
    transition: border-color 0.2s;
}
.section-card:hover {
    border-color: rgba(255, 255, 255, 0.14);
}

.sc-top {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: 100%;
}
.sc-icon-wrap {
    width: 28px;
    height: 28px;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.sc-icon-wrap svg {
    width: 16px;
    height: 16px;
}
.sc-label {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #94a3b8;
    flex: 1;
}
.sc-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.sc-circle {
    flex-shrink: 0;
}

.sc-badge {
    display: inline-block;
    padding: 0.2rem 0.625rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
}
.sb-pass {
    background: rgba(16, 185, 129, 0.12);
    color: #6ee7b7;
    border: 1px solid rgba(16, 185, 129, 0.2);
}
.sb-warn {
    background: rgba(245, 158, 11, 0.12);
    color: #fcd34d;
    border: 1px solid rgba(245, 158, 11, 0.2);
}
.sb-fail {
    background: rgba(239, 68, 68, 0.1);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.sc-fds-info {
    font-size: 0.75rem;
    color: #64748b;
}

/* ── Missing Requirements ───────────────────────────────── */
.missing-section {
    background: rgba(245, 158, 11, 0.07);
    border: 1px solid rgba(245, 158, 11, 0.2);
    border-radius: 1rem;
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}
.ms-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.ms-icon {
    width: 16px;
    height: 16px;
    color: #fcd34d;
    flex-shrink: 0;
}
.ms-title {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #fcd34d;
}
.ms-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}
.ms-item {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: #94a3b8;
}
.ms-bullet {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #f59e0b;
    margin-top: 0.4rem;
    flex-shrink: 0;
}

.all-clear {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 0.875rem;
    padding: 0.875rem 1rem;
    font-size: 0.875rem;
    color: #6ee7b7;
    font-weight: 500;
}
.all-clear svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}
</style>
