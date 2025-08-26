<?php

namespace Fleetbase\Samsara\Http\Controllers;

use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Http\Requests\FleetbaseRequest;
use Fleetbase\Http\Resources\FleetbaseResource;
use Fleetbase\Http\Resources\FleetbaseResourceCollection;
use Fleetbase\Samsara\Models\SamsaraCredential;
use Fleetbase\Samsara\Models\SamsaraVehicle;
use Fleetbase\Samsara\Services\SamsaraApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Class SamsaraVehicleController.
 *
 * Controller for managing Samsara vehicle sync operations
 */
class SamsaraVehicleController extends Controller
{
    protected SamsaraApiService $samsaraApi;

    public function __construct(SamsaraApiService $samsaraApi)
    {
        $this->samsaraApi = $samsaraApi;
        FleetbaseResource::wrap('samsaraVehicle');
    }

    /**
     * Display a listing of Samsara vehicles.
     */
    public function index(FleetbaseRequest $request): FleetbaseResourceCollection|AnonymousResourceCollection
    {
        $vehicles = SamsaraVehicle::where('company_uuid', session('company'))
            ->with(['vehicle'])
            ->when($request->filled('sync_status'), function ($query) use ($request) {
                return $query->where('sync_status', $request->input('sync_status'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');

                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('vin', 'like', "%{$search}%")
                      ->orWhere('samsara_vehicle_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate();

        FleetbaseResource::wrap('samsaraVehicles');

        return FleetbaseResource::collection($vehicles);
    }

    /**
     * Store a newly created Samsara vehicle sync.
     */
    public function store(FleetbaseRequest $request): FleetbaseResource
    {
        $request->validate([
            'samsara_vehicle_id' => 'required|string',
            'vehicle_uuid'       => 'nullable|string|exists:vehicles,uuid',
        ]);

        // Check if vehicle already exists
        $existingVehicle = SamsaraVehicle::where('company_uuid', session('company'))
            ->where('samsara_vehicle_id', $request->input('samsara_vehicle_id'))
            ->first();

        if ($existingVehicle) {
            return response()->json([
                'message' => 'Samsara vehicle already exists',
                'vehicle' => $existingVehicle,
            ], 409);
        }

        // Get active credential
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            return response()->json([
                'message' => 'No active Samsara credential found',
            ], 400);
        }

        // Fetch vehicle data from Samsara API
        try {
            $samsaraData = $this->samsaraApi->getVehicle($credential, $request->input('samsara_vehicle_id'));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch vehicle from Samsara API: ' . $e->getMessage(),
            ], 400);
        }

        $samsaraVehicle = SamsaraVehicle::create([
            'company_uuid'           => session('company'),
            'vehicle_uuid'           => $request->input('vehicle_uuid'),
            'samsara_vehicle_id'     => $request->input('samsara_vehicle_id'),
            'samsara_vehicle_name'   => $samsaraData['name'] ?? null,
            'samsara_vehicle_vin'    => $samsaraData['vin'] ?? null,
            'samsara_vehicle_serial' => $samsaraData['serial'] ?? null,
            'samsara_vehicle_data'   => $samsaraData,
            'sync_status'            => 'active',
            'last_sync_at'           => now(),
        ]);

        return new FleetbaseResource($samsaraVehicle);
    }

    /**
     * Display the specified Samsara vehicle.
     */
    public function show(string $id): FleetbaseResource
    {
        $vehicle = SamsaraVehicle::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->with(['vehicle'])
            ->firstOrFail();

        return new FleetbaseResource($vehicle);
    }

    /**
     * Update the specified Samsara vehicle.
     */
    public function update(FleetbaseRequest $request, string $id): FleetbaseResource
    {
        $samsaraVehicle = SamsaraVehicle::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        $request->validate([
            'vehicle_uuid' => 'nullable|string|exists:vehicles,uuid',
            'sync_status'  => 'sometimes|in:active,disabled,failed',
        ]);

        $samsaraVehicle->update($request->only([
            'vehicle_uuid',
            'sync_status',
        ]));

        return new FleetbaseResource($samsaraVehicle);
    }

    /**
     * Remove the specified Samsara vehicle.
     */
    public function destroy(string $id): FleetbaseResource
    {
        $vehicle = SamsaraVehicle::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        $vehicle->delete();

        return new FleetbaseResource($vehicle);
    }

    /**
     * Sync all vehicles from Samsara API.
     */
    public function syncAll(): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            return response()->json([
                'message' => 'No active Samsara credential found',
            ], 400);
        }

        try {
            $result = $this->samsaraApi->syncAllVehicles($credential);

            return response()->json([
                'message' => 'Vehicle sync completed',
                'result'  => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Vehicle sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync a specific vehicle from Samsara API.
     */
    public function sync(string $id): JsonResponse
    {
        $samsaraVehicle = SamsaraVehicle::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            return response()->json([
                'message' => 'No active Samsara credential found',
            ], 400);
        }

        try {
            $samsaraVehicle->markAsSyncing();

            $samsaraData = $this->samsaraApi->getVehicle($credential, $samsaraVehicle->samsara_vehicle_id);
            $samsaraVehicle->updateFromSamsaraData($samsaraData);
            $samsaraVehicle->markSyncComplete();

            return response()->json([
                'vehicle' => $samsaraVehicle->load('vehicle'),
                'message' => 'Vehicle synced successfully',
            ]);
        } catch (\Exception $e) {
            $samsaraVehicle->markSyncFailed($e->getMessage());

            return response()->json([
                'message' => 'Vehicle sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available Samsara vehicles that are not yet synced.
     */
    public function getAvailable(): JsonResponse
    {
        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            return response()->json([
                'message' => 'No active Samsara credential found',
            ], 400);
        }

        try {
            $allVehicles = $this->samsaraApi->getAllVehicles($credential);

            // Get already synced vehicle IDs
            $syncedVehicleIds = SamsaraVehicle::where('company_uuid', session('company'))
                ->pluck('samsara_vehicle_id')
                ->toArray();

            // Filter out already synced vehicles
            $availableVehicles = array_filter($allVehicles, function ($vehicle) use ($syncedVehicleIds) {
                return !in_array($vehicle['id'], $syncedVehicleIds);
            });

            return response()->json([
                'vehicles' => array_values($availableVehicles),
                'total'    => count($availableVehicles),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch available vehicles: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get vehicle location history.
     */
    public function getLocationHistory(string $id, FleetbaseRequest $request): JsonResponse
    {
        $samsaraVehicle = SamsaraVehicle::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        $credential = SamsaraCredential::where('company_uuid', session('company'))
            ->where('is_active', true)
            ->first();

        if (!$credential) {
            return response()->json([
                'message' => 'No active Samsara credential found',
            ], 400);
        }

        $startTime = $request->input('start_time', now()->subHours(24)->toISOString());
        $endTime   = $request->input('end_time', now()->toISOString());

        try {
            $locations = $this->samsaraApi->getVehicleLocationHistory(
                $credential,
                $samsaraVehicle->samsara_vehicle_id,
                $startTime,
                $endTime
            );

            return response()->json([
                'vehicle'    => $samsaraVehicle,
                'locations'  => $locations,
                'start_time' => $startTime,
                'end_time'   => $endTime,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch location history: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Link Samsara vehicle to FleetOps vehicle.
     */
    public function linkVehicle(string $id, FleetbaseRequest $request): JsonResponse
    {
        $samsaraVehicle = SamsaraVehicle::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        $request->validate([
            'vehicle_uuid' => 'required|string|exists:vehicles,uuid',
        ]);

        // Verify the vehicle belongs to the same company
        $vehicle = Vehicle::where('uuid', $request->input('vehicle_uuid'))
            ->where('company_uuid', session('company'))
            ->firstOrFail();

        $samsaraVehicle->update([
            'vehicle_uuid' => $vehicle->uuid,
        ]);

        return response()->json([
            'vehicle' => $samsaraVehicle->load('vehicle'),
            'message' => 'Vehicle linked successfully',
        ]);
    }

    /**
     * Unlink Samsara vehicle from FleetOps vehicle.
     */
    public function unlinkVehicle(string $id): JsonResponse
    {
        $samsaraVehicle = SamsaraVehicle::where('company_uuid', session('company'))
            ->where('public_id', $id)
            ->firstOrFail();

        $samsaraVehicle->update([
            'vehicle_uuid' => null,
        ]);

        return response()->json([
            'vehicle' => $samsaraVehicle,
            'message' => 'Vehicle unlinked successfully',
        ]);
    }
}
