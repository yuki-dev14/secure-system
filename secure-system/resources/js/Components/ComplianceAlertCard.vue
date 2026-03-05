<script setup>
defineProps({
    alert: {
        type: Object,
        required: true,
        // { id, title, message, notification_type, severity, icon, data, created_at_human, is_read }
    },
});

const emit = defineEmits(['dismiss', 'review']);

const severityConfig = {
    high:   { label: 'High',   bg: 'rgba(239,68,68,0.1)',   border: 'rgba(239,68,68,0.25)',   text: '#fca5a5', dot: '#ef4444' },
    medium: { label: 'Medium', bg: 'rgba(245,158,11,0.1)',  border: 'rgba(245,158,11,0.25)',  text: '#fcd34d', dot: '#f59e0b' },
    low:    { label: 'Low',    bg: 'rgba(99,102,241,0.1)',  border: 'rgba(99,102,241,0.25)',  text: '#a5b4fc', dot: '#6366f1' },
};

function cfg(severity) {
    return severityConfig[severity] ?? severityConfig.low;
}

const iconPaths = {
    alert:    'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
    clock:    'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
    calendar: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
    'x-circle': 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    bell:     'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
};

function typeLabel(t) {
    return {
        non_compliant_beneficiary: 'Non-Compliant',
        pending_verification:      'Pending Verification',
        expiring_period:           'Expiring Period',
        compliance_alert:          'Compliance Alert',
    }[t] ?? t;
}

function affectedCount(data) {
    if (!data) return null;
    return data.pending_count ?? null;
}
</script>

<template>
    <div class="cac-card" :style="`background:${cfg(alert.severity).bg}; border-color:${cfg(alert.severity).border}`">

        <!-- Top row: icon + severity + time + dismiss -->
        <div class="cac-top">
            <div class="cac-icon-wrap" :style="`background:${cfg(alert.severity).bg}; border-color:${cfg(alert.severity).border}`">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     :style="`color:${cfg(alert.severity).text}`">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconPaths[alert.icon] ?? iconPaths.bell"/>
                </svg>
            </div>

            <div class="cac-meta">
                <div class="cac-badges">
                    <!-- Severity indicator -->
                    <span class="sev-badge" :style="`color:${cfg(alert.severity).text}; background:${cfg(alert.severity).bg}; border-color:${cfg(alert.severity).border}`">
                        <span class="sev-dot" :style="`background:${cfg(alert.severity).dot}`"></span>
                        {{ cfg(alert.severity).label }}
                    </span>
                    <!-- Type label -->
                    <span class="type-badge">{{ typeLabel(alert.notification_type) }}</span>
                </div>
                <span class="cac-time">{{ alert.created_at_human }}</span>
            </div>

            <button class="cac-dismiss" @click.stop="emit('dismiss', alert)" :title="'Dismiss'" :id="`cac-dismiss-${alert.id}`">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                </svg>
            </button>
        </div>

        <!-- Title & message -->
        <h4 class="cac-title">{{ alert.title }}</h4>
        <p class="cac-message">{{ alert.message }}</p>

        <!-- Affected count if applicable -->
        <div v-if="affectedCount(alert.data)" class="cac-count">
            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M7 8a3 3 0 100-6 3 3 0 000 6zM14.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM1.615 16.428a1.224 1.224 0 01-.569-1.175 6.002 6.002 0 0111.908 0c.058.467-.172.92-.57 1.174A9.953 9.953 0 017 17a9.953 9.953 0 01-5.385-1.572zM14.5 16h-.106c.07-.297.088-.611.048-.933a7.47 7.47 0 00-1.588-3.755 4.502 4.502 0 015.874 2.636.818.818 0 01-.36.98A7.465 7.465 0 0114.5 16z"/></svg>
            <span>{{ affectedCount(alert.data) }} beneficiar{{ affectedCount(alert.data) === 1 ? 'y' : 'ies' }} affected</span>
        </div>

        <!-- Action buttons -->
        <div class="cac-actions">
            <button class="cac-btn-review" @click.stop="emit('review', alert)" :id="`cac-review-${alert.id}`">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41z" clip-rule="evenodd"/></svg>
                Review
            </button>
            <button v-if="!alert.is_read" class="cac-btn-dismiss-text" @click.stop="emit('dismiss', alert)">
                Dismiss
            </button>
            <span v-else class="cac-read-tag">✓ Read</span>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.cac-card {
    font-family: 'Inter', sans-serif;
    border: 1px solid;
    border-radius: 1rem; padding: 1rem 1.25rem;
    display: flex; flex-direction: column; gap: 0.625rem;
    transition: transform 0.15s, box-shadow 0.15s;
}
.cac-card:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }

/* Top row */
.cac-top { display: flex; align-items: flex-start; gap: 0.75rem; }
.cac-icon-wrap {
    width: 36px; height: 36px; border-radius: 0.625rem; border: 1px solid;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.cac-icon-wrap svg { width: 18px; height: 18px; }
.cac-meta { flex: 1; display: flex; flex-direction: column; gap: 0.25rem; }
.cac-badges { display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; }
.sev-badge {
    display: flex; align-items: center; gap: 0.3rem;
    padding: 0.15rem 0.5rem; border-radius: 999px;
    border: 1px solid; font-size: 0.7rem; font-weight: 700;
}
.sev-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.type-badge {
    background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.12);
    border-radius: 999px; padding: 0.15rem 0.5rem;
    font-size: 0.68rem; font-weight: 600; color: #64748b;
}
.cac-time { font-size: 0.7rem; color: #475569; }

.cac-dismiss {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 0.375rem; padding: 0.25rem; cursor: pointer; color: #475569;
    flex-shrink: 0; transition: all 0.15s;
}
.cac-dismiss svg { width: 14px; height: 14px; display: block; }
.cac-dismiss:hover { background: rgba(239,68,68,0.12); color: #fca5a5; }

/* Content */
.cac-title   { font-size: 0.875rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.cac-message { font-size: 0.8rem; color: #94a3b8; margin: 0; line-height: 1.5; }

/* Count */
.cac-count {
    display: flex; align-items: center; gap: 0.375rem;
    font-size: 0.78rem; color: #64748b;
}
.cac-count svg { width: 14px; height: 14px; flex-shrink: 0; }

/* Actions */
.cac-actions { display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem; }
.cac-btn-review {
    display: flex; align-items: center; gap: 0.375rem;
    padding: 0.375rem 0.875rem; border-radius: 0.5rem;
    background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.3);
    color: #a5b4fc; font-size: 0.78rem; font-weight: 600; cursor: pointer;
    font-family: 'Inter', sans-serif; transition: all 0.15s;
}
.cac-btn-review:hover { background: rgba(99,102,241,0.25); }
.cac-btn-review svg   { width: 14px; height: 14px; }
.cac-btn-dismiss-text {
    background: none; border: none; font-size: 0.78rem; color: #475569;
    cursor: pointer; font-family: 'Inter', sans-serif; font-weight: 600;
    padding: 0.375rem 0.5rem; border-radius: 0.375rem; transition: color 0.15s;
}
.cac-btn-dismiss-text:hover { color: #94a3b8; }
.cac-read-tag { font-size: 0.72rem; color: #334155; font-style: italic; }
</style>
