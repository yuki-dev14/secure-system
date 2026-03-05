<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    records:     { type: Array, default: () => [] },
    // [{ id, compliance_type, compliance_period, is_compliant, verified_at, family_member_name, ... }]
});

const emit = defineEmits(['view']);

const filterType = ref('all');  // 'all' | 'education' | 'health' | 'fds'

const types = [
    { key: 'all',       label: 'All',       color: '#94a3b8' },
    { key: 'education', label: 'Education', color: '#a5b4fc' },
    { key: 'health',    label: 'Health',    color: '#f9a8d4' },
    { key: 'fds',       label: 'FDS',       color: '#fcd34d' },
];

const filtered = computed(() =>
    filterType.value === 'all'
        ? props.records
        : props.records.filter(r => r.compliance_type === filterType.value)
);

// Group by compliance_period
const byPeriod = computed(() => {
    const map = {};
    for (const r of filtered.value) {
        if (!map[r.compliance_period]) map[r.compliance_period] = [];
        map[r.compliance_period].push(r);
    }
    // Sort periods descending
    return Object.keys(map)
        .sort((a, b) => b.localeCompare(a))
        .map(period => ({ period, records: map[period] }));
});

function typeColor(type) {
    return { education: '#a5b4fc', health: '#f9a8d4', fds: '#fcd34d' }[type] ?? '#94a3b8';
}

function typeIcon(type) {
    const icons = {
        education: 'M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342',
        health:    'M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z',
        fds:       'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
    };
    return icons[type] ?? '';
}

function formattedPeriod(period) {
    const [yr, mo] = period.split('-');
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return `${months[parseInt(mo) - 1]} ${yr}`;
}

function periodOverall(records) {
    const total    = records.length;
    const passing  = records.filter(r => r.is_compliant).length;
    if (total === 0) return 'none';
    if (passing === total) return 'compliant';
    if (passing === 0)     return 'non_compliant';
    return 'partial';
}
</script>

<template>
    <div class="ct-shell">
        <div class="ct-header">
            <h3 class="ct-title">Compliance Timeline</h3>
            <!-- Filter tabs -->
            <div class="filter-tabs">
                <button v-for="t in types" :key="t.key"
                        class="ftab"
                        :class="{ 'ftab-active': filterType === t.key }"
                        :style="filterType === t.key ? `--tc:${t.color}` : ''"
                        @click="filterType = t.key">
                    {{ t.label }}
                </button>
            </div>
        </div>

        <div v-if="byPeriod.length === 0" class="ct-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
            </svg>
            <p>No compliance records found.</p>
        </div>

        <div class="timeline">
            <div v-for="group in byPeriod" :key="group.period" class="tl-group">
                <!-- Period header -->
                <div class="tl-period-header">
                    <div class="tl-line-wrap">
                        <div class="tl-dot"
                             :class="{
                                'dot-compliant':     periodOverall(group.records) === 'compliant',
                                'dot-partial':       periodOverall(group.records) === 'partial',
                                'dot-noncompliant':  periodOverall(group.records) === 'non_compliant',
                             }">
                        </div>
                    </div>
                    <div class="tl-period-label">
                        <span class="period-name">{{ formattedPeriod(group.period) }}</span>
                        <span class="period-pill"
                              :class="{
                                'pill-pass':  periodOverall(group.records) === 'compliant',
                                'pill-part':  periodOverall(group.records) === 'partial',
                                'pill-fail':  periodOverall(group.records) === 'non_compliant',
                              }">
                            {{ periodOverall(group.records) === 'compliant' ? 'Compliant'
                               : periodOverall(group.records) === 'partial' ? 'Partial'
                               : 'Non-Compliant' }}
                        </span>
                    </div>
                </div>

                <!-- Records for this period -->
                <div class="tl-records">
                    <div v-for="record in group.records" :key="record.id"
                         class="tl-record"
                         :class="record.is_compliant ? 'rec-pass' : 'rec-fail'"
                         @click="emit('view', record)"
                         role="button" :tabindex="0"
                         @keydown.enter="emit('view', record)">

                        <!-- Type icon -->
                        <div class="rec-icon" :style="`color:${typeColor(record.compliance_type)}`">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="typeIcon(record.compliance_type)"/>
                            </svg>
                        </div>

                        <div class="rec-body">
                            <div class="rec-top">
                                <span class="rec-type">{{ record.compliance_type.charAt(0).toUpperCase() + record.compliance_type.slice(1) }}</span>
                                <span class="rec-member">{{ record.family_member_name }}</span>
                            </div>
                            <div class="rec-meta">
                                <!-- Education -->
                                <span v-if="record.compliance_type === 'education' && record.attendance_percentage != null">
                                    Attendance: {{ record.attendance_percentage }}%
                                </span>
                                <!-- Health -->
                                <span v-if="record.compliance_type === 'health' && record.vaccination_status">
                                    Vaccination: {{ record.vaccination_status }}
                                </span>
                                <!-- FDS -->
                                <span v-if="record.compliance_type === 'fds'">
                                    {{ record.fds_attendance ? 'Attended' : 'Absent' }}
                                </span>
                                <!-- Verified -->
                                <span v-if="record.verified_at" class="rec-verified">
                                    ✓ Verified {{ record.verified_at_human }}
                                </span>
                                <span v-else class="rec-unverified">Pending verification</span>
                            </div>
                        </div>

                        <div class="rec-status-dot" :class="record.is_compliant ? 'sdot-pass' : 'sdot-fail'"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.ct-shell {
    font-family: 'Inter', sans-serif;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1.25rem;
    padding: 1.5rem;
    display: flex; flex-direction: column; gap: 1.25rem;
}

