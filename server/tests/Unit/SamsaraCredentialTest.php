<?php

namespace Fleetbase\Samsara\Tests\Unit;

use Fleetbase\Samsara\Models\SamsaraCredential;
use PHPUnit\Framework\TestCase;

/**
 * Class SamsaraCredentialTest
 * 
 * Unit tests for SamsaraCredential model
 * 
 * @package Fleetbase\Samsara\Tests\Unit
 */
class SamsaraCredentialTest extends TestCase
{
    protected SamsaraCredential $credential;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->credential = new SamsaraCredential([
            'name' => 'Test Credential',
            'api_token' => 'test-token-123',
            'api_base_url' => 'https://api.samsara.com',
            'webhook_url' => 'https://example.com/webhook',
            'webhook_secret' => 'secret-123',
            'is_active' => true,
            'is_sandbox' => false,
            'sync_interval' => 5,
            'company_uuid' => 'test-company-uuid',
        ]);
    }

    public function testModelHasRequiredAttributes()
    {
        $requiredAttributes = [
            'name',
            'api_token',
            'api_base_url',
            'webhook_url',
            'webhook_secret',
            'is_active',
            'is_sandbox',
            'sync_interval',
            'company_uuid',
        ];

        foreach ($requiredAttributes as $attribute) {
            $this->assertTrue(
                in_array($attribute, $this->credential->getFillable()),
                "SamsaraCredential should have fillable attribute: {$attribute}"
            );
        }
    }

    public function testGetAuthHeaders()
    {
        $headers = $this->credential->getAuthHeaders();
        
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertEquals('Bearer test-token-123', $headers['Authorization']);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals('application/json', $headers['Accept']);
    }

    public function testIsActiveScope()
    {
        // Test that the active scope method exists
        $this->assertTrue(
            method_exists($this->credential, 'scopeActive'),
            'SamsaraCredential should have active scope'
        );
    }

    public function testUpdateLastSync()
    {
        // Test that the updateLastSync method exists
        $this->assertTrue(
            method_exists($this->credential, 'updateLastSync'),
            'SamsaraCredential should have updateLastSync method'
        );
    }

    public function testModelHasRequiredRelationships()
    {
        $requiredRelationships = [
            'vehicles',
            'webhookEvents',
        ];

        foreach ($requiredRelationships as $relationship) {
            $this->assertTrue(
                method_exists($this->credential, $relationship),
                "SamsaraCredential should have relationship: {$relationship}"
            );
        }
    }

    public function testModelCasts()
    {
        $expectedCasts = [
            'is_active' => 'boolean',
            'is_sandbox' => 'boolean',
            'sync_interval' => 'integer',
            'last_sync_at' => 'datetime',
            'meta' => 'array',
        ];

        $actualCasts = $this->credential->getCasts();

        foreach ($expectedCasts as $attribute => $expectedCast) {
            $this->assertArrayHasKey(
                $attribute,
                $actualCasts,
                "SamsaraCredential should cast {$attribute}"
            );
        }
    }

    public function testHiddenAttributes()
    {
        $hiddenAttributes = $this->credential->getHidden();
        
        $this->assertContains('api_token', $hiddenAttributes);
        $this->assertContains('webhook_secret', $hiddenAttributes);
    }

    public function testModelUsesUuid()
    {
        // Test that the model uses UUID trait
        $traits = class_uses_recursive(SamsaraCredential::class);
        
        $this->assertContains(
            'Fleetbase\Traits\HasUuid',
            $traits,
            'SamsaraCredential should use HasUuid trait'
        );
    }

    public function testModelUsesPublicId()
    {
        // Test that the model uses PublicId trait
        $traits = class_uses_recursive(SamsaraCredential::class);
        
        $this->assertContains(
            'Fleetbase\Traits\HasPublicId',
            $traits,
            'SamsaraCredential should use HasPublicId trait'
        );
    }

    public function testModelTable()
    {
        $this->assertEquals('samsara_credentials', $this->credential->getTable());
    }
}

