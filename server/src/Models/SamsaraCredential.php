<?php

namespace Fleetbase\Samsara\Models;

use Fleetbase\Models\Model;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasPublicId;
use Fleetbase\Traits\TracksApiCredential;
use Illuminate\Support\Facades\Crypt;

/**
 * Class SamsaraCredential
 * 
 * Model for managing Samsara API credentials and configuration
 * 
 * @package Fleetbase\Samsara\Models
 */
class SamsaraCredential extends Model
{
    use HasUuid, HasPublicId, TracksApiCredential;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'samsara_credentials';

    /**
     * The type of public Id to generate
     *
     * @var string
     */
    protected $publicIdType = 'samsara_cred';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'public_id',
        'company_uuid',
        'name',
        'api_token',
        'api_base_url',
        'webhook_url',
        'webhook_secret',
        'is_active',
        'is_sandbox',
        'sync_interval',
        'last_sync_at',
        'meta',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'meta' => 'json',
        'is_active' => 'boolean',
        'is_sandbox' => 'boolean',
        'last_sync_at' => 'datetime',
        'sync_interval' => 'integer',
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
    protected $hidden = ['api_token', 'webhook_secret'];

    /**
     * Set the API token (encrypted)
     *
     * @param string $value
     * @return void
     */
    public function setApiTokenAttribute($value)
    {
        if ($value) {
            $this->attributes['api_token'] = Crypt::encryptString($value);
        }
    }

    /**
     * Get the API token (decrypted)
     *
     * @return string|null
     */
    public function getApiTokenAttribute()
    {
        if ($this->attributes['api_token']) {
            try {
                return Crypt::decryptString($this->attributes['api_token']);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Set the webhook secret (encrypted)
     *
     * @param string $value
     * @return void
     */
    public function setWebhookSecretAttribute($value)
    {
        if ($value) {
            $this->attributes['webhook_secret'] = Crypt::encryptString($value);
        }
    }

    /**
     * Get the webhook secret (decrypted)
     *
     * @return string|null
     */
    public function getWebhookSecretAttribute()
    {
        if ($this->attributes['webhook_secret']) {
            try {
                return Crypt::decryptString($this->attributes['webhook_secret']);
            } catch (\Exception $e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Get the API base URL with fallback to default
     *
     * @return string
     */
    public function getApiBaseUrlAttribute()
    {
        return $this->attributes['api_base_url'] ?? 'https://api.samsara.com';
    }

    /**
     * Get the sync interval in minutes with fallback to default
     *
     * @return int
     */
    public function getSyncIntervalAttribute()
    {
        return $this->attributes['sync_interval'] ?? 5; // Default 5 minutes
    }

    /**
     * Check if credentials are configured and active
     *
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->api_token) && $this->is_active;
    }

    /**
     * Check if webhook is configured
     *
     * @return bool
     */
    public function hasWebhook()
    {
        return !empty($this->webhook_url);
    }

    /**
     * Get authorization header for API requests
     *
     * @return array
     */
    public function getAuthHeaders()
    {
        return [
            'Authorization' => 'Bearer ' . $this->api_token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Test the API connection
     *
     * @return array
     */
    public function testConnection()
    {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'Credentials not configured',
            ];
        }

        try {
            // Make a simple API call to test connection
            $client = new \GuzzleHttp\Client();
            $response = $client->get($this->api_base_url . '/fleet/vehicles', [
                'headers' => $this->getAuthHeaders(),
                'query' => ['limit' => 1],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                ];
            }

            return [
                'success' => false,
                'message' => 'Unexpected response: ' . $response->getStatusCode(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Update last sync timestamp
     *
     * @return void
     */
    public function updateLastSync()
    {
        $this->update(['last_sync_at' => now()]);
    }

    /**
     * Scope to get active credentials
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get credentials for a specific company
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $companyUuid
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCompany($query, $companyUuid)
    {
        return $query->where('company_uuid', $companyUuid);
    }
}

