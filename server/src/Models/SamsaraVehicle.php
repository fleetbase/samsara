<?php

namespace Fleetbase\Samsara\Models;

use Fleetbase\FleetOps\Models\Vehicle;
use Fleetbase\Models\Model;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\TracksApiCredential;

/**
 * Class SamsaraVehicle.
 *
 * Model for managing Samsara vehicle data and sync with FleetOps vehicles
 */
class SamsaraVehicle extends Model
{
    use HasUuid;
    use HasPublicId;
    use TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'samsara_vehicles';

    /**
     * The type of public Id to generate.
     *
     * @var string
     */
    protected $publicIdType = 'samsara_vehicle';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'public_id',
        'company_uuid',
        'credential_uuid',
        'vehicle_uuid',
        'samsara_vehicle_id',
        'name',
        'vin',
        'serial',
        'license_plate',
        'vehicle_type',
        'sync_status',
        'is_linked',
        'last_location',
        'metadata',
        'last_sync_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'last_location' => 'json',
        'metadata'      => 'json',
        'last_sync_at'  => 'datetime',
        'is_linked'     => 'boolean',
    ];

    /**
     * Dynamic attributes that are appended to model.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Get the associated FleetOps vehicle.
     */
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_uuid', 'uuid');
    }

    /**
     * Get the credential that owns this vehicle.
     */
    public function credential()
    {
        return $this->belongsTo(SamsaraCredential::class, 'credential_uuid', 'uuid');
    }

    /**
     * Get the Samsara vehicle ID.
     *
     * @return string|null
     */
    public function getSamsaraVehicleIdAttribute()
    {
        return $this->attributes['samsara_vehicle_id'];
    }

    /**
     * Get the last known location from location data.
     *
     * @return array|null
     */
    public function getLastLocationAttribute()
    {
        $location = $this->attributes['last_location'] ?? null;

        if ($location && is_string($location)) {
            return json_decode($location, true);
        }

        return $location;
    }

    /**
     * Check if vehicle is currently syncing.
     *
     * @return bool
     */
    public function isSyncing()
    {
        return $this->sync_status === 'syncing';
    }

    /**
     * Check if vehicle sync is active.
     *
     * @return bool
     */
    public function isSyncActive()
    {
        return in_array($this->sync_status, ['active', 'syncing']);
    }

    /**
     * Check if vehicle is linked to FleetOps.
     *
     * @return bool
     */
    public function isLinkedToFleetOps()
    {
        return $this->is_linked && !empty($this->vehicle_uuid);
    }

    /**
     * Mark vehicle as syncing.
     *
     * @return void
     */
    public function markAsSyncing()
    {
        $this->update(['sync_status' => 'syncing']);
    }

    /**
     * Mark vehicle sync as complete.
     *
     * @return void
     */
    public function markSyncComplete()
    {
        $this->update([
            'sync_status'  => 'active',
            'last_sync_at' => now(),
        ]);
    }

    /**
     * Mark vehicle sync as failed.
     *
     * @param string $error
     *
     * @return void
     */
    public function markSyncFailed($error = null)
    {
        $metadata = $this->metadata ?? [];
        if ($error) {
            $metadata['last_sync_error'] = $error;
        }

        $this->update([
            'sync_status' => 'failed',
            'metadata'    => $metadata,
        ]);
    }

    /**
     * Update vehicle data from Samsara API response.
     *
     * @return void
     */
    public function updateFromSamsaraData(array $samsaraData)
    {
        $this->update([
            'name'          => $samsaraData['name'] ?? $this->name,
            'vin'           => $samsaraData['vin'] ?? $this->vin,
            'serial'        => $samsaraData['serial'] ?? $this->serial,
            'license_plate' => $samsaraData['licensePlate'] ?? $this->license_plate,
            'vehicle_type'  => $this->mapVehicleType($samsaraData['vehicleType'] ?? 'unknown'),
            'metadata'      => array_merge($this->metadata ?? [], [
                'make'              => $samsaraData['make'] ?? null,
                'model'             => $samsaraData['model'] ?? null,
                'year'              => $samsaraData['year'] ?? null,
                'fuel_type'         => $samsaraData['fuelType'] ?? null,
                'engine_hours'      => $samsaraData['engineHours'] ?? null,
                'odometer_meters'   => $samsaraData['odometerMeters'] ?? null,
                'last_samsara_data' => $samsaraData,
            ]),
            'last_sync_at' => now(),
        ]);
    }

    /**
     * Link to FleetOps vehicle.
     *
     * @return void
     */
    public function linkToFleetOpsVehicle(string $fleetOpsVehicleUuid)
    {
        $this->update([
            'vehicle_uuid' => $fleetOpsVehicleUuid,
            'is_linked'    => true,
        ]);
    }

    /**
     * Unlink from FleetOps vehicle.
     *
     * @return void
     */
    public function unlinkFromFleetOpsVehicle()
    {
        $this->update([
            'vehicle_uuid' => null,
            'is_linked'    => false,
        ]);
    }

    /**
     * Map Samsara vehicle type to internal type.
     */
    protected function mapVehicleType(string $samsaraType): string
    {
        $typeMap = [
            'truck'      => 'truck',
            'van'        => 'van',
            'car'        => 'car',
            'trailer'    => 'trailer',
            'motorcycle' => 'motorcycle',
            'bus'        => 'bus',
            'equipment'  => 'equipment',
        ];

        return $typeMap[strtolower($samsaraType)] ?? 'truck';
    }

    /**
     * Scope to get vehicles that need syncing.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNeedsSync($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_sync_at')
              ->orWhere('last_sync_at', '<', now()->subMinutes(5));
        })->where('sync_status', '!=', 'disabled');
    }

    /**
     * Scope to get active synced vehicles.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActiveSynced($query)
    {
        return $query->where('sync_status', 'active');
    }

    /**
     * Scope to get linked vehicles.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLinked($query)
    {
        return $query->where('is_linked', true)->whereNotNull('vehicle_uuid');
    }

    /**
     * Scope to get unlinked vehicles.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnlinked($query)
    {
        return $query->where('is_linked', false)->orWhereNull('vehicle_uuid');
    }
}
