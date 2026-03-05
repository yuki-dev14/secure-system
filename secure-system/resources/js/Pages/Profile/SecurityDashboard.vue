<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    sessions:      Array,
    loginAttempts: Array,
    stats:         Object,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

// Terminate other sessions
const terminatePassword = ref('');
const terminateError    = ref('');
const terminating       = ref(false);
const showTerminateForm = ref(false);

const terminateOtherSessions = () => {
    terminating.value = true;
    terminateError.value = '';

    router.delete(route('security.sessions.terminate'), {
        data: { password: terminatePassword.value },
        preserveScroll: true,
        onSuccess: () => {
            terminatePassword.value = '';
            showTerminateForm.value = false;
        },
        onError: (errors) => {
            terminateError.value = errors.password ?? 'An error occurred.';
        },
        onFinish: () => {
            terminating.value = false;
        },
    });
};

const formatTime = (ts) => {
    if (!ts) return '—';
    // ts may be unix timestamp (from sessions table) or ISO string
    const d = typeof ts === 'number' ? new Date(ts * 1000) : new Date(ts);
    return d.toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' });
};

const parseUA = (ua) => {
    if (!ua) return { browser: 'Unknown', os: 'Unknown' };
    const browser =
        /Chrome\/\d/.test(ua) && !/Edg\//.test(ua) ? 'Chrome' :
        /Firefox\/\d/.test(ua) ? 'Firefox' :
        /Edg\/\d/.test(ua) ? 'Edge' :
        /Safari\/\d/.test(ua) && !/Chrome/.test(ua) ? 'Safari' :
        'Browser';
    const os =
        /Windows/.test(ua) ? 'Windows' :
        /Mac OS/.test(ua) ? 'macOS' :
        /Linux/.test(ua) ? 'Linux' :
        /Android/.test(ua) ? 'Android' :
        /iPhone|iPad/.test(ua) ? 'iOS' : 'Unknown OS';
    return { browser, os };
};
</script>

