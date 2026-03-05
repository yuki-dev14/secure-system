<?php

namespace App\Policies;

use App\Models\User;

/**
 * UserPolicy — defines who can manage system users.
 *
 * Only Administrators can create, update, delete, or view
 * the user management screens. Other roles have no access.
 */
class UserPolicy
{
    /**
     * Administrators bypass all policy checks automatically.
     */
    public function before(User $authUser, string $ability): bool|null
    {
        if ($authUser->isAdministrator()) {
            return true;
        }
        return null;
    }

    /** View the user list / any user. */
    public function viewAny(User $authUser): bool
    {
        return false; // Non-admins blocked (admin bypassed via before())
    }

    /** View a specific user. */
    public function view(User $authUser, User $model): bool
    {
        // Users may view their own profile
        return $authUser->id === $model->id;
    }

    /** Create a new user (admin form). */
    public function create(User $authUser): bool
    {
        return false;
    }

    /** Update any user's details. */
    public function update(User $authUser, User $model): bool
    {
        // Allow users to update their own profile
        return $authUser->id === $model->id;
    }

    /** Delete / deactivate a user. */
    public function delete(User $authUser, User $model): bool
    {
        return false;
    }
}
