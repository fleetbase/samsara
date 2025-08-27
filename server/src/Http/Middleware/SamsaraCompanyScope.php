<?php

namespace Fleetbase\Samsara\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class SamsaraCompanyScope.
 *
 * Middleware to ensure proper company scoping for Samsara resources
 */
class SamsaraCompanyScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->error('Unauthorized', 401);
        }

        if (!$user->company_uuid) {
            return response()->error('User does not belong to a company', 403);
        }

        // Add company UUID to request for use in controllers
        $request->merge(['company_uuid' => $user->company_uuid]);

        // Set global scope for Eloquent queries
        $this->setGlobalScope($user->company_uuid);

        return $next($request);
    }

    /**
     * Set global scope for Samsara models.
     */
    protected function setGlobalScope(string $companyUuid): void
    {
        // Apply global scope to Samsara models to ensure company isolation
        \Fleetbase\Samsara\Models\SamsaraCredential::addGlobalScope('company', function ($builder) use ($companyUuid) {
            $builder->where('company_uuid', $companyUuid);
        });

        \Fleetbase\Samsara\Models\SamsaraVehicle::addGlobalScope('company', function ($builder) use ($companyUuid) {
            $builder->where('company_uuid', $companyUuid);
        });

        \Fleetbase\Samsara\Models\SamsaraWebhookEvent::addGlobalScope('company', function ($builder) use ($companyUuid) {
            $builder->where('company_uuid', $companyUuid);
        });
    }
}
