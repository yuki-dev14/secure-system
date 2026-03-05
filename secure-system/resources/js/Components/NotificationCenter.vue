<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    pollInterval: { type: Number, default: 60000 }, // ms between refreshes
});

const notifications  = ref([]);
const unreadCount    = ref(0);
const isOpen         = ref(false);
const loading        = ref(false);
const filterType     = ref('all');
let   pollTimer      = null;

/* ── Fetch ───────────────────────────────────────────────── */
async function fetch() {
    loading.value = true;
    try {
        const url = filterType.value === 'all' ? '/notifications/all' : `/notifications/all?type=${filterType.value}`;
        const res = await axios.get(url);
        notifications.value = res.data.notifications ?? [];
        unreadCount.value   = res.data.unread_count  ?? 0;
    } catch {
        // silently fail
    } finally {
        loading.value = false;
    }
}

/* ── Mark one as read ────────────────────────────────────── */
async function markRead(notification) {
    if (notification.is_read) return;
    try {
        await axios.post(`/notifications/${notification.id}/read`);
        notification.is_read = true;
        notification.read_at = new Date().toISOString();
        if (unreadCount.value > 0) unreadCount.value--;

        // Navigate if data contains redirect_url
        if (notification.data?.redirect_url) {
            window.location.href = notification.data.redirect_url;
        }
    } catch { /* ignore */ }
}

/* ── Mark all as read ────────────────────────────────────── */
async function markAllRead() {
    try {
        await axios.post('/notifications/read-all');
        notifications.value.forEach(n => { n.is_read = true; });
        unreadCount.value = 0;
    } catch { /* ignore */ }
}

/* ── Toggle dropdown ─────────────────────────────────────── */
function toggle() {
    isOpen.value = !isOpen.value;
    if (isOpen.value) fetch();
}

function closeOnOutside(e) {
    if (!e.target.closest('.nc-wrap')) isOpen.value = false;
}

onMounted(() => {
    fetch();
    pollTimer = setInterval(fetch, props.pollInterval);
    document.addEventListener('click', closeOnOutside);
});

onUnmounted(() => {
    clearInterval(pollTimer);
    document.removeEventListener('click', closeOnOutside);
});

/* ── Computed / Helpers ──────────────────────────────────── */
const types = [
    { key: 'all',                       label: 'All' },
    { key: 'non_compliant_beneficiary', label: 'Non-Compliant' },
    { key: 'pending_verification',      label: 'Pending' },
    { key: 'expiring_period',           label: 'Expiring' },
    { key: 'compliance_alert',          label: 'Alerts' },
];

function iconPath(icon) {
    const paths = {
        alert:    'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
        clock:    'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
        calendar: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
        'x-circle': 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        bell:     'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
    };
    return paths[icon] ?? paths.bell;
}

function severityColor(s) {
    return { high: '#ef4444', medium: '#f59e0b', low: '#6366f1' }[s] ?? '#6366f1';
}
</script>

<template>
    <div class="nc-wrap">
        <!-- Bell button -->
        <button class="nc-bell" @click.stop="toggle" id="notification-bell-btn" aria-label="Notifications">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath('bell')"/>
            </svg>
            <Transition name="badge-pop">
                <span v-if="unreadCount > 0" class="nc-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
            </Transition>
        </button>

        <!-- Dropdown panel -->
        <Transition name="dropdown">
            <div v-if="isOpen" class="nc-panel" @click.stop>
                <!-- Panel header -->
                <div class="nc-ph">
                    <span class="nc-ph-title">Notifications</span>
                    <div class="nc-ph-right">
                        <span v-if="unreadCount > 0" class="nc-unread-badge">{{ unreadCount }} unread</span>
                        <button v-if="unreadCount > 0" class="nc-mark-all" @click="markAllRead" id="mark-all-read-btn">
                            Mark all read
                        </button>
                    </div>
                </div>

                <!-- Filter tabs -->
                <div class="nc-filters">
                    <button v-for="t in types" :key="t.key"
                            class="nc-ft"
                            :class="{ 'nc-ft-active': filterType === t.key }"
                            @click="filterType = t.key; fetch()">
                        {{ t.label }}
                    </button>
                </div>

                <!-- List -->
                <div class="nc-list">
                    <div v-if="loading && notifications.length === 0" class="nc-loading">
                        <div class="loader"></div> Loading…
                    </div>

                    <button v-for="n in notifications" :key="n.id"
                            class="nc-item"
                            :class="{ 'nc-unread': !n.is_read }"
                            @click="markRead(n)">
                        <!-- Icon -->
                        <div class="nc-item-icon" :style="`background: ${severityColor(n.severity)}18; border-color: ${severityColor(n.severity)}33`">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" :style="`color: ${severityColor(n.severity)}`">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(n.icon)"/>
                            </svg>
                        </div>

                        <div class="nc-item-body">
                            <div class="nc-item-title">{{ n.title }}</div>
                            <div class="nc-item-msg">{{ n.message }}</div>
                            <div class="nc-item-time">{{ n.created_at_human }}</div>
                        </div>

                        <div v-if="!n.is_read" class="nc-dot"></div>
                    </button>

                    <div v-if="!loading && notifications.length === 0" class="nc-empty">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                        </svg>
                        <p>No notifications</p>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
