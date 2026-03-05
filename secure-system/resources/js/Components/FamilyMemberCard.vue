<script setup>
/**
 * FamilyMemberCard.vue
 *
 * Compact card for mobile view of a single family member.
 * Shows key info and a quick-actions dropdown.
 */
const props = defineProps({
    member:    Object,
    canManage: Boolean,
});

const emit = defineEmits(['edit', 'delete']);

const enrollmentColor = (status) => ({
    'Enrolled':        'enroll-yes',
    'Not Enrolled':    'enroll-no',
    'Not Applicable':  'enroll-na',
}[status] ?? 'enroll-na');

const relationshipIcon = (rel) => ({
    Spouse:  '💑',
    Child:   '👦',
    Parent:  '👴',
    Sibling: '🤝',
    Other:   '👤',
}[rel] ?? '👤');

const formatDatePH = (d) => d
    ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
    : '—';
</script>

<template>
    <div class="member-card">
        <!-- Card header -->
        <div class="card-header">
            <div class="member-avatar">
                {{ member.full_name.charAt(0).toUpperCase() }}
            </div>
            <div class="member-info">
                <div class="member-name">{{ member.full_name }}</div>
                <div class="member-meta">
                    <span class="rel-chip">
                        <span class="rel-icon">{{ relationshipIcon(member.relationship_to_head) }}</span>
                        {{ member.relationship_to_head }}
                    </span>
                    <span class="age-chip">{{ member.age_label ?? member.age + ' yrs' }}</span>
                </div>
            </div>
            <!-- Quick actions -->
            <div v-if="canManage" class="card-actions">
                <button class="action-btn edit-btn" title="Edit" @click="emit('edit', member)">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z"/>
                    </svg>
                </button>
                <button class="action-btn del-btn" title="Remove" @click="emit('delete', member)">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Card body — detail rows -->
        <div class="card-body">
            <div class="detail-row">
                <span class="detail-icon">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 01.75.75V4h7V2.75a.75.75 0 011.5 0V4h.25A2.75 2.75 0 0118 6.75v8.5A2.75 2.75 0 0115.25 18H4.75A2.75 2.75 0 012 15.25v-8.5A2.75 2.75 0 014.75 4H5V2.75A.75.75 0 015.75 2z" clip-rule="evenodd"/></svg>
                </span>
                <span class="detail-label">DOB</span>
                <span class="detail-val">{{ formatDatePH(member.birthdate) }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-icon">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>
                </span>
                <span class="detail-label">Gender</span>
                <span class="detail-val">{{ member.gender }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-icon">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5.25 8.051a.999.999 0 01.356-.405l4-2.666A.978.978 0 0110 5a.978.978 0 01.394.98L10 5.835l1.604.688A1 1 0 0112 7.5v2.5l-2 .857V17a1 1 0 001 1h1a1 1 0 001-1v-7.857L14.75 8.5V7a1 1 0 01.606-.918l1.144-.489A1 1 0 0017 7v2.5A10.003 10.003 0 0110 19c-2.21 0-4.256-.716-5.916-1.923"/></svg>
                </span>
                <span class="detail-label">School</span>
                <span :class="['enroll-badge', enrollmentColor(member.school_enrollment_status)]">
                    {{ member.school_enrollment_status }}
                </span>
            </div>
            <div class="detail-row">
                <span class="detail-icon">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3.5 2A1.5 1.5 0 002 3.5V5c0 1.149.15 2.263.43 3.326a13.022 13.022 0 009.244 9.244c1.063.28 2.177.43 3.326.43h1.5a1.5 1.5 0 001.5-1.5v-1.148a1.5 1.5 0 00-1.175-1.465l-3.223-.716a1.5 1.5 0 00-1.767 1.052l-.267.933c-.117.41-.555.643-.95.48a11.542 11.542 0 01-6.254-6.254c-.163-.395.07-.833.48-.95l.933-.267a1.5 1.5 0 001.052-1.767l-.716-3.223A1.5 1.5 0 004.648 2H3.5z" clip-rule="evenodd"/></svg>
                </span>
                <span class="detail-label">Health Ctr.</span>
                <span :class="['health-badge', member.health_center_registered ? 'health-yes' : 'health-no']">
                    {{ member.health_center_registered ? 'Registered' : 'Not Registered' }}
                </span>
            </div>
            <!-- PSA cert if present -->
            <div v-if="member.birth_certificate_no" class="detail-row">
                <span class="detail-icon">
                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-4.12-4.122A1.5 1.5 0 0011.378 2H4.5zm2.25 8.5a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5zm0 3a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5z" clip-rule="evenodd"/></svg>
                </span>
                <span class="detail-label">PSA No.</span>
                <span class="detail-val mono">{{ member.birth_certificate_no }}</span>
            </div>
            <!-- Flags -->
            <div v-if="member.needs_health_monitoring" class="flag-row flag-health">
                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/></svg>
                Early childhood health monitoring required
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

.member-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1rem; overflow: hidden; font-family: 'Inter', sans-serif;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.member-card:hover { border-color: rgba(99,102,241,0.25); box-shadow: 0 4px 20px rgba(0,0,0,0.2); }

/* Header */
.card-header {
    display: flex; align-items: center; gap: 0.875rem;
    padding: 1rem 1rem 0.75rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.member-avatar {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; font-weight: 700; color: white;
}
.member-info { flex: 1; min-width: 0; }
.member-name { font-size: 0.9375rem; font-weight: 700; color: #f1f5f9; truncate: ellipsis; }
.member-meta { display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap; margin-top: 0.25rem; }

.rel-chip {
    display: inline-flex; align-items: center; gap: 0.25rem;
    font-size: 0.72rem; font-weight: 600; color: #94a3b8;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    padding: 0.15rem 0.5rem; border-radius: 9999px;
}
.rel-icon { font-size: 0.8rem; }
.age-chip {
    font-size: 0.7rem; font-weight: 700; color: #a5b4fc;
    background: rgba(99,102,241,0.15); padding: 0.15rem 0.5rem; border-radius: 9999px;
}

/* Actions */
.card-actions { display: flex; gap: 0.375rem; margin-left: auto; flex-shrink: 0; }
.action-btn {
    width: 30px; height: 30px; border-radius: 0.5rem;
    display: flex; align-items: center; justify-content: center;
    border: none; cursor: pointer; transition: all 0.15s;
}
.action-btn svg { width: 14px; height: 14px; }
.edit-btn { background: rgba(245,158,11,0.12); color: #fcd34d; }
.edit-btn:hover { background: rgba(245,158,11,0.25); }
.del-btn  { background: rgba(239,68,68,0.1);  color: #fca5a5; }
.del-btn:hover  { background: rgba(239,68,68,0.22); }

/* Body */
.card-body { padding: 0.875rem 1rem; display: flex; flex-direction: column; gap: 0.5rem; }

.detail-row {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.8125rem;
}
.detail-icon { width: 14px; height: 14px; color: #475569; flex-shrink: 0; }
.detail-icon svg { width: 14px; height: 14px; }
.detail-label { color: #64748b; font-weight: 600; font-size: 0.75rem; min-width: 72px; flex-shrink: 0; }
.detail-val   { color: #cbd5e1; }
.detail-val.mono { font-family: monospace; font-size: 0.8rem; }

/* Badges */
.enroll-badge {
    display: inline-block; padding: 0.2rem 0.55rem;
    border-radius: 9999px; font-size: 0.7rem; font-weight: 700;
}
.enroll-yes { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
.enroll-no  { background: rgba(239,68,68,0.12);  color: #fca5a5; border: 1px solid rgba(239,68,68,0.3); }
.enroll-na  { background: rgba(100,116,139,0.15); color: #94a3b8; border: 1px solid rgba(100,116,139,0.2); }

.health-badge {
    display: inline-block; padding: 0.2rem 0.55rem;
    border-radius: 9999px; font-size: 0.7rem; font-weight: 700;
}
.health-yes { background: rgba(16,185,129,0.15); color: #6ee7b7; }
.health-no  { background: rgba(100,116,139,0.12); color: #94a3b8; }

/* Flags */
.flag-row {
    display: flex; align-items: center; gap: 0.375rem;
    font-size: 0.72rem; font-weight: 600; padding: 0.5rem 0.75rem;
    border-radius: 0.5rem; margin-top: 0.25rem;
}
.flag-row svg { width: 13px; height: 13px; flex-shrink: 0; }
.flag-health { background: rgba(245,158,11,0.1); color: #fcd34d; border: 1px solid rgba(245,158,11,0.25); }
</style>
