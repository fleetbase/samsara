<?php

namespace Fleetbase\Samsara\Policies;

use Fleetbase\Models\User;
use Fleetbase\Samsara\Models\SamsaraVehicle;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class SamsaraVehiclePolicy.
 *
 * Policy for controlling access to Samsara vehicles
 */
class SamsaraVehiclePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any vehicles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('samsara view vehicles');
    }

    /**
     * Determine whether the user can view the vehicle.
     */
    public function view(User $user, SamsaraVehicle $vehicle): bool
    {
        return $user->hasPermissionTo('samsara view vehicles')
               && $vehicle->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can create vehicles.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('samsara create vehicles');
    }

    /**
     * Determine whether the user can update the vehicle.
     */
    public function update(User $user, SamsaraVehicle $vehicle): bool
    {
        return $user->hasPermissionTo('samsara update vehicles')
               && $vehicle->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can delete the vehicle.
     */
    public function delete(User $user, SamsaraVehicle $vehicle): bool
    {
        return $user->hasPermissionTo('samsara delete vehicles')
               && $vehicle->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can sync vehicles.
     */
    public function sync(User $user, ?SamsaraVehicle $vehicle = null): bool
    {
        if ($vehicle) {
            return $user->hasPermissionTo('samsara sync vehicles')
                   && $vehicle->company_uuid === $user->company_uuid;
        }

        return $user->hasPermissionTo('samsara sync vehicles');
    }

    /**
     * Determine whether the user can link vehicles to FleetOps.
     */
    public function link(User $user, SamsaraVehicle $vehicle): bool
    {
        return $user->hasPermissionTo('samsara link vehicles')
               && $vehicle->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can unlink vehicles from FleetOps.
     */
    public function unlink(User $user, SamsaraVehicle $vehicle): bool
    {
        return $user->hasPermissionTo('samsara link vehicles')
               && $vehicle->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can view location history.
     */
    public function viewLocationHistory(User $user, SamsaraVehicle $vehicle): bool
    {
        return $user->hasPermissionTo('samsara view location history')
               && $vehicle->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can view available vehicles for linking.
     */
    public function viewAvailable(User $user): bool
    {
        return $user->hasPermissionTo('samsara view vehicles');
    }
}
