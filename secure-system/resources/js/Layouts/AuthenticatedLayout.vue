<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useSession } from '@/Composables/useSession';
import { usePermissions } from '@/Composables/usePermissions';
import ToastNotification    from '@/Components/ToastNotification.vue';
import NotificationCenter  from '@/Components/NotificationCenter.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const { showTimeoutWarning, timeLeftSeconds, renewSession } = useSession();
const { isAdmin, isFieldOfficer, isComplianceVerifier, can } = usePermissions();

const showingNav = ref(false);
const showingUserMenu = ref(false);

const formatTime = (secs) => {
    const m = Math.floor(secs / 60);
    const s = secs % 60;
    return `${m}:${s.toString().padStart(2, '0')}`;
};

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="app-layout">
        <!-- Toast notifications -->
        <ToastNotification />

        <!-- ── Session Timeout Warning ──────────────────────────────── -->
        <Transition name="slide-down">
            <div v-if="showTimeoutWarning" class="session-warning">
                <div class="session-warning-content">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="warn-icon">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/>
                    </svg>
                    <span>Your session will expire in <strong>{{ formatTime(timeLeftSeconds) }}</strong> due to inactivity.</span>
                    <button class="session-renew-btn" @click="renewSession">Stay Signed In</button>
                    <button class="session-logout-btn" @click="logout">Sign Out</button>
                </div>
            </div>
        </Transition>

        <!-- ── Navigation ─────────────────────────────────────────────── -->
        <nav class="main-nav">
            <div class="nav-inner">
                <!-- Logo -->
                <Link :href="route('dashboard')" class="nav-logo">
                    <div class="logo-icon">
                        <svg viewBox="0 0 48 48" fill="none">
                            <path d="M24 4L6 12V24C6 33.94 13.94 43.28 24 46C34.06 43.28 42 33.94 42 24V12L24 4Z" fill="rgba(99,102,241,0.3)" stroke="#6366f1" stroke-width="2"/>
                            <path d="M17 24L22 29L31 20" stroke="#a5b4fc" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="logo-text">SECURE</span>
                </Link>

                <!-- Desktop nav links -->
                <div class="nav-links">
                    <Link :href="route('dashboard')" class="nav-link" :class="{ active: route().current('dashboard') }">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/></svg>
                        Dashboard
                    </Link>
                    <Link :href="route('beneficiaries.index')" class="nav-link" :class="{ active: route().current('beneficiaries.*') }">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"/></svg>
                        Beneficiaries
                    </Link>
                    <Link :href="route('dashboard.compliance.index')" class="nav-link" :class="{ active: route().current('dashboard.compliance.*') }">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                        Compliance
                    </Link>
                    <Link v-if="isAdmin" :href="route('users.index')" class="nav-link" :class="{ active: route().current('users.*') }">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                        Users
                    </Link>
                </div>

                <!-- Right side: role badge + user menu -->
                <div class="nav-right">
                    <!-- Role badge -->
                    <span class="role-badge" :class="{
                        'role-admin': isAdmin,
                        'role-officer': isFieldOfficer,
                        'role-verifier': isComplianceVerifier,
                    }">{{ user?.role }}</span>

                    <!-- Notification Bell -->
                    <NotificationCenter :poll-interval="60000" />

                    <!-- User dropdown -->
                    <div class="user-menu-wrapper">
                        <button class="user-menu-trigger" @click="showingUserMenu = !showingUserMenu">
                            <div class="user-avatar">{{ user?.name?.charAt(0)?.toUpperCase() }}</div>
                            <div class="user-info">
                                <span class="user-name">{{ user?.name }}</span>
                                <span class="user-email">{{ user?.email }}</span>
                            </div>
                            <svg class="chevron" viewBox="0 0 20 20" fill="currentColor" :class="{ rotated: showingUserMenu }">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                            </svg>
                        </button>

                        <Transition name="dropdown">
                            <div v-if="showingUserMenu" class="user-dropdown">
                                <div class="dropdown-header">
                                    <div class="dropdown-avatar">{{ user?.name?.charAt(0)?.toUpperCase() }}</div>
                                    <div>
                                        <div class="dropdown-name">{{ user?.name }}</div>
                                        <div class="dropdown-role">{{ user?.role }}</div>
                                        <div class="dropdown-office">{{ user?.office_location }}</div>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <Link :href="route('profile.edit')" class="dropdown-item" @click="showingUserMenu = false">
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>
                                    My Profile
                                </Link>
                                <div class="dropdown-divider"></div>
                                <button class="dropdown-item dropdown-logout" @click="logout">
                                    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 015.25 2h5.5A2.25 2.25 0 0113 4.25v2a.75.75 0 01-1.5 0v-2a.75.75 0 00-.75-.75h-5.5a.75.75 0 00-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 00.75-.75v-2a.75.75 0 011.5 0v2A2.25 2.25 0 0110.75 18h-5.5A2.25 2.25 0 013 15.75V4.25z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M19 10a.75.75 0 00-.75-.75H8.704l1.048-.943a.75.75 0 10-1.004-1.114l-2.5 2.25a.75.75 0 000 1.114l2.5 2.25a.75.75 0 101.004-1.114l-1.048-.943h9.546A.75.75 0 0019 10z" clip-rule="evenodd"/></svg>
                                    Sign Out
                                </button>
                            </div>
                        </Transition>
                    </div>

                    <!-- Mobile hamburger -->
                    <button class="hamburger" @click="showingNav = !showingNav">
                        <svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" fill="none">
                            <path v-if="!showingNav" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile nav -->
            <Transition name="slide-down">
                <div v-if="showingNav" class="mobile-nav">
                    <Link :href="route('dashboard')" class="mobile-link" @click="showingNav = false">Dashboard</Link>
                    <Link :href="route('beneficiaries.index')" class="mobile-link" @click="showingNav = false">Beneficiaries</Link>
                    <Link :href="route('dashboard.compliance.index')" class="mobile-link" @click="showingNav = false">Compliance</Link>
                    <Link v-if="isAdmin" :href="route('users.index')" class="mobile-link" @click="showingNav = false">User Management</Link>
                    <Link :href="route('profile.edit')" class="mobile-link" @click="showingNav = false">My Profile</Link>
                    <button class="mobile-link mobile-logout" @click="logout">Sign Out</button>
                </div>
            </Transition>
        </nav>

        <!-- ── Page Header ─────────────────────────────────────────── -->
        <header v-if="$slots.header" class="page-header">
            <div class="page-header-inner">
                <slot name="header" />
            </div>
        </header>

        <!-- ── Main Content ───────────────────────────────────────── -->
        <main class="page-main">
            <slot />
        </main>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
