import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * usePermissions — Vue 3 composable for checking RBAC permissions.
 *
 * The permissions array and role are shared from the Laravel backend
 * via HandleInertiaRequests middleware on every page load.
 *
 * Usage:
 *   const { can, isAdmin, role } = usePermissions();
 *   if (can('beneficiary.create')) { ... }
 */
export function usePermissions() {
    const page = usePage();

    /** Current user's role string, e.g. 'Administrator' */
    const role = computed(() => page.props.auth?.user?.role ?? null);

    /** Array of permission strings for this role */
    const permissions = computed(() => page.props.auth?.permissions ?? []);

    /**
     * Check if the current user has a specific permission.
     * @param {string} permission - e.g. 'beneficiary.create'
     */
    const can = (permission) => permissions.value.includes(permission);

    /**
     * Check if the current user has ANY of the given permissions.
     * @param {string[]} perms
     */
    const canAny = (perms) => perms.some((p) => permissions.value.includes(p));

    /**
     * Check if the current user has ALL of the given permissions.
     * @param {string[]} perms
     */
    const canAll = (perms) => perms.every((p) => permissions.value.includes(p));

    /** Convenience role checkers */
    const isAdmin             = computed(() => role.value === 'Administrator');
    const isFieldOfficer      = computed(() => role.value === 'Field Officer');
    const isComplianceVerifier = computed(() => role.value === 'Compliance Verifier');

    return {
        role,
        permissions,
        can,
        canAny,
        canAll,
        isAdmin,
        isFieldOfficer,
        isComplianceVerifier,
    };
}
