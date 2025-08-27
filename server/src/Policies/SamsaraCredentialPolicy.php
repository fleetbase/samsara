<?php

namespace Fleetbase\Samsara\Policies;

use Fleetbase\Models\User;
use Fleetbase\Samsara\Models\SamsaraCredential;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class SamsaraCredentialPolicy.
 *
 * Policy for controlling access to Samsara credentials
 */
class SamsaraCredentialPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any credentials.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('samsara view credential');
    }

    /**
     * Determine whether the user can view the credential.
     */
    public function view(User $user, SamsaraCredential $credential): bool
    {
        // User must have permission and credential must belong to their company
        return $user->hasPermissionTo('samsara view credential') && $credential->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can create credentials.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('samsara create credential');
    }

    /**
     * Determine whether the user can update the credential.
     */
    public function update(User $user, SamsaraCredential $credential): bool
    {
        return $user->hasPermissionTo('samsara update credential') && $credential->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can delete the credential.
     */
    public function delete(User $user, SamsaraCredential $credential): bool
    {
        return $user->hasPermissionTo('samsara delete credential') && $credential->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can test credentials.
     */
    public function test(User $user, ?SamsaraCredential $credential = null): bool
    {
        if ($credential) {
            return $user->hasPermissionTo('samsara test credential') && $credential->company_uuid === $user->company_uuid;
        }

        return $user->hasPermissionTo('samsara test credential');
    }

    /**
     * Determine whether the user can activate/deactivate credentials.
     */
    public function activate(User $user, SamsaraCredential $credential): bool
    {
        return $user->hasPermissionTo('samsara manage credential') && $credential->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can view credential stats.
     */
    public function viewStats(User $user, SamsaraCredential $credential): bool
    {
        return $user->hasPermissionTo('samsara view credential') && $credential->company_uuid === $user->company_uuid;
    }
}
