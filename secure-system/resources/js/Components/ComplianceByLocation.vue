<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    data: {
        type: Array,
        default: () => [],
        // Each: { municipality, total, compliant, partial, non_compliant,
        //         compliant_pct, avg_education, avg_health, avg_fds, avg_overall }
    },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['drill-down']);

const view = ref('list'); // 'list' | 'grid'
const selected = ref(null);

const sorted = computed(() =>
    [...props.data].sort((a, b) => a.avg_overall - b.avg_overall)
);

function barColor(pct) {
    if (pct >= 85) return '#10b981';
    if (pct >= 60) return '#f59e0b';
    return '#ef4444';
}

function statusLabel(pct) {
    if (pct >= 85) return 'Good';
    if (pct >= 60) return 'Fair';
    return 'Needs Attention';
}

function statusClass(pct) {
    if (pct >= 85) return 'sl-pass';
    if (pct >= 60) return 'sl-warn';
    return 'sl-fail';
}
</script>

<template>
    <div class="cbl-shell">
        <div class="cbl-hd">
            <div>
                <h3 class="cbl-title">Compliance by Location</h3>
                <p class="cbl-sub">Sorted by overall compliance (lowest first)</p>
            </div>
            <div class="view-toggle">
                <button :class="['vt-btn', view === 'list' ? 'vt-active' : '']" @click="view = 'list'">List</button>
                <button :class="['vt-btn', view === 'grid' ? 'vt-active' : '']" @click="view = 'grid'">Grid</button>
            </div>
        </div>

        <div v-if="loading" class="cbl-loading">
            <div class="loader"></div><span>Loading location data…</span>
        </div>

        <!-- List view -->
        <div v-else-if="view === 'list'" class="cbl-list">
            <div v-for="loc in sorted" :key="loc.municipality"
                 class="cbl-item"
                 @click="emit('drill-down', loc)"
                 role="button" tabindex="0">
                <!-- Municipality name & overall pct -->
                <div class="cbl-left">
                    <div class="cbl-dot" :style="`background:${barColor(loc.avg_overall)}`"></div>
                    <div class="cbl-info">
                        <span class="cbl-muni">{{ loc.municipality }}</span>
                        <span :class="['cbl-status', statusClass(loc.avg_overall)]">
                            {{ statusLabel(loc.avg_overall) }}
                        </span>
                    </div>
                </div>
                <!-- Progress bars for edu/health/fds -->
                <div class="cbl-bars">
                    <div class="mini-bar-wrap">
                        <span class="mini-label">Edu</span>
                        <div class="mini-bar-bg">
                            <div class="mini-bar-fill" :style="`width:${loc.avg_education}%;background:${barColor(loc.avg_education)}`"></div>
                        </div>
                        <span class="mini-pct">{{ loc.avg_education }}%</span>
                    </div>
                    <div class="mini-bar-wrap">
                        <span class="mini-label">Health</span>
                        <div class="mini-bar-bg">
                            <div class="mini-bar-fill" :style="`width:${loc.avg_health}%;background:${barColor(loc.avg_health)}`"></div>
                        </div>
                        <span class="mini-pct">{{ loc.avg_health }}%</span>
                    </div>
                    <div class="mini-bar-wrap">
                        <span class="mini-label">FDS</span>
                        <div class="mini-bar-bg">
                            <div class="mini-bar-fill" :style="`width:${loc.avg_fds}%;background:${barColor(loc.avg_fds)}`"></div>
                        </div>
                        <span class="mini-pct">{{ loc.avg_fds }}%</span>
                    </div>
                </div>
                <!-- Overall -->
                <div class="cbl-overall">
                    <span class="cbl-pct" :style="`color:${barColor(loc.avg_overall)}`">{{ loc.avg_overall }}%</span>
                    <span class="cbl-total">{{ loc.total }} beneficiaries</span>
                </div>
                <svg class="cbl-arrow" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                </svg>
            </div>

            <div v-if="!sorted.length" class="cbl-empty">No location data yet.</div>
        </div>

        <!-- Grid view -->
        <div v-else class="cbl-grid">
            <div v-for="loc in sorted" :key="loc.municipality"
                 class="cbl-card"
                 @click="emit('drill-down', loc)"
                 :style="`border-color: ${barColor(loc.avg_overall)}33`">
                <div class="cbl-card-top">
                    <span class="cbl-card-muni">{{ loc.municipality }}</span>
                    <span class="cbl-card-pct" :style="`color:${barColor(loc.avg_overall)}`">{{ loc.avg_overall }}%</span>
                </div>
                <!-- Compliance breakdown mini indicators -->
                <div class="cbl-card-types">
                    <div v-for="([label, val]) in [['E', loc.avg_education], ['H', loc.avg_health], ['F', loc.avg_fds]]" :key="label"
                         class="cbl-mini-ring">
                        <svg width="40" height="40" viewBox="0 0 40 40">
                            <circle cx="20" cy="20" r="14" fill="none" stroke="rgba(255,255,255,0.07)" stroke-width="4"/>
                            <circle cx="20" cy="20" r="14" fill="none"
                                    :stroke="barColor(val)"
                                    stroke-width="4"
                                    stroke-linecap="round"
                                    :stroke-dasharray="2 * Math.PI * 14"
                                    :stroke-dashoffset="(2 * Math.PI * 14) * (1 - val/100)"
                                    transform="rotate(-90 20 20)"/>
                            <text x="50%" y="50%" text-anchor="middle" dominant-baseline="central"
                                  font-size="9" font-weight="700" :fill="barColor(val)">{{ label }}</text>
                        </svg>
                    </div>
                </div>
                <div class="cbl-card-footer">
                    <span :class="['cs-badge', statusClass(loc.avg_overall)]">{{ statusLabel(loc.avg_overall) }}</span>
                    <span class="cbl-card-total">{{ loc.total }} HHs</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }
