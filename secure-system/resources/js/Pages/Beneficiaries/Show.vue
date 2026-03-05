<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { usePermissions } from '@/Composables/usePermissions';

const props = defineProps({
    beneficiary:        Object,
    familyMembersCount: Number,
    recentActivities:   Array,
    canEdit:            Boolean,
    canDelete:          Boolean,
});

const { isAdmin } = usePermissions();
const page = usePage();

const activeTab = ref('basic');

const TABS = [
    { id: 'basic',        label: 'Basic Information',   icon: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z' },
    { id: 'family',       label: 'Family Members',      icon: 'M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z' },
    { id: 'compliance',   label: 'Compliance Records',  icon: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z' },
    { id: 'documents',    label: 'Submitted Docs',      icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z' },
    { id: 'distribution', label: 'Distribution History',icon: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z' },
    { id: 'activity',     label: 'Activity Log',        icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const b = computed(() => props.beneficiary);

const formatDate = (d) => d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—';
const formatDateTime = (d) => d ? new Date(d).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
const formatCurrency = (v) => v !== undefined ? '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '—';

const activityTypeColor = (type) => ({
    scan: 'act-scan', verify: 'act-verify', approve: 'act-approve',
    reject: 'act-reject', view: 'act-view', edit: 'act-edit', delete: 'act-delete',
}[type] ?? 'act-view');

const confirmDeactivate = () => {
    if (confirm(`Deactivate ${b.value.family_head_name} (${b.value.bin})? This cannot be undone without Administrator action.`)) {
        router.delete(route('beneficiaries.destroy', b.value.id));
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <div class="page-hd">
                <div class="hd-left">
                    <Link :href="route('beneficiaries.index')" class="back-link">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.78 5.22a.75.75 0 010 1.06L8.06 10l3.72 3.72a.75.75 0 11-1.06 1.06l-4.25-4.25a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0z" clip-rule="evenodd"/></svg>
                        Beneficiaries
                    </Link>
                    <div class="hd-title-row">
                        <div class="hd-avatar">{{ b.family_head_name.charAt(0).toUpperCase() }}</div>
                        <div>
                            <h1 class="page-title">{{ b.family_head_name }}</h1>
                            <div class="hd-meta">
                                <span class="bin-code">{{ b.bin }}</span>
                                <span :class="['status-badge', b.is_active ? 'badge-active' : 'badge-inactive']">
                                    {{ b.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="token-badge">Token: {{ b.token_status }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="hd-actions">
                    <Link
                        v-if="canEdit"
                        :href="route('beneficiaries.edit', b.id)"
                        class="btn-secondary"
                    >
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z"/></svg>
                        Edit
                    </Link>
                    <button
                        v-if="canDelete && b.is_active"
                        class="btn-danger"
                        @click="confirmDeactivate"
                    >
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                        Deactivate
                    </button>
                </div>
            </div>
        </template>

        <div class="detail-wrap">
            <!-- ── Summary stat strip ─────────────────────────────────── -->
            <div class="stat-strip">
                <div class="strip-item">
                    <span class="strip-label">Household Size</span>
                    <span class="strip-val">{{ b.household_size }} member(s)</span>
                </div>
                <div class="strip-item">
                    <span class="strip-label">Registered Family Members</span>
                    <span class="strip-val">{{ familyMembersCount }}</span>
                </div>
                <div class="strip-item">
                    <span class="strip-label">Annual Income</span>
                    <span class="strip-val">{{ formatCurrency(b.annual_income) }}</span>
                </div>
                <div class="strip-item">
                    <span class="strip-label">Registered On</span>
                    <span class="strip-val">{{ formatDate(b.created_at) }}</span>
                </div>
                <div class="strip-item">
                    <span class="strip-label">Registered By</span>
                    <span class="strip-val">{{ b.registered_by?.name ?? '—' }}</span>
                </div>
            </div>

            <!-- ── Tab navigation ─────────────────────────────────────── -->
            <div class="tab-nav">
                <button
                    v-for="tab in TABS"
                    :key="tab.id"
                    :id="`tab-${tab.id}`"
                    class="tab-btn"
                    :class="{ 'tab-active': activeTab === tab.id }"
                    @click="activeTab = tab.id"
                >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon"/>
                    </svg>
                    {{ tab.label }}
                    <span v-if="tab.id === 'family' && familyMembersCount > 0" class="tab-count">{{ familyMembersCount }}</span>
                </button>
            </div>

            <!-- ── Tab content ────────────────────────────────────────── -->
            <div class="tab-panel">

                <!-- Tab 1: Basic Information -->
                <div v-if="activeTab === 'basic'" class="info-sections">
                    <div class="info-card">
                        <h3 class="info-card-title">Personal Details</h3>
                        <div class="info-grid">
                            <div class="info-field"><span class="info-label">Full Name</span><span class="info-val">{{ b.family_head_name }}</span></div>
                            <div class="info-field"><span class="info-label">Date of Birth</span><span class="info-val">{{ formatDate(b.family_head_birthdate) }}</span></div>
                            <div class="info-field"><span class="info-label">Gender</span><span class="info-val">{{ b.gender }}</span></div>
                            <div class="info-field"><span class="info-label">Civil Status</span><span class="info-val">{{ b.civil_status }}</span></div>
                            <div class="info-field"><span class="info-label">Contact Number</span><span class="info-val mono">{{ b.contact_number }}</span></div>
                            <div class="info-field"><span class="info-label">Email Address</span><span class="info-val">{{ b.email ?? 'Not provided' }}</span></div>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3 class="info-card-title">Address</h3>
                        <div class="info-grid">
                            <div class="info-field"><span class="info-label">Barangay</span><span class="info-val">{{ b.barangay }}</span></div>
                            <div class="info-field"><span class="info-label">Municipality</span><span class="info-val">{{ b.municipality }}</span></div>
                            <div class="info-field"><span class="info-label">Province</span><span class="info-val">{{ b.province }}</span></div>
                            <div class="info-field"><span class="info-label">ZIP Code</span><span class="info-val mono">{{ b.zip_code }}</span></div>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3 class="info-card-title">Economic Profile</h3>
                        <div class="info-grid">
                            <div class="info-field"><span class="info-label">Annual Income</span><span class="info-val">{{ formatCurrency(b.annual_income) }}</span></div>
                            <div class="info-field"><span class="info-label">Household Size</span><span class="info-val">{{ b.household_size }} member(s)</span></div>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3 class="info-card-title">System Information</h3>
                        <div class="info-grid">
                            <div class="info-field"><span class="info-label">BIN</span><span class="info-val mono accent">{{ b.bin }}</span></div>
                            <div class="info-field"><span class="info-label">Token Status</span>
                                <span :class="['token-pill', `token-${b.token_status}`]">{{ b.token_status }}</span>
                            </div>
                            <div class="info-field"><span class="info-label">Registered By</span><span class="info-val">{{ b.registered_by?.name ?? '—' }}</span></div>
                            <div class="info-field"><span class="info-label">Date Registered</span><span class="info-val">{{ formatDateTime(b.created_at) }}</span></div>
                            <div class="info-field"><span class="info-label">Last Updated</span><span class="info-val">{{ formatDateTime(b.updated_at) }}</span></div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Family Members -->
                <div v-if="activeTab === 'family'">
                    <div class="family-tab-header">
                        <p class="family-tab-sub">{{ familyMembersCount }} member(s) registered in this household.</p>
                        <Link
                            :href="route('family-members.index', b.id)"
                            class="btn-manage-family"
                        >
                            <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                            Manage Family Members
                        </Link>
                    </div>
                    <div v-if="!b.family_members || b.family_members.length === 0" class="empty-msg">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                        <p>No family members registered yet.</p>
                        <p class="empty-sub">Use the Manage button to add household members.</p>
                    </div>
                    <div v-else class="family-table-wrap">
                        <table class="data-table">
                            <thead><tr>
                                <th>Name</th><th>Relationship</th><th>Age</th>
                                <th>Gender</th><th>School Status</th><th>Health Ctr.</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="m in b.family_members" :key="m.id">
                                    <td class="fw600">{{ m.full_name }}</td>
                                    <td>{{ m.relationship_to_head }}</td>
                                    <td>
                                        <span class="age-pill">
                                            {{ new Date().getFullYear() - new Date(m.birthdate).getFullYear() }} yrs
                                        </span>
                                    </td>
                                    <td>{{ m.gender }}</td>
                                    <td>
                                        <span :class="['badge-mini', m.school_enrollment_status === 'Enrolled' ? 'badge-yes' : m.school_enrollment_status === 'Not Enrolled' ? 'badge-red-mini' : 'badge-no']">
                                            {{ m.school_enrollment_status }}
                                        </span>
                                    </td>
                                    <td>
                                        <span :class="['badge-mini', m.health_center_registered ? 'badge-yes' : 'badge-no']">
                                            {{ m.health_center_registered ? 'Registered' : 'Not Registered' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tabs 3–5: Placeholder cards -->
                <div v-if="activeTab === 'compliance' || activeTab === 'documents' || activeTab === 'distribution'" class="empty-msg">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z"/></svg>
                    <p>No records found for this section.</p>
                    <p class="empty-sub">Records will appear here once data is available.</p>
                </div>

                <!-- Tab 6: Activity Log -->
                <div v-if="activeTab === 'activity'">
                    <div v-if="!recentActivities || recentActivities.length === 0" class="empty-msg">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p>No activity recorded yet.</p>
                    </div>
                    <div v-else class="activity-list">
                        <div v-for="log in recentActivities" :key="log.id" class="activity-item">
                            <div :class="['act-dot', activityTypeColor(log.activity_type)]"></div>
                            <div class="act-content">
                                <div class="act-desc">{{ log.activity_description }}</div>
                                <div class="act-meta">
                                    <span>{{ log.user?.name ?? 'System' }}</span>
                                    <span>·</span>
                                    <span>{{ formatDateTime(log.timestamp) }}</span>
                                    <span :class="['act-status', log.status === 'success' ? 'act-success' : 'act-failed']">
                                        {{ log.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

.detail-wrap { display: flex; flex-direction: column; gap: 1.5rem; font-family: 'Inter', sans-serif; }

/* Page header */
.page-hd { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.hd-left { display: flex; flex-direction: column; gap: 0.75rem; }
.back-link {
    display: inline-flex; align-items: center; gap: 0.375rem;
    font-size: 0.8125rem; color: #64748b; text-decoration: none; transition: color 0.2s;
}
.back-link svg { width: 16px; height: 16px; }
.back-link:hover { color: #a5b4fc; }
.hd-title-row { display: flex; align-items: center; gap: 1rem; }
.hd-avatar {
    width: 52px; height: 52px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.375rem; font-weight: 800; color: white;
}
.page-title { font-size: 1.375rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.375rem; }
.hd-meta    { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
.bin-code   { font-family: monospace; font-size: 0.875rem; font-weight: 700; color: #a5b4fc; }
.status-badge { display: inline-block; padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
.badge-active   { background: rgba(16,185,129,0.15); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
.badge-inactive { background: rgba(239,68,68,0.12);  color: #fca5a5; border: 1px solid rgba(239,68,68,0.25); }
.token-badge { font-size: 0.7rem; color: #64748b; background: rgba(255,255,255,0.05); padding: 0.2rem 0.5rem; border-radius: 9999px; border: 1px solid rgba(255,255,255,0.1); }

.hd-actions { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
.btn-secondary {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1.125rem;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
    border-radius: 0.75rem; color: #f1f5f9;
    font-size: 0.875rem; font-weight: 600; text-decoration: none;
    transition: all 0.2s;
}
.btn-secondary svg { width: 16px; height: 16px; }
.btn-secondary:hover { background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.3); color: #a5b4fc; }
.btn-danger {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1.125rem;
    background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25);
    border-radius: 0.75rem; color: #fca5a5;
    font-size: 0.875rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif;
}
.btn-danger svg { width: 16px; height: 16px; }
.btn-danger:hover { background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.4); }

/* Stat strip */
.stat-strip {
    display: flex; gap: 0; flex-wrap: wrap;
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; overflow: hidden;
}
.strip-item {
    flex: 1; min-width: 150px; padding: 1.25rem 1.5rem;
    display: flex; flex-direction: column; gap: 0.25rem;
    border-right: 1px solid rgba(255,255,255,0.06);
}
.strip-item:last-child { border-right: none; }
.strip-label { font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }
.strip-val   { font-size: 1rem; font-weight: 700; color: #f1f5f9; }

/* Tab nav */
.tab-nav {
    display: flex; gap: 0.25rem; overflow-x: auto; padding-bottom: 0.25rem;
    scrollbar-width: none;
}
.tab-nav::-webkit-scrollbar { display: none; }
.tab-btn {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1rem; border-radius: 0.75rem;
    font-size: 0.8125rem; font-weight: 600; color: #64748b;
    background: none; border: 1px solid transparent;
    cursor: pointer; white-space: nowrap; transition: all 0.2s;
    font-family: 'Inter', sans-serif; position: relative;
}
.tab-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
.tab-btn:hover { background: rgba(255,255,255,0.05); color: #94a3b8; }
.tab-active {
    background: rgba(99,102,241,0.12) !important;
    border-color: rgba(99,102,241,0.25) !important;
    color: #a5b4fc !important;
}
.tab-count {
    background: rgba(99,102,241,0.3); color: #a5b4fc;
    font-size: 0.65rem; font-weight: 700;
    padding: 0.1rem 0.4rem; border-radius: 9999px;
}

/* Tab panel */
.tab-panel {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; padding: 1.5rem; min-height: 300px;
}

/* Info sections */
.info-sections { display: flex; flex-direction: column; gap: 1.25rem; }
.info-card {
    background: rgba(0,0,0,0.15); border: 1px solid rgba(255,255,255,0.06);
    border-radius: 0.875rem; overflow: hidden;
}
.info-card-title {
    font-size: 0.75rem; font-weight: 700; color: #a5b4fc;
    text-transform: uppercase; letter-spacing: 0.05em;
    padding: 0.75rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.06);
    background: rgba(99,102,241,0.05);
}
.info-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 0;
}
.info-field {
    display: flex; flex-direction: column; gap: 0.25rem;
    padding: 0.875rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.04);
    border-right: 1px solid rgba(255,255,255,0.04);
}
.info-label { font-size: 0.7rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
.info-val   { font-size: 0.9rem; color: #f1f5f9; font-weight: 500; }
.info-val.mono   { font-family: monospace; }
.info-val.accent { color: #a5b4fc; }
.fw600 { font-weight: 600; }

/* Token pill */
.token-pill { display: inline-block; padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; }
.token-active  { background: rgba(16,185,129,0.15); color: #6ee7b7; }
.token-expired { background: rgba(245,158,11,0.15); color: #fcd34d; }
.token-revoked { background: rgba(239,68,68,0.12);  color: #fca5a5; }

/* Family table */
.family-table-wrap { overflow-x: auto; }
.family-tab-header {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem;
}
.family-tab-sub { font-size: 0.875rem; color: #64748b; margin: 0; }
.btn-manage-family {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border-radius: 0.75rem; color: white;
    font-size: 0.8125rem; font-weight: 700; text-decoration: none;
    box-shadow: 0 4px 12px rgba(99,102,241,0.3); transition: all 0.2s;
}
.btn-manage-family svg { width: 15px; height: 15px; }
.btn-manage-family:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(99,102,241,0.4); }
.age-pill {
    display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px;
    font-size: 0.7rem; font-weight: 700; color: #a5b4fc;
    background: rgba(99,102,241,0.15);
}
.badge-red-mini { background: rgba(239,68,68,0.12); color: #fca5a5; }
.data-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; border-radius: 0.75rem; overflow: hidden; }
.data-table th {
    padding: 0.75rem 1rem; text-align: left;
    font-size: 0.75rem; font-weight: 600; color: #64748b;
    text-transform: uppercase; letter-spacing: 0.04em;
    background: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.07);
}
.data-table td { padding: 0.75rem 1rem; color: #cbd5e1; border-bottom: 1px solid rgba(255,255,255,0.04); }
.badge-mini { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 700; }
.badge-yes { background: rgba(16,185,129,0.15); color: #6ee7b7; }
.badge-no  { background: rgba(100,116,139,0.15); color: #94a3b8; }

/* Empty message */
.empty-msg {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 0.75rem; padding: 3rem 1rem; color: #475569; text-align: center;
}
.empty-msg svg { width: 52px; height: 52px; color: #334155; }
.empty-msg p   { font-size: 0.9375rem; font-weight: 600; color: #64748b; margin: 0; }
.empty-sub     { font-size: 0.8125rem; font-weight: 400; color: #475569 !important; }

/* Activity log */
.activity-list { display: flex; flex-direction: column; gap: 0; }
.activity-item {
    display: flex; gap: 1rem; align-items: flex-start;
    padding: 0.875rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.activity-item:last-child { border-bottom: none; }
.act-dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; margin-top: 5px;
}
.act-scan    { background: #60a5fa; }
.act-verify  { background: #34d399; }
.act-approve { background: #6ee7b7; }
.act-reject  { background: #f87171; }
.act-view    { background: #a5b4fc; }
.act-edit    { background: #fcd34d; }
.act-delete  { background: #fca5a5; }

.act-content { flex: 1; }
.act-desc    { font-size: 0.875rem; color: #f1f5f9; font-weight: 500; margin-bottom: 0.25rem; }
.act-meta    { display: flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; color: #64748b; flex-wrap: wrap; }
.act-status  { padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 700; }
.act-success { background: rgba(16,185,129,0.15); color: #6ee7b7; }
.act-failed  { background: rgba(239,68,68,0.12);  color: #fca5a5; }
</style>
