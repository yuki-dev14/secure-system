<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * AgeCalculationService
 *
 * Centralises age and eligibility computations used throughout the
 * family-member compliance workflow.
 */
class AgeCalculationService
{
    /**
     * Calculate age in full years from a Carbon birthdate.
     */
    public function calculateAge(Carbon $birthdate): int
    {
        return $birthdate->age;
    }

    /**
     * Resolve the correct school_enrollment_status based on age.
     *
     * Rules:
     *  - age <  5 → 'Not Applicable'
     *  - age >= 5 and <= 21 → caller must supply a real status
     *  - age > 21 → 'Not Applicable'
     */
    public function resolveEnrollmentStatus(Carbon $birthdate, string $suppliedStatus): string
    {
        $age = $this->calculateAge($birthdate);

        if ($age < 5 || $age > 21) {
            return 'Not Applicable';
        }

        // School-age range: validate the supplied value
        return in_array($suppliedStatus, ['Enrolled', 'Not Enrolled', 'Not Applicable'])
            ? $suppliedStatus
            : 'Not Applicable';
    }

    /**
     * Return true if the member is within the Philippine school-age range (5–21).
     */
    public function isSchoolAge(Carbon $birthdate): bool
    {
        $age = $this->calculateAge($birthdate);
        return $age >= 5 && $age <= 21;
    }

    /**
     * Return true for early-childhood health compliance monitoring.
     * Covers children aged 0–5 and should also cover pregnant/lactating
     * mothers (handled separately at the household-head level).
     */
    public function requiresHealthCompliance(Carbon $birthdate): bool
    {
        return $this->calculateAge($birthdate) <= 5;
    }

    /**
     * Build a human-readable age label, e.g. "3 yrs" or "8 months".
     */
    public function ageLabel(Carbon $birthdate): string
    {
        $age = $this->calculateAge($birthdate);

        if ($age === 0) {
            $months = $birthdate->diffInMonths(now());
            return $months . ' month' . ($months !== 1 ? 's' : '');
        }

        return $age . ' yr' . ($age !== 1 ? 's' : '');
    }

    /**
     * School-enrollment status label for display purposes.
     */
    public function enrollmentLabel(Carbon $birthdate, string $status): string
    {
        return $this->isSchoolAge($birthdate) ? $status : 'N/A (not school-age)';
    }
}
