<script setup>
import { computed } from 'vue';

const props = defineProps({
    matches: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['proceed', 'draft', 'close']);

const scoreColor = (score) => {
    if (score >= 95) return 'score-high';
    if (score >= 85) return 'score-medium';
    return 'score-low';
};

const scoreLabel = (score) => {
    if (score >= 95) return 'Very High';
    if (score >= 85) return 'High';
    return 'Moderate';
};

const topMatches = computed(() => props.matches.slice(0, 5));
</script>

<template>
    <!-- Modal overlay -->
    <Teleport to="body">
        <div class="modal-overlay" @click.self="emit('close')">
            <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="dup-modal-title">

                <!-- Header -->
                <div class="modal-header">
                    <div class="modal-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 id="dup-modal-title" class="modal-title">Potential Duplicates Detected</h2>
                        <p class="modal-subtitle">
                            {{ matches.length }} existing record{{ matches.length !== 1 ? 's' : '' }} may match this registration.
                        </p>
                    </div>
                    <button class="modal-close" @click="emit('close')" aria-label="Close">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                    </button>
                </div>

                <!-- Warning banner -->
                <div class="warn-banner">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>
                    <p>The system has automatically detected possible duplicate records. Please review before proceeding. Override requires <strong>Administrator</strong> approval only.</p>
                </div>

                <!-- Match list -->
                <div class="matches-list">
                    <div
                        v-for="(match, idx) in topMatches"
                        :key="idx"
                        class="match-card"
                    >
                        <div class="match-header-row">
                            <div class="match-name-block">
                                <div class="match-avatar">
                                    {{ match.beneficiary.family_head_name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <div class="match-name">{{ match.beneficiary.family_head_name }}</div>
                                    <div class="match-bin">{{ match.beneficiary.bin }}</div>
                                </div>
                            </div>
                            <div :class="['score-badge', scoreColor(match.score)]">
                                <div class="score-ring" :style="{ '--pct': match.score }">
                                    <span class="score-num">{{ match.score }}%</span>
                                </div>
                                <span class="score-lbl">{{ scoreLabel(match.score) }} Match</span>
                            </div>
                        </div>

                        <!-- Match details -->
                        <div class="match-details">
                            <div class="match-detail-item">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.69 18.933l.003.001C9.89 19.02 10 19 10 19s.11.02.31-.066l.002-.001.006-.003.018-.008a5.741 5.741 0 00.281-.14c.186-.096.446-.24.757-.433.62-.384 1.445-.966 2.274-1.765C15.302 15.187 17 12.discussfoo" clip-rule="evenodd"/></svg>
                                {{ match.beneficiary.barangay }}, {{ match.beneficiary.municipality }}, {{ match.beneficiary.province }}
                            </div>
                            <div class="match-detail-item">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 013.5 2h1.148a1.5 1.5 0 011.465 1.175l.716 3.223a1.5 1.5 0 01-1.052 1.767l-.933.267c-.41.117-.643.555-.48.95a11.542 11.542 0 006.254 6.254c.395.163.833-.07.95-.48l.267-.933a1.5 1.5 0 011.767-1.052l3.223.716A1.5 1.5 0 0118 15.352V16.5a1.5 1.5 0 01-1.5 1.5H15c-1.149 0-2.263-.15-3.326-.43A13.022 13.022 0 012.43 8.326 13.019 13.019 0 012 5V3.5z" clip-rule="evenodd"/></svg>
                                {{ match.beneficiary.contact_number }}
                            </div>
                            <div class="match-detail-item">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>
                                {{ match.beneficiary.gender }} · {{ match.beneficiary.civil_status }}
                                <span :class="['active-pill', match.beneficiary.is_active ? 'pill-active' : 'pill-inactive']">
                                    {{ match.beneficiary.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>

                        <!-- Reasons -->
                        <div class="reasons-row">
                            <span v-for="(reason, ri) in match.reasons" :key="ri" class="reason-tag">
                                {{ reason }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Action footer -->
                <div class="modal-footer">
                    <button class="btn-ghost" @click="emit('draft')">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/></svg>
                        Save as Draft
                    </button>
                    <div class="footer-right-btns">
                        <button class="btn-cancel" @click="emit('close')">Cancel</button>
                        <button class="btn-override" @click="emit('proceed')">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                            Override & Register Anyway
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

/* Overlay */
.modal-overlay {
    position: fixed; inset: 0; z-index: 1000;
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(8px);
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
    animation: fadeIn 0.2s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal-panel {
    background: #0f172a;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.25rem;
    width: 100%; max-width: 680px;
    max-height: 90vh; overflow-y: auto;
    box-shadow: 0 25px 80px rgba(0,0,0,0.7);
    animation: slideUp 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    font-family: 'Inter', sans-serif;
}
@keyframes slideUp { from { opacity: 0; transform: translateY(20px) scale(0.97); } to { opacity: 1; transform: none; } }

/* Header */
.modal-header {
    display: flex; align-items: flex-start; gap: 1rem;
    padding: 1.5rem 1.5rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
    position: sticky; top: 0; background: #0f172a; z-index: 2;
}
.modal-icon {
    width: 42px; height: 42px; border-radius: 0.75rem; flex-shrink: 0;
    background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3);
    display: flex; align-items: center; justify-content: center;
}
.modal-icon svg { width: 22px; height: 22px; color: #fcd34d; }
.modal-title    { font-size: 1.125rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.25rem; }
.modal-subtitle { font-size: 0.875rem; color: #64748b; margin: 0; }
.modal-close {
    margin-left: auto; flex-shrink: 0;
    width: 32px; height: 32px; border-radius: 0.5rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: #64748b; transition: all 0.15s;
}
.modal-close svg { width: 16px; height: 16px; }
.modal-close:hover { background: rgba(239,68,68,0.15); color: #fca5a5; border-color: rgba(239,68,68,0.3); }

/* Warning banner */
.warn-banner {
    display: flex; gap: 0.75rem; align-items: flex-start;
    margin: 1.25rem 1.5rem;
    padding: 0.875rem 1rem;
    background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25);
    border-radius: 0.75rem;
}
.warn-banner svg { width: 18px; height: 18px; color: #fcd34d; flex-shrink: 0; margin-top: 1px; }
.warn-banner p   { font-size: 0.8125rem; color: #fcd34d; margin: 0; line-height: 1.5; }
.warn-banner strong { color: #fbbf24; }

/* Matches list */
.matches-list { display: flex; flex-direction: column; gap: 0.875rem; padding: 0 1.5rem 1rem; }

.match-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 0.875rem; padding: 1rem; transition: border-color 0.2s;
}
.match-card:hover { border-color: rgba(99,102,241,0.3); }

.match-header-row {
    display: flex; align-items: center; justify-content: space-between;
    gap: 1rem; margin-bottom: 0.875rem; flex-wrap: wrap;
}
.match-name-block { display: flex; align-items: center; gap: 0.75rem; }
.match-avatar {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; font-weight: 700; color: white;
}
.match-name { font-size: 0.9375rem; font-weight: 700; color: #f1f5f9; }
.match-bin  { font-size: 0.75rem; color: #a5b4fc; font-family: monospace; margin-top: 2px; }

/* Score badge */
.score-badge {
    display: flex; flex-direction: column; align-items: center; gap: 0.25rem;
    flex-shrink: 0;
}
.score-ring {
    width: 52px; height: 52px; border-radius: 50%;
    border: 3px solid;
    display: flex; align-items: center; justify-content: center;
    position: relative;
}
.score-high   .score-ring { border-color: #f87171; background: rgba(239,68,68,0.1); }
.score-medium .score-ring { border-color: #fcd34d; background: rgba(245,158,11,0.1); }
.score-low    .score-ring { border-color: #6ee7b7; background: rgba(16,185,129,0.1); }
.score-num { font-size: 0.75rem; font-weight: 700; }
.score-high   .score-num { color: #f87171; }
.score-medium .score-num { color: #fcd34d; }
.score-low    .score-num { color: #6ee7b7; }
.score-lbl { font-size: 0.65rem; font-weight: 600; color: #64748b; text-align: center; white-space: nowrap; }

/* Match details */
.match-details { display: flex; flex-direction: column; gap: 0.375rem; margin-bottom: 0.875rem; }
.match-detail-item {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.8125rem; color: #94a3b8;
}
.match-detail-item svg { width: 14px; height: 14px; color: #475569; flex-shrink: 0; }

.active-pill {
    display: inline-block; padding: 0.125rem 0.5rem;
    border-radius: 9999px; font-size: 0.65rem; font-weight: 700;
    margin-left: 0.25rem;
}
.pill-active   { background: rgba(16,185,129,0.15); color: #6ee7b7; }
.pill-inactive { background: rgba(239,68,68,0.12);  color: #fca5a5; }

/* Reasons */
.reasons-row { display: flex; flex-wrap: wrap; gap: 0.375rem; }
.reason-tag {
    display: inline-block; padding: 0.25rem 0.625rem;
    border-radius: 9999px; font-size: 0.7rem; font-weight: 600;
    background: rgba(99,102,241,0.12); color: #a5b4fc;
    border: 1px solid rgba(99,102,241,0.25);
}

/* Footer */
.modal-footer {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.07);
    position: sticky; bottom: 0; background: #0f172a; z-index: 2;
}
.footer-right-btns { display: flex; align-items: center; gap: 0.75rem; }

.btn-ghost {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.75rem; color: #94a3b8;
    font-size: 0.8125rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.btn-ghost svg { width: 16px; height: 16px; }
.btn-ghost:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }

.btn-cancel {
    padding: 0.625rem 1rem;
    background: transparent; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 0.75rem; color: #64748b;
    font-size: 0.875rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.btn-cancel:hover { border-color: rgba(255,255,255,0.2); color: #94a3b8; }

.btn-override {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1.125rem;
    background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.35);
    border-radius: 0.75rem; color: #fca5a5;
    font-size: 0.875rem; font-weight: 700;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.btn-override svg { width: 15px; height: 15px; }
.btn-override:hover { background: rgba(239,68,68,0.25); border-color: rgba(239,68,68,0.5); color: #fecaca; }
</style>