* { box-sizing: border-box; }

/* Layout */
.app-layout { font-family: 'Inter', sans-serif; min-height: 100vh; background: #0f172a; color: #e2e8f0; }

/* ── Session Warning ────────────────────────────────────── */
.session-warning {
    position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
    background: rgba(234,179,8,0.15);
    border-bottom: 1px solid rgba(234,179,8,0.3);
    backdrop-filter: blur(12px);
}
.session-warning-content {
    max-width: 1280px; margin: 0 auto;
    padding: 0.75rem 1.5rem;
    display: flex; align-items: center; gap: 0.75rem;
    font-size: 0.875rem; color: #fcd34d; flex-wrap: wrap;
}
.warn-icon { width: 18px; height: 18px; flex-shrink: 0; }
.session-renew-btn {
    padding: 0.375rem 0.875rem;
    background: rgba(234,179,8,0.25); border: 1px solid rgba(234,179,8,0.4);
    color: #fcd34d; border-radius: 0.5rem; cursor: pointer; font-size: 0.8125rem;
    font-family: 'Inter', sans-serif; font-weight: 500; transition: background 0.2s;
    margin-left: auto;
}
.session-renew-btn:hover { background: rgba(234,179,8,0.35); }
.session-logout-btn {
    padding: 0.375rem 0.875rem;
    background: transparent; border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.7); border-radius: 0.5rem; cursor: pointer;
    font-size: 0.8125rem; font-family: 'Inter', sans-serif; font-weight: 500;
    transition: all 0.2s;
}
.session-logout-btn:hover { border-color: rgba(255,255,255,0.3); color: white; }

/* ── Nav ─────────────────────────────────────────────────── */
.main-nav {
    position: sticky; top: 0; z-index: 50;
    background: rgba(15,23,42,0.95);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    backdrop-filter: blur(20px);
}
.nav-inner {
    max-width: 1280px; margin: 0 auto;
    padding: 0 1.5rem; height: 64px;
    display: flex; align-items: center; gap: 2rem;
}

