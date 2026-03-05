<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Primary beneficiary (the one being scanned) */
    primary: { type: Object, required: true },
    /** Array of similar beneficiaries from duplicateResult.similar_beneficiaries */
    similar: { type: Array, default: () => [] },
});

// Fields we compare side-by-side
const COMPARE_FIELDS = [
    { key: 'family_head_name', label: 'Name' },
    { key: 'bin',              label: 'BIN' },
    { key: 'contact_number',   label: 'Contact' },
    { key: 'barangay',         label: 'Barangay' },
    { key: 'municipality',     label: 'Municipality' },
    { key: 'province',         label: 'Province' },
    { key: 'gender',           label: 'Gender' },
    { key: 'civil_status',     label: 'Civil Status' },
    { key: 'household_size',   label: 'Household Size' },
    { key: 'registered_at',    label: 'Registered' },
];

/**
 * Determine if two field values are "matching" or "different".
 * Uses case-insensitive comparison; numbers compared directly.
 */
function isMatch(val1, val2) {
    if (val1 == null || val2 == null) return false;
    return String(val1).toLowerCase().trim() === String(val2).toLowerCase().trim();
}

function similarityClass(score) {
    if (!score && score !== 0) return 'sim-na';
    if (score >= 90) return 'sim-high';
    if (score >= 65) return 'sim-med';
    return 'sim-low';
}

function scoreColor(score) {
    if ((score ?? 0) >= 90) return '#f87171';
    if ((score ?? 0) >= 65) return '#fbbf24';
    return '#60a5fa';
}

function typeLabel(type) {
    const MAP = {
        address_match:         'Address Match',
        multiple_registration: 'Name/Contact Match',
        contact_match:         'Contact Match',
        name_match:            'Name Match',
        recent_scan:           'Recent Scan',
    };
    return MAP[type] ?? type ?? '—';
}

const limitedSimilar = computed(() => props.similar.slice(0, 5));
</script>

