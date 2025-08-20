<?php

namespace Fleetbase\Samsara\Policies;

use Fleetbase\Models\User;
use Fleetbase\Samsara\Models\SamsaraWebhookEvent;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class SamsaraWebhookEventPolicy
 * 
 * Policy for controlling access to Samsara webhook events
 * 
 * @package Fleetbase\Samsara\Policies
 */
class SamsaraWebhookEventPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any webhook events.
     *
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('samsara view webhook events');
    }

    /**
     * Determine whether the user can view the webhook event.
     *
     * @param User $user
     * @param SamsaraWebhookEvent $event
     * @return bool
     */
    public function view(User $user, SamsaraWebhookEvent $event): bool
    {
        return $user->hasPermissionTo('samsara view webhook events') && 
               $event->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can retry webhook events.
     *
     * @param User $user
     * @param SamsaraWebhookEvent $event
     * @return bool
     */
    public function retry(User $user, SamsaraWebhookEvent $event): bool
    {
        return $user->hasPermissionTo('samsara manage webhook events') && 
               $event->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can view webhook statistics.
     *
     * @param User $user
     * @return bool
     */
    public function viewStats(User $user): bool
    {
        return $user->hasPermissionTo('samsara view webhook events');
    }

    /**
     * Determine whether the user can view webhook URLs.
     *
     * @param User $user
     * @return bool
     */
    public function viewWebhookUrl(User $user): bool
    {
        return $user->hasPermissionTo('samsara view webhook events');
    }

    /**
     * Determine whether the user can test webhook functionality.
     *
     * @param User $user
     * @return bool
     */
    public function test(User $user): bool
    {
        return $user->hasPermissionTo('samsara manage webhook events');
    }
}