* { box-sizing: border-box; }

.nc-wrap { position: relative; display: inline-block; font-family: 'Inter', sans-serif; }

/* Bell */
.nc-bell {
    position: relative; background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1); border-radius: 0.625rem;
    padding: 0.5rem; color: #94a3b8; cursor: pointer; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.nc-bell svg { width: 22px; height: 22px; }
.nc-bell:hover { background: rgba(255,255,255,0.1); color: #f1f5f9; }

.nc-badge {
    position: absolute; top: -6px; right: -6px;
    min-width: 18px; height: 18px; border-radius: 999px;
    background: #ef4444; color: white; font-size: 0.65rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center; padding: 0 3px;
    border: 2px solid #0f172a;
}

/* Panel */
.nc-panel {
    position: absolute; top: calc(100% + 10px); right: 0; z-index: 9000;
    width: 380px; max-width: calc(100vw - 1rem);
    background: #0f172a; border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.25rem; box-shadow: 0 25px 60px rgba(0,0,0,0.6);
    display: flex; flex-direction: column; overflow: hidden;
}

/* Panel header */
.nc-ph {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.25rem 0.75rem;
    border-bottom: 1px solid rgba(255,255,255,0.07);
}
.nc-ph-title { font-weight: 700; color: #f1f5f9; font-size: 0.9375rem; }
.nc-ph-right { display: flex; align-items: center; gap: 0.625rem; }
.nc-unread-badge {
    background: rgba(239,68,68,0.12); color: #fca5a5;
    border: 1px solid rgba(239,68,68,0.2); border-radius: 999px;
    padding: 0.15rem 0.5rem; font-size: 0.72rem; font-weight: 700;
}
.nc-mark-all {
    background: none; border: none; color: #6366f1; font-size: 0.78rem;
    font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif;
    padding: 0; transition: color 0.15s;
}
.nc-mark-all:hover { color: #a5b4fc; }

/* Filter tabs */
.nc-filters {
    display: flex; gap: 0.25rem; padding: 0.625rem 1.25rem;
    overflow-x: auto; border-bottom: 1px solid rgba(255,255,255,0.05);
}
.nc-ft {
    padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.07);
    color: #64748b; cursor: pointer; white-space: nowrap; font-family: 'Inter', sans-serif;
    transition: all 0.15s;
}
.nc-ft.nc-ft-active { background: rgba(99,102,241,0.15); border-color: rgba(99,102,241,0.3); color: #a5b4fc; }

/* List */
.nc-list {
    overflow-y: auto; max-height: 420px;
    display: flex; flex-direction: column;
}
.nc-loading {
    display: flex; align-items: center; justify-content: center;
    gap: 0.625rem; padding: 2rem; color: #64748b; font-size: 0.875rem;
}
.loader {
    width: 14px; height: 14px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.1); border-top-color: #6366f1;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.nc-item {
    display: flex; align-items: flex-start; gap: 0.75rem;
    padding: 0.875rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,0.04);
    background: none; border-left: none; border-right: none; border-top: none;
    text-align: left; cursor: pointer; width: 100%; transition: background 0.15s;
    position: relative;
}
.nc-item:hover { background: rgba(255,255,255,0.04); }
.nc-unread { background: rgba(99,102,241,0.04) !important; }

.nc-item-icon {
    width: 36px; height: 36px; border-radius: 0.625rem; border: 1px solid;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.nc-item-icon svg { width: 18px; height: 18px; }

.nc-item-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 0.2rem; }
.nc-item-title { font-size: 0.8125rem; font-weight: 700; color: #f1f5f9; }
.nc-item-msg   { font-size: 0.75rem; color: #64748b; line-height: 1.45; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.nc-item-time  { font-size: 0.7rem; color: #334155; }

.nc-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #6366f1; box-shadow: 0 0 6px rgba(99,102,241,0.6);
    flex-shrink: 0; margin-top: 0.3rem;
}

.nc-empty {
    display: flex; flex-direction: column; align-items: center; gap: 0.75rem;
    padding: 2.5rem 1rem; color: #475569;
}
.nc-empty svg { width: 36px; height: 36px; }
.nc-empty p   { font-size: 0.875rem; margin: 0; }

/* Transitions */
.dropdown-enter-active { transition: all 0.25s cubic-bezier(0.175,0.885,0.32,1.275); }
.dropdown-leave-active { transition: all 0.15s ease-in; }
.dropdown-enter-from   { opacity: 0; transform: translateY(-8px) scale(0.97); }
.dropdown-leave-to     { opacity: 0; transform: translateY(-4px) scale(0.98); }

.badge-pop-enter-active { transition: transform 0.3s cubic-bezier(0.175,0.885,0.32,1.6); }
.badge-pop-enter-from   { transform: scale(0); }
</style>
