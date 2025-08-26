<?php

namespace Fleetbase\Samsara\Services;

use Fleetbase\Samsara\Models\SamsaraCredential;
use Fleetbase\Samsara\Models\SamsaraVehicle;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Class SamsaraApiService.
 *
 * Service for handling Samsara API integration
 */
class SamsaraApiService
{
    protected Client $httpClient;
    protected int $timeout;
    protected int $retryAttempts;

    public function __construct()
    {
        $this->httpClient    = new Client();
        $this->timeout       = 30; // 30 seconds timeout
        $this->retryAttempts = 3;
    }

    /**
     * Get all vehicles from Samsara API.
     *
     * @throws \Exception
     */
    public function getAllVehicles(SamsaraCredential $credential, array $options = []): array
    {
        $vehicles = [];
        $after    = null;
        $limit    = $options['limit'] ?? 512;

        do {
            $params = [
                'limit' => $limit,
            ];

            if ($after) {
                $params['after'] = $after;
            }

            $response = $this->makeRequest(
                $credential,
                'GET',
                '/fleet/vehicles',
                ['query' => $params]
            );

            $data     = $response['data'] ?? [];
            $vehicles = array_merge($vehicles, $data);

            // Check for pagination
            $pagination  = $response['pagination'] ?? [];
            $after       = $pagination['endCursor'] ?? null;
            $hasNextPage = $pagination['hasNextPage'] ?? false;
        } while ($hasNextPage && $after);

        return $vehicles;
    }

    /**
     * Alias for get all vehicles from Samsara API.
     *
     * @throws \Exception
     */
    public function getVehicles(SamsaraCredential $credential, array $options = []): array
    {
        return $this->getAllVehicles($credential, $options);
    }

    /**
     * Get a specific vehicle from Samsara API.
     *
     * @throws \Exception
     */
    public function getVehicle(SamsaraCredential $credential, string $vehicleId): array
    {
        $response = $this->makeRequest(
            $credential,
            'GET',
            "/fleet/vehicles/{$vehicleId}"
        );

        return $response['data'] ?? $response;
    }

    /**
     * Get vehicle locations snapshot.
     *
     * @throws \Exception
     */
    public function getVehicleLocations(SamsaraCredential $credential, array $vehicleIds = [], ?string $time = null): array
    {
        $params = [];

        if (!empty($vehicleIds)) {
            $params['vehicleIds'] = implode(',', $vehicleIds);
        }

        if ($time) {
            $params['time'] = $time;
        }

        $response = $this->makeRequest(
            $credential,
            'GET',
            '/fleet/vehicles/locations',
            ['query' => $params]
        );

        return $response['data'] ?? [];
    }

    /**
     * Get vehicle location history.
     *
     * @throws \Exception
     */
    public function getVehicleLocationHistory(SamsaraCredential $credential, string $vehicleId, string $startTime, string $endTime): array
    {
        $params = [
            'vehicleIds' => $vehicleId,
            'startTime'  => $startTime,
            'endTime'    => $endTime,
        ];

        $response = $this->makeRequest(
            $credential,
            'GET',
            '/fleet/vehicles/locations/feed',
            ['query' => $params]
        );

        return $response['data'] ?? [];
    }

    /**
     * Get vehicle stats (newer API for location and telemetry).
     *
     * @throws \Exception
     */
    public function getVehicleStats(SamsaraCredential $credential, array $vehicleIds, array $types = ['gps'], ?string $startTime = null, ?string $endTime = null): array
    {
        $params = [
            'vehicleIds' => implode(',', $vehicleIds),
            'types'      => implode(',', $types),
        ];

        if ($startTime) {
            $params['startTime'] = $startTime;
        }

        if ($endTime) {
            $params['endTime'] = $endTime;
        }

        $response = $this->makeRequest(
            $credential,
            'GET',
            '/fleet/vehicles/stats',
            ['query' => $params]
        );

        return $response['data'] ?? [];
    }

    /**
     * Update vehicle information.
     *
     * @throws \Exception
     */
    public function updateVehicle(SamsaraCredential $credential, string $vehicleId, array $data): array
    {
        $response = $this->makeRequest(
            $credential,
            'PATCH',
            "/fleet/vehicles/{$vehicleId}",
            ['json' => $data]
        );

        return $response['data'] ?? $response;
    }

