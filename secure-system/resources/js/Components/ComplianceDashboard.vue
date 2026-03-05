<script setup>
import { computed } from 'vue';

const props = defineProps({
    compliance: { type: Object, required: true },
    // compliance shape: { compliant, summary, details: [{type, label, compliant, reason, data}] }
});

const educationData = computed(() => props.compliance.details?.find(d => d.type === 'education')?.data ?? null);
const healthData    = computed(() => props.compliance.details?.find(d => d.type === 'health')?.data ?? null);
const fdsData       = computed(() => props.compliance.details?.find(d => d.type === 'fds')?.data ?? null);

// Circular progress helper
function circleProps(pct) {
    const r = 22;
    const c = 2 * Math.PI * r;
    const offset = c - (pct / 100) * c;
    return { r, circumference: c, dashoffset: offset };
}

function overallPct(details) {
    if (!details?.length) return 0;
    const passing = details.filter(d => d.compliant).length;
    return Math.round((passing / details.length) * 100);
}

const pct = computed(() => overallPct(props.compliance.details));
const cp  = computed(() => circleProps(pct.value));

function statusColor(pct) {
    if (pct === 100) return '#10b981';
    if (pct >= 66)   return '#f59e0b';
    return '#ef4444';
}
</script>

<template>
    <div class="cd-shell">
        <!-- Overall indicator -->
        <div class="cd-overall">
            <div class="circle-wrap">
                <svg :width="60" :height="60" viewBox="0 0 60 60">
                    <circle cx="30" cy="30" :r="cp.r" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="5"/>
                    <circle cx="30" cy="30" :r="cp.r" fill="none"
                        :stroke="statusColor(pct)"
                        stroke-width="5"
                        stroke-linecap="round"
                        :stroke-dasharray="cp.circumference"
                        :stroke-dashoffset="cp.dashoffset"
                        transform="rotate(-90 30 30)"
                        style="transition: stroke-dashoffset 0.6s ease;"
                    />
                    <text x="50%" y="50%" text-anchor="middle" dominant-baseline="central"
                          font-size="12" font-weight="700" :fill="statusColor(pct)">
                        {{ pct }}%
                    </text>
                </svg>
            </div>
            <div class="overall-text">
                <p class="overall-title">Overall Compliance</p>
                <p class="overall-summary" :class="compliance.compliant ? 'clr-green' : 'clr-red'">
                    {{ compliance.compliant ? 'All Requirements Met' : 'Requirements Not Met' }}
                </p>
                <p class="overall-detail">
                    {{ compliance.details?.filter(d => d.compliant).length ?? 0 }} / {{ compliance.details?.length ?? 0 }} criteria passing
                </p>
            </div>
        </div>

        <!-- Per-category cards -->
        <div class="comp-grid">
            <!-- Education -->
            <div :class="['comp-card', educationData?.compliant ? 'card-pass' : 'card-fail']">
                <div class="comp-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/>
                    </svg>
                    <span>Education</span>
                    <span :class="['comp-pill', educationData?.compliant ? 'pill-pass' : 'pill-fail']">
                        {{ educationData?.compliant ? 'Compliant' : 'Non-Compliant' }}
                    </span>
                </div>
                <div v-if="educationData" class="comp-card-body">
                    <div v-if="educationData.members_checked === 0" class="comp-na">
                        No school-age children
                    </div>
                    <template v-else>
                        <div class="comp-stat">
                            <span class="stat-lbl">Avg. Attendance</span>
                            <span :class="['stat-val', educationData.average_percentage >= 85 ? 'clr-green' : 'clr-red']">
                                {{ educationData.average_percentage }}%
                            </span>
                        </div>
                        <div class="comp-stat">
                            <span class="stat-lbl">Required</span>
                            <span class="stat-val">85%</span>
                        </div>
                        <div v-if="educationData.missing_members?.length" class="missing-list">
                            <p class="missing-title">Non-compliant members:</p>
                            <div v-for="m in educationData.missing_members" :key="m.member_id" class="missing-row">
                                <span>{{ m.full_name }}</span>
                                <span class="clr-red">{{ m.attendance_pct }}%</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Health -->
            <div :class="['comp-card', healthData?.compliant ? 'card-pass' : 'card-fail']">
                <div class="comp-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/>
                    </svg>
                    <span>Health</span>
                    <span :class="['comp-pill', healthData?.compliant ? 'pill-pass' : 'pill-fail']">
                        {{ healthData?.compliant ? 'Compliant' : 'Non-Compliant' }}
                    </span>
                </div>
                <div v-if="healthData" class="comp-card-body">
                    <div v-if="!healthData.issues?.length" class="comp-na clr-green">
                        All health requirements met
                    </div>
                    <div v-else>
                        <p class="missing-title">Issues found:</p>
                        <div v-for="(issue, i) in healthData.issues" :key="i" class="missing-row">
                            <span>{{ issue.full_name }}</span>
                            <span class="clr-red small">{{ issue.issue }}</span>
                        </div>
                    </div>
                    <div class="comp-stat">
                        <span class="stat-lbl">Members Checked</span>
                        <span class="stat-val">{{ healthData.members_checked }}</span>
                    </div>
                </div>
            </div>

            <!-- FDS -->
            <div :class="['comp-card', fdsData?.compliant ? 'card-pass' : 'card-fail', 'card-full']">
                <div class="comp-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
                    </svg>
                    <span>Family Development Sessions (FDS)</span>
                    <span :class="['comp-pill', fdsData?.compliant ? 'pill-pass' : 'pill-fail']">
                        {{ fdsData?.compliant ? 'Compliant' : 'Non-Compliant' }}
                    </span>
                </div>
                <div v-if="fdsData" class="comp-card-body fds-body">
                    <div class="fds-bar-wrap">
                        <div class="fds-bar-bg">
                            <div class="fds-bar-fill"
                                 :style="{ width: Math.min((fdsData.sessions_attended / fdsData.sessions_required) * 100, 100) + '%',
                                           background: fdsData.compliant ? '#10b981' : '#ef4444' }">
                            </div>
                        </div>
                        <span class="fds-count" :class="fdsData.compliant ? 'clr-green' : 'clr-red'">
                            {{ fdsData.sessions_attended }} / {{ fdsData.sessions_required }}
                        </span>
                    </div>
                    <p class="fds-label">Sessions attended this period ({{ fdsData.period }})</p>
                    <p v-if="!fdsData.compliant" class="comp-reason-txt">{{ fdsData.reason }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
* { box-sizing: border-box; }

.cd-shell {
    font-family: 'Inter', sans-serif;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

/* Overall */
.cd-overall {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem;
    padding: 1.25rem;
}
.circle-wrap { flex-shrink: 0; }
.overall-text { display: flex; flex-direction: column; gap: 0.125rem; }
.overall-title  { font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.06em; margin: 0; }
.overall-summary { font-size: 1rem; font-weight: 800; margin: 0; }
.overall-detail  { font-size: 0.8rem; color: #64748b; margin: 0; }

.clr-green { color: #6ee7b7; }
.clr-red   { color: #f87171; }

/* Cards grid */
.comp-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.875rem;
}
.card-full { grid-column: span 2; }

.comp-card {
    background: rgba(255,255,255,0.02);
    border-radius: 0.875rem;
    overflow: hidden;
}
.card-pass { border: 1px solid rgba(16,185,129,0.2); }
.card-fail { border: 1px solid rgba(239,68,68,0.2); }

.comp-card-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1rem;
    font-size: 0.875rem;
    font-weight: 700;
    color: #e2e8f0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    flex-wrap: wrap;
}
.comp-card-header svg { width: 18px; height: 18px; flex-shrink: 0; }
.card-pass .comp-card-header svg { color: #6ee7b7; }
.card-fail .comp-card-header svg { color: #f87171; }

.comp-pill {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.69rem;
    font-weight: 700;
    margin-left: auto;
    white-space: nowrap;
}
.pill-pass { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.25); }
.pill-fail { background: rgba(239,68,68,0.1); color: #fca5a5; border: 1px solid rgba(239,68,68,0.2); }

.comp-card-body { padding: 0.875rem 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
.comp-na { font-size: 0.8125rem; color: #64748b; font-style: italic; }

.comp-stat { display: flex; justify-content: space-between; align-items: baseline; }
.stat-lbl  { font-size: 0.75rem; color: #64748b; }
.stat-val  { font-size: 0.9rem; font-weight: 700; color: #e2e8f0; }

.missing-title { font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.04em; margin: 0.25rem 0 0; }
.missing-row   { display: flex; justify-content: space-between; font-size: 0.8rem; color: #94a3b8; border-top: 1px solid rgba(255,255,255,0.04); padding: 0.25rem 0; }
.small { font-size: 0.72rem; }

/* FDS bar */
.fds-body { gap: 0.75rem; }
.fds-bar-wrap { display: flex; align-items: center; gap: 0.875rem; }
.fds-bar-bg {
    flex: 1; height: 8px;
    background: rgba(255,255,255,0.06);
    border-radius: 999px;
    overflow: hidden;
}
.fds-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.6s ease;
}
.fds-count { font-size: 0.9rem; font-weight: 800; white-space: nowrap; }
.fds-label { font-size: 0.75rem; color: #64748b; margin: 0; }
.comp-reason-txt { font-size: 0.8rem; color: #fca5a5; margin: 0; }
</style>
