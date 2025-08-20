<?php

namespace Fleetbase\Samsara\Services;

use Fleetbase\Samsara\Models\SamsaraWebhookEvent;
use Fleetbase\Samsara\Models\SamsaraVehicle;
use Fleetbase\Samsara\Models\SamsaraCredential;
use Fleetbase\FleetOps\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class SamsaraWebhookService
 * 
 * Service for handling Samsara webhook events
 * 
 * @package Fleetbase\Samsara\Services
 */
class SamsaraWebhookService
{
    /**
     * Process incoming webhook payload
     *
     * @param array $payload
     * @param string $companyUuid
     * @param string $credentialUuid
     * @return array
     */
    public function processWebhook(array $payload, string $companyUuid, string $credentialUuid): array
    {
        try {
            // Create webhook event record
            $event = SamsaraWebhookEvent::createFromWebhook($payload, $companyUuid, $credentialUuid);
            
            // Process the event
            $result = $this->processEvent($event);
            
            return [
                'success' => $result['success'],
                'event_id' => $event->public_id,
                'error' => $result['error'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'company_uuid' => $companyUuid,
                'credential_uuid' => $credentialUuid,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process a specific webhook event
     *
     * @param SamsaraWebhookEvent $event
     * @return array
     */
    public function processEvent(SamsaraWebhookEvent $event): array
    {
        try {
            $event->markAsProcessing();

            $eventType = $event->event_type;
            $eventData = $event->event_data;

            Log::info('Processing webhook event', [
                'event_id' => $event->public_id,
                'event_type' => $eventType,
                'company_uuid' => $event->company_uuid,
            ]);

            switch ($eventType) {
                case 'Alert':
                    $result = $this->processAlertEvent($event, $eventData);
                    break;
                
                case 'VehicleLocationUpdate':
                case 'location':
                    $result = $this->processLocationEvent($event, $eventData);
                    break;
                
                case 'VehicleUpdate':
                case 'vehicle':
                    $result = $this->processVehicleUpdateEvent($event, $eventData);
                    break;
                
                default:
                    $result = $this->processGenericEvent($event, $eventData);
                    break;
            }

            if ($result['success']) {
                $event->markAsProcessed();
            } else {
                $event->markAsFailed($result['error'] ?? 'Unknown processing error');
            }

            return $result;

        } catch (\Exception $e) {
            $event->markAsFailed($e->getMessage());
            
            Log::error('Event processing error', [
                'event_id' => $event->public_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process alert webhook event
     *
     * @param SamsaraWebhookEvent $event
     * @param array $eventData
     * @return array
     */
    protected function processAlertEvent(SamsaraWebhookEvent $event, array $eventData): array
    {
        try {
            $alertData = $eventData['event'] ?? $eventData;
            $deviceInfo = $alertData['device'] ?? null;

            if (!$deviceInfo || !isset($deviceInfo['id'])) {
                return [
                    'success' => false,
                    'error' => 'No device information in alert event',
                ];
            }

            $vehicleId = $deviceInfo['id'];
            
            // Find the Samsara vehicle
            $samsaraVehicle = SamsaraVehicle::where('company_uuid', $event->company_uuid)
                ->where('samsara_vehicle_id', $vehicleId)
                ->first();

            if ($samsaraVehicle) {
                $event->update(['samsara_vehicle_uuid' => $samsaraVehicle->uuid]);
                
                // Update vehicle data with alert information
                $vehicleData = $samsaraVehicle->samsara_vehicle_data ?? [];
                $vehicleData['last_alert'] = $alertData;
                $vehicleData['last_alert_time'] = now()->toISOString();
                
                $samsaraVehicle->update([
                    'samsara_vehicle_data' => $vehicleData,
                ]);

                Log::info('Alert processed for vehicle', [
                    'vehicle_id' => $vehicleId,
                    'alert_type' => $alertData['alertConditionId'] ?? 'unknown',
                    'summary' => $alertData['summary'] ?? 'No summary',
                ]);
            }

            return ['success' => true];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process location update webhook event
     *
     * @param SamsaraWebhookEvent $event
     * @param array $eventData
     * @return array
     */
    protected function processLocationEvent(SamsaraWebhookEvent $event, array $eventData): array
    {
        try {
            $locationData = $eventData['location'] ?? $eventData;
            $vehicleId = $eventData['vehicleId'] ?? $locationData['vehicleId'] ?? null;

            if (!$vehicleId) {
                return [
                    'success' => false,
                    'error' => 'No vehicle ID in location event',
                ];
            }

            // Find the Samsara vehicle
            $samsaraVehicle = SamsaraVehicle::where('company_uuid', $event->company_uuid)
                ->where('samsara_vehicle_id', $vehicleId)
                ->first();

            if ($samsaraVehicle) {
                $event->update(['samsara_vehicle_uuid' => $samsaraVehicle->uuid]);
                
                // Update vehicle location data
                $vehicleData = $samsaraVehicle->samsara_vehicle_data ?? [];
                $vehicleData['location'] = $locationData;
                $vehicleData['last_location_update'] = now()->toISOString();
                
                $samsaraVehicle->update([
                    'samsara_vehicle_data' => $vehicleData,
                    'last_sync_at' => now(),
                ]);

                // If linked to FleetOps vehicle, update its location too
                if ($samsaraVehicle->vehicle) {
                    $this->updateFleetOpsVehicleLocation($samsaraVehicle->vehicle, $locationData);
                }

                Log::info('Location updated for vehicle', [
                    'vehicle_id' => $vehicleId,
                    'latitude' => $locationData['latitude'] ?? null,
                    'longitude' => $locationData['longitude'] ?? null,
                    'timestamp' => $locationData['time'] ?? null,
                ]);
            }

            return ['success' => true];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process vehicle update webhook event
     *
     * @param SamsaraWebhookEvent $event
     * @param array $eventData
     * @return array
     */
    protected function processVehicleUpdateEvent(SamsaraWebhookEvent $event, array $eventData): array
    {
        try {
            $vehicleData = $eventData['vehicle'] ?? $eventData;
            $vehicleId = $vehicleData['id'] ?? null;

            if (!$vehicleId) {
                return [
                    'success' => false,
                    'error' => 'No vehicle ID in vehicle update event',
                ];
            }

            // Find the Samsara vehicle
            $samsaraVehicle = SamsaraVehicle::where('company_uuid', $event->company_uuid)
                ->where('samsara_vehicle_id', $vehicleId)
                ->first();

            if ($samsaraVehicle) {
                $event->update(['samsara_vehicle_uuid' => $samsaraVehicle->uuid]);
                
                // Update vehicle data
                $samsaraVehicle->updateFromSamsaraData($vehicleData);

                Log::info('Vehicle data updated', [
                    'vehicle_id' => $vehicleId,
                    'name' => $vehicleData['name'] ?? 'unknown',
                ]);
            }

            return ['success' => true];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process generic webhook event
     *
     * @param SamsaraWebhookEvent $event
     * @param array $eventData
     * @return array
     */
    protected function processGenericEvent(SamsaraWebhookEvent $event, array $eventData): array
    {
        // For unknown event types, just log and mark as processed
        Log::info('Generic webhook event processed', [
            'event_id' => $event->public_id,
            'event_type' => $event->event_type,
            'data_keys' => array_keys($eventData),
        ]);

        return ['success' => true];
    }

    /**
     * Update FleetOps vehicle location from Samsara location data
     *
     * @param Vehicle $vehicle
     * @param array $locationData
     * @return void
     */
    protected function updateFleetOpsVehicleLocation(Vehicle $vehicle, array $locationData): void
    {
        try {
            $latitude = $locationData['latitude'] ?? null;
            $longitude = $locationData['longitude'] ?? null;
            $timestamp = $locationData['time'] ?? null;

            if ($latitude && $longitude) {
                // Update vehicle location in FleetOps
                $vehicle->update([
                    'location' => [
                        'type' => 'Point',
                        'coordinates' => [$longitude, $latitude],
                    ],
                    'heading' => $locationData['heading'] ?? null,
                    'speed' => $locationData['speed'] ?? null,
                    'meta' => array_merge($vehicle->meta ?? [], [
                        'samsara_last_update' => $timestamp,
                        'samsara_location_source' => 'webhook',
                    ]),
                ]);

                Log::debug('FleetOps vehicle location updated', [
                    'vehicle_uuid' => $vehicle->uuid,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update FleetOps vehicle location', [
                'vehicle_uuid' => $vehicle->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verify webhook signature
     *
     * @param Request $request
     * @param string $secret
     * @return bool
     */
    public function verifySignature(Request $request, string $secret): bool
    {
        try {
            $signature = $request->header('X-Samsara-Signature');
            $timestamp = $request->header('X-Samsara-Timestamp');
            $payload = $request->getContent();

            if (!$signature || !$timestamp) {
                Log::warning('Missing signature headers', [
                    'has_signature' => !empty($signature),
                    'has_timestamp' => !empty($timestamp),
                ]);
                return false;
            }

            // Samsara uses HMAC-SHA256 with format: v1=<hash>
            if (!str_starts_with($signature, 'v1=')) {
                Log::warning('Invalid signature format', ['signature' => $signature]);
                return false;
            }

            $expectedSignature = 'v1=' . hash_hmac('sha256', $timestamp . $payload, $secret);
            
            $isValid = hash_equals($expectedSignature, $signature);
            
            if (!$isValid) {
                Log::warning('Signature verification failed', [
                    'expected' => $expectedSignature,
                    'received' => $signature,
                ]);
            }

            return $isValid;

        } catch (\Exception $e) {
            Log::error('Signature verification error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Process pending webhook events
     *
     * @param string|null $companyUuid
     * @param int $limit
     * @return array
     */
    public function processPendingEvents(string $companyUuid = null, int $limit = 100): array
    {
        $query = SamsaraWebhookEvent::pending()
            ->orderBy('created_at', 'asc')
            ->limit($limit);

        if ($companyUuid) {
            $query->where('company_uuid', $companyUuid);
        }

        $events = $query->get();
        
        $result = [
            'total' => $events->count(),
            'processed' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($events as $event) {
            try {
                $processResult = $this->processEvent($event);
                
                if ($processResult['success']) {
                    $result['processed']++;
                } else {
                    $result['failed']++;
                    $result['errors'][] = [
                        'event_id' => $event->public_id,
                        'error' => $processResult['error'] ?? 'Unknown error',
                    ];
                }
            } catch (\Exception $e) {
                $result['failed']++;
                $result['errors'][] = [
                    'event_id' => $event->public_id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    /**
     * Clean up old webhook events
     *
     * @param int $daysOld
     * @return int
     */
    public function cleanupOldEvents(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);
        
        $deletedCount = SamsaraWebhookEvent::where('created_at', '<', $cutoffDate)
            ->where('processing_status', 'processed')
            ->delete();

        Log::info('Cleaned up old webhook events', [
            'deleted_count' => $deletedCount,
            'cutoff_date' => $cutoffDate->toDateString(),
        ]);

        return $deletedCount;
    }
}

