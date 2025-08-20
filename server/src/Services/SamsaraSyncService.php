<?php

namespace Fleetbase\Samsara\Services;

use Fleetbase\Samsara\Models\SamsaraCredential;
use Fleetbase\Samsara\Models\SamsaraVehicle;
use Fleetbase\Samsara\Models\SamsaraWebhookEvent;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Class SamsaraSyncService
 * 
 * Service for handling scheduled synchronization tasks and FleetOps integration
 * 
 * @package Fleetbase\Samsara\Services
 */
class SamsaraSyncService
{
    protected SamsaraApiService $apiService;
    protected SamsaraWebhookService $webhookService;

    public function __construct(SamsaraApiService $apiService, SamsaraWebhookService $webhookService)
    {
        $this->apiService = $apiService;
        $this->webhookService = $webhookService;
    }

    /**
     * Sync vehicles from Samsara API and create/update FleetOps vehicles.
     */
    public function syncVehicles(SamsaraCredential $credential, bool $force = false, bool $includeInactive = false): array
    {
        $startTime = microtime(true);
        $stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'linked' => 0,
            'errors' => 0,
            'error_details' => [],
        ];

        try {
            // Check if sync is already in progress
            if (!$force && $this->isSyncInProgress($credential)) {
                throw new \Exception('Sync already in progress for this credential');
            }

            // Mark sync as in progress
            $this->markSyncInProgress($credential);

            // Get vehicles from Samsara API
            $samsaraVehicles = $this->apiService->getVehicles($credential, $includeInactive);
            $stats['total'] = count($samsaraVehicles);

            foreach ($samsaraVehicles as $vehicleData) {
                try {
                    $result = $this->syncVehicle($credential, $vehicleData);
                    
                    if ($result['samsara_vehicle']->wasRecentlyCreated) {
                        $stats['created']++;
                    } else {
                        $stats['updated']++;
                    }
                    
                    if ($result['fleetops_vehicle_created'] || $result['fleetops_vehicle_updated']) {
                        $stats['linked']++;
                    }
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $stats['error_details'][] = "Vehicle {$vehicleData['id']}: {$e->getMessage()}";
                    
                    Log::error('Samsara vehicle sync error', [
                        'credential_id' => $credential->uuid,
                        'vehicle_id' => $vehicleData['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Update credential sync timestamp
            $credential->update(['last_sync_at' => now()]);

        } catch (\Exception $e) {
            $stats['errors']++;
            $stats['error_details'][] = $e->getMessage();
            
            Log::error('Samsara sync failed', [
                'credential_id' => $credential->uuid,
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Mark sync as complete
            $this->markSyncComplete($credential);
        }

        $stats['duration'] = microtime(true) - $startTime;
        $stats['synced'] = $stats['created'] + $stats['updated'];
        
        return $stats;
    }

    /**
     * Sync a single vehicle and create/update corresponding FleetOps vehicle.
     */
    public function syncVehicle(SamsaraCredential $credential, array $vehicleData): array
    {
        $result = [
            'samsara_vehicle' => null,
            'fleetops_vehicle' => null,
            'fleetops_vehicle_created' => false,
            'fleetops_vehicle_updated' => false,
        ];

        DB::transaction(function () use ($credential, $vehicleData, &$result) {
            // Create or update Samsara vehicle record
            $samsaraVehicle = SamsaraVehicle::updateOrCreate(
                [
                    'company_uuid' => $credential->company_uuid,
                    'samsara_vehicle_id' => $vehicleData['id'],
                ],
                [
                    'credential_uuid' => $credential->uuid,
                    'name' => $vehicleData['name'] ?? 'Unknown Vehicle',
                    'vin' => $vehicleData['vin'] ?? null,
                    'serial' => $vehicleData['serial'] ?? null,
                    'license_plate' => $vehicleData['licensePlate'] ?? null,
                    'vehicle_type' => $this->mapVehicleType($vehicleData['vehicleType'] ?? 'unknown'),
                    'sync_status' => 'active',
                    'metadata' => [
                        'make' => $vehicleData['make'] ?? null,
                        'model' => $vehicleData['model'] ?? null,
                        'year' => $vehicleData['year'] ?? null,
                        'fuel_type' => $vehicleData['fuelType'] ?? null,
                        'engine_hours' => $vehicleData['engineHours'] ?? null,
                        'odometer_meters' => $vehicleData['odometerMeters'] ?? null,
                    ],
                    'last_sync_at' => now(),
                ]
            );

            $result['samsara_vehicle'] = $samsaraVehicle;

            // Create or update FleetOps vehicle
            $fleetOpsResult = $this->createOrUpdateFleetOpsVehicle($samsaraVehicle, $vehicleData);
            $result['fleetops_vehicle'] = $fleetOpsResult['vehicle'];
            $result['fleetops_vehicle_created'] = $fleetOpsResult['created'];
            $result['fleetops_vehicle_updated'] = $fleetOpsResult['updated'];

            // Link the vehicles if FleetOps vehicle was created/updated
            if ($fleetOpsResult['vehicle']) {
                $samsaraVehicle->update([
                    'vehicle_uuid' => $fleetOpsResult['vehicle']->uuid,
                    'is_linked' => true,
                ]);
            }

            // Update location if available
            if (isset($vehicleData['location'])) {
                $this->updateVehicleLocation($samsaraVehicle, $vehicleData['location']);
            }
        });

        return $result;
    }

    /**
     * Create or update FleetOps vehicle from Samsara data.
     */
    protected function createOrUpdateFleetOpsVehicle(SamsaraVehicle $samsaraVehicle, array $vehicleData): array
    {
        $result = [
            'vehicle' => null,
            'created' => false,
            'updated' => false,
        ];

        // Check if FleetOps vehicle already exists
        $fleetOpsVehicle = null;
        
        if ($samsaraVehicle->vehicle_uuid) {
            $fleetOpsVehicle = Vehicle::where('uuid', $samsaraVehicle->vehicle_uuid)->first();
        }

        // If no linked vehicle, try to find by VIN or license plate
        if (!$fleetOpsVehicle && !empty($samsaraVehicle->vin)) {
            $fleetOpsVehicle = Vehicle::where('company_uuid', $samsaraVehicle->company_uuid)
                ->where('vin', $samsaraVehicle->vin)
                ->first();
        }

        if (!$fleetOpsVehicle && !empty($samsaraVehicle->license_plate)) {
            $fleetOpsVehicle = Vehicle::where('company_uuid', $samsaraVehicle->company_uuid)
                ->where('plate_number', $samsaraVehicle->license_plate)
                ->first();
        }

        $vehicleAttributes = [
            'company_uuid' => $samsaraVehicle->company_uuid,
            'name' => $samsaraVehicle->name,
            'vin' => $samsaraVehicle->vin,
            'plate_number' => $samsaraVehicle->license_plate,
            'year' => $samsaraVehicle->metadata['year'] ?? null,
            'make' => $samsaraVehicle->metadata['make'] ?? null,
            'model' => $samsaraVehicle->metadata['model'] ?? null,
            'trim' => null,
            'type' => $this->mapToFleetOpsVehicleType($samsaraVehicle->vehicle_type),
            'status' => 'active',
            'meta' => array_merge($samsaraVehicle->metadata ?? [], [
                'samsara_vehicle_id' => $samsaraVehicle->samsara_vehicle_id,
                'samsara_synced' => true,
                'samsara_sync_source' => 'api',
                'fuel_type' => $samsaraVehicle->metadata['fuel_type'] ?? null,
                'engine_hours' => $samsaraVehicle->metadata['engine_hours'] ?? null,
                'odometer_meters' => $samsaraVehicle->metadata['odometer_meters'] ?? null,
            ]),
        ];

        if ($fleetOpsVehicle) {
            // Update existing vehicle
            $fleetOpsVehicle->update($vehicleAttributes);
            $result['vehicle'] = $fleetOpsVehicle;
            $result['updated'] = true;
        } else {
            // Create new vehicle
            $fleetOpsVehicle = Vehicle::create($vehicleAttributes);
            $result['vehicle'] = $fleetOpsVehicle;
            $result['created'] = true;
        }

        return $result;
    }

    /**
     * Update vehicle location from Samsara data.
     */
    protected function updateVehicleLocation(SamsaraVehicle $samsaraVehicle, array $locationData): void
    {
        $location = [
            'latitude' => $locationData['latitude'] ?? null,
            'longitude' => $locationData['longitude'] ?? null,
            'timestamp' => $locationData['time'] ?? now()->toISOString(),
            'speed' => $locationData['speedMilesPerHour'] ?? null,
            'heading' => $locationData['heading'] ?? null,
            'address' => $locationData['address'] ?? null,
        ];

        $samsaraVehicle->update(['last_location' => $location]);

        // Update FleetOps vehicle location if linked
        if ($samsaraVehicle->vehicle_uuid && $location['latitude'] && $location['longitude']) {
            $fleetOpsVehicle = Vehicle::where('uuid', $samsaraVehicle->vehicle_uuid)->first();
            if ($fleetOpsVehicle) {
                $fleetOpsVehicle->update([
                    'location' => [
                        'type' => 'Point',
                        'coordinates' => [$location['longitude'], $location['latitude']],
                    ],
                    'heading' => $location['heading'],
                    'speed' => $location['speed'],
                    'altitude' => $locationData['altitude'] ?? null,
                ]);
            }
        }
    }

    /**
     * Map Samsara vehicle type to internal type.
     */
    protected function mapVehicleType(string $samsaraType): string
    {
        $typeMap = [
            'truck' => 'truck',
            'van' => 'van',
            'car' => 'car',
            'trailer' => 'trailer',
            'motorcycle' => 'motorcycle',
            'bus' => 'bus',
            'equipment' => 'equipment',
        ];

        return $typeMap[strtolower($samsaraType)] ?? 'truck';
    }

    /**
     * Map to FleetOps vehicle type.
     */
    protected function mapToFleetOpsVehicleType(string $vehicleType): string
    {
        $typeMap = [
            'truck' => 'truck',
            'van' => 'van',
            'car' => 'car',
            'trailer' => 'trailer',
            'motorcycle' => 'motorcycle',
            'bus' => 'bus',
            'equipment' => 'other',
        ];

        return $typeMap[$vehicleType] ?? 'truck';
    }

    /**
     * Preview what would be synced without making changes.
     */
    public function previewSync(SamsaraCredential $credential, bool $includeInactive = false): array
    {
        try {
            $samsaraVehicles = $this->apiService->getVehicles($credential, $includeInactive);
            
            $newVehicles = 0;
            $existingVehicles = 0;
            $linkableVehicles = 0;
            $sampleVehicles = [];

            foreach ($samsaraVehicles as $vehicleData) {
                $existingSamsara = SamsaraVehicle::where('company_uuid', $credential->company_uuid)
                    ->where('samsara_vehicle_id', $vehicleData['id'])
                    ->first();

                if ($existingSamsara) {
                    $existingVehicles++;
                } else {
                    $newVehicles++;
                }

                // Check if can be linked to existing FleetOps vehicle
                if (!empty($vehicleData['vin']) || !empty($vehicleData['licensePlate'])) {
                    $query = Vehicle::where('company_uuid', $credential->company_uuid);
                    
                    if (!empty($vehicleData['vin'])) {
                        $query->where('vin', $vehicleData['vin']);
                    } elseif (!empty($vehicleData['licensePlate'])) {
                        $query->where('plate_number', $vehicleData['licensePlate']);
                    }
                    
                    if ($query->exists()) {
                        $linkableVehicles++;
                    }
                }

                $sampleVehicles[] = [
                    'id' => $vehicleData['id'],
                    'name' => $vehicleData['name'] ?? 'Unknown Vehicle',
                ];
            }

            return [
                'total_vehicles' => count($samsaraVehicles),
                'new_vehicles' => $newVehicles,
                'existing_vehicles' => $existingVehicles,
                'linkable_vehicles' => $linkableVehicles,
                'sample_vehicles' => $sampleVehicles,
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'total_vehicles' => 0,
                'new_vehicles' => 0,
                'existing_vehicles' => 0,
                'linkable_vehicles' => 0,
                'sample_vehicles' => [],
            ];
        }
    }

    /**
     * Check if sync is in progress for a credential.
     */
    protected function isSyncInProgress(SamsaraCredential $credential): bool
    {
        return cache()->has("samsara_sync_in_progress_{$credential->uuid}");
    }

    /**
     * Mark sync as in progress.
     */
    protected function markSyncInProgress(SamsaraCredential $credential): void
    {
        cache()->put("samsara_sync_in_progress_{$credential->uuid}", true, now()->addMinutes(30));
    }

    /**
     * Mark sync as complete.
     */
    protected function markSyncComplete(SamsaraCredential $credential): void
    {
        cache()->forget("samsara_sync_in_progress_{$credential->uuid}");
    }

    /**
     * Run full synchronization for all active credentials
     */
    public function runFullSync(): array
    {
        $result = [
            'credentials_processed' => 0,
            'total_vehicles_synced' => 0,
            'total_locations_synced' => 0,
            'errors' => [],
            'start_time' => now(),
        ];

        try {
            $credentials = SamsaraCredential::active()->get();
            
            Log::info('Starting full Samsara sync', [
                'credentials_count' => $credentials->count(),
            ]);

            foreach ($credentials as $credential) {
                try {
                    $credentialResult = $this->syncVehicles($credential);
                    
                    $result['credentials_processed']++;
                    $result['total_vehicles_synced'] += $credentialResult['synced'] ?? 0;
                    
                    if (!empty($credentialResult['error_details'])) {
                        $result['errors'] = array_merge($result['errors'], $credentialResult['error_details']);
                    }

                } catch (\Exception $e) {
                    $error = "Credential {$credential->public_id} sync failed: " . $e->getMessage();
                    $result['errors'][] = $error;
                    
                    Log::error('Credential sync error', [
                        'credential_id' => $credential->public_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $result['end_time'] = now();
            $result['duration'] = $result['end_time']->diffInSeconds($result['start_time']);

            Log::info('Full Samsara sync completed', $result);

        } catch (\Exception $e) {
            $result['errors'][] = 'Full sync failed: ' . $e->getMessage();
            Log::error('Full sync error', ['error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Get sync status for all credentials
     */
    public function getSyncStatus(): array
    {
        try {
            $credentials = SamsaraCredential::active()->get();
            $status = [];

            foreach ($credentials as $credential) {
                $vehiclesCount = SamsaraVehicle::where('company_uuid', $credential->company_uuid)->count();
                $activeVehiclesCount = SamsaraVehicle::where('company_uuid', $credential->company_uuid)
                    ->where('sync_status', 'active')
                    ->count();
                $linkedVehiclesCount = SamsaraVehicle::where('company_uuid', $credential->company_uuid)
                    ->where('is_linked', true)
                    ->count();

                $status[] = [
                    'credential_id' => $credential->public_id,
                    'company_uuid' => $credential->company_uuid,
                    'last_sync_at' => $credential->last_sync_at,
                    'sync_interval' => $credential->sync_interval,
                    'vehicles_total' => $vehiclesCount,
                    'vehicles_active' => $activeVehiclesCount,
                    'vehicles_linked' => $linkedVehiclesCount,
                    'is_healthy' => $credential->last_sync_at && 
                                   $credential->last_sync_at->gt(now()->subMinutes($credential->sync_interval * 2)),
                ];
            }

            return [
                'credentials' => $status,
                'total_credentials' => count($status),
                'healthy_credentials' => count(array_filter($status, fn($s) => $s['is_healthy'])),
                'last_check' => now(),
            ];

        } catch (\Exception $e) {
            Log::error('Sync status check error', ['error' => $e->getMessage()]);
            
            return [
                'credentials' => [],
                'total_credentials' => 0,
                'healthy_credentials' => 0,
                'last_check' => now(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process pending webhook events
     */
    public function processPendingWebhooks(int $limit = 100): array
    {
        try {
            Log::info('Processing pending webhooks', ['limit' => $limit]);
            
            $result = $this->webhookService->processPendingEvents(null, $limit);
            
            Log::info('Pending webhooks processed', $result);
            
            return $result;

        } catch (\Exception $e) {
            $error = 'Pending webhooks processing failed: ' . $e->getMessage();
            Log::error('Pending webhooks error', ['error' => $e->getMessage()]);
            
            return [
                'total' => 0,
                'processed' => 0,
                'failed' => 0,
                'errors' => [$error],
            ];
        }
    }

    /**
     * Run health check for all integrations
     */
    public function healthCheck(): array
    {
        $result = [
            'overall_status' => 'healthy',
            'checks' => [],
            'timestamp' => now(),
        ];

        try {
            // Check database connectivity
            $result['checks']['database'] = $this->checkDatabase();
            
            // Check API connectivity for each credential
            $result['checks']['api_connections'] = $this->checkApiConnections();
            
            // Check sync status
            $result['checks']['sync_status'] = $this->getSyncStatus();
            
            // Check for failed events
            $result['checks']['failed_events'] = $this->checkFailedEvents();

            // Determine overall status
            $hasErrors = false;
            foreach ($result['checks'] as $check) {
                if (isset($check['status']) && $check['status'] !== 'healthy') {
                    $hasErrors = true;
                    break;
                }
                if (isset($check['error'])) {
                    $hasErrors = true;
                    break;
                }
            }

            $result['overall_status'] = $hasErrors ? 'unhealthy' : 'healthy';

        } catch (\Exception $e) {
            $result['overall_status'] = 'error';
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Check database connectivity
     */
    protected function checkDatabase(): array
    {
        try {
            SamsaraCredential::count();
            return ['status' => 'healthy', 'message' => 'Database accessible'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    /**
     * Check API connections for all credentials
     */
    protected function checkApiConnections(): array
    {
        $connections = [];
        
        try {
            $credentials = SamsaraCredential::active()->get();
            
            foreach ($credentials as $credential) {
                $test = $this->apiService->testConnection($credential);
                $connections[] = [
                    'credential_id' => $credential->public_id,
                    'status' => $test['success'] ? 'healthy' : 'unhealthy',
                    'message' => $test['message'],
                ];
            }

        } catch (\Exception $e) {
            $connections[] = [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }

        return $connections;
    }

    /**
     * Check for failed webhook events
     */
    protected function checkFailedEvents(): array
    {
        try {
            $failedCount = SamsaraWebhookEvent::where('status', 'failed')
                ->where('created_at', '>=', now()->subHours(24))
                ->count();

            return [
                'status' => $failedCount > 10 ? 'unhealthy' : 'healthy',
                'failed_events_24h' => $failedCount,
                'message' => $failedCount > 10 ? 'High number of failed events' : 'Normal event processing',
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }
}

