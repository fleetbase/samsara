<?php

namespace Fleetbase\Samsara\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;

/**
 * Class SamsaraWebhookEvent
 * 
 * Model for tracking Samsara webhook events and location updates
 * 
 * @package Fleetbase\Samsara\Models
 */
class SamsaraWebhookEvent extends Model
{
    use HasUuid, HasPublicId, TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'samsara_webhook_events';

    /**
     * The type of public Id to generate
     *
     * @var string
     */
    protected $publicIdType = 'samsara_event';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'public_id',
        'company_uuid',
        'samsara_credential_uuid',
        'samsara_vehicle_uuid',
        'event_id',
        'event_type',
        'event_data',
        'processed_at',
        'processing_status',
        'error_message',
        'meta',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'event_data' => 'json',
        'meta' => 'json',
        'processed_at' => 'datetime',
    ];

    /**
     * Dynamic attributes that are appended to model
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
     * Relationship to the SamsaraCredential model
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function credential()
    {
        return $this->belongsTo(SamsaraCredential::class, 'samsara_credential_uuid', 'uuid');
    }

    /**
     * Relationship to the SamsaraVehicle model
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function samsaraVehicle()
    {
        return $this->belongsTo(SamsaraVehicle::class, 'samsara_vehicle_uuid', 'uuid');
    }

    /**
     * Get the location data from event if available
     *
     * @return array|null
     */
    public function getLocationDataAttribute()
    {
        $data = $this->event_data;
        
        if ($this->event_type === 'location' && isset($data['location'])) {
            return [
                'latitude' => $data['location']['latitude'] ?? null,
                'longitude' => $data['location']['longitude'] ?? null,
                'timestamp' => $data['location']['time'] ?? null,
                'speed' => $data['location']['speed'] ?? null,
                'heading' => $data['location']['heading'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Get the vehicle information from event data
     *
     * @return array|null
     */
    public function getVehicleInfoAttribute()
    {
        $data = $this->event_data;
        
        if (isset($data['vehicle'])) {
            return [
                'id' => $data['vehicle']['id'] ?? null,
                'name' => $data['vehicle']['name'] ?? null,
                'vin' => $data['vehicle']['vin'] ?? null,
                'serial' => $data['vehicle']['serial'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Check if event is processed
     *
     * @return bool
     */
    public function isProcessed()
    {
        return $this->processing_status === 'processed';
    }

    /**
     * Check if event processing failed
     *
     * @return bool
     */
    public function isFailed()
    {
        return $this->processing_status === 'failed';
    }

    /**
     * Check if event is pending processing
     *
     * @return bool
     */
    public function isPending()
    {
        return $this->processing_status === 'pending';
    }

    /**
     * Mark event as processed
     *
     * @return void
     */
    public function markAsProcessed()
    {
        $this->update([
            'processing_status' => 'processed',
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark event as failed
     *
     * @param string $error
     * @return void
     */
    public function markAsFailed($error)
    {
        $this->update([
            'processing_status' => 'failed',
            'error_message' => $error,
        ]);
    }

    /**
     * Mark event as processing
     *
     * @return void
     */
    public function markAsProcessing()
    {
        $this->update(['processing_status' => 'processing']);
    }

    /**
     * Create event from webhook payload
     *
     * @param array $payload
     * @param string $companyUuid
     * @param string $credentialUuid
     * @return static
     */
    public static function createFromWebhook(array $payload, $companyUuid, $credentialUuid)
    {
        return static::create([
            'company_uuid' => $companyUuid,
            'samsara_credential_uuid' => $credentialUuid,
            'event_id' => $payload['eventId'] ?? null,
            'event_type' => $payload['eventType'] ?? 'unknown',
            'event_data' => $payload,
            'processing_status' => 'pending',
        ]);
    }

    /**
     * Scope to get pending events
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('processing_status', 'pending');
    }

    /**
     * Scope to get processed events
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeProcessed($query)
    {
        return $query->where('processing_status', 'processed');
    }

    /**
     * Scope to get failed events
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('processing_status', 'failed');
    }

    /**
     * Scope to get events by type
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByType($query, $type)
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope to get recent events
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $hours
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}

