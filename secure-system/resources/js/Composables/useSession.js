import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * useSession — Vue 3 composable for client-side session tracking.
 *
 * Tracks user activity (click, mousemove, keydown, scroll) and warns
 * them 2 minutes before the 30-minute inactivity timeout. Auto-redirects
 * to /login when the session expires on the client side.
 *
 * The real session enforcement happens server-side via TrackActivity middleware.
 * This composable provides the UI experience (warning banner + countdown).
 *
 * Usage (in AuthenticatedLayout.vue):
 *   const { showTimeoutWarning, timeLeftSeconds, renewSession } = useSession();
 */
export function useSession() {
    const TIMEOUT_MS       = 30 * 60 * 1000; // 30 minutes
    const WARN_BEFORE_MS   = 2 * 60 * 1000;  // Warn 2 minutes before
    const CHECK_INTERVAL   = 10_000;          // Check every 10 seconds

    const showTimeoutWarning = ref(false);
    const timeLeftSeconds    = ref(0);

    let lastActivity    = Date.now();
    let warnTimer       = null;
    let logoutTimer     = null;
    let intervalId      = null;

    const resetTimers = () => {
        lastActivity = Date.now();
        showTimeoutWarning.value = false;

        clearTimeout(warnTimer);
        clearTimeout(logoutTimer);

        // Warn user WARN_BEFORE_MS before expiry
        warnTimer = setTimeout(() => {
            showTimeoutWarning.value = true;
        }, TIMEOUT_MS - WARN_BEFORE_MS);

        // Auto-logout at TIMEOUT_MS
        logoutTimer = setTimeout(() => {
            router.post('/logout');
        }, TIMEOUT_MS);
    };

    const renewSession = () => {
        resetTimers();
    };

    // Activity events that renew the session
    const activityEvents = ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'];

    const onActivity = () => {
        lastActivity = Date.now();
        if (showTimeoutWarning.value) {
            resetTimers();
        }
    };

    // Interval to update the countdown displayed to the user
    const startCountdown = () => {
        intervalId = setInterval(() => {
            if (showTimeoutWarning.value) {
                const elapsed = Date.now() - lastActivity;
                const remaining = Math.max(0, TIMEOUT_MS - elapsed);
                timeLeftSeconds.value = Math.ceil(remaining / 1000);
            }
        }, 1000);
    };

    onMounted(() => {
        resetTimers();
        startCountdown();
        activityEvents.forEach((e) => window.addEventListener(e, onActivity, { passive: true }));
    });

    onUnmounted(() => {
        clearTimeout(warnTimer);
        clearTimeout(logoutTimer);
        clearInterval(intervalId);
        activityEvents.forEach((e) => window.removeEventListener(e, onActivity));
    });

    return {
        showTimeoutWarning,
        timeLeftSeconds,
        renewSession,
    };
}