<template>
    <Head title="Security Dashboard — SECURE System" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-hdr">
                <div class="hdr-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="page-title">Security Dashboard</h1>
                    <p class="page-subtitle">Monitor your account security and active sessions</p>
                </div>
                <Link :href="route('profile.edit')" class="back-btn">← Back to Profile</Link>
            </div>
        </template>

        <div class="content-wrap">
            <!-- ── Stats row ────────────────────────────────────────── -->
            <div class="stats-row">
                <div class="stat-card stat-sessions">
                    <div class="stat-icon">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M19 10a.75.75 0 00-.75-.75H8.704l1.048-.943a.75.75 0 10-1.004-1.114l-2.5 2.25a.75.75 0 000 1.114l2.5 2.25a.75.75 0 101.004-1.114l-1.048-.943h9.546A.75.75 0 0019 10z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value">{{ stats.active_sessions }}</span>
                        <span class="stat-label">Active Sessions</span>
                    </div>
                </div>

                <div class="stat-card" :class="stats.failed_logins_24h > 0 ? 'stat-danger' : 'stat-safe'">
                    <div class="stat-icon">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value">{{ stats.failed_logins_24h }}</span>
                        <span class="stat-label">Failed Logins (24h)</span>
                    </div>
                </div>

                <div class="stat-card stat-info-card">
                    <div class="stat-icon">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value stat-value-sm">{{ stats.password_changed_at ? formatTime(stats.password_changed_at) : 'Never changed' }}</span>
                        <span class="stat-label">Password Last Changed</span>
                    </div>
                </div>

                <div class="stat-card stat-info-card">
                    <div class="stat-icon">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>
                    </div>
                    <div class="stat-info">
                        <span class="stat-value stat-value-sm">{{ stats.last_login_at ? formatTime(stats.last_login_at) : 'N/A' }}</span>
                        <span class="stat-label">Last Sign-In</span>
                    </div>
                </div>
            </div>

            <!-- ── Sessions ────────────────────────────────────────── -->
            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd"/></svg>
                        Active Sessions
                    </h2>
                    <button
                        v-if="stats.active_sessions > 1"
                        @click="showTerminateForm = !showTerminateForm"
                        class="btn-danger-outline"
                    >
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                        Terminate Other Sessions
                    </button>
                </div>

                <!-- Confirm terminate form -->
                <Transition name="slide">
                    <div v-if="showTerminateForm" class="terminate-form">
                        <p class="terminate-note">Enter your current password to sign out all other devices.</p>
                        <div class="term-row">
                            <div class="input-wrapper" :class="{ 'is-invalid': terminateError }">
                                <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                                <input type="password" v-model="terminatePassword" class="field-input" placeholder="Current password" @keyup.enter="terminateOtherSessions"/>
                            </div>
                            <button @click="terminateOtherSessions" class="btn-danger" :disabled="!terminatePassword || terminating">
                                <span v-if="!terminating">Confirm</span>
                                <svg v-else class="spinner" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="60" stroke-dashoffset="20" stroke-linecap="round"/></svg>
                            </button>
                            <button @click="showTerminateForm = false; terminateError = ''" class="btn-cancel">Cancel</button>
                        </div>
                        <span v-if="terminateError" class="field-error">{{ terminateError }}</span>
                    </div>
                </Transition>

                <!-- Sessions list -->
                <div class="sessions-list">
                    <div v-for="session in sessions" :key="session.id" class="session-item" :class="{ 'is-current': session.is_current }">
                        <div class="session-device">
                            <div class="device-icon" :class="{ 'device-current': session.is_current }">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z" clip-rule="evenodd"/></svg>
                            </div>
                            <div>
                                <div class="device-name">
                                    {{ parseUA(session.user_agent).browser }} on {{ parseUA(session.user_agent).os }}
                                    <span v-if="session.is_current" class="current-tag">This device</span>
                                </div>
                                <div class="device-meta">{{ session.ip_address }} · Last active {{ formatTime(session.last_activity) }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Recent Login Attempts ───────────────────────────── -->
            <div class="section-card">
                <div class="section-head">
                    <h2 class="section-title">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                        Recent Login Attempts
                    </h2>
                    <Link :href="route('activity.index', { type: 'verify' })" class="view-all-link">View All →</Link>
                </div>

                <div v-if="loginAttempts.length === 0" class="empty-mini">No recent login activity recorded.</div>

                <div class="attempts-list">
                    <div v-for="(attempt, i) in loginAttempts" :key="i" class="attempt-item">
                        <div class="attempt-status" :class="attempt.status === 'success' ? 'att-success' : 'att-fail'">
                            <svg v-if="attempt.status === 'success'" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/></svg>
                            <svg v-else viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                        </div>
                        <div class="attempt-desc">{{ attempt.activity_description }}</div>
                        <div class="attempt-meta">
                            <code class="ip-code">{{ attempt.ip_address }}</code>
                            <span class="attempt-time">{{ formatTime(attempt.timestamp) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

/* Header */
.page-hdr { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; font-family: 'Inter', sans-serif; }
.hdr-icon { width: 44px; height: 44px; border-radius: 12px; background: rgba(16,185,129,0.12); border: 1px solid rgba(16,185,129,0.25); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.hdr-icon svg { width: 22px; height: 22px; color: #34d399; }
.page-title { font-size: 1.125rem; font-weight: 700; color: #f1f5f9; margin: 0 0 0.2rem; }
.page-subtitle { font-size: 0.8125rem; color: #64748b; margin: 0; }
.back-btn { margin-left: auto; font-size: 0.8125rem; color: #94a3b8; text-decoration: none; padding: 0.5rem 0.875rem; border-radius: 0.625rem; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.04); transition: all 0.2s; font-family: 'Inter', sans-serif; }
.back-btn:hover { color: #f1f5f9; background: rgba(255,255,255,0.08); }

/* Layout */
.content-wrap { font-family: 'Inter', sans-serif; display: flex; flex-direction: column; gap: 1.5rem; }

/* Stats */
.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; }
.stat-card {
    display: flex; align-items: center; gap: 1rem;
    padding: 1.125rem 1.25rem; border-radius: 1rem;
    border: 1px solid; transition: transform 0.2s;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-sessions   { background: rgba(99,102,241,0.08);  border-color: rgba(99,102,241,0.2); }
.stat-sessions .stat-icon { color: #a5b4fc; }
.stat-sessions .stat-value { color: #a5b4fc; }
.stat-danger     { background: rgba(239,68,68,0.08);   border-color: rgba(239,68,68,0.25); }
.stat-danger .stat-icon { color: #f87171; }
.stat-danger .stat-value { color: #f87171; }
.stat-safe       { background: rgba(16,185,129,0.08);  border-color: rgba(16,185,129,0.2); }
.stat-safe .stat-icon { color: #34d399; }
.stat-safe .stat-value { color: #34d399; }
.stat-info-card  { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.08); }
.stat-info-card .stat-icon { color: #64748b; }
.stat-info-card .stat-value { color: #94a3b8; }
.stat-icon { display: flex; align-items: center; }
.stat-icon svg { width: 24px; height: 24px; }
.stat-info { display: flex; flex-direction: column; gap: 0.2rem; }
.stat-value { font-size: 1.625rem; font-weight: 700; line-height: 1; }
.stat-value-sm { font-size: 0.8125rem; line-height: 1.4; }
.stat-label { font-size: 0.75rem; color: #64748b; }

/* Section card */
.section-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; overflow: hidden;
}
.section-head { display: flex; align-items: center; gap: 0.5rem; padding: 1.125rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
.section-title { display: flex; align-items: center; gap: 0.5rem; font-size: 0.9375rem; font-weight: 700; color: #f1f5f9; margin: 0; flex: 1; }
.section-title svg { width: 18px; height: 18px; color: #64748b; }

.btn-danger-outline {
    display: flex; align-items: center; gap: 0.375rem;
    padding: 0.4375rem 0.875rem; border-radius: 0.625rem;
    font-size: 0.8rem; font-weight: 500;
    background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25);
    color: #f87171; cursor: pointer; font-family: 'Inter', sans-serif;
    transition: all 0.2s;
}
.btn-danger-outline svg { width: 14px; height: 14px; }
.btn-danger-outline:hover { background: rgba(239,68,68,0.18); }

.view-all-link { font-size: 0.8rem; color: #64748b; text-decoration: none; transition: color 0.2s; }
.view-all-link:hover { color: #a5b4fc; }

/* Terminate form */
.terminate-form { padding: 1rem 1.25rem; background: rgba(239,68,68,0.05); border-bottom: 1px solid rgba(239,68,68,0.12); display: flex; flex-direction: column; gap: 0.75rem; }
.terminate-note { font-size: 0.8125rem; color: #fca5a5; margin: 0; }
.term-row { display: flex; gap: 0.625rem; align-items: center; flex-wrap: wrap; }
.input-wrapper { display: flex; align-items: center; border-radius: 0.625rem; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); flex: 1; min-width: 200px; transition: border-color 0.2s; }
.input-wrapper:focus-within { border-color: #f87171; }
.input-wrapper.is-invalid { border-color: #f87171; }
.input-icon { width: 15px; height: 15px; color: #64748b; margin-left: 0.75rem; flex-shrink: 0; }
.field-input { flex: 1; background: transparent; border: none; outline: none; padding: 0.6rem 0.75rem; font-size: 0.875rem; color: #f1f5f9; font-family: 'Inter', sans-serif; }
.field-error { font-size: 0.75rem; color: #f87171; }
.btn-danger { padding: 0.6rem 1.125rem; background: #ef4444; color: white; border: none; border-radius: 0.625rem; cursor: pointer; font-size: 0.875rem; font-weight: 600; font-family: 'Inter', sans-serif; display: flex; align-items: center; transition: all 0.2s; }
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-cancel { padding: 0.6rem 1rem; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #94a3b8; border-radius: 0.625rem; cursor: pointer; font-size: 0.875rem; font-family: 'Inter', sans-serif; transition: all 0.2s; }
.btn-cancel:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }
@keyframes spin { to { transform: rotate(360deg); } }
.spinner { width: 16px; height: 16px; animation: spin 0.8s linear infinite; }

/* Sessions list */
.sessions-list { display: flex; flex-direction: column; }
.session-item { display: flex; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.15s; }
.session-item:last-child { border-bottom: none; }
.session-item:hover { background: rgba(255,255,255,0.02); }
.session-item.is-current { background: rgba(99,102,241,0.06); }
.session-device { display: flex; align-items: center; gap: 0.875rem; }
.device-icon { width: 36px; height: 36px; border-radius: 8px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.device-icon svg { width: 18px; height: 18px; color: #64748b; }
.device-current svg { color: #a5b4fc; }
.device-current { background: rgba(99,102,241,0.12); border-color: rgba(99,102,241,0.3); }
.device-name { font-size: 0.875rem; font-weight: 500; color: #e2e8f0; display: flex; align-items: center; gap: 0.5rem; }
.device-meta { font-size: 0.75rem; color: #64748b; margin-top: 0.2rem; }
.current-tag { padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; background: rgba(99,102,241,0.2); color: #a5b4fc; }

/* Login attempts */
.attempts-list { display: flex; flex-direction: column; }
.empty-mini { padding: 1.5rem 1.25rem; font-size: 0.8125rem; color: #475569; }
.attempt-item { display: flex; align-items: center; gap: 0.875rem; padding: 0.875rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.04); flex-wrap: wrap; }
.attempt-item:last-child { border-bottom: none; }
.attempt-status svg { width: 18px; height: 18px; flex-shrink: 0; }
.att-success svg { color: #34d399; }
.att-fail svg    { color: #f87171; }
.attempt-desc { flex: 1; font-size: 0.8125rem; color: #94a3b8; min-width: 200px; }
.attempt-meta { display: flex; align-items: center; gap: 0.75rem; margin-left: auto; }
.ip-code { font-size: 0.75rem; color: #64748b; background: rgba(255,255,255,0.05); padding: 0.15rem 0.4rem; border-radius: 4px; }
.attempt-time { font-size: 0.75rem; color: #475569; white-space: nowrap; }

/* Transitions */
.slide-enter-active, .slide-leave-active { transition: all 0.3s ease; max-height: 200px; overflow: hidden; }
.slide-enter-from, .slide-leave-to { max-height: 0; opacity: 0; }
</style>