.ct-header {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;
}
.ct-title { font-size: 1rem; font-weight: 700; color: #f1f5f9; margin: 0; }

/* Filter tabs */
.filter-tabs { display: flex; gap: 0.375rem; flex-wrap: wrap; }
.ftab {
    padding: 0.3rem 0.75rem; border-radius: 999px;
    font-size: 0.78rem; font-weight: 600;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
    color: #64748b; cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.15s;
}
.ftab:hover { background: rgba(255,255,255,0.09); color: #94a3b8; }
.ftab-active {
    background: color-mix(in srgb, var(--tc, #6366f1) 20%, transparent);
    border-color: color-mix(in srgb, var(--tc, #6366f1) 40%, transparent);
    color: var(--tc, #6366f1);
}

/* Empty */
.ct-empty {
    text-align: center; padding: 3rem 1rem;
    display: flex; flex-direction: column; align-items: center; gap: 0.75rem;
    color: #475569;
}
.ct-empty svg { width: 40px; height: 40px; }
.ct-empty p   { font-size: 0.875rem; margin: 0; }

/* Timeline */
.timeline { display: flex; flex-direction: column; gap: 1.5rem; }
.tl-group { display: flex; flex-direction: column; gap: 0.75rem; }

.tl-period-header { display: flex; align-items: center; gap: 0.75rem; }
.tl-line-wrap { display: flex; align-items: center; justify-content: center; width: 24px; flex-shrink: 0; }
.tl-dot {
    width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0;
    border: 2px solid transparent;
}
.dot-compliant   { background: #10b981; border-color: rgba(16,185,129,0.3); box-shadow: 0 0 10px rgba(16,185,129,0.4); }
.dot-partial     { background: #f59e0b; border-color: rgba(245,158,11,0.3); box-shadow: 0 0 10px rgba(245,158,11,0.3); }
.dot-noncompliant { background: #ef4444; border-color: rgba(239,68,68,0.3); box-shadow: 0 0 10px rgba(239,68,68,0.3); }
.tl-period-label { display: flex; align-items: center; gap: 0.625rem; }
.period-name { font-size: 0.9375rem; font-weight: 700; color: #e2e8f0; }
.period-pill {
    padding: 0.15rem 0.6rem; border-radius: 999px; font-size: 0.7rem; font-weight: 700;
}
.pill-pass { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.25); }
.pill-part { background: rgba(245,158,11,0.15); color: #fcd34d; border: 1px solid rgba(245,158,11,0.25); }
.pill-fail { background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); }

.tl-records {
    margin-left: 36px;
    display: flex; flex-direction: column; gap: 0.5rem;
}

.tl-record {
    display: flex; align-items: center; gap: 0.875rem;
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 0.75rem; padding: 0.75rem 1rem;
    cursor: pointer; transition: all 0.15s; position: relative;
}
.tl-record:hover { background: rgba(255,255,255,0.06); transform: translateX(2px); }
.rec-pass { border-left: 3px solid rgba(16,185,129,0.4); }
.rec-fail { border-left: 3px solid rgba(239,68,68,0.4); }

.rec-icon { width: 32px; height: 32px; flex-shrink: 0; }
.rec-icon svg { width: 100%; height: 100%; }

.rec-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.25rem; }
.rec-top  { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.rec-type { font-size: 0.8125rem; font-weight: 700; color: #e2e8f0; }
.rec-member { font-size: 0.78rem; color: #64748b; }

.rec-meta {
    display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;
    font-size: 0.78rem; color: #64748b;
}
.rec-verified   { color: #6ee7b7; }
.rec-unverified { color: #f59e0b; font-style: italic; }

.rec-status-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.sdot-pass { background: #10b981; }
.sdot-fail { background: #ef4444; }
</style>
