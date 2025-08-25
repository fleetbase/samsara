<?php

namespace Fleetbase\Samsara\Policies;

use Fleetbase\Models\User;
use Fleetbase\Samsara\Models\SamsaraWebhookEvent;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Class SamsaraWebhookEventPolicy.
 *
 * Policy for controlling access to Samsara webhook events
 */
class SamsaraWebhookEventPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any webhook events.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('samsara view webhook events');
    }

    /**
     * Determine whether the user can view the webhook event.
     */
    public function view(User $user, SamsaraWebhookEvent $event): bool
    {
        return $user->hasPermissionTo('samsara view webhook events')
               && $event->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can retry webhook events.
     */
    public function retry(User $user, SamsaraWebhookEvent $event): bool
    {
        return $user->hasPermissionTo('samsara manage webhook events')
               && $event->company_uuid === $user->company_uuid;
    }

    /**
     * Determine whether the user can view webhook statistics.
     */
    public function viewStats(User $user): bool
    {
        return $user->hasPermissionTo('samsara view webhook events');
    }

    /**
     * Determine whether the user can view webhook URLs.
     */
    public function viewWebhookUrl(User $user): bool
    {
        return $user->hasPermissionTo('samsara view webhook events');
    }

    /**
     * Determine whether the user can test webhook functionality.
     */
    public function test(User $user): bool
    {
        return $user->hasPermissionTo('samsara manage webhook events');
    }
}
