<?php

namespace Fleetbase\Samsara\Policies;

use Fleetbase\Models\User;
use Fleetbase\Samsara\Models\SamsaraCredential;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class SamsaraCredentialPolicy
 * 
 * Policy for controlling access to Samsara credentials
 * 
 * @package Fleetbase\Samsara\Policies
 */
class SamsaraCredentialPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any credentials.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('samsara view credentials');
    }

    /**
     * Determine whether the user can view the credential.
     *
     * @param User $user
     * @param SamsaraCredential $credential
     * @return bool
     */
    public function view(User $user, SamsaraCredential $credential): bool
    {
        // User must have permission and credential must belong to their company
        return $user->hasPermissionTo('samsara view credentials') && 
               $credential->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can create credentials.
     *
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('samsara create credentials');
    }

    /**
     * Determine whether the user can update the credential.
     *
     * @param User $user
     * @param SamsaraCredential $credential
     * @return bool
     */
    public function update(User $user, SamsaraCredential $credential): bool
    {
        return $user->hasPermissionTo('samsara update credentials') && 
               $credential->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can delete the credential.
     *
     * @param User $user
     * @param SamsaraCredential $credential
     * @return bool
     */
    public function delete(User $user, SamsaraCredential $credential): bool
    {
        return $user->hasPermissionTo('samsara delete credentials') && 
               $credential->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can test credentials.
     *
     * @param User $user
     * @param SamsaraCredential|null $credential
     * @return bool
     */
    public function test(User $user, SamsaraCredential $credential = null): bool
    {
        if ($credential) {
            return $user->hasPermissionTo('samsara test credentials') && 
                   $credential->company_uuid === $user->company_uuid;
        }
        
        return $user->hasPermissionTo('samsara test credentials');
    }

    /**
     * Determine whether the user can activate/deactivate credentials.
     *
     * @param User $user
     * @param SamsaraCredential $credential
     * @return bool
     */
    public function activate(User $user, SamsaraCredential $credential): bool
    {
        return $user->hasPermissionTo('samsara manage credentials') && 
               $credential->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can view credential stats.
     *
     * @param User $user
     * @param SamsaraCredential $credential
     * @return bool
     */
    public function viewStats(User $user, SamsaraCredential $credential): bool
    {
        return $user->hasPermissionTo('samsara view credentials') && 
               $credential->company_uuid === $user->company_uuid;
    }
}

