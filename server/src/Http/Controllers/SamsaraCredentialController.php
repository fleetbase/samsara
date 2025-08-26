<?php

namespace Fleetbase\Samsara\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Http\Resources\FleetbaseResourceCollection;
use Fleetbase\Samsara\Models\SamsaraCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Validator;

/**
 * Class SamsaraCredentialController.
 *
 * Controller for managing Samsara API credentials
 */
class SamsaraCredentialController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // $this->authorizeResource(SamsaraCredential::class, 'credential');
        FleetbaseResource::wrap('samsaraCredential');
    }

    /**
     * Display a listing of Samsara credentials.
     */
    public function index(FleetbaseRequest $request): FleetbaseResourceCollection|AnonymousResourceCollection
    {
        $credentials = SamsaraCredential::where('company_uuid', session('company'))
            ->when($request->filled('active'), function ($query) use ($request) {
                return $query->where('is_active', $request->boolean('active'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate();

        FleetbaseResource::wrap('samsaraCredentials');

        return FleetbaseResource::collection($credentials);
    }

    /**
     * Store a newly created Samsara credential.
     */
    public function store(FleetbaseRequest $request): FleetbaseResource
    {
        $validator = Validator::make($request->input('samsaraCredential', []), [
            'name'           => 'required|string|max:255',
            'api_token'      => 'required|string',
            'api_base_url'   => 'nullable|url',
            'webhook_url'    => 'nullable|url',
            'webhook_secret' => 'nullable|string',
            'is_sandbox'     => 'boolean',
            'sync_interval'  => 'integer|min:1|max:60',
        ]);

        if ($validator->fails()) {
            return response()->validationError($validator);
        }

        $data = $validator->validated();

        // enforce defaults / server-side fallbacks
        $data['company_uuid']  = session('company');
        $data['api_base_url']  = $data['api_base_url'] ?? 'https://api.samsara.com';
        $data['sync_interval'] = $data['sync_interval'] ?? 5;
        $data['is_active']     = true;

        $credential = SamsaraCredential::create($data);

        return new FleetbaseResource($credential);
    }

    /**
     * Display the specified Samsara credential.
     */
    public function show(string $id): FleetbaseResource
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('uuid', $id)
            ->firstOrFail();

        return new FleetbaseResource($credential);
    }

    /**
     * Update the specified Samsara credential.
     */
    public function update(FleetbaseRequest $request, string $id): FleetbaseResource
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('uuid', $id)
            ->first();

        if (!$credential) {
            return response()->error('Samsara credential not found.');
        }

        $validator = Validator::make($request->input('samsaraCredential', []), [
            'name'           => 'sometimes|required|string|max:255',
            'api_token'      => 'sometimes|required|string',
            'api_base_url'   => 'nullable|url',
            'webhook_url'    => 'nullable|url',
            'webhook_secret' => 'nullable|string',
            'is_active'      => 'boolean',
            'is_sandbox'     => 'boolean',
            'sync_interval'  => 'integer|min:1|max:60',
        ]);

        if ($validator->fails()) {
            return response()->validationError($validator);
        }

        $credential->update($validator->validated());

        return new FleetbaseResource($credential);
    }

    /**
     * Remove the specified Samsara credential.
     */
    public function destroy(string $id): FleetbaseResource
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('uuid', $id)
            ->first();

        if (!$credential) {
            return response()->error('Samsara credential not found.');
        }

        $credential->delete();

        return new FleetbaseResource($credential);
    }

    /**
     * Test the API connection for the specified credential.
     */
    public function testConnection(string $id): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('uuid', $id)
            ->first();

        if (!$credential) {
            return response()->error('Samsara credential not found.');
        }

        $result = $credential->testConnection();

        return response()->json($result);
    }

    /**
     * Test API connection with provided credentials (without saving).
     */
    public function testCredentials(FleetbaseRequest $request): JsonResponse
    {
        $request->validate([
            'api_token'    => 'required|string',
            'api_base_url' => 'nullable|url',
        ]);

        $tempCredential = new SamsaraCredential([
            'api_token'    => $request->input('api_token'),
            'api_base_url' => $request->input('api_base_url', 'https://api.samsara.com'),
            'is_active'    => true,
        ]);

        $result = $tempCredential->testConnection();

        return response()->json($result);
    }

    /**
     * Get the active credential for the current company.
     */
    public function getActive(): FleetbaseResource
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            return response()->error('Samsara credential not found.');
        }

        return new FleetbaseResource($credential);
    }

    /**
     * Activate a specific credential (deactivates others).
     */
    public function activate(string $id): FleetbaseResource
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('uuid', $id)
            ->first();

        if (!$credential) {
            return response()->error('Samsara credential not found.');
        }

        // Deactivate all other credentials for this company
        SamsaraCredential::where('company_uuid', session('company'))
            ->where('uuid', '!=', $credential->uuid)
            ->update(['is_active' => false]);

        // Activate the selected credential
        $credential->update(['is_active' => true]);

        return new FleetbaseResource($credential);
    }

    /**
     * Get sync statistics for the credential.
     */
    public function getSyncStats(string $id): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('uuid', $id)
            ->first();

        if (!$credential) {
            return response()->error('Samsara credential not found.');
        }

        // Get related statistics
        $stats = [
            'last_sync_at'          => $credential->last_sync_at,
            'sync_interval'         => $credential->sync_interval,
            'is_active'             => $credential->is_active,
            'vehicles_count'        => $credential->samsaraVehicles()->count(),
            'active_vehicles_count' => $credential->samsaraVehicles()->activeSynced()->count(),
            'pending_events_count'  => $credential->webhookEvents()->pending()->count(),
            'failed_events_count'   => $credential->webhookEvents()->failed()->count(),
        ];

        return response()->json($stats);
    }
}
