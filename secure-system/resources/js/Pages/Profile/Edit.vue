<script setup>
import { ref, computed } from 'vue';
import { Head, usePage, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import UserProfile from './Partials/UserProfile.vue';
import ChangePassword from './Partials/ChangePassword.vue';

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const activeTab = ref('profile');

const tabs = [
    { id: 'profile',  label: 'Profile Info',    icon: 'user' },
    { id: 'password', label: 'Change Password',  icon: 'lock' },
];
</script>

<template>
    <Head title="My Profile — SECURE System" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-header-content">
                <div class="header-avatar">{{ user?.name?.charAt(0)?.toUpperCase() }}</div>
                <div>
                    <h1 class="page-title">My Profile</h1>
                    <p class="page-subtitle">{{ user?.role }} · {{ user?.office_location }}</p>
                </div>
                <div class="header-actions">
                    <Link :href="route('activity.index')" class="header-btn">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                        Activity Log
                    </Link>
                    <Link :href="route('security.index')" class="header-btn header-btn-accent">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.661 2.237a.531.531 0 01.678 0 11.947 11.947 0 007.078 2.749.5.5 0 01.479.425c.069.52.104 1.05.104 1.589 0 5.162-3.26 9.563-7.834 11.256a.48.48 0 01-.332 0C5.26 16.563 2 12.162 2 7c0-.538.035-1.069.104-1.589a.5.5 0 01.48-.425 11.947 11.947 0 007.077-2.749z" clip-rule="evenodd"/></svg>
                        Security
                    </Link>
                </div>
            </div>
        </template>

        <div class="profile-layout">
            <!-- Sidebar tabs -->
            <aside class="profile-sidebar">
                <nav class="sidebar-nav">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        class="sidebar-tab"
                        :class="{ active: activeTab === tab.id }"
                        @click="activeTab = tab.id"
                    >
                        <!-- User icon -->
                        <svg v-if="tab.icon === 'user'" class="tab-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/>
                        </svg>
                        <!-- Lock icon -->
                        <svg v-if="tab.icon === 'lock'" class="tab-icon" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/>
                        </svg>
                        {{ tab.label }}
                    </button>
                </nav>

                <!-- Quick info card -->
                <div class="quick-info">
                    <div class="qi-row">
                        <span class="qi-label">Role</span>
                        <span class="role-badge" :class="{
                            'role-admin': user?.role === 'Administrator',
                            'role-officer': user?.role === 'Field Officer',
                            'role-verifier': user?.role === 'Compliance Verifier',
                        }">{{ user?.role }}</span>
                    </div>
                    <div class="qi-row">
                        <span class="qi-label">Status</span>
                        <span class="status-badge" :class="user?.status === 'active' ? 'status-active' : 'status-inactive'">
                            {{ user?.status }}
                        </span>
                    </div>
                    <div class="qi-row">
                        <span class="qi-label">Last Login</span>
                        <span class="qi-value">
                            {{ user?.last_login_at
                                ? new Date(user.last_login_at).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'short' })
                                : 'Never' }}
                        </span>
                    </div>
                </div>
            </aside>

            <!-- Main panel -->
            <main class="profile-main">
                <Transition name="tab-fade" mode="out-in">
                    <UserProfile
                        v-if="activeTab === 'profile'"
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                    <ChangePassword
                        v-else-if="activeTab === 'password'"
                    />
                </Transition>
            </main>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

/* ── Header ─────────────────────────────────────────────── */
.page-header-content {
    display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;
    font-family: 'Inter', sans-serif;
}
.header-avatar {
    width: 52px; height: 52px; border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.375rem; font-weight: 700; color: white;
    box-shadow: 0 4px 16px rgba(99,102,241,0.4); flex-shrink: 0;
}
.page-title { font-size: 1.25rem; font-weight: 700; color: #f1f5f9; margin: 0; }
.page-subtitle { font-size: 0.8125rem; color: #64748b; margin: 0.2rem 0 0; }
.header-actions { display: flex; gap: 0.5rem; margin-left: auto; }
.header-btn {
    display: flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 0.875rem; border-radius: 0.625rem;
    font-size: 0.8125rem; font-weight: 500; color: #94a3b8;
    text-decoration: none; background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.08); transition: all 0.2s;
    font-family: 'Inter', sans-serif;
}
.header-btn svg { width: 15px; height: 15px; }
.header-btn:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }
.header-btn-accent { background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.3); color: #a5b4fc; }
.header-btn-accent:hover { background: rgba(99,102,241,0.25); }

/* ── Layout ─────────────────────────────────────────────── */
.profile-layout {
    display: grid;
    grid-template-columns: 220px 1fr;
    gap: 1.5rem;
    font-family: 'Inter', sans-serif;
    align-items: start;
}
@media (max-width: 768px) {
    .profile-layout { grid-template-columns: 1fr; }
}

/* ── Sidebar ─────────────────────────────────────────────── */
.profile-sidebar {
    display: flex; flex-direction: column; gap: 1rem;
    position: sticky; top: 80px;
}

.sidebar-nav {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; overflow: hidden;
    display: flex; flex-direction: column;
}

.sidebar-tab {
    display: flex; align-items: center; gap: 0.625rem;
    padding: 0.875rem 1rem;
    font-size: 0.875rem; font-weight: 500; color: #64748b;
    background: none; border: none; cursor: pointer;
    text-align: left; width: 100%; transition: all 0.2s;
    font-family: 'Inter', sans-serif;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.sidebar-tab:last-child { border-bottom: none; }
.sidebar-tab:hover { background: rgba(255,255,255,0.05); color: #94a3b8; }
.sidebar-tab.active { background: rgba(99,102,241,0.12); color: #a5b4fc; }
.tab-icon { width: 16px; height: 16px; flex-shrink: 0; }

/* Quick Info card */
.quick-info {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; padding: 1rem;
    display: flex; flex-direction: column; gap: 0.75rem;
}
.qi-row { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
.qi-label { font-size: 0.75rem; color: #475569; font-weight: 500; }
.qi-value { font-size: 0.75rem; color: #94a3b8; text-align: right; }

.role-badge { padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; white-space: nowrap; }
.role-admin    { background: rgba(99,102,241,0.2); color: #a5b4fc; }
.role-officer  { background: rgba(16,185,129,0.2); color: #6ee7b7; }
.role-verifier { background: rgba(245,158,11,0.2); color: #fcd34d; }

.status-badge { padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.65rem; font-weight: 600; }
.status-active   { background: rgba(16,185,129,0.15); color: #34d399; border: 1px solid rgba(16,185,129,0.3); }
.status-inactive { background: rgba(239,68,68,0.15);  color: #f87171; border: 1px solid rgba(239,68,68,0.3); }

/* Main content */
.profile-main {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 1rem; overflow: hidden;
    min-height: 400px;
}

/* Tab transitions */
.tab-fade-enter-active, .tab-fade-leave-active { transition: opacity 0.2s, transform 0.2s; }
.tab-fade-enter-from { opacity: 0; transform: translateY(6px); }
.tab-fade-leave-to   { opacity: 0; transform: translateY(-6px); }
</style>