    /**
     * Sync all vehicles for a credential.
     *
     * @throws \Exception
     */
    public function syncAllVehicles(SamsaraCredential $credential): array
    {
        $result = [
            'total'   => 0,
            'created' => 0,
            'updated' => 0,
            'errors'  => [],
        ];

        try {
            $samsaraVehicles = $this->getAllVehicles($credential);
            $result['total'] = count($samsaraVehicles);

            foreach ($samsaraVehicles as $vehicleData) {
                try {
                    $vehicleId = $vehicleData['id'] ?? null;

                    if (!$vehicleId) {
                        $result['errors'][] = 'Vehicle missing ID: ' . json_encode($vehicleData);
                        continue;
                    }

                    // Find or create SamsaraVehicle record
                    $samsaraVehicle = SamsaraVehicle::where('company_uuid', $credential->company_uuid)
                        ->where('samsara_vehicle_id', $vehicleId)
                        ->first();

                    if ($samsaraVehicle) {
                        // Update existing vehicle
                        $samsaraVehicle->updateFromSamsaraData($vehicleData);
                        $result['updated']++;
                    } else {
                        // Create new vehicle
                        SamsaraVehicle::create([
                            'company_uuid'           => $credential->company_uuid,
                            'samsara_vehicle_id'     => $vehicleId,
                            'samsara_vehicle_name'   => $vehicleData['name'] ?? null,
                            'samsara_vehicle_vin'    => $vehicleData['vin'] ?? null,
                            'samsara_vehicle_serial' => $vehicleData['serial'] ?? null,
                            'samsara_vehicle_data'   => $vehicleData,
                            'sync_status'            => 'active',
                            'last_sync_at'           => now(),
                        ]);
                        $result['created']++;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Error syncing vehicle {$vehicleId}: " . $e->getMessage();
                    Log::error('Vehicle sync error', [
                        'vehicle_id' => $vehicleId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // Update credential last sync time
            $credential->updateLastSync();
        } catch (\Exception $e) {
            $result['errors'][] = 'API error: ' . $e->getMessage();
            throw $e;
        }

        return $result;
    }

    /**
     * Sync vehicle locations for all active vehicles.
     *
     * @throws \Exception
     */
    public function syncVehicleLocations(SamsaraCredential $credential): array
    {
        $result = [
            'total'   => 0,
            'updated' => 0,
            'errors'  => [],
        ];

        try {
            // Get all active Samsara vehicles for this company
            $samsaraVehicles = SamsaraVehicle::where('company_uuid', $credential->company_uuid)
                ->where('sync_status', 'active')
                ->get();

            if ($samsaraVehicles->isEmpty()) {
                return $result;
            }

            $vehicleIds      = $samsaraVehicles->pluck('samsara_vehicle_id')->toArray();
            $result['total'] = count($vehicleIds);

            // Get current locations from Samsara
            $locations = $this->getVehicleLocations($credential, $vehicleIds);

            foreach ($locations as $locationData) {
                try {
                    $vehicleId = $locationData['id'] ?? null;

                    if (!$vehicleId) {
                        continue;
                    }

                    $samsaraVehicle = $samsaraVehicles->firstWhere('samsara_vehicle_id', $vehicleId);

                    if ($samsaraVehicle) {
                        // Update vehicle data with location
                        $vehicleData             = $samsaraVehicle->samsara_vehicle_data ?? [];
                        $vehicleData['location'] = $locationData;

                        $samsaraVehicle->update([
                            'samsara_vehicle_data' => $vehicleData,
                            'last_sync_at'         => now(),
                        ]);

                        $result['updated']++;
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = "Error updating location for vehicle {$vehicleId}: " . $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            $result['errors'][] = 'Location sync error: ' . $e->getMessage();
            throw $e;
        }

        return $result;
    }

    /**
     * Test API connection.
     */
    public function testConnection(SamsaraCredential $credential): array
    {
        try {
            $response = $this->makeRequest(
                $credential,
                'GET',
                '/fleet/vehicles',
                ['query' => ['limit' => 1]]
            );

            return [
                'success'  => true,
                'message'  => 'Connection successful',
                'response' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Make HTTP request to Samsara API.
     *
     * @throws \Exception
     */
    protected function makeRequest(SamsaraCredential $credential, string $method, string $endpoint, array $options = []): array
    {
        $url = rtrim($credential->api_base_url, '/') . $endpoint;

        $defaultOptions = [
            'headers' => $credential->getAuthHeaders(),
            'timeout' => $this->timeout,
        ];

        $requestOptions = array_merge($defaultOptions, $options);

        $attempt       = 0;
        $lastException = null;

        while ($attempt < $this->retryAttempts) {
            try {
                Log::debug('Samsara API request', [
                    'method'  => $method,
                    'url'     => $url,
                    'attempt' => $attempt + 1,
                ]);

                $response = $this->httpClient->request($method, $url, $requestOptions);
                $body     = $response->getBody()->getContents();

                $data = json_decode($body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
                }

                Log::debug('Samsara API response', [
                    'status'     => $response->getStatusCode(),
                    'data_count' => is_array($data['data'] ?? null) ? count($data['data']) : 'N/A',
                ]);

                return $data;
            } catch (RequestException $e) {
                $lastException = $e;
                $attempt++;

                $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;

                Log::warning('Samsara API request failed', [
                    'method'      => $method,
                    'url'         => $url,
                    'attempt'     => $attempt,
                    'status_code' => $statusCode,
                    'error'       => $e->getMessage(),
                ]);

                // Don't retry on client errors (4xx)
                if ($statusCode >= 400 && $statusCode < 500) {
                    break;
                }

                // Wait before retrying (exponential backoff)
                if ($attempt < $this->retryAttempts) {
                    sleep(pow(2, $attempt));
                }
            }
        }

        // If we get here, all attempts failed
        $errorMessage = $lastException ? $lastException->getMessage() : 'Unknown error';

        if ($lastException && $lastException->getResponse()) {
            $responseBody = $lastException->getResponse()->getBody()->getContents();
            $errorMessage .= ' Response: ' . $responseBody;
        }

        throw new \Exception("Samsara API request failed after {$this->retryAttempts} attempts: {$errorMessage}");
    }

    /**
     * Set request timeout.
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * Set retry attempts.
     */
    public function setRetryAttempts(int $attempts): void
    {
        $this->retryAttempts = $attempts;
    }
}
