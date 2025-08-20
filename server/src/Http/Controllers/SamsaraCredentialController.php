<?php

namespace Fleetbase\Samsara\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Samsara\Models\SamsaraCredential;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Class SamsaraCredentialController
 * 
 * Controller for managing Samsara API credentials
 * 
 * @package Fleetbase\Samsara\Http\Controllers
 */
class SamsaraCredentialController extends Controller
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->middleware('auth:fleetbase');
        $this->middleware(\Fleetbase\Samsara\Http\Middleware\SamsaraCompanyScope::class);
        $this->authorizeResource(SamsaraCredential::class, 'credential');
    }

    /**
     * Display a listing of Samsara credentials
     *
     * @param FleetbaseRequest $request
     * @return JsonResponse
     */
    public function index(FleetbaseRequest $request): JsonResponse
    {
        $credentials = SamsaraCredential::where('company_uuid', session('company'))
            ->when($request->filled('active'), function ($query) use ($request) {
                return $query->where('is_active', $request->boolean('active'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate();

        return response()->json($credentials);
    }

    /**
     * Store a newly created Samsara credential
     *
     * @param FleetbaseRequest $request
     * @return JsonResponse
     */
    public function store(FleetbaseRequest $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'api_token' => 'required|string',
            'api_base_url' => 'nullable|url',
            'webhook_url' => 'nullable|url',
            'webhook_secret' => 'nullable|string',
            'is_sandbox' => 'boolean',
            'sync_interval' => 'integer|min:1|max:60',
        ]);

        $credential = SamsaraCredential::create([
            'company_uuid' => session('company'),
            'name' => $request->input('name'),
            'api_token' => $request->input('api_token'),
            'api_base_url' => $request->input('api_base_url', 'https://api.samsara.com'),
            'webhook_url' => $request->input('webhook_url'),
            'webhook_secret' => $request->input('webhook_secret'),
            'is_sandbox' => $request->boolean('is_sandbox'),
            'sync_interval' => $request->input('sync_interval', 5),
            'is_active' => true,
        ]);

        return response()->json([
            'credential' => $credential,
            'message' => 'Samsara credential created successfully',
        ], 201);
    }

    /**
     * Display the specified Samsara credential
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        return response()->json($credential);
    }

    /**
     * Update the specified Samsara credential
     *
     * @param FleetbaseRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(FleetbaseRequest $request, string $id): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'api_token' => 'sometimes|required|string',
            'api_base_url' => 'nullable|url',
            'webhook_url' => 'nullable|url',
            'webhook_secret' => 'nullable|string',
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
            'sync_interval' => 'integer|min:1|max:60',
        ]);

        $credential->update($request->only([
            'name',
            'api_token',
            'api_base_url',
            'webhook_url',
            'webhook_secret',
            'is_active',
            'is_sandbox',
            'sync_interval',
        ]));

        return response()->json([
            'credential' => $credential,
            'message' => 'Samsara credential updated successfully',
        ]);
    }

    /**
     * Remove the specified Samsara credential
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        $credential->delete();

        return response()->json([
            'message' => 'Samsara credential deleted successfully',
        ]);
    }

    /**
     * Test the API connection for the specified credential
     *
     * @param string $id
     * @return JsonResponse
     */
    public function testConnection(string $id): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        $result = $credential->testConnection();

        return response()->json($result);
    }

    /**
     * Test API connection with provided credentials (without saving)
     *
     * @param FleetbaseRequest $request
     * @return JsonResponse
     */
    public function testCredentials(FleetbaseRequest $request): JsonResponse
    {
        $request->validate([
            'api_token' => 'required|string',
            'api_base_url' => 'nullable|url',
        ]);

        $tempCredential = new SamsaraCredential([
            'api_token' => $request->input('api_token'),
            'api_base_url' => $request->input('api_base_url', 'https://api.samsara.com'),
            'is_active' => true,
        ]);

        $result = $tempCredential->testConnection();

        return response()->json($result);
    }

    /**
     * Get the active credential for the current company
     *
     * @return JsonResponse
     */
    public function getActive(): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            return response()->json([
                'message' => 'No active Samsara credential found',
            ], 404);
        }

        return response()->json($credential);
    }

    /**
     * Activate a specific credential (deactivates others)
     *
     * @param string $id
     * @return JsonResponse
     */
    public function activate(string $id): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        // Deactivate all other credentials for this company
        SamsaraCredential::where('company_uuid', session('company'))
            ->where('uuid', '!=', $credential->uuid)
            ->update(['is_active' => false]);

        // Activate the selected credential
        $credential->update(['is_active' => true]);

        return response()->json([
            'credential' => $credential,
            'message' => 'Samsara credential activated successfully',
        ]);
    }

    /**
     * Get sync statistics for the credential
     *
     * @param string $id
     * @return JsonResponse
     */
    public function getSyncStats(string $id): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        // Get related statistics
        $stats = [
            'last_sync_at' => $credential->last_sync_at,
            'sync_interval' => $credential->sync_interval,
            'is_active' => $credential->is_active,
            'vehicles_count' => $credential->samsaraVehicles()->count(),
            'active_vehicles_count' => $credential->samsaraVehicles()->activeSynced()->count(),
            'pending_events_count' => $credential->webhookEvents()->pending()->count(),
            'failed_events_count' => $credential->webhookEvents()->failed()->count(),
        ];

        return response()->json($stats);
    }
}