<template>
    <div class="sb-shell">
        <p class="sb-title">
            Side-by-Side Beneficiary Comparison
            <span class="sb-count">{{ limitedSimilar.length }} potential duplicate{{ limitedSimilar.length !== 1 ? 's' : '' }}</span>
        </p>

        <div class="sb-scroller">
            <table class="sb-table">
                <thead>
                    <tr>
                        <th class="field-col">Field</th>

                        <!-- Primary column -->
                        <th class="bene-col primary-col">
                            <div class="col-header">
                                <div class="col-avatar primary-avatar">
                                    {{ (primary.family_head_name ?? '?')[0].toUpperCase() }}
                                </div>
                                <div class="col-meta">
                                    <p class="col-name">{{ primary.family_head_name }}</p>
                                    <p class="col-bin">BIN: {{ primary.bin }}</p>
                                    <span class="col-badge badge-primary">Current Scan</span>
                                </div>
                            </div>
                        </th>

                        <!-- Similar columns -->
                        <th v-for="sim in limitedSimilar" :key="sim.id" class="bene-col">
                            <div class="col-header">
                                <div class="col-avatar similar-avatar">
                                    {{ (sim.family_head_name ?? '?')[0].toUpperCase() }}
                                </div>
                                <div class="col-meta">
                                    <p class="col-name">{{ sim.family_head_name }}</p>
                                    <p class="col-bin">BIN: {{ sim.bin }}</p>
                                    <div class="col-badges">
                                        <span v-if="sim.score" class="col-badge"
                                              :class="similarityClass(sim.score)"
                                              :style="{ borderColor: scoreColor(sim.score) + '44' }">
                                            {{ sim.score }}% match
                                        </span>
                                        <span v-if="sim.similarity_type" class="col-type-badge">
                                            {{ typeLabel(sim.similarity_type) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="field in COMPARE_FIELDS" :key="field.key">
                        <td class="field-lbl">{{ field.label }}</td>

                        <!-- Primary value -->
                        <td class="primary-val">
                            {{ primary[field.key] ?? '—' }}
                        </td>

                        <!-- Similar values -->
                        <td v-for="sim in limitedSimilar" :key="sim.id"
                            :class="['sim-val', isMatch(primary[field.key], sim[field.key]) ? 'match-cell' : 'diff-cell']">
                            <span class="val-text">{{ sim[field.key] ?? '—' }}</span>
                            <span v-if="primary[field.key] != null && sim[field.key] != null"
                                  :class="['match-indicator', isMatch(primary[field.key], sim[field.key]) ? 'ind-match' : 'ind-diff']">
                                {{ isMatch(primary[field.key], sim[field.key]) ? '=' : '≠' }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Legend -->
        <div class="sb-legend">
            <div class="legend-item">
                <span class="legend-dot dot-match"></span>
                Matching field
            </div>
            <div class="legend-item">
                <span class="legend-dot dot-diff"></span>
                Different field
            </div>
            <div class="legend-item">
                <span class="sim-high" style="padding:0.1rem 0.45rem;border-radius:999px;font-size:0.7rem;font-weight:700;">High</span>
                ≥ 90% match
            </div>
            <div class="legend-item">
                <span class="sim-med" style="padding:0.1rem 0.45rem;border-radius:999px;font-size:0.7rem;font-weight:700;">Medium</span>
                ≥ 65%
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
* { box-sizing: border-box; }

.sb-shell {
    font-family: 'Inter', sans-serif;
    display: flex;
    flex-direction: column;
    gap: 0.875rem;
}

.sb-title {
    font-size: 0.875rem; font-weight: 700; color: #e2e8f0;
    display: flex; align-items: center; justify-content: space-between;
    margin: 0;
}
.sb-count {
    font-size: 0.75rem; font-weight: 600; color: #64748b;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 9999px;
    padding: 0.1rem 0.625rem;
}

/* Scrollable table */
.sb-scroller {
    overflow-x: auto;
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 0.875rem;
}
.sb-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.8rem;
}

/* Column header */
.sb-table thead tr { background: rgba(255,255,255,0.02); }
.sb-table th {
    padding: 0.625rem 0.875rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    vertical-align: top;
    font-weight: 600;
}
.field-col {
    min-width: 110px;
    color: #475569;
    font-size: 0.6875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.bene-col  { min-width: 180px; }
.primary-col { background: rgba(99,102,241,0.04); border-right: 1px solid rgba(255,255,255,0.06); }

.col-header { display: flex; align-items: flex-start; gap: 0.5rem; }
.col-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.875rem; font-weight: 800; flex-shrink: 0;
}
.primary-avatar { background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.25); color: #a5b4fc; }
.similar-avatar { background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.2); color: #fbbf24; }

.col-meta { display: flex; flex-direction: column; gap: 0.125rem; }
.col-name { font-size: 0.8125rem; font-weight: 700; color: #e2e8f0; margin: 0; }
.col-bin  { font-size: 0.72rem; color: #64748b; margin: 0; }
.col-badges { display: flex; flex-wrap: wrap; gap: 0.25rem; margin-top: 0.125rem; }
.col-badge {
    display: inline-block;
    padding: 0.1rem 0.375rem;
    border-radius: 9999px;
    font-size: 0.68rem; font-weight: 700;
    border: 1px solid;
}
.badge-primary { background: rgba(99,102,241,0.12); color: #a5b4fc; border-color: rgba(99,102,241,0.25); }
.sim-high { background: rgba(239,68,68,0.12); color: #fca5a5; }
.sim-med  { background: rgba(245,158,11,0.12); color: #fde68a; }
.sim-low  { background: rgba(59,130,246,0.1);  color: #bfdbfe; }
.sim-na   { background: rgba(100,116,139,0.1); color: #94a3b8; }

.col-type-badge {
    display: inline-block;
    padding: 0.1rem 0.375rem;
    border-radius: 4px;
    font-size: 0.65rem; font-weight: 600;
    background: rgba(255,255,255,0.04);
    color: #64748b;
    border: 1px solid rgba(255,255,255,0.07);
}

/* Body rows */
.sb-table tbody tr:hover td { background: rgba(255,255,255,0.015); }
.sb-table td {
    padding: 0.5rem 0.875rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle;
}
.field-lbl {
    font-size: 0.72rem; font-weight: 700; color: #475569;
    text-transform: uppercase; letter-spacing: 0.04em;
    white-space: nowrap;
}
.primary-val {
    color: #e2e8f0; font-weight: 600;
    background: rgba(99,102,241,0.03);
    border-right: 1px solid rgba(255,255,255,0.05);
}
.sim-val { position: relative; }
.match-cell { background: rgba(16,185,129,0.04); }
.diff-cell  { background: rgba(239,68,68,0.03); }

.val-text { color: #cbd5e1; }
.match-indicator {
    float: right;
    font-size: 0.7rem; font-weight: 800;
    padding: 0.05rem 0.3rem;
    border-radius: 4px;
}
.ind-match { color: #6ee7b7; background: rgba(16,185,129,0.12); }
.ind-diff  { color: #fca5a5; background: rgba(239,68,68,0.1); }

/* Legend */
.sb-legend {
    display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap;
    padding: 0.25rem 0.125rem;
    font-size: 0.75rem; color: #64748b;
}
.legend-item { display: flex; align-items: center; gap: 0.375rem; }
.legend-dot { width: 10px; height: 10px; border-radius: 2px; }
.dot-match { background: rgba(16,185,129,0.25); border: 1px solid rgba(16,185,129,0.4); }
.dot-diff  { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); }
</style>
