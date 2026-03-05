<?php

namespace App\Policies;

use App\Models\User;

/**
 * BeneficiaryPolicy — defines who can manage beneficiary records.
 *
 * Role permissions:
 *   Administrator       → all operations
 *   Field Officer       → create, view, update (no delete, no compliance actions)
 *   Compliance Verifier → view only
 */
class BeneficiaryPolicy
{
    /**
     * Administrators bypass all policy checks.
     */
    public function before(User $authUser, string $ability): bool|null
    {
        if ($authUser->isAdministrator()) {
            return true;
        }
        return null;
    }

    /** View any beneficiary list. */
    public function viewAny(User $authUser): bool
    {
        return $authUser->hasRole(['Field Officer', 'Compliance Verifier']);
    }

    /** View a specific beneficiary record. */
    public function view(User $authUser, mixed $beneficiary = null): bool
    {
        return $authUser->hasRole(['Field Officer', 'Compliance Verifier']);
    }

    /** Create / register a new beneficiary. */
    public function create(User $authUser): bool
    {
        return $authUser->isFieldOfficer();
    }

    /** Update beneficiary details. */
    public function update(User $authUser, mixed $beneficiary): bool
    {
        return $authUser->isFieldOfficer();
    }

    /** Permanently delete a beneficiary record. */
    public function delete(User $authUser, mixed $beneficiary): bool
    {
        return false; // Only admin via before()
    }

    /** Approve a compliance check. */
    public function approve(User $authUser, mixed $beneficiary): bool
    {
        return $authUser->isComplianceVerifier();
    }

    /** Reject a compliance check. */
    public function reject(User $authUser, mixed $beneficiary): bool
    {
        return $authUser->isComplianceVerifier();
    }

    /** Authorize a payout. */
    public function authorizePayout(User $authUser, mixed $beneficiary): bool
    {
        return $authUser->isComplianceVerifier();
    }

    /** Scan QR code for a beneficiary. */
    public function scanQr(User $authUser): bool
    {
        return $authUser->isFieldOfficer();
    }
}