.cbl-shell {
    font-family: 'Inter', sans-serif;
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1.25rem; padding: 1.5rem;
    display: flex; flex-direction: column; gap: 1rem;
}
.cbl-hd { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.cbl-title { font-size: 1rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.cbl-sub   { font-size: 0.78rem; color: #64748b; margin: 0; }

.view-toggle { display: flex; gap: 0.25rem; }
.vt-btn {
    padding: 0.3rem 0.75rem; border-radius: 0.5rem; font-size: 0.78rem; font-weight: 600;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
    color: #64748b; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.15s;
}
.vt-btn:hover { background: rgba(255,255,255,0.09); }
.vt-active { background: rgba(99,102,241,0.15) !important; border-color: rgba(99,102,241,0.3) !important; color: #a5b4fc !important; }

.cbl-loading {
    display: flex; align-items: center; gap: 0.75rem; justify-content: center;
    padding: 3rem; color: #64748b; font-size: 0.875rem;
}
.loader {
    width: 16px; height: 16px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.1); border-top-color: #6366f1;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* List view */
.cbl-list { display: flex; flex-direction: column; gap: 0.5rem; }
.cbl-item {
    display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
    background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 0.75rem; padding: 0.875rem 1rem;
    cursor: pointer; transition: all 0.15s;
}
.cbl-item:hover { background: rgba(255,255,255,0.05); transform: translateX(2px); }
.cbl-left { display: flex; align-items: center; gap: 0.625rem; min-width: 180px; }
.cbl-dot  { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.cbl-info { display: flex; flex-direction: column; gap: 0.1rem; }
.cbl-muni { font-weight: 700; color: #e2e8f0; font-size: 0.875rem; }
.cbl-status { font-size: 0.72rem; font-weight: 600; }
.sl-pass { color: #6ee7b7; }
.sl-warn { color: #fcd34d; }
.sl-fail { color: #f87171; }

.cbl-bars { flex: 1; display: flex; flex-direction: column; gap: 0.3rem; min-width: 200px; }
.mini-bar-wrap { display: flex; align-items: center; gap: 0.5rem; }
.mini-label { font-size: 0.7rem; color: #475569; width: 36px; font-weight: 600; }
.mini-bar-bg { flex: 1; height: 5px; background: rgba(255,255,255,0.07); border-radius: 999px; overflow: hidden; }
.mini-bar-fill { height: 100%; border-radius: 999px; transition: width 0.5s ease; }
.mini-pct { font-size: 0.72rem; color: #64748b; width: 34px; text-align: right; font-weight: 600; }

.cbl-overall { display: flex; flex-direction: column; align-items: center; min-width: 70px; }
.cbl-pct   { font-size: 1.25rem; font-weight: 800; }
.cbl-total { font-size: 0.7rem; color: #475569; }
.cbl-arrow { width: 16px; height: 16px; color: #334155; flex-shrink: 0; }
.cbl-empty { text-align: center; padding: 2rem; color: #475569; font-size: 0.875rem; font-style: italic; }

/* Grid view */
.cbl-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem;
}
.cbl-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1rem; padding: 1rem; cursor: pointer; transition: all 0.15s;
    display: flex; flex-direction: column; gap: 0.75rem;
}
.cbl-card:hover { background: rgba(255,255,255,0.06); transform: translateY(-2px); }
.cbl-card-top { display: flex; justify-content: space-between; align-items: flex-start; }
.cbl-card-muni { font-weight: 700; color: #e2e8f0; font-size: 0.875rem; flex: 1; }
.cbl-card-pct  { font-size: 1.25rem; font-weight: 800; }
.cbl-card-types { display: flex; justify-content: space-around; }
.cbl-mini-ring  { }
.cbl-card-footer { display: flex; justify-content: space-between; align-items: center; }
.cs-badge {
    padding: 0.15rem 0.5rem; border-radius: 999px;
    font-size: 0.65rem; font-weight: 700;
}
.sl-pass { background: rgba(16,185,129,0.12); color: #6ee7b7; }
.sl-warn { background: rgba(245,158,11,0.12); color: #fcd34d; }
.sl-fail { background: rgba(239,68,68,0.1);   color: #fca5a5; }
.cbl-card-total { font-size: 0.7rem; color: #475569; }
</style>