.nav-logo { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
.logo-icon { width: 36px; height: 36px; }
.logo-icon svg { width: 100%; height: 100%; }
.logo-text { font-size: 1.25rem; font-weight: 800; letter-spacing: 0.12em; color: #a5b4fc; }

.nav-links { display: flex; align-items: center; gap: 0.25rem; flex: 1; }
@media (max-width: 768px) { .nav-links { display: none; } }

.nav-link {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.875rem; border-radius: 0.625rem;
    font-size: 0.875rem; font-weight: 500; color: #94a3b8;
    text-decoration: none; transition: all 0.2s;
}
.nav-link svg { width: 16px; height: 16px; }
.nav-link:hover { background: rgba(255,255,255,0.06); color: #f1f5f9; }
.nav-link.active { background: rgba(99,102,241,0.15); color: #a5b4fc; }

.nav-right { display: flex; align-items: center; gap: 0.75rem; margin-left: auto; }

/* Role badge */
.role-badge {
    padding: 0.25rem 0.75rem; border-radius: 9999px;
    font-size: 0.7rem; font-weight: 600; letter-spacing: 0.04em;
    white-space: nowrap;
}
.role-admin    { background: rgba(99,102,241,0.2); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.3); }
.role-officer  { background: rgba(16,185,129,0.2); color: #6ee7b7; border: 1px solid rgba(16,185,129,0.3); }
.role-verifier { background: rgba(245,158,11,0.2); color: #fcd34d; border: 1px solid rgba(245,158,11,0.3); }
@media (max-width: 640px) { .role-badge { display: none; } }

/* User menu */
.user-menu-wrapper { position: relative; }
.user-menu-trigger {
    display: flex; align-items: center; gap: 0.625rem;
    background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 0.75rem; padding: 0.4rem 0.75rem;
    cursor: pointer; transition: all 0.2s; color: #f1f5f9;
}
.user-menu-trigger:hover { background: rgba(255,255,255,0.08); }
@media (max-width: 640px) { .user-menu-trigger { display: none; } }

.user-avatar {
    width: 32px; height: 32px; border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.875rem; font-weight: 700; color: white; flex-shrink: 0;
}
.user-info { text-align: left; display: flex; flex-direction: column; }
.user-name  { font-size: 0.8125rem; font-weight: 600; line-height: 1.2; color: #f1f5f9; }
.user-email { font-size: 0.7rem; color: #64748b; }
.chevron { width: 16px; height: 16px; color: #64748b; transition: transform 0.2s; flex-shrink: 0; }
.chevron.rotated { transform: rotate(180deg); }

.user-dropdown {
    position: absolute; right: 0; top: calc(100% + 0.5rem);
    background: rgba(15,23,42,0.98); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 1rem; width: 240px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    backdrop-filter: blur(20px); overflow: hidden;
}
.dropdown-header { display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem; }
.dropdown-avatar {
    width: 40px; height: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; font-weight: 700; color: white; flex-shrink: 0;
}
.dropdown-name  { font-size: 0.875rem; font-weight: 600; color: #f1f5f9; }
.dropdown-role  { font-size: 0.75rem; color: #a5b4fc; }
.dropdown-office { font-size: 0.7rem; color: #64748b; margin-top: 0.125rem; }

.dropdown-divider { height: 1px; background: rgba(255,255,255,0.06); }

.dropdown-item {
    display: flex; align-items: center; gap: 0.625rem;
    padding: 0.75rem 1rem; width: 100%;
    font-size: 0.875rem; font-weight: 500; color: #94a3b8;
    text-decoration: none; background: none; border: none; cursor: pointer;
    transition: all 0.15s; text-align: left; font-family: 'Inter', sans-serif;
}
.dropdown-item svg { width: 16px; height: 16px; }
.dropdown-item:hover { background: rgba(255,255,255,0.05); color: #f1f5f9; }
.dropdown-logout:hover { background: rgba(239,68,68,0.1); color: #fca5a5; }

/* Hamburger */
.hamburger {
    display: none; background: none; border: none; cursor: pointer;
    color: #94a3b8; padding: 0.25rem;
}
.hamburger svg { width: 24px; height: 24px; }
@media (max-width: 640px) { .hamburger { display: block; } }

/* Mobile nav */
.mobile-nav {
    background: rgba(15,23,42,0.98); border-top: 1px solid rgba(255,255,255,0.06);
    padding: 0.75rem 1rem; display: flex; flex-direction: column; gap: 0.25rem;
}
.mobile-link {
    display: block; padding: 0.75rem 1rem; border-radius: 0.625rem;
    font-size: 0.875rem; font-weight: 500; color: #94a3b8;
    text-decoration: none; transition: all 0.2s;
    background: none; border: none; cursor: pointer; text-align: left;
    font-family: 'Inter', sans-serif; width: 100%;
}
.mobile-link:hover { background: rgba(255,255,255,0.06); color: #f1f5f9; }
.mobile-logout:hover { background: rgba(239,68,68,0.1); color: #fca5a5; }

/* Page header */
.page-header { background: rgba(255,255,255,0.02); border-bottom: 1px solid rgba(255,255,255,0.05); }
.page-header-inner { max-width: 1280px; margin: 0 auto; padding: 1.5rem; }

/* Main */
.page-main { max-width: 1280px; margin: 0 auto; padding: 1.5rem; }

/* Transitions */
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-10px); }

.dropdown-enter-active { transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
.dropdown-leave-active { transition: all 0.15s ease-in; }
.dropdown-enter-from, .dropdown-leave-to { opacity: 0; transform: translateY(-8px) scale(0.95); }
</style>
